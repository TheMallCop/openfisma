<?php
/**
 * Copyright (c) 2010 Endeavor Systems, Inc.
 *
 * This file is part of OpenFISMA.
 *
 * OpenFISMA is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * OpenFISMA is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with OpenFISMA.  If not, see
 * {@link http://www.gnu.org/licenses/}.
 */

/**
 * The incident controller is used for searching, displaying, and updating incidents.
 *
 * @author     Mark E. Haase
 * @copyright  (c) Endeavor Systems, Inc. 2010 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Controller
 */
class IncidentController extends Fisma_Zend_Controller_Action_Object
{
    /**
     * The main name of the model.
     *
     * This model is the main subject which the controller operates on.
     */
    protected $_modelName = 'Incident';

    /**
     * Override parent in order to turn off default ACL checks.
     *
     * Incident ACL checks are unusual and are performed within this controller, not the parent.
     */
    protected $_enforceAcl = false;

    /**
     * A list of the separate parts of the incident report form, in order
     *
     * @var array
     */
    private $_formParts = array(
        array('name' => 'incident0Instructions', 'title' => 'Instructions'),
        array('name' => 'incident1Contact', 'title' => 'Contact Information'),
        array('name' => 'incident2Basic', 'title' => 'Incident Details'),
        array('name' => 'incident3Host', 'title' => 'Affected Asset'),
        array('name' => 'incident4PiiQuestion', 'title' => 'Was PII Involved?'),
        array('name' => 'incident5PiiDetails', 'title' => 'PII Details'),
        array('name' => 'incident6Shipping', 'title' => 'Shipment Details'),
        array('name' => 'incident7Source', 'title' => 'Incident Source')
    );

    /**
     * Set up context switches
     */
    public function init()
    {
        parent::init();

        $this->_helper->fismaContextSwitch()
                      ->addActionContext('add-user', 'json')
                      ->addActionContext('remove-user', 'json')
                      ->initContext();
    }

   /**
     * preDispatch() - invoked before each Actions
     */
    function preDispatch()
    {
        parent::preDispatch();

        $module = Doctrine::getTable('Module')->findOneByName('Incident Reporting');

        if (!$module->enabled) {
            throw new Fisma_Zend_Exception('This module is not enabled.');
        }

        $this->_paging['startIndex'] = $this->getRequest()->getParam('startIndex', 0);
    }

    /**
     * Handles the process of creating a new incident report.
     *
     * This is organized like a wizard which has several, successive screens to make the process simpler for
     * the user.
     *
     * Notice that this method is allowed for unauthenticated users
     *
     * @GETAllowed
     */
    public function reportAction()
    {
        $this->view->toolbarButtons = array(
            new Fisma_Yui_Form_Button(
                'nextButton',
                array(
                    'label' => 'Next',
                    'onClickFunction' => 'Fisma.Util.submitFirstForm',
                    'imageSrc' => '/images/next.png'
                )
            )
        );
        $this->view->form = $this->getForm('incident_report');

        // Unauthenticated users see a different layout that doesn't have a menubar
        if (!$this->_me) {
            $this->_helper->layout->setLayout('anonymous');
        }

        // Save the current form into the Incident and save the incident into the session
        if ($this->_request->isPost()) {
            if ($this->view->form->isValid($this->getRequest()->getPost())) {
                $incident = new Incident();
                $incident->merge($this->view->form->getValues());
                if ($incident->isValid()) {
                    $session = Fisma::getSession();
                    $session->irDraft = serialize($incident);
                    $this->_redirect('/incident/review-report');
                } else {
                    $this->view->priorityMessenger($incident->getErrorStackAsString(), 'error');
                }
            } else {
                $this->view->priorityMessenger(Fisma_Zend_Form_Manager::getErrors($this->view->form), 'error');
            }
        }
    }

    /**
     * Lets a user review the incident report in its entirety before submitting it.
     *
     * This action is available to unauthenticated users.
     *
     * @GETAllowed
     */
    public function reviewReportAction()
    {
        if (!$this->_me) {
            $this->_helper->layout->setLayout('anonymous');
        }

        // Fetch the incident report draft from the session
        $session = Fisma::getSession();
        if (isset($session->irDraft)) {
            $incident = unserialize($session->irDraft);
        } else {
            $this->_redirect('/incident/report');
            return;
        }

        // Load the view with all of the non-empty values that the user provided
        $incidentReport = $incident->toArray();
        $incidentReview = array();
        $richColumns = array();
        $incidentTable = Doctrine::getTable('Incident');
        $formFields = $this->getForm("incident_report")->getElements();
        unset($formFields["incidentTimezone"]);
        $formFields = array_keys($formFields);

        foreach ($incidentReport as $key => &$value) {
            if (!in_array($key, $formFields)) {
                continue;
            }
            $cleanValue = trim(strip_tags($value));
            if (!empty($cleanValue)) {
                $columnDef = $incidentTable->getDefinitionOf($key);

                if ('boolean' == $columnDef['type']) {
                    $value = ($value == 1) ? 'YES' : 'NO';
                }

                if ($key == 'organizationId') {
                    $value = "{$incident->Organization->nickname} - {$incident->Organization->name}";
                } else if ($key === 'incidentTime') {
                    $date = new Zend_Date($value, Fisma_Date::FORMAT_TIME);
                    $value = $date->toString(Fisma_Date::FORMAT_AM_PM_TIME);
                }

                if ($columnDef) {
                    if (isset($columnDef['extra']['logicalName'])) {
                        $logicalName = stripslashes($columnDef['extra']['logicalName']);
                        $incidentReview[$logicalName] = stripslashes($value);

                        // we need to know, in the view, which fields are rich-text
                        if (!empty($columnDef['extra']['purify'])) {
                            $richColumns[$logicalName] = $columnDef['extra']['purify'];
                        }
                    }
                } else {
                    throw new Fisma_Zend_Exception("Column ($key) is not defined");
                }
            }
        }

        $this->view->incidentReview = $incidentReview;
        $this->view->richColumns = $richColumns;
        $this->view->step = count($this->_formParts);
        $this->view->actionUrlBase = $this->_me
                                   ? '/incident'
                                   : '/incident';
        $this->view->toolbarButtons = array(
            new Fisma_Yui_Form_Button(
                'submitReportButton',
                array(
                    'label' => 'Submit Report',
                    'onClickFunction' => 'Fisma.Util.formPostAction',
                    'onClickArgument' => array(
                        'action' => '/incident/save-report'
                    ),
                    'imageSrc' => '/images/ok.png'
                )
            )
        );
    }

    /**
     * Inserts an incident record and forwards to the success page
     *
     * This action is available to unauthenticated users
     *
     * @GETAllowed
     * @return string the rendered page
     */
    public function saveReportAction()
    {
        $conn = Doctrine_Manager::connection();
        $conn->beginTransaction();

        // Unauthenticated users see a different layout that doesn't have a menubar
        if (!$this->_me) {
            $this->_helper->layout->setLayout('anonymous');
        }

        // Fetch the incident report draft from the session. If no incident report draft is in the session,
        // such as refresh this page, for anonymous user, it goes to incident report page. Otherwise, it goes
        // to incident list page.
        $session = Fisma::getSession();
        if (isset($session->irDraft)) {
            $incident = new Incident();
            $incident->merge(unserialize($session->irDraft));
        } else {
            if (!$this->_me) {
                $this->_redirect('/incident/report');
            } else {
                $this->_redirect('/incident/list');
            }
        }

        if ($incident->organizationId) {
            $org = Doctrine::getTable('Organization')->find($incident->organizationId);
            if (!empty($org->pocId)) {
                $incident->pocId = $org->pocId;

                $message = "Responsible people have been notified of this incident.";
                $this->view->priorityMessenger($message, 'notice');
            }
        }

        if ($this->_me) {
            $incident->reportingUserId = $this->_me->id;
        }

        $incident->save();

        $conn->commit();

        // Clear out serialized incident object
        unset($session->irDraft);

        // Create buttons
        if ($this->_me) {
            $this->view->viewIncidentButton = new Fisma_Yui_Form_Button_Link(
                'viewIncidentButton',
                array('value' => 'View Incident', 'href' => "/incident/view/id/{$incident->id}")
            );
        }

        $this->view->createNewButton = new Fisma_Yui_Form_Button_Link(
            'createNewButton',
            array('value' => 'Create New Incident', 'href' => '/incident/report', 'imageSrc' => '/images/create.png')
        );
    }

    /**
     * Remove the serialized incident object from the session object.
     *
     * This action is available to unauthenticated users
     *
     * @GETAllowed
     */
    public function cancelReportAction()
    {
        $this->view->priorityMessenger('The incident report has been canceled.');
        $session = Fisma::getSession();

        $incident = unserialize($session->irDraft);
        $incident->delete();
        
        if ( isset($incident) ) {
            unset($incident);
        }
        
        if (isset($session->irDraft)) {
            unset($session->irDraft);
        }

        $this->_redirect('/');
    }

    /**
     * Displays information for editing or viewing a particular incident
     *
     * @GETAllowed
     * @return string the rendered page
     */
    public function viewAction()
    {
        $id = $this->_request->getParam('id');

        $incidentQuery = Doctrine_Query::create()
                         ->from('Incident i')
                         ->leftJoin('i.Attachments a')
                         ->where('i.id = ?', $id);
        $results = $incidentQuery->execute();
        $incident = $results->getFirst();

        $incident = $this->_getSubject($id);

        $this->_assertCurrentUserCanViewIncident($id);

        $this->view->id = $id;
        $this->view->incident = $incident;

        $fromSearchParams = $this->_getFromSearchParams($this->_request);
        $fromSearchUrl = $this->_helper->makeUrlParams($fromSearchParams);

        // Put a span around the comment count so that it can be updated from Javascript
        $commentCount = '<span id=\'incidentCommentsCount\'>' . $incident->getComments()->count() . '</span>';

        $artifactCount = $incident->Attachments->count();

        // Create tab view
        $tabView = new Fisma_Yui_TabView('SystemView', $id);

        $tabView->addTab("Incident $id", "/incident/incident/id/$id");
        $tabView->addTab('Workflow', "/incident/workflow/id/$id");
        $tabView->addTab('Actors & Observers', "/incident/users/id/$id");
        $tabView->addTab("Comments ($commentCount)", "/incident/comments/id/$id");
        $tabView->addTab(
            $this->view->escape($this->view->translate('Incident_Attachments')) . " ($artifactCount)",
            "/incident/artifacts/id/$id"
        );
        $tabView->addTab('Audit Log', "/incident/audit-log/id/$id");

        $this->view->tabView = $tabView;
        $this->view->formAction = "/incident/update/id/$id$fromSearchUrl";
        $this->view->toolbarButtons = $this->getToolbarButtons($incident, $fromSearchParams);
        $this->view->searchButtons = $this->getSearchButtons($incident, $fromSearchParams);
    }

    /**
     * Display incident details
     *
     * This is loaded into a tab view, so it has no layout
     *
     * @GETAllowed
     */
    public function incidentAction()
    {
        /** @todo move to ajax context */
        $this->_helper->layout->disableLayout();

        $id = $this->_request->getParam('id');

        $incident = Doctrine_Query::create()
                         ->from('Incident i')
                         ->leftJoin('i.Organization o')
                         ->leftJoin('i.ParentOrganization po')
                         ->leftJoin('i.Category category')
                         ->leftJoin('i.ReportingUser reporter')
                         ->leftJoin('i.PointOfContact poc')
                         ->leftJoin('category.Category mainCategory')
                         ->where('i.id = ?', $id)
                         ->fetchOne(array(), Doctrine::HYDRATE_ARRAY);

        $this->view->incident = $incident;
        $timezone = date_default_timezone_get();

        if (!empty($incident['reportTz'])) {
            date_default_timezone_set($incident['reportTz']);
        }
        $createdDateTime = new Zend_Date($incident['reportTs'], Fisma_Date::FORMAT_DATETIME);
        date_default_timezone_set($timezone);
        $createdDateTime->setTimezone('UTC');
        $this->view->createDateTime = $createdDateTime->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                      . ' at '
                                      . $createdDateTime->toString(Fisma_Date::FORMAT_AM_PM_TIME);
        $createdDateTime->setTimezone(CurrentUser::getAttribute('timezone'));
        $this->view->createdDateTimeLocal = $createdDateTime->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                           . ' at '
                                           . $createdDateTime->toString(Fisma_Date::FORMAT_AM_PM_TIME);

        if (!empty($incident['incidentTimezone'])) {
            date_default_timezone_set($incident['incidentTimezone']);
        }
        $incidentDateTime = $incident['incidentDate'] . ' ' . $incident['incidentTime'];
        $incidentDate = new Zend_Date($incidentDateTime, Fisma_Date::FORMAT_DATETIME);
        date_default_timezone_set($timezone);
        $incidentDate->setTimezone('UTC');
        $this->view->incidentDateTime = $incidentDate->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                       . ' at '
                                       . $incidentDate->toString(Fisma_Date::FORMAT_AM_PM_TIME);
        $incidentDate->setTimezone(CurrentUser::getAttribute('timezone'));
        $this->view->incidentDateTimeLocal = $incidentDate->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                           . ' at '
                                           . $incidentDate->toString(Fisma_Date::FORMAT_AM_PM_TIME);

        $updateDateTime = new Zend_Date($incident['modifiedTs'], Fisma_Date::FORMAT_DATETIME);
        $updateDateTime->setTimezone('UTC');
        $this->view->updateTs = $updateDateTime->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                              . ' at '
                              . $updateDateTime->toString(Fisma_Date::FORMAT_AM_PM_TIME);
        $updateDateTime->setTimezone(CurrentUser::getAttribute('timezone'));
        $this->view->updateTsLocal = $updateDateTime->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                              . ' at '
                              . $updateDateTime->toString(Fisma_Date::FORMAT_AM_PM_TIME);

        if (!empty($incident['closedTs'])) {
            $closedDateTime = new Zend_Date($incident['closedTs'], Fisma_Date::FORMAT_DATETIME);
            $closedDateTime->setTimezone('UTC');
            $this->view->closedTs = $closedDateTime->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                      . ' at '
                                      . $closedDateTime->toString(Fisma_Date::FORMAT_AM_PM_TIME);
            $closedDateTime->setTimezone(CurrentUser::getAttribute('timezone'));
            $this->view->closedTsLocal = $closedDateTime->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                      . ' at '
                                      . $closedDateTime->toString(Fisma_Date::FORMAT_AM_PM_TIME);
        }

        $this->_assertCurrentUserCanViewIncident($id);

        $this->view->updateIncidentPrivilege = $this->_currentUserCanUpdateIncident($id);
        $this->view->lockIncidentPrivilege = $this->_acl->hasPrivilegeForClass('lock', 'Incident');

        $orgId = $incident['Organization']['id'];
        $organization = Doctrine::getTable('Organization')->find($orgId);

        // $organization will be false if an organization has not been selected yet
        if ($organization === false) {
            $this->view->userCanViewOrganization = false;
        } else {
            $this->view->userCanViewOrganization = $this->_acl->hasPrivilegeForObject('read', $organization);
        }

        $parentOrgId = $incident['ParentOrganization']['id'];
        $parentOrganization = Doctrine::getTable('Organization')->find($parentOrgId);

        // $organization will be false if an organization has not been selected yet
        if ($parentOrganization === false) {
            $this->view->userCanViewParentOrganization = false;
        } else {
            $this->view->userCanViewParentOrganization =
                $this->_acl->hasPrivilegeForObject('read', $parentOrganization);
        }
    }

    /**
     * Lock the incident
     *
     * The access control for these actions is handled inside the Lockable behavior
     *
     * @return void
     */
    public function lockAction()
    {
        $id = $this->_request->getParam('id');
        $incident = Doctrine::getTable('Incident')->find($id);
        $this->_acl->requirePrivilegeForObject('lock', $incident);
        $incident->isLocked = TRUE;
        $incident->save();

        $incident->getAuditLog()->write("The incident has been locked.");
        Notification::notify('INCIDENT_LOCKED', $incident, CurrentUser::getInstance());

        $fromSearchParams = $this->_getFromSearchParams($this->_request);
        $fromSearchUrl = $this->_helper->makeUrlParams($fromSearchParams);

        $this->_redirect("/incident/view/id/$id$fromSearchUrl");
    }

    /**
     * Unlock the incident
     *
     * The access control for these actions is handled inside the Lockable behavior
     *
     * @return void
     */
    public function unlockAction()
    {
        $id = $this->_request->getParam('id');
        $incident = Doctrine::getTable('Incident')->find($id);
        $this->_acl->requirePrivilegeForObject('lock', $incident);
        $incident->isLocked = FALSE;
        $incident->save();

        $incident->getAuditLog()->write("The incident has been unlocked.");
        Notification::notify('INCIDENT_UNLOCKED', $incident, CurrentUser::getInstance());

        $fromSearchParams = $this->_getFromSearchParams($this->_request);
        $fromSearchUrl = $this->_helper->makeUrlParams($fromSearchParams);

        $this->_redirect("/incident/view/id/$id$fromSearchUrl");
    }

    /**
     * Display the audit log for an incident
     *
     * @GETAllowed
     */
    public function auditLogAction()
    {
        $id = $this->_request->getParam('id');

        $this->_assertCurrentUserCanViewIncident($id);

        /** @todo move to ajax context */
        $this->_helper->layout->disableLayout();

        $incident = Doctrine::getTable('Incident')->find($id);

        $logs = $incident->getAuditLog()->fetch(Doctrine::HYDRATE_SCALAR);

        $logRows = array();
        foreach ($logs as $log) {
            $logRows[] = array(
                'timestamp' => $log['o_createdTs'],
                'user' => !empty($log['u_id']) ? $this->view->userInfo($log['u_displayName'], $log['u_id']) : '',
                'message' =>  $this->view->textToHtml($this->view->escape($log['o_message']))
            );
        }

        $dataTable = new Fisma_Yui_DataTable_Local();

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Timestamp',
                true,
                null,
                null,
                'timestamp'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'User',
                true,
                'Fisma.TableFormat.formatHtml',
                null,
                'username'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Message',
                false,
                'Fisma.TableFormat.formatHtml',
                null,
                'message'
            )
        );

        $dataTable->setData($logRows);
        $this->view->dataTable = $dataTable;
    }

    /**
     * Display users with actor or observer privileges and provide controls to add/remove actors and observers
     *
     * @GETAllowed
     */
    public function usersAction()
    {
        $this->_helper->layout->disableLayout();

        $id = $this->_request->getParam('id');
        $this->view->assign('id', $id);

        $this->_assertCurrentUserCanViewIncident($id);

        $updateIncidentPrivilege = $this->_currentUserCanUpdateIncident($id);
        $this->view->updateIncidentPrivilege = $updateIncidentPrivilege;

        // Get list of actors
        $actorQuery = Doctrine_Query::create()
                      ->select('i.id, a.id, a.username, a.nameFirst, a.nameLast')
                      ->from('Incident i')
                      ->innerJoin('i.IrIncidentUser iu')
                      ->innerJoin('iu.User a')
                      ->where('i.id = ? AND iu.accessType = ?', array($id, 'ACTOR'))
                      ->orderBy('a.username')
                      ->setHydrationMode(Doctrine::HYDRATE_SCALAR);
        $actors = $actorQuery->execute();

        $actorRows = array();

        foreach ($actors as $actor) {
            $actorColumns = array(
                $actor['i_id'],
                $actor['a_id'],
                $actor['a_username'],
                $actor['a_nameFirst'],
                $actor['a_nameLast'],
                null // This is for the delete column
            );

            if (!$updateIncidentPrivilege) {
                array_pop($actorColumns);
            }

            $actorRows[] = $actorColumns;
        }

        $actorTable = new Fisma_Yui_DataTable_Local();
        $actorTable->setRegistryName('actorTable');

        $col = "Fisma_Yui_DataTable_Column";
        $actorTable->addColumn(new $col('', true, 'Fisma.TableFormat.formatHtml', null, 'incidentId', true))
                   ->addColumn(new $col('', true, 'Fisma.TableFormat.formatHtml', null, 'userId', true))
                   ->addColumn(new $col('Username', true, 'Fisma.TableFormat.formatHtml', null, 'username'))
                   ->addColumn(new $col('First Name', true, null, null, 'nameFirst'))
                   ->addColumn(new $col('Last Name', true, null, null, 'nameLast'));

        if ($updateIncidentPrivilege) {
            $actorTable->addColumn(new $col('Action', true, 'Fisma.TableFormat.remover', null, 'remover'));
        }

        $actorTable->setData($actorRows);

        $this->view->actorDataTable = $actorTable;

        // Get list of observers
        $observerQuery = Doctrine_Query::create()
                         ->select('i.id, o.id, o.username, o.nameFirst, o.nameLast')
                         ->from('Incident i')
                         ->innerJoin('i.IrIncidentUser iu')
                         ->innerJoin('iu.User o')
                         ->where('i.id = ? AND iu.accessType = ?', array($id, 'OBSERVER'))
                         ->orderBy('o.username')
                         ->setHydrationMode(Doctrine::HYDRATE_SCALAR);
        $observers = $observerQuery->execute();

        $observerRows = array();

        foreach ($observers as $observer) {
            $observerColumns = array(
                $observer['i_id'],
                $observer['o_id'],
                $observer['o_username'],
                $observer['o_nameFirst'],
                $observer['o_nameLast'],
                null // This is for the delete column
            );

            if (!$updateIncidentPrivilege) {
                array_pop($observerColumns);
            }

            $observerRows[] = $observerColumns;
        }

        $observerTable = new Fisma_Yui_DataTable_Local();
        $observerTable->setRegistryName('observerTable');

        $observerTable->addColumn(new $col('', true, 'Fisma.TableFormat.formatHtml', null, 'incidentId', true))
                      ->addColumn(new $col('', true, 'Fisma.TableFormat.formatHtml', null, 'userId', true))
                      ->addColumn(new $col('Username', true, 'Fisma.TableFormat.formatHtml', null, 'username'))
                      ->addColumn(new $col('First Name', true, null, null, 'nameFirst'))
                      ->addColumn(new $col('Last Name', true, null, null, 'nameLast'));

        if ($updateIncidentPrivilege) {
            $observerTable->addColumn(new $col('Action', true, 'Fisma.TableFormat.remover', null, 'remover'));
        }

        $observerTable->setData($observerRows);

        $this->view->observerDataTable = $observerTable;

        // Create autocomplete for actors
        $this->view->actorAutocomplete = new Fisma_Yui_Form_AutoComplete(
            'actorAutocomplete',
            array(
                'resultsList' => 'pointsOfContact',
                'fields' => 'name',
                'xhr' => "/incident/get-eligible-users/id/$id",
                'hiddenField' => 'actorId',
                'queryPrepend' => '/keyword/',
                'containerId' => 'actorAutocompleteContainer',
                'enterKeyEventHandler' => 'Fisma.Incident.handleAutocompleteEnterKey',
                'enterKeyEventArgs' => 'actor'
            )
        );

        $this->view->addActorButton = new Fisma_Yui_Form_Button(
            'addActor',
            array(
                'label' => 'Add Actor',
                'onClickFunction' => 'Fisma.Incident.addUser',
                'onClickArgument' => array('type' => 'actor', 'incidentId' => $id)
            )
        );

        // Create autocomplete for observers
        $this->view->observerAutocomplete = new Fisma_Yui_Form_AutoComplete(
            'observerAutocomplete',
            array(
                'resultsList' => 'pointsOfContact',
                'fields' => 'name',
                'xhr' => "/incident/get-eligible-users/id/$id",
                'hiddenField' => 'observerId',
                'queryPrepend' => '/keyword/',
                'containerId' => 'observerAutocompleteContainer',
                'enterKeyEventHandler' => 'Fisma.Incident.handleAutocompleteEnterKey',
                'enterKeyEventArgs' => 'observer'
            )
        );

        $this->view->addObserverButton = new Fisma_Yui_Form_Button(
            'addObserver',
            array(
                'label' => 'Add Observer',
                'onClickFunction' => 'Fisma.Incident.addUser',
                'onClickArgument' => array('type' => 'observer', 'incidentId' => $id)
            )
        );
    }

    /**
     * Add a user as an actor or observer to the specified incident.
     *
     * This is called asynchronously from Fisma.Incident.addUser().
     */
    public function addUserAction()
    {
        $response = new Fisma_AsyncResponse;

        $incidentId = $this->getRequest()->getParam('incidentId');

        $this->_assertCurrentUserCanUpdateIncident($incidentId);

        $type = $this->getRequest()->getParam('type');

        if (!in_array($type, array('actor', 'observer'))) {
            throw new Fisma_Zend_Exception("Invalid incident user type: '$type'");
        }

        $userId = $this->getRequest()->getParam('userId');
        $username = $this->getRequest()->getParam('username');

        /*
         * User ID is supplied by an autocomplete. If the user did not use autocomplete, then check to see if the
         * username can be looked up.
         */
        if (strlen($userId) > 0) {
            $user = Doctrine::getTable('User')->find($userId, Doctrine::HYDRATE_ARRAY);
        } elseif (strlen($username) > 0) {
            $user = Doctrine::getTable('User')->findOneByUsername($username, Doctrine::HYDRATE_ARRAY);
        }

        if (isset($user) && !empty($user)) {
            // Create the requested link
            $incidentActor = new IrIncidentUser();

            $incidentActor->userId = $user['id'];
            $incidentActor->incidentId = $incidentId;
            $incidentActor->accessType = strtoupper($type);

            try {
                $incidentActor->save();
            } catch (Doctrine_Connection_Exception $e) {
                $portableCode = $e->getPortableCode();

                if (Doctrine::ERR_ALREADY_EXISTS == $portableCode) {
                    $message = 'That user is already an actor or an observer on this incident.';
                    $response->fail($message);
                } else {
                    throw $e;
                }
            }

            // Send e-mail
            $mailSubject = "You have been assigned to a new incident.";
            $this->_sendMailToAssignedUser($userId, $incidentId, $mailSubject);
        } else {
            $response->fail("No user found with that name.");
        }

        if ($response->success) {
            $this->view->user = array(
                'userId' => $user['id'],
                'incidentId' => $incidentId,
                'username' => $user['username'],
                'nameFirst' => $user['nameFirst'],
                'nameLast' => $user['nameLast']
            );
        }

        $this->view->response = $response;
    }

    /**
     * Remove user's actor or observer privileges for the specified incident
     */
    public function removeUserAction()
    {
        $response = new Fisma_AsyncResponse;

        $incidentId = $this->getRequest()->getParam('incidentId');
        $incident = Doctrine::getTable('Incident')->find($incidentId);

        $this->_assertCurrentUserCanUpdateIncident($incidentId);

        // Remove the specified user from this incident
        $userId = $this->getRequest()->getParam('userId');

        Doctrine_Query::create()->delete()->from('IrIncidentUser iu')
                                          ->where('iu.userId = ? AND iu.incidentId = ?', array($userId, $incidentId))
                                          ->execute();

        $this->view->response = $response;
    }

    /**
     * Displays the incident workflow interface
     *
     * This actually forwards to one of several different views and doesn't render anything itself
     *
     * @GETAllowed
     *
     * @return string the rendered page
     */
    public function workflowAction()
    {
        $id = $this->_request->getParam('id');
        $this->view->id = $id;

        $this->_assertCurrentUserCanViewIncident($id);

        $incident = Doctrine::getTable('Incident')->find($id, Doctrine::HYDRATE_ARRAY);
        $this->view->incident = $incident;

        $stepsQuery = Doctrine_Query::create()
                      ->from('IrIncidentWorkflow iw')
                      ->leftJoin('iw.User user')
                      ->leftJoin('iw.Role role')
                      ->where('iw.incidentId = ?', $id)
                      ->orderBy('iw.cardinality')
                      ->setHydrationMode(Doctrine::HYDRATE_ARRAY);

        $steps = $stepsQuery->execute();

        $this->view->updateIncidentPrivilege = $this->_currentUserCanUpdateIncident($id);
        $this->view->steps = $steps;

        $this->_helper->layout->disableLayout();
    }

    /**
     * Updates an incident object by marking a step as completed
     *
     * @var Incident $incident
     */
    private function _completeWorkflowStep(Incident $incident)
    {
        try {
            $comment = $this->getRequest()->getParam('comment');

            $incident->completeStep($comment);
            Notification::notify(
                (($incident->status === 'closed') ? 'INCIDENT_RESOLVED' : 'INCIDENT_STEP'),
                $incident,
                CurrentUser::getInstance(),
                array(
                    'recipientList' => $this->_getAssociatedUsers($incident->id)
                )
            );

            $message = 'Workflow step completed. ';
            if ('closed' == $incident->status) {
                $message .= 'All steps have been now completed and the incident has been marked as closed.';
            }

            $this->view->priorityMessenger($message, 'success');
        } catch (Fisma_Zend_Exception_User $e) {
            $this->view->priorityMessenger($e->getMessage(), 'error');
        } catch (Fisma_Doctrine_Behavior_Lockable_Exception $e) {
            $this->view->priorityMessenger($e->getMessage(), 'error');
        }
    }

    /**
     * Displays the incident comment interface
     *
     * @GETAllowed
     * @return Zend_Form
     */
    function commentsAction()
    {
        $id = $this->_request->getParam('id');
        $this->view->assign('id', $id);
        $incident = Doctrine::getTable('Incident')->find($id);

        $this->_assertCurrentUserCanViewIncident($id);

        /** @todo move to ajax context */
        $this->_helper->layout->disableLayout();

        $comments = $incident->getComments()->fetch(Doctrine::HYDRATE_ARRAY);

        $commentRows = array();

        foreach ($comments as $comment) {
            $commentTs = new Zend_Date($comment['createdTs'], Fisma_Date::FORMAT_DATETIME);
            $commentTs->setTimezone('UTC');
            $commentDateTime = $commentTs->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                  . ' at '
                                  . $commentTs->toString(Fisma_Date::FORMAT_AM_PM_TIME);
            $commentTs->setTimezone(CurrentUser::getAttribute('timezone'));
            $commentDateTimeLocal = $commentTs->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                  . ' at '
                                  . $commentTs->toString(Fisma_Date::FORMAT_AM_PM_TIME);
            $commentRows[] = array(
                'timestamp' => Zend_Json::encode(array("local" => $commentDateTimeLocal, "utc" => $commentDateTime)),
                'unixtimestamp' => $commentTs->getTimestamp(),
                'username' => $this->view->userInfo($comment['User']['displayName'], $comment['User']['id']),
                'comment' =>  $this->view->textToHtml($this->view->escape($comment['comment'])),
                'delete' => (($comment['User']['id'] === CurrentUser::getAttribute('id'))
                    ? '/comment/remove/format/json/id/' . $id . '/type/Incident/commentId/' . $comment['id']
                    : ''
                )
            );
        }

        $dataTable = new Fisma_Yui_DataTable_Local();

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Timestamp',
                true,
                'Fisma.TableFormat.formatDateTimeLocal',
                null,
                'timestamp',
                false,
                'string',
                'unixtimestamp'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'unixtimestamp',
                false,
                null,
                null,
                'unixtimestamp',
                true
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'User',
                true,
                'Fisma.TableFormat.formatHtml',
                null,
                'username'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Comment',
                false,
                'Fisma.TableFormat.formatHtml',
                null,
                'comment'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Action',
                false,
                'Fisma.TableFormat.deleteControl',
                null,
                'delete'
            )
        );

        $dataTable->setData($commentRows);

        $this->view->dataTable = $dataTable;

        $commentButton = new Fisma_Yui_Form_Button(
            'commentButton',
            array(
                'label' => 'Add Comment',
                'onClickFunction' => 'Fisma.Commentable.showPanel',
                'onClickArgument' => array(
                    'id' => $id,
                    'type' => 'Incident',
                    'callback' => array(
                        'object' => 'Incident',
                        'method' => 'commentCallback'
                    )
                )
            )
        );

        if (!$this->_currentUserCanComment($id)) {
            $commentButton->readOnly = true;
        }

        $this->view->commentButton = $commentButton;
    }

    /**
     * Display file artifacts associated with an incident
     *
     * @GETAllowed
     */
    public function artifactsAction()
    {
        $id = $this->_request->getParam('id');
        $this->view->assign('id', $id);
        $incident = Doctrine_Query::create()
                            ->from('Incident i')
                            ->leftJoin('i.Attachments a')
                            ->where('i.id = ?', $id)
                            ->execute()
                            ->getLast();

        /** @todo move to ajax context */
        $this->_helper->layout->disableLayout();

        $this->_assertCurrentUserCanViewIncident($id);

        // Upload button
        $uploadPanelButton = new Fisma_Yui_Form_Button(
            'uploadPanelButton',
            array(
                'label' => 'Upload New ' . $this->view->escape($this->view->translate('Incident_Attachment')),
                'onClickFunction' => 'Fisma.AttachArtifacts.showPanel',
                'onClickArgument' => array(
                    'id' => $id,
                    'server' => array(
                        'controller' => 'incident',
                        'action' => 'attach-artifact'
                    ),
                    'callback' => array(
                        'object' => 'Incident',
                        'method' => 'attachArtifactCallback'
                    ),
                    'title' => 'Upload New ' . $this->view->escape($this->view->translate('Incident_Attachment'))
                )
            )
        );

        if (!$this->_currentUserCanUpdateIncident($id)) {
            $uploadPanelButton->readOnly = true;
        }

        $this->view->uploadPanelButton = $uploadPanelButton;

        /**
         * Get artifact data as Doctrine Collection. Loop over to get icon URLs and file size, then convert to array
         * for view binding.
         */
        $artifactCollection = $incident->Attachments;
        $artifactRows = array();

        foreach ($artifactCollection as $artifact) {
            $createdTs = new Zend_Date($artifact->createdTs, Fisma_Date::FORMAT_DATETIME);
            $createdTs->setTimezone('UTC');
            $createdDateTime = $createdTs->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                  . ' at '
                                  . $createdTs->toString(Fisma_Date::FORMAT_AM_PM_TIME);
            $createdTs->setTimezone(CurrentUser::getAttribute('timezone'));
            $createdDateTimeLocal = $createdTs->toString(Fisma_Date::FORMAT_MONTH_DAY_YEAR)
                                  . ' at '
                                  . $createdTs->toString(Fisma_Date::FORMAT_AM_PM_TIME);

            $downloadUrl = '/incident/download-artifact/id/' . $id . '/artifactId/' . $artifact->id;
            $artifactRows[] = array(
                'iconUrl'  => "<a href='$downloadUrl'><img alt='"
                            . $this->view->escape($artifact->getFileType())
                            . "'' src='"
                            . $this->view->escape($artifact->getIconUrl())
                            . "'></a>",
                'fileName' => $this->view->escape($artifact->fileName),
                'fileNameLink' => "<a href='$downloadUrl'>" . $this->view->escape($artifact->fileName) . "</a>",
                'fileSize' => $artifact->getFileSize(),
                'user'     => $this->view->userInfo($artifact->User->displayName, $artifact->User->id),
                'date'     => Zend_Json::encode(array("local" => $createdDateTimeLocal, "utc" => $createdDateTime)),
                'comment'  => $this->view->textToHtml($this->view->escape($artifact->description)),
                'delete' => (($artifact->User->id === CurrentUser::getAttribute('id'))
                    ? '/incident/delete-artifact/id/' . $id . '/artifactId/' . $artifact->id
                    : ''
                )
            );
        }

        $dataTable = new Fisma_Yui_DataTable_Local();

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Icon',
                false,
                'Fisma.TableFormat.formatHtml',
                null,
                'icon'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'File Name',
                true,
                'Fisma.TableFormat.formatHtml',
                null,
                'fileName',
                true
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'File Name',
                true,
                'Fisma.TableFormat.formatHtml',
                null,
                'fileNameLink',
                false,
                'string',
                'fileName'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Size',
                true,
                'Fisma.TableFormat.formatFileSize',
                null,
                'size',
                false,
                'number'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Uploaded By',
                true,
                'Fisma.TableFormat.formatHtml',
                null,
                'user'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Upload Date',
                true,
                'Fisma.TableFormat.formatDateTimeLocal',
                null,
                'date'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Comment',
                false,
                'Fisma.TableFormat.formatHtml',
                null,
                'comment'
            )
        );

        $dataTable->addColumn(
            new Fisma_Yui_DataTable_Column(
                'Action',
                false,
                'Fisma.TableFormat.deleteControl',
                null,
                'delete'
            )
        );

        $dataTable->setData($artifactRows);

        $this->view->dataTable = $dataTable;
    }

    /**
     * Attach a new artifact to this incident
     *
     * This is called asychronously through the attach artifacts behavior. This is a bit hacky since it is invoked
     * by YUI's asynchronous file upload. This means the response is written to an iframe, so we can't render this view
     * as JSON.
     *
     * Instead, we render an HTML view with the JSON-serialized response inside it.
     *
     * @GETAllowed
     */
    public function attachArtifactAction()
    {
        $id = $this->getRequest()->getParam('id');
        $comment = $this->getRequest()->getParam('comment');

        $this->_helper->layout->disableLayout();

        $response = new Fisma_AsyncResponse();

        try {
            $incident = Doctrine_Query::create()
                            ->from('Incident i')
                            ->leftJoin('i.Attachments a')
                            ->where('i.id = ?', $id)
                            ->execute()
                            ->getLast();

            $this->_assertCurrentUserCanUpdateIncident($id);

            $file = $_FILES['file'];
            if (Fisma_FileManager::getUploadFileError($file)) {
               $error = Fisma_FileManager::getUploadFileError($file);
               throw new Fisma_Zend_Exception_User($error);
            }

            $incident->attach($_FILES['file'], $comment);
            $incident->save();

        } catch (Fisma_Zend_Exception_User $e) {
            $response->fail($e->getMessage());
        } catch (Exception $e) {
            if (Fisma::debug()) {
                $response->fail("Failure (debug mode): " . $e->getMessage());
            } else {
                $response->fail("Internal system error. File not uploaded.");
            }

            $this->getInvokeArg('bootstrap')->getResource('log')->err($e->getMessage() . "\n" . $e->getTraceAsString());
        }

        $this->view->response = json_encode($response);

        if ($response->success) {
            $this->view->priorityMessenger('Artifact uploaded successfully', 'success');
        }
    }

    /**
     * Download an artifact to the user's browser
     *
     * @GETAllowed
     */
    public function downloadArtifactAction()
    {
        $incidentId = $this->getRequest()->getParam('id');
        $artifactId = $this->getRequest()->getParam('artifactId');

        // If user can view this artifact's incident, then they can download the artifact itself
        $incident = Doctrine::getTable('Incident')->find($incidentId);

        $this->_assertCurrentUserCanViewIncident($incidentId);

        // Send artifact to browser
        $upload = Doctrine::getTable('Upload')->find($artifactId);
        $this->_helper->downloadAttachment($upload->fileHash, $upload->fileName);
    }

    /**
     * Update incident
     */
    public function updateAction()
    {
        $id = $this->_request->getParam('id');
        $this->_assertCurrentUserCanUpdateIncident($id);
        $incident = Doctrine::getTable('Incident')->find($id);

        if (!$incident) {
            throw new Fisma_Zend_Exception_User("Invalid Incident ID");
        }

        $fromSearchParams = $this->_getFromSearchParams($this->_request);
        $fromSearchUrl = $this->_helper->makeUrlParams($fromSearchParams);

        if ($this->_hasParam('reject')) {
            $incident->reject();
            $incident->save();
        }

        if ($this->_hasParam('completeStep')) {
            $this->_completeWorkflowStep($incident);
        }

        try {
            // Update the incident's data
            $newValues = $this->getRequest()->getParam('incident');
            if (!empty($newValues)) {
                foreach ($newValues as &$value) {
                    $value = $value == '' ? null : $value;
                }
                $incident->merge($newValues);
                $incident->save();
            }

             // If the POC changed, then send the POC an e-mail.
            if (isset($newValues['pocId']) && !empty($newValues['pocId'])) {
                $mailSubject = "You have been assigned as the "
                             . $this->view->translate('Incident_Point_of_Contact')
                             . " for an incident.";
                $this->_sendMailToAssignedUser($newValues['pocId'], $incident->id, $mailSubject);

                $this->view->priorityMessenger('A notification has been sent to the new '
                             . $this->view->translate('Incident_Point_of_Contact')
                             . '.', 'notice');
            }
        } catch (Doctrine_Validator_Exception $e) {
            $this->view->priorityMessenger($e->getMessage(), 'error');
        }

        $this->_redirect("/incident/view/id/$id$fromSearchUrl");
    }

    /**
     * Check whether the current user can update the specified incident
     *
     * This is an expensive operation. DO NOT CALL IT IN A TIGHT LOOP.
     *
     * @param int $incidentId The ID of the incident
     * @return bool
     */
    public function _currentUserCanUpdateIncident($incidentId)
    {
        $userCanUpdate = false;
        $incident = Doctrine::getTable('Incident')->findOneById($incidentId);

        if (
            $this->_acl->hasPrivilegeForObject('update', $incident) &&
            ((!$incident->isLocked) ||
            ($incident->isLocked && $this->_acl->hasPrivilegeForObject('lock', $incident)))
        ) {
            $userCanUpdate = true;
        } else {
            // Check if this user is an actor
            $userId = $this->_me->id;
            $actorCount = Doctrine_Query::create()
                 ->from('Incident i')
                 ->innerJoin('i.IrIncidentUser iu')
                 ->innerJoin('iu.User u')
                 ->where('i.id = ? AND u.id = ? AND iu.accessType = ?', array($incidentId, $this->_me->id, 'ACTOR'))
                 ->count();

            if ($actorCount > 0) {
                $userCanUpdate = true;
            }
        }

        return $userCanUpdate;
    }

    /**
     * Check whether the current user can comment on an incident
     *
     * This is an expensive operation. DO NOT CALL IT IN A TIGHT LOOP.
     *
     * @param int $incidentId The ID of the incident
     * @return bool
     */
    public function _currentUserCanComment($incidentId)
    {
        $userCanComment = false;
        $incident = Doctrine::getTable('Incident')->findOneById($incidentId);

        if (
            $this->_acl->hasPrivilegeForObject('comment', $incident) &&
            ((!$incident->isLocked) ||
            ($incident->isLocked && $this->_acl->hasPrivilegeForObject('lock', $incident)))
        ) {
            $userCanComment = true;
        } else {
            // Check if this user is an actor
            $userId = $this->_me->id;
            $userCount = Doctrine_Query::create()
                 ->from('Incident i')
                 ->innerJoin('i.IrIncidentUser iu')
                 ->innerJoin('iu.User u')
                 ->where('i.id = ? AND u.id = ?', array($incidentId, $this->_me->id))
                 ->count();

            if ($userCount > 0) {
                $userCanComment = true;
            }
        }

        return $userCanComment;
    }

    /**
     * Assert that the current user is allowed to modify the specified incident.
     *
     * Throws an exception if the current user is not allowed to modify the specified incident.
     *
     * This is an expensive operation. DO NOT CALL IT IN A TIGHT LOOP.
     *
     * @param int $incidentId
     */
    private function _assertCurrentUserCanUpdateIncident($incidentId)
    {
        if (!$this->_currentUserCanUpdateIncident($incidentId)) {
            throw new Fisma_Zend_Exception_InvalidPrivilege('You are not allowed to edit this incident.');
        }
    }

    /**
     * Check whether the current user can view the specified incident
     *
     * This is an expensive operation. DO NOT CALL IT IN A TIGHT LOOP.
     *
     * @param int $incidentId The ID of the incident
     * @return bool
     */
    public function _currentUserCanViewIncident($incidentId)
    {
        $userCanView = false;

        if (!$this->_acl->hasPrivilegeForClass('read', 'Incident')) {
            // Check if this user is an observer or actor
            $observerCount = Doctrine_Query::create()
                 ->select('i.id')
                 ->from('Incident i')
                 ->leftJoin('i.Users u')
                 ->where('i.id = ? AND u.id = ?', array($incidentId, $this->_me->id))
                 ->count();

            if ($observerCount > 0) {
                $userCanView = true;
            }

        } else {
            $userCanView = true;
        }

        return $userCanView;
    }

    /**
     * Check whether the current user can classify the specified incident
     *
     * @param int $incidentId The ID of the incident
     * @return boolean
     */
    private function _currentUserCanClassifyIncident($incidentId)
    {
        $incident = Doctrine::getTable('Incident')->findOneById($incidentId);

        if ($this->_acl->hasPrivilegeForObject('classify', $incident)) {
            if ($incident->isLocked) {
                if ($this->_acl->hasPrivilegeForObject('lock', $incident)) {
                    return true;
                }
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Assert that the current user is allowed to view the specified incident.
     *
     * Throws an exception if the current user is not allowed to view the specified incident.
     *
     * This is an expensive operation. DO NOT CALL IT IN A TIGHT LOOP.
     *
     * @param int $incidentId
     */
    private function _assertCurrentUserCanViewIncident($incidentId)
    {
        if (!$this->_currentUserCanViewIncident($incidentId)) {
            throw new Fisma_Zend_Exception_InvalidPrivilege('You are not allowed to view this incident.');
        }
    }

    private function _getStates()
    {
        $states = array (
              'AL' => 'Alabama',
              'AK' => 'Alaska',
              'AZ' => 'Arizona',
              'AR' => 'Arkansas',
              'CA' => 'California',
              'CO' => 'Colorado',
              'CT' => 'Connecticut',
              'DE' => 'Delaware',
              'DC' => 'District of Columbia',
              'FL' => 'Florida',
              'GA' => 'Georgia',
              'HI' => 'Hawaii',
              'ID' => 'Idaho',
              'IL' => 'Illinois',
              'IN' => 'Indiana',
              'IA' => 'Iowa',
              'KS' => 'Kansas',
              'KY' => 'Kentucky',
              'LA' => 'Louisiana',
              'ME' => 'Maine',
              'MD' => 'Maryland',
              'MA' => 'Massachusetts',
              'MI' => 'Michigan',
              'MN' => 'Minnesota',
              'MS' => 'Mississippi',
              'MO' => 'Missouri',
              'MT' => 'Montana',
              'NE' => 'Nebraska',
              'NV' => 'Nevada',
              'NH' => 'New Hampshire',
              'NJ' => 'New Jersey',
              'NM' => 'New Mexico',
              'NY' => 'New York',
              'NC' => 'North Carolina',
              'ND' => 'North Dakota',
              'OH' => 'Ohio',
              'OK' => 'Oklahoma',
              'OR' => 'Oregon',
              'PW' => 'Palau',
              'PA' => 'Pennsylvania',
              'PR' => 'Puerto Rico',
              'RI' => 'Rhode Island',
              'SC' => 'South Carolina',
              'SD' => 'South Dakota',
              'TN' => 'Tennessee',
              'TX' => 'Texas',
              'UT' => 'Utah',
              'VT' => 'Vermont',
              'VI' => 'Virgin Island',
              'VA' => 'Virginia',
              'WA' => 'Washington',
              'WV' => 'West Virginia',
              'WI' => 'Wisconsin',
              'WY' => 'Wyoming'
        );

        return $states;
    }

    private function _getOS()
    {
        return array(        '' => '',
                         'win7' => 'Windows 7',
                        'vista' => 'Vista',
                           'xp' => 'XP',
                        'macos' => 'Mac OSX',
                        'linux' => 'Linux',
                         'unix' => 'Unix'
                    );
    }

    private function _getMobileMedia()
    {
        return array(    'laptop' => 'Laptop',
                           'disc' => 'CD/DVD',
                       'document' => 'Document',
                            'usb' => 'USB/Flash Drive',
                           'tape' => 'Magnetic Tape',
                          'other' => 'Other'
                    );
    }

    private function _createBoolean(&$form, $elements)
    {
        foreach ($elements as $elementName) {
            $element = $form->getElement($elementName);
            $element->addMultiOptions(array('' => ' -- select -- '));
            $element->addMultiOptions(array('NO' => ' NO '));
            $element->addMultiOptions(array('YES' => ' YES '));
        }

        return 1;
    }

    /**
     * Get the user ids of all IRCs
     */
    private function _getIrcs()
    {
        $query = Doctrine_Query::create()
                 ->select("u.email as email, CONCAT(u.nameFirst, ' ', u.nameLast) as name")
                 ->from('User u')
                 ->innerJoin('u.Roles r')
                 ->where('r.nickname LIKE ?', 'IRC')
                 ->setHydrationMode(Doctrine::HYDRATE_SCALAR);
        $ircs = $query->execute();

        return $ircs;
    }

    /**
     * Return an array of users with the inspector general (OIG) role
     *
     * @return Doctrine_Collection
     */
    private function _getOigUsers()
    {
        $oigQuery = Doctrine_Query::create()
                    ->from('User u')
                    ->innerJoin('u.Roles r')
                    ->where('r.nickname = ?', 'OIG');

        $oigUsers = $oigQuery->execute();

        return $oigUsers;
    }

    /**
     * Return an array of all users with the privacy advocate (PA) role
     *
     * @return Doctrine_Collection
     */
    private function _getPrivacyAdvocates()
    {
        $paQuery = Doctrine_Query::create()
                   ->from('User u')
                   ->innerJoin('u.Roles r')
                   ->where('r.nickname = ?', 'PA');

        $paUsers = $paQuery->execute();

        return $paUsers;
    }

    private function _getAssociatedUsers($incidentId)
    {
        $incidentUsersQuery = Doctrine_Query::create()
                              ->select("iru.userId as id")
                              ->from('IrIncidentUser iru')
                              ->where('iru.incidentId = ?', $incidentId)
                              ->setHydrationMode(Doctrine::HYDRATE_SCALAR);

        $incidentUsers = $incidentUsersQuery->execute();
        array_walk($incidentUsers, function(&$item, $key) { //flatten the array to use with whereIn
            $item = $item['iru_id'];
        });

        return array_values($incidentUsers);
    }

    /**
     * List users eligible to be an actor or observer
     *
     * All users are eligible unless they are already an actor or observer for this incident.
     *
     * @GETAllowed
     */
    public function getEligibleUsersAction()
    {
        $id = Inspekt::getInt($this->getRequest()->getParam('id'));
        $keyword = $this->getRequest()->getParam('keyword');

        $users = Doctrine::getTable('User')
            ->getAutocompleteQuery($keyword)
            ->leftJoin("u.IrIncidentUser iu ON u.id = iu.userId AND iu.incidentId = $id")
            ->andWhere('iu.incidentId IS NULL')
            ->execute();
        Doctrine::getTable('User')->parseAutocompleteResult($users);

        return $this->_helper->json(array('pointsOfContact' => $users));
    }

    /**
     * Replace the default "Create" button with a "Report Incident" button
     *
     * @param Fisma_Doctrine_Record $record The object for which this toolbar applies, or null if not applicable
     * @param array $fromSearchParams The array for "Previous" and "Next" button null if not
     * @return array Array of Fisma_Yui_Form_Button
     */
    public function getToolbarButtons(Fisma_Doctrine_Record $record = null, $fromSearchParams = null)
    {
        $buttons = parent::getToolbarButtons($record, $fromSearchParams);

        $fromSearchUrl = '';
        if (!empty($fromSearchParams)) {
            $fromSearchUrl = $this->_helper->makeUrlParams($fromSearchParams);
        }

        if ($this->getRequest()->getActionName() === 'create') {
            unset($buttons['discardButton']);
        } else {
            $buttons['create'] = new Fisma_Yui_Form_Button_Link(
                'toolbarReportIncidentButton',
                array(
                    'value' => 'New',
                    'href' => $this->getBaseUrl() . '/create',
                    'imageSrc' => '/images/create.png'
                )
            );
        }

        // Add a "Reject" button if the incident is still in "new" status
        if ($record && 'new' == $record->status) {
            $buttons['reject'] = new Fisma_Yui_Form_Button(
                'reject',
                array(
                    'label' => 'Reject',
                    'onClickFunction' => 'Fisma.Incident.confirmReject',
                    'imageSrc' => '/images/trash_recyclebin_empty_closed.png'
                )
            );
        }

        // Add lock/unlock buttons if the user has the capability to use them
        if ($record && $this->_acl->hasPrivilegeForClass('lock', 'Incident')) {
            if ($record->isLocked) {
                $buttons['unlock'] = new Fisma_Yui_Form_Button(
                    'unlock',
                    array(
                        'label' => 'Unlock',
                        'onClickFunction' => 'Fisma.Util.formPostAction',
                        'onClickArgument' => array(
                            'action' => "/incident/unlock$fromSearchUrl",
                            'id' => $record->id,
                        ),
                        'imageSrc' => '/images/privacy-small.png'
                    )
                );
            } else {
                $buttons['lock'] = new Fisma_Yui_Form_Button(
                    'lock',
                    array(
                        'label' => 'Lock',
                        'onClickFunction' => 'Fisma.Util.formPostAction',
                        'onClickArgument' => array(
                            'action' => "/incident/lock$fromSearchUrl",
                            'id' => $record->id
                        ),
                        'imageSrc' => '/images/privacy-small.png'
                    )
                );
            }
        }

        return $buttons;
    }

    /**
     * Send email to the user who has been assigned an incident
     *
     * @param integer $userId The id of user
     * @param integer $incidentId The id of incident
     * @param string $mailSubject The subject of mail
     *
     * @return void
     */
    private function _sendMailToAssignedUser($userId, $incidentId, $mailSubject)
    {
        $user = Doctrine::getTable('User')->find($userId);

        $options = array(
            'incidentUrl' => Fisma_Url::baseUrl() . '/incident/view/id/' . $incidentId,
            'incidentId' => $incidentId,
            'isUser' => ($user instanceof User)
        );

        $mail = new Mail();
        $mail->recipient     = $user->email;
        $mail->recipientName = $user->nameFirst . ' ' . $user->nameLast;
        $mail->subject       = $mailSubject;

        $mail->mailTemplate('ir_assign', $options);

        Zend_Registry::get('mail_handler')->setMail($mail)->send();
    }

    /**
     * Delete artifact
     */
    public function deleteArtifactAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $id = $this->_request->getParam('id');
        $artifactId = $this->_request->getParam('artifactId');

        $incident = Doctrine::getTable('Incident')->getAttachmentQuery($id, $artifactId)->execute()->getLast();

        if (empty($incident)) {
            throw new Fisma_Zend_Exception_User('Invalid incident ID');
        }

        if ($incident->Attachments->count() <= 0) {
            throw new Fisma_Zend_Exception_User('Invalid artifact ID');
        }

        // There is no ACL defined for artifact objects, access is only based on the associated incident:
        $this->_acl->requirePrivilegeForObject('update', $incident);

        $message = "Artifact deleted: {$incident->Attachments[0]->fileName} (#{$incident->Attachments[0]->id})";
        $incident->Attachments->remove(0);
        $incident->save();

        $incident->getAuditLog()->write($message);
        if ($returnUrl = $this->getRequest()->getParam('returnUrl')) {
            $this->_redirect($returnUrl);
        }
    }

    public function getForm($formName = null)
    {
        $form = parent::getForm($formName);
        if ($elem = $form->getElement('categoryId')) {
            $elem->addMultiOption('')
                 ->addMultiOptions(IrCategoryTable::getCategoriesForSelect());
        }
        if ($elem = $form->getElement('severityLevel')) {
            $tags = Doctrine::getTable('Tag')->findOneByTagId('incident-severity-level')->labels;
            $elem->addMultiOption('')
                 ->addMultiOptions(array_combine($tags, $tags));
        }
        if ($elem = $form->getElement('organizationId')) {
            $elem->addMultiOption('')
                ->addMultiOptions(
                    $this->view->treeToSelect(
                        CurrentUser::getInstance()
                            ->getOrganizationsQuery()
                            ->leftJoin('o.System s')
                            ->leftJoin('o.OrganizationType orgType ')
                            ->andWhere('orgType.nickname <> ? OR s.sdlcPhase <> ?', array('system', 'disposal'))
                            ->execute(),
                        'nickname'
                    )
                );
        }
        if ($elem = $form->getElement('source')) {
            $tags = Doctrine::getTable('Tag')->findOneByTagId('incident-source')->labels;
            $elem->addMultiOption('')
                 ->addMultiOptions(array_combine($tags, $tags));
        }
        if ($elem = $form->getElement('impact')) {
            $tags = Doctrine::getTable('Tag')->findOneByTagId('incident-impact')->labels;
            $elem->addMultiOption('')
                 ->addMultiOptions(array_combine($tags, $tags));
        }
        $form->setDefaults(
            array(
                'incidentDate' => Zend_Date::now()->toString(Fisma_Date::FORMAT_DATE),
                'incidentTime' => Zend_Date::now()->setSecond(0)->get(Fisma_Date::FORMAT_TIME)
            )
        );
        $form->getElement('incidentDate')->addDecorator(new Fisma_Zend_Form_Decorator_Date());
        return $form;
    }

    /**
     * Hooks for manipulating and saving the values retrieved by Forms
     *
     * @param Zend_Form $form The specified form
     * @param Doctrine_Record|null $subject The specified subject model
     * @return Fisma_Doctrine_Record The saved object.
     * @throws Fisma_Zend_Exception if the subject is not instance of Doctrine_Record
     */
    protected function saveValue($form, $subject = null)
    {
        if (is_null($subject)) {
            $subject = new Incident();
            $subject->ReportingUser = CurrentUser::getInstance();
            $orgId = $form->getValue('organizationId');
            if (!empty($orgId)) {
                $subject->pocId = Doctrine::getTable('Organization')->find($orgId)->pocId;
            }
        }
        return parent::saveValue($form, $subject);
    }
}
