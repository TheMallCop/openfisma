<?php
/**
 * Copyright (c) 2008 Endeavor Systems, Inc.
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
 * The remediation controller handles CRUD for findings in remediation.
 *
 * @author     Jim Chen <xhorse@users.sourceforge.net>
 * @copyright  (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Controller
 */
class Finding_RemediationController extends Fisma_Zend_Controller_Action_Object
{
    /**
     * The main name of the model.
     * 
     * This model is the main subject which the controller operates on.
     * 
     * @var string
     */
    protected $_modelName = 'Finding';

    /**
     * The orgSystems which are belongs to current user.
     * 
     * @var Doctrine_Collection
     */
    protected $_organizations = null;
    
    /**
     * The preDispatch hook is used to split off poam modify actions, mitigation approval actions, and evidence
     * approval actions into separate controller actions.
     * 
     * @return void
     */
    public function preDispatch() 
    {
        parent::preDispatch();

        $this->_organizations = $this->_me->getOrganizationsByPrivilege('finding', 'read');

        $request = $this->getRequest();
        $this->_paging['startIndex'] = $request->getParam('startIndex', 0);
        if ('modify' == $request->getActionName()) {
            // If this is a mitigation, evidence approval, or evidence upload, then redirect to the 
            // corresponding controller action
            if (isset($_POST['submit_msa'])) {
                $request->setParam('sub', null);
                $this->_forward('msa');
            } elseif (isset($_POST['submit_ea'])) {
                $request->setParam('sub', null);
                $this->_forward('evidence');
            } elseif (isset($_POST['upload_evidence'])) {
                $request->setParam('sub', null);
                $this->_forward('uploadevidence');
            }
        }
    }

    /**
     * Create contexts for printable tab views.
     * 
     * @return void
     */
    public function init()
    {
        $this->_helper->ajaxContext()
             ->addActionContext('finding', 'html')
             ->addActionContext('mitigation-strategy', 'html')
             ->addActionContext('risk-analysis', 'html')
             ->addActionContext('security-control', 'html')
             ->addActionContext('comments', 'html')
             ->addActionContext('artifacts', 'html')
             ->addActionContext('audit-log', 'html')
             ->initContext();

        parent::init();
    }
    
    /**
     * Default action.
     * 
     * It combines the searching and summary into one page.
     * 
     * @return void
     */
    public function indexAction()
    {
        $this->_acl->requirePrivilegeForClass('read', 'Finding');
        
        $this->_helper->actionStack('searchbox', 'Remediation');
        $this->_helper->actionStack('summary', 'Remediation');
    }

    /** 
     * Overriding Hooks
     * 
     * @param Zend_Form $form The specified form to save
     * @param Doctrine_Record|null $subject The subject model related to the form
     * @return integer ID of the object 
     * @throws Fisma_Zend_Exception if the subject is not null or the organization of the finding associated
     * to the subject doesn`t exist
     */
    protected function saveValue($form, $finding = null)
    {
        if (is_null($finding)) {
            $finding = new $this->_modelName();
        } else {
            throw new Fisma_Zend_Exception('Invalid parameter expecting a Record model');
        }

        $values = $this->getRequest()->getPost();

        if (empty($values['securityControlId'])) {
            unset($values['securityControlId']);
        }

        $finding->merge($values);
        
        $organization = Doctrine::getTable('Organization')->find($values['responsibleOrganizationId']);

        if ($organization !== false) {
            $finding->Organization = $organization;
        } else {
            throw new Fisma_Zend_Exception("The user tried to associate a new finding with a"
                                         . " non-existent organization (id={$values['orgSystemId']}).");
        }

        $finding->CreatedBy = CurrentUser::getInstance();

        $finding->save();

        return $finding->id;
    }

    /**
     * Override to fill in option values for the select elements, etc.
     *
     * @param string|null $formName The name of the specified form
     * @return Zend_Form The specified form of the subject model
     */
    public function getForm($formName = null)
    {
        $form = parent::getForm($formName);

        // Default discovered date is today
        $form->getElement('discoveredDate')
             ->setValue(Zend_Date::now()->toString(Fisma_Date::FORMAT_DATE))
             ->addDecorator(new Fisma_Zend_Form_Decorator_Date);

        // Populate <select> for finding sources
        $sources = Doctrine::getTable('Source')->findAll()->toArray();

        $form->getElement('sourceId')->addMultiOptions(array('' => null));

        foreach ($sources as $source) {
            $form->getElement('sourceId')
                 ->addMultiOptions(array($source['id'] => html_entity_decode($source['name'])));
        }

        // Populate <select> for threat level
        $threatLevels = Doctrine::getTable('Finding')->getEnumValues('threatLevel');
        $threatLevels = array('' => '') + array_combine($threatLevels, $threatLevels);

        $form->getElement('threatLevel')->setMultiOptions($threatLevels);

        // Populate <select> for responsible organization
        $systems = $this->_me->getOrganizationsByPrivilege('finding', 'create');
        $selectArray = $this->view->systemSelect($systems);
        $form->getElement('responsibleOrganizationId')->addMultiOptions($selectArray);
        
        // If the user can't create a POC object, then don't set up the POC create form
        if (!$this->_acl->hasPrivilegeForClass('create', 'Poc')) {
            $form->getElement('pocAutocomplete')->setAttrib('setupCallback', null);
        }

        return $form;
    }

    /**
     * Override to set some non-trivial default values (such as the security control autocomplete)
     *
     * @param Doctrine_Record $subject The specified subject model
     * @param Zend_Form $form The specified form
     * @return Zend_Form The manipulated form
     */
    protected function setForm($subject, $form)
    {
        parent::setForm($subject, $form);
        
        $values = $this->getRequest()->getPost();

        // Set default value for security control autocomplete
        if (empty($values['securityControlId'])) {
            unset($values['securityControlId']);
        }

        $form->getElement('securityControlAutocomplete')
             ->setValue($values['securityControlId'])
             ->setDisplayText($values['securityControlAutocomplete']);

        return $form;
    }

    /**
     * View details of a finding object
     * 
     * @return void
     */
    public function viewAction()
    {
        $id = $this->_request->getParam('id');

        $finding = $this->_getSubject($id);
        $this->view->finding = $finding;
        
        $this->_acl->requirePrivilegeForObject('read', $finding);
        
        // Put a span around the comment count so that it can be updated from Javascript
        $commentCount = '<span id=\'findingCommentsCount\'>' . $finding->getComments()->count() . '</span>';

        $tabView = new Fisma_Yui_TabView('FindingView', $id);

        $tabView->addTab("Finding $id", "/finding/remediation/finding/id/$id/format/html");
        $tabView->addTab("Mitigation Strategy", "/finding/remediation/mitigation-strategy/id/$id/format/html");
        $tabView->addTab("Risk Analysis", "/finding/remediation/risk-analysis/id/$id/format/html");
        $tabView->addTab("Security Control", "/finding/remediation/security-control/id/$id/format/html");
        $tabView->addTab("Comments ($commentCount)", "/finding/remediation/comments/id/$id/format/html");
        $tabView->addTab(
            "Artifacts (" . $finding->Evidence->count() . ")",
            "/finding/remediation/artifacts/id/$id/format/html"
        );
        $tabView->addTab("Audit Log", "/finding/remediation/audit-log/id/$id/format/html");

        $this->view->tabView = $tabView;

        $buttons = array();
        $buttons['list'] = new Fisma_Yui_Form_Button_Link(
            'toolbarListButton',
            array(
                'value' => 'Return to Search Results',
                'href' => $this->getBaseUrl() . '/list'
            )
        );
        // Only display controls if the finding has not been deleted
        if (!$finding->isDeleted()) {
            // Display the delete finding button if the user has the delete finding privilege
            if ($this->view->acl()->hasPrivilegeForObject('delete', $finding)) {

                $buttons['delete'] = new Fisma_Yui_Form_Button(
                    'deleteFinding', 
                    array(
                          'label' => 'Delete Finding',
                          'onClickFunction' => 'Fisma.Util.showConfirmDialog',
                          'onClickArgument' => array(
                              'url' => "/finding/remediation/delete/id/$id",
                              'text' => "WARNING: You are about to delete the finding record. This action cannot be " 
                                        . "undone. Do you want to continue?",
                              'isLink' => false
                        ) 
                    )
                );
            }
            
            // The "save" and "discard" buttons are only displayed if the user can update any of the findings fields
            if ($this->view->acl()->hasPrivilegeForObject('update_*', $finding)) {
                $discardChangesButtonConfig = array(
                    'value' => 'Discard Changes',
                    'href' => '/finding/remediation/view/id/' . $finding->id
                );
                
                $buttons['discard'] = new Fisma_Yui_Form_Button_Link(
                    'discardChanges', 
                    $discardChangesButtonConfig
                );
            
                $buttons['save'] = new Fisma_Yui_Form_Button_Submit(
                    'saveChanges', 
                    array('label' => 'Save Changes')
                );
            }
        }

        // printer friendly version
        $buttons['print'] = new Fisma_Yui_Form_Button_Link(
            'toolbarPrintButton',
            array(
                'value' => 'Printer Friendly Version',
                'href' => $this->getBaseUrl() . '/print/id/' . $id,
                'target' => '_new'
            )
        );
        $this->view->toolbarButtons = $buttons;
    }

    /**
     * Printer-friendly version of the finding view page.
     * 
     * @return void
     */
    public function printAction()
    {
        $this->findingAction();
        $this->mitigationStrategyAction();
        $this->riskAnalysisAction();
        $this->securityControlAction();
        $this->commentsAction();
        $this->artifactsAction();
        $this->auditLogAction();
    }

    /**
     * Display comments for this finding
     */
    public function commentsAction()
    {
        $id = $this->_request->getParam('id');
        $this->view->assign('id', $id);
        $finding = $this->_getSubject($id);

        $this->_acl->requirePrivilegeForObject('read', $finding);

        $comments = $finding->getComments()->fetch(Doctrine::HYDRATE_ARRAY);

        $commentRows = array();

        foreach ($comments as $comment) {
            $commentRows[] = array(
                'timestamp' => $comment['createdTs'],
                'username' => $this->view->userInfo($comment['User']['username']),
                'Comment' =>  $this->view->textToHtml($this->view->escape($comment['comment']))
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
                'Comment',
                false,
                'Fisma.TableFormat.formatHtml',
                null,
                'comment'
            )
        );

        $dataTable->setData($commentRows);

        $this->view->commentDataTable = $dataTable;

        $commentButton = new Fisma_Yui_Form_Button(
            'commentButton', 
            array(
                'label' => 'Add Comment', 
                'onClickFunction' => 'Fisma.Commentable.showPanel',
                'onClickArgument' => array(
                    'id' => $id,
                    'type' => 'Finding',
                    'callback' => array(
                        'object' => 'Finding',
                        'method' => 'commentCallback'
                    )
                )
            )
        );

        if (!$this->_acl->hasPrivilegeForObject('comment', $finding) || $finding->isDeleted()) {
            $commentButton->readOnly = true;
        }

        $this->view->commentButton = $commentButton;
    }
    
    /**
     * Modify the finding
     * 
     * @return void
     */
    public function modifyAction()
    {
        // ACL for finding objects is handled inside the finding listener, because it has to do some
        // very fine-grained error checking
        
        $id = $this->_request->getParam('id');
        $findingData = $this->_request->getPost('finding', array());
        $findingSecurityControlId = $this->getRequest()->getPost('securityControlId');
        if (!empty($findingSecurityControlId)) {
            $findingData['securityControlId'] = $findingSecurityControlId;
        }

        $this->_forward('view', null, null, array('id' => $id));

        if (isset($findingData['currentEcd'])) {
            if (Zend_Validate::is($findingData['currentEcd'], 'Date')) {
                $date = new Zend_Date();
                $ecd  = new Zend_Date($findingData['currentEcd'], Fisma_Date::FORMAT_DATE);

                if ($ecd->isEarlier($date)) {
                    $error = 'Expected completion date has been set before the current date.'
                           . ' Make sure that this is correct.';
                    $this->view->priorityMessenger($error, 'notice');
                }
            } else {
                $error = 'Expected completion date provided is not a valid date. Unable to update finding.';
                $this->view->priorityMessenger($error, 'warning');
                return;
            }
        }
        
        if (isset($findingData['threatLevel']) && $findingData['threatLevel'] === '') {
            $error = 'Threat Level is a required field.';
            $this->view->priorityMessenger($error, 'warning');
            return;
        }

        if (
            isset($findingData['countermeasuresEffectiveness']) && 
            $findingData['countermeasuresEffectiveness'] === ''
        ) {
            $error = 'Countermeasures Effectiveness is a required field.';
            $this->view->priorityMessenger($error, 'warning');
            return;
        }
        
        $finding = $this->_getSubject($id);

        // Security control is a hidden field. If it is blank, that means the user did not submit it, and it needs to
        // be unset.
        if (empty($findingData['securityControlId'])) {
            unset($findingData['securityControlId']);
        }

        try {
            Doctrine_Manager::connection()->beginTransaction();
            $finding->merge($findingData);
            $finding->save();
            Doctrine_Manager::connection()->commit();
            $this->_redirect("/finding/remediation/view/id/$id");
        } catch (Fisma_Zend_Exception_User $e) {
            $this->view->priorityMessenger($e->getMessage(), 'warning');
        } catch (Exception $e) {
            Doctrine_Manager::connection()->rollback();
            $message = "Error: Unable to update finding. ";
            if (Fisma::debug()) {
                $message .= $e->getMessage();
            }
            $model = 'warning';
            $this->view->priorityMessenger($message, $model);
        }
    }

    /**
     * Mitigation Strategy Approval Process
     * 
     * @return void
     */
    public function msaAction()
    {
        $id       = $this->_request->getParam('id');
        $do       = $this->_request->getParam('do');
        $decision = $this->_request->getPost('decision');

        $finding  = $this->_getSubject($id);
        if (!empty($decision)) {
            $this->_acl->requirePrivilegeForObject($finding->CurrentEvaluation->Privilege->action, $finding);
        }

        try {
            Doctrine_Manager::connection()->beginTransaction();

            if ('submitmitigation' == $do) {
                $this->_acl->requirePrivilegeForObject('mitigation_strategy_submit', $finding);
                $finding->submitMitigation(CurrentUser::getInstance());
            }
            if ('revisemitigation' == $do) {
                $this->_acl->requirePrivilegeForObject('mitigation_strategy_revise', $finding);
                $finding->reviseMitigation(CurrentUser::getInstance());
            }

            if ('APPROVED' == $decision) {
                $comment = $this->_request->getParam('comment');
                $finding->approve(CurrentUser::getInstance(), $comment);
            }

            if ('DENIED' == $decision) {
                $comment = $this->_request->getParam('comment');
                $finding->deny(CurrentUser::getInstance(), $comment);
            }
            Doctrine_Manager::connection()->commit();
        } catch (Doctrine_Connection_Exception $e) {
            Doctrine_Manager::connection()->rollback();
            $message = 'Failure in this operation. '
                     . $e->getPortableMessage() 
                     . ' ('
                     . $e->getPortableCode()
                     . ')';
            if (Fisma::debug()) {
                $message .= $e->getMessage();
            }
            $model = 'warning';
            $this->view->priorityMessenger($message, $model);
        }

        $this->_redirect("/finding/remediation/view/id/$id");
    }

    /**
     * Upload evidence
     * 
     * @return void
     */
    public function uploadevidenceAction()
    {
        $id = $this->_request->getParam('id');
        $finding = $this->_getSubject($id);

        if ($finding->isDeleted()) {
            $message = "Evidence cannot be uploaded to a deleted finding.";
            throw new Fisma_Zend_Exception($message);
        }

        $this->_acl->requirePrivilegeForObject('upload_evidence', $finding);

        define('EVIDENCE_PATH', Fisma::getPath('data') . '/uploads/evidence');
        $file = $_FILES['evidence'];

        try {
            if ($file['error'] != UPLOAD_ERR_OK) {
              if ($file['error'] == UPLOAD_ERR_INI_SIZE) {
                $message = "The uploaded file is larger than is allowed by the server.";
              } elseif ($file['error'] == UPLOAD_ERR_PARTIAL) {
                $message = "The uploaded file was only partially received.";
              } else {
                $message = "An error occurred while processing the uploaded file.";
              }
              throw new Fisma_Zend_Exception($message);
            }

            if (!$file['name']) {
                $message = "You did not select a file to upload. Please select a file and try again.";
                throw new Fisma_Zend_Exception($message);
            }

            $extension = explode('.', $file['name']);
            $extension = end($extension);

            /** @todo cleanup */
            if (in_array(strtolower($extension), array('exe', 'php', 'phtml', 'php5', 'php4', 'js', 'css'))) {
                $message = 'This file type is not allowed.';
                throw new Fisma_Zend_Exception($message);
            }

            if (!file_exists(EVIDENCE_PATH)) {
                mkdir(EVIDENCE_PATH);
            }
            if (!file_exists(EVIDENCE_PATH .'/'. $id)) {
                mkdir(EVIDENCE_PATH .'/'. $id);
            }
            $nowStr = Zend_Date::now()->toString(Fisma_Date::FORMAT_FILENAME_DATETIMESTAMP);
            $count = 0;
            $filename = preg_replace('/^(.*)\.(.*)$/', '$1-' . $nowStr . '.$2', $file['name'], 2, $count);
            $absFile = EVIDENCE_PATH ."/{$id}/{$filename}";
            if ($count > 0) {
                if (!move_uploaded_file($file['tmp_name'], $absFile)) {
                    $message = 'The file upload failed due to a server configuration error.' 
                             . ' Please contact the administrator.';
                    $logger = $this->getInvokeArg('bootstrap')->getResource('Log');
                    $logger->log('Failed in move_uploaded_file(). ' . $absFile . "\n" . $file['error'], Zend_Log::ERR);
                    throw new Fisma_Zend_Exception($message);
                }
            } else {
                throw new Fisma_Zend_Exception('The filename is not valid');
            }

            $finding->uploadEvidence($filename, CurrentUser::getInstance());
        } catch (Fisma_Zend_Exception $e) {
            $this->view->priorityMessenger($e->getMessage(), 'warning');
        }

        $this->_redirect("/finding/remediation/view/id/$id");
    }
    
    /**
     * Download evidence
     * 
     * @return void
     */
    public function downloadevidenceAction()
    {
        $id = $this->_request->getParam('id');
        $evidence = Doctrine::getTable('Evidence')->find($id);
        if (empty($evidence)) {
            throw new Fisma_Zend_Exception('Invalid evidence ID');
        }

        // There is no ACL defined for evidence objects, access is only based on the associated finding:
        $this->_acl->requirePrivilegeForObject('read', $evidence->Finding);

        $fileName = $evidence->filename;
        $filePath = Fisma::getPath('data') . '/uploads/evidence/'. $evidence->findingId . '/';

        if (file_exists($filePath . $fileName)) {
            $this->_helper->layout->disableLayout(true);
            $this->_helper->viewRenderer->setNoRender();
            ob_end_clean();
            $expireDateTime = new Zend_Date(time()+31536000, Zend_Date::TIMESTAMP);
            $expireDateTime->setTimezone('GMT');
            header(
                'Expires: '
                . $expireDateTime->toString(Fisma_Date::FORMAT_WEEKDAY_SHORT_DAY_MONTH_NAME_SHORT_YEAR_TIME)
                . ' GMT'
            );
            header('Content-type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . urlencode($fileName));
            header('Content-Length: ' . filesize($filePath . $fileName));
            header('Pragma: ');
            $fp = fopen($filePath . $fileName, 'rb');
            while (!feof($fp)) {
                $buffer = fgets($fp, 4096);
                echo $buffer;
            }
            fclose($fp);
        } else {
            throw new Fisma_Zend_Exception('The requested file could not be found');
        }
    }
    
    /**
     * Handle the evidence evaluations
     * 
     * @return void
     */
    public function evidenceAction()
    {
        $id       = $this->_request->getParam('id');
        $decision = $this->_request->getPost('decision');

        $finding  = $this->_getSubject($id);

        if (!empty($decision)) {
            $this->_acl->requirePrivilegeForObject($finding->CurrentEvaluation->Privilege->action, $finding);
        }

        try {
            Doctrine_Manager::connection()->beginTransaction();
            if ('APPROVED' == $decision) {
                $comment = $this->_request->getParam('comment');
                $finding->approve(CurrentUser::getInstance(), $comment);
            }

            if ('DENIED' == $decision) {
                $comment = $this->_request->getParam('comment');
                $finding->deny(CurrentUser::getInstance(), $comment);
            }
            Doctrine_Manager::connection()->commit();
        } catch (Doctrine_Exception $e) {
            Doctrine_Manager::connection()->rollback();
            $message = "Failure in this operation. ";
            if (Fisma::debug()) {
                $message .= $e->getMessage();
            }
            $model = 'warning';
            $this->view->priorityMessenger($message, $model);
        }

        $this->_redirect("/finding/remediation/view/id/$id");
    }

    /**
     * Generate RAF report
     *
     * It can handle different format of RAF report.
     * 
     * @return void
     */
    public function rafAction()
    {
        $id = $this->_request->getParam('id');
        $finding = $this->_getSubject($id);

        $this->_acl->requirePrivilegeForObject('read', $finding);

        try {
            if ($finding->threat == '' ||
                empty($finding->threatLevel) ||
                $finding->countermeasures == '' ||
                empty($finding->countermeasuresEffectiveness)) {
                throw new Fisma_Zend_Exception("The Threat or Countermeasures Information is not "
                    ."completed. An analysis of risk cannot be generated, unless these values are defined.");
            }
            
            $system = $finding->Organization->System;
            if (NULL == $system->fipsCategory) {
                throw new Fisma_Zend_Exception('The security categorization for ' .
                     '(' . $finding->responsibleOrganizationId . ')' . 
                     $finding->Organization->name . ' is not defined. An analysis of ' .
                     'risk cannot be generated unless these values are defined.');
            }
            $this->view->securityCategorization = $system->fipsCategory;
        } catch (Fisma_Zend_Exception $e) {
            if ($e instanceof Fisma_Zend_Exception) {
                $message = $e->getMessage();
            }
            $this->view->priorityMessenger($message, 'warning');
            $this->_forward('view', null, null, array('id' => $id));
            return;
        }
        $this->_helper->fismaContextSwitch()
               ->setHeader('pdf', 'Content-Disposition', "attachement;filename={$id}_raf.pdf")
               ->addActionContext('raf', array('pdf'))
               ->initContext();
        $this->view->finding = $finding;
    }
    
    /**
     * Display basic data about the finding and the affected asset
     * 
     * @return void
     */
    function findingAction() 
    {
        $this->_viewFinding();
        
        $finding = $this->view->finding;
        $organization = $finding->Organization;

        // For users who can view organization or system URLs, construct that URL
        $controller = ($organization->OrganizationType->nickname == 'system' ? 'system' : 'organization');
        $idParameter = ($organization->OrganizationType->nickname == 'system' ? 'oid' : 'id');
        $this->view->organizationViewUrl = "/$controller/view/$idParameter/$organization->id";

        $this->view->keywords = $this->_request->getParam('keywords');
    }

    /**
     * Fields for defining the mitigation strategy
     * 
     * @return void
     */
    function mitigationStrategyAction() 
    {
        $this->_viewFinding();
    }

    /**
     * Display fields related to risk analysis such as threats and countermeasures
     * 
     * @return void
     */
    function riskAnalysisAction() 
    {
        $this->_viewFinding();
        $this->view->keywords = $this->_request->getParam('keywords');
    }

    /**
     * Display fields related to risk analysis such as threats and countermeasures
     * 
     * @return void
     */
    function artifactsAction() 
    {
        $this->_viewFinding();

        // Get a list of artifacts related to this finding
        $artifactsQuery = Doctrine_Query::create()
                          ->from('Evidence e')
                          ->leftJoin('e.FindingEvaluations fe')
                          ->leftJoin('e.User u1')
                          ->leftJoin('fe.User u2')
                          ->where('e.findingId = ?', $this->view->finding->id)
                          ->orderBy('e.createdTs DESC');

        $this->view->artifacts = $artifactsQuery->execute();

        // Get a list of all evaluations so that the ones which are skipped or pending can still be rendered.
        $evaluationsQuery = Doctrine_Query::create()
                            ->from('Evaluation e')
                            ->where('e.approvalGroup = ?', 'evidence')
                            ->orderBy('e.precedence');

        $this->view->evaluations = $evaluationsQuery->execute();
    }
        
    /**
     * Display the audit log associated with a finding
     * 
     * @return void
     */
    function auditLogAction() 
    {
        $this->_viewFinding();
        
        $logs = $this->view->finding->getAuditLog()->fetch(Doctrine::HYDRATE_SCALAR);

        $logRows = array();

        foreach ($logs as $log) {
            $logRows[] = array(
                'timestamp' => $log['o_createdTs'],
                'user' => $this->view->userInfo($log['u_username']),
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

        $this->view->auditLogDataTable = $dataTable;
    }

    /**
     * Display the NIST SP 800-53 control mapping and related information
     * 
     * @return void
     */
    function securityControlAction() 
    {
        $this->_viewFinding();
        
        $form = Fisma_Zend_Form_Manager::loadForm('finding_security_control');

        // Set up the available and default values for the form
        $scId = $this->view->finding->securityControlId;
        if (!empty($scId)) {
            $sc = $this->view->finding->SecurityControl;
            $c = $sc->Catalog;
            $name = sprintf("%s %s [%s]", $sc->code, $sc->name, $c->name);
            $form->getElement('securityControlId')->setValue($scId);
            $form->getElement('securityControlAutocomplete')->setValue($name);
        }
        
        $form->setDefaults($this->getRequest()->getParams());

        // Don't want any form markup (since this is being embedded into an existing form), just the form elements
        $form->setDecorators(
            array(
                'FormElements',
                array('HtmlTag', array('tag' => 'span'))
            )
        );
            
        $form->setElementDecorators(array('RenderSelf', 'Label'), array('securityControlAutocomplete'));

        $this->view->form = $form;
        
        $securityControlSearchButton = new Fisma_Yui_Form_Button(
            'securityControlSearchButton',
            array(
                'label' => 'Edit Security Control Mapping',
                'onClickFunction' => 'Fisma.Finding.showSecurityControlSearch'
            )
        );
        
        if ($this->view->finding->isDeleted()) {
            $securityControlSearchButton->readOnly = true;
        }

        if ($this->view->finding->status != 'NEW' &&  $this->view->finding->status != 'DRAFT') {
            $securityControlSearchButton->readOnly = true;
        }
        
        $this->view->securityControlSearchButton = $securityControlSearchButton;
    }
    
    /** 
     * Renders the form for uploading artifacts.
     * 
     * @return void
     */
    function uploadFormAction() 
    {
        $this->_helper->layout()->disableLayout();
    }

    /**
     * Get the finding and assign it to view
     * 
     * @return void
     */
    private function _viewFinding()
    {
        $id = $this->_request->getParam('id');
        $finding = $this->_getSubject($id);
        $orgNickname = $finding->Organization->nickname;

        // Check that the user is permitted to view this finding
        $this->_acl->requirePrivilegeForObject('read', $finding);

        $this->view->finding = $finding;
    }

    /**
     * Override createAction() to show the warning message on the finding create page if there is no system.
     * 
     * @return void
     */
    public function createAction()
    {
        parent::createAction();

        $systemCount = $this->_me->getOrganizationsByPrivilegeQuery('finding', 'create')->count(); 
        if (0 === $systemCount) {
            $message = "There are no organizations or systems to create findings for. "
                     . "Please create an organization or system first.";
            $this->view->priorityMessenger($message, 'warning');
        }
    }
}
