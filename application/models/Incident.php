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
 * Represents the report of an information security incident
 *
 * @author     Mark E. Haase <mhaase@endeavorsystems.com>
 * @copyright  (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Model
 */
class Incident extends BaseIncident
{
    /**
     * Override constructor to set initial values
     */
    public function construct()
    {
        // Only operate on new objects (i.e. transient), not persistent objects which are being rehydrated
        $state = $this->state();
        if ($state == Doctrine_Record::STATE_TCLEAN || $state == Doctrine_Record::STATE_TDIRTY) {

            // REMOTE_ADDR may not be set (e.g. command line mode)
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $this->reporterIp = $_SERVER['REMOTE_ADDR'];
            }

            $this->status = 'new';

            $this->reportTs = Fisma::now();
            $this->reportTz = Zend_Date::now()->getTimezone();
        }
    }

    /**
     * Set custom mutators
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->hasMutator('categoryId', 'setCategoryId');
        $this->hasMutator('hostIp', 'setHostIp');
        $this->hasMutator('organizationId', 'setOrganizationId');
        $this->hasMutator('pocId', 'setPocId');
        $this->hasMutator('reporterEmail', 'setReporterEmail');
        $this->hasMutator('reportingUserId', 'setReportingUserId');
        $this->hasMutator('sourceIp', 'setSourceIp');
        $this->hasMutator('responseStrategies', 'setResponseStrategies');
        $this->hasMutator('incidentDateTime', 'setIncidentDateTime');
    }

    /**
     * Reject this incident
     *
     * @param string $comment A comment to add with this rejection
     */
    public function reject($comment = null)
    {
        $conn = Doctrine_Manager::connection();
        $conn->beginTransaction();

        if ('new' != $this->status) {
            throw new Fisma_Zend_Exception('Cannot reject an incident unless it is in "new" status');
        }

        // Create a workflow step for rejecting then mark it as closed
        $rejectStep = new IrIncidentWorkflow();

        $rejectStep->Incident = $this;
        $rejectStep->name = 'Reject Incident';
        $rejectStep->cardinality = 1;

        $rejectStep->completeStep($comment);
        $rejectStep->save();

        $this->status = 'closed';
        $this->closedTs = Zend_Date::now()->get(Zend_Date::ISO_8601);
        $this->resolution = 'rejected';
        Notification::notify('INCIDENT_REJECTED', $this, CurrentUser::getInstance());

        // Update incident log
        $this->getAuditLog()->write('Rejected Incident Report');

        $conn->commit();
    }

    /**
     * Sets the category (and corresponding workflow) for this incident.
     *
     * If it doesn't already have a workflow, then the workflow steps are copied from the workflow definition into this
     * incident's workflow.
     *
     * If a workflow *does* exist, then all completed steps are kept as-is, but the remaining
     * steps are removed. Then a new step is inserted showing the change in workflow (and marked as completed by
     * the current user) then the steps for the new workflow are appended to the end of the list.
     *
     * @param int $categoryId An IrSubCategory primary key
     */
    public function setCategoryId($categoryId)
    {
        $oldValue = $this->categoryId;
        if (
            $categoryId === '0'   // This is the "I don't know" category in the report wizard
            || $categoryId === '' // Empty option
        ) {
            $categoryId = null;
        }

        if ($oldValue == $categoryId) {
            return;
        }

        $this->_set('categoryId', $categoryId);

        if ('new' == $this->status) {
            $this->status = 'open';
        }
        $this->currentWorkflowName = null;
        $completedCount = 0;
        foreach ($this->Workflow as $key => $step) {
            if ($step->status === 'completed') {
                $completedCount++;
            } else {
                $this->Workflow->remove($key);
            }
        }
        $category = null;
        if (!empty($categoryId)) {
            $category = Doctrine::getTable('IrSubCategory')->find($categoryId);
        }

        $changedWorkflowName = "Change Workflows";

        if (!empty($category)) {
            $changedWorkflowMessage = "<p>The category has been changed to \"{$category->name}\" and the workflow"
                                    . " has been modified accordingly.</p>";
        } else {
            $changedWorkflowMessage = "<p>The category has been removed and the workflow has been closed.</p>";
        }

        if ($completedCount > 0) {
            $completedCount++;
            $iw = new IrIncidentWorkflow();
            $iw->name = $changedWorkflowName;
            $iw->description = $changedWorkflowMessage;
            $iw->cardinality = $completedCount;
            $iw->completeStep();
            $this->Workflow[] = $iw;
        }

        if (!empty($category)) {
            /*
             * Create a copy of the workflow and assign it to this incident. This is like a SQL
             * 'INSERT INTO <table> SELECT...' statement, except Doctrine doesn't support that kind of query.
             */
            $workflowQuery = Doctrine_Query::create()
                             ->select('s.id, s.roleId, s.cardinality, s.name, s.description')
                             ->from('IrStep s')
                             ->where('s.workflowid = ?', $category->workflowId)
                             ->orderby('s.cardinality');

            $workflowSteps = $workflowQuery->execute();

            $firstLoop = true;

            foreach ($workflowSteps as $step) {
                $iw = new IrIncidentWorkflow();
                $iw->Role = $step->Role;
                $iw->name = $step->name;
                $iw->description = $step->description;
                $iw->cardinality = $step->cardinality + $completedCount;
                $iw->Incident = $this;
                $this->Workflow[] = $iw;

                if ($firstLoop) {
                    $firstLoop = false;
                    $iw->status = 'current';
                    $this->currentWorkflowName = $iw->name;
                }
            }

            $this->getAuditLog()->write('Changed Category: ' .  $category->name);
        } else {
            $this->getAuditLog()->write('Removed Category');
        }
    }

    /**
     * Set the organization ID.
     *
     * @param int $organizationId
     * @param boolean $load
     */
    public function setOrganizationId($organizationId, $load = true)
    {
        if ($organizationId === '0' || empty($organizationId)) {
            $organizationId = null;
        }

        $this->_set('organizationId', $organizationId);

        // now deal with the parent organization
        $parentOrganizationId = null;
        if (!empty($organizationId)) {
            $organization = Doctrine::getTable('Organization')->find($organizationId);
            $parent = $organization->getNode()->getParent();
            while (!empty($parent) && !empty($parent->systemId)) {
                $parent = $parent->getNode()->getParent();
            }
            if (empty($parent)) {
                $parentOrganizationId = null;
            } else {
                $parentOrganizationId = $parent->id;
            }
        }

        $this->_set('denormalizedParentOrganizationId', $parentOrganizationId);
    }

    /**
     * Complete the current workflow step for this incident and advance to the next step
     *
     * @param string $comment The user's comment associated with completing this workflow step
     */
    public function completeStep($comment)
    {
        // Validate that comment is not empty
        if ('' == trim($comment)) {
            throw new Fisma_Zend_Exception_User('You must provide a comment');
        }

        // Update the completed step first
        $completedStep = null;
        foreach ($this->Workflow as $step) {
            if ($step->status == 'current') {
                $completedStep = $step;
                break;
            }
        }
        $completedStep->completeStep($comment);
        $completedStep->save();

        // Log the completed step
        $logMessage = 'Completed workflow step #'
                    . $completedStep->cardinality
                    . ': '
                    . $completedStep->name;
        $this->getAuditLog()->write($logMessage);

        /*
         * The next step can be identified by its cardinality, which is always one more than the cardinality of the
         * current step. If no such step exists, then the current step is the last step.
         */
        $nextStepQuery = Doctrine_Query::create()
                         ->from('IrIncidentWorkflow iw')
                         ->where('iw.incidentId = ?', $this->id)
                         ->andWhere('iw.cardinality = ?', $completedStep->cardinality + 1);

        $nextStepResult = $nextStepQuery->execute();

        if (0 == count($nextStepResult)) {
            // There is no next step, so close this incident
            $this->currentWorkflowName = null;
            $this->status = 'closed';
            $this->closedTs = Zend_Date::now()->get(Zend_Date::ISO_8601);
            $this->resolution = 'resolved';
            $this->save();

            // Log the closure of the incident
            $this->getAuditLog()->write('Incident Resolved and Closed');
        } elseif (1 == count($nextStepResult)) {
            // The next step will change status to 'current'
            $nextStep = $nextStepResult[0];
            $nextStep->status = 'current';
            $nextStep->save();

            // Update this record's workflow pointer
            $this->currentWorkflowName = $nextStep->name;
            $this->save();
        } else {
            $message = "The workflow for this incident ($this->id) appears to be corrupted. There are two steps"
                     . " with the same id.";
            throw new Fisma_Zend_Exception($message);
        }
    }

    /**
     * Mutator for hostIp to convert blank values to null for validation purposes
     *
     * @param string $value
     */
    public function setHostIp($value)
    {
        if (empty($value)) {
            $this->_set('hostIp', null);
        } else {
            $this->_set('hostIp', $value);
        }
    }

    /**
     * Mutator for sourceIp to convert blank values to null for validation purposes
     *
     * @param string $value
     */
    public function setSourceIp($value)
    {
        if (empty($value)) {
            $this->_set('sourceIp', null);
        } else {
            $this->_set('sourceIp', $value);
        }
    }

    /**
     * Mutator for reporterEmail to convert blank values to null for validation purposes
     *
     * @param string $value
     */
    public function setReporterEmail($value='')
    {
        if (empty($value)) {
            $this->_set('reporterEmail', null);
        } else {
            $this->_set('reporterEmail', $value);
        }
    }

    /**
     * When setting a user as the incident reporter, then unset all of the reporter fields
     *
     * @param User $user
     */
    public function setReportingUserId($userId)
    {
        $this->_set('reportingUserId', $userId);

        if (empty($userId)) {
            return;
        }

        // Make sure the POC is an actor or observer
        $found = false;
        foreach ($this->IrIncidentUsers as $iiu) {
            if (((int)$iiu->userId) === ((int)$userId)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $iiu = new IrIncidentUser;
            $iiu->accessType = 'OBSERVER';
            $iiu->userId = $userId;
            $this->IrIncidentUsers[] = $iiu;
        }
    }

    /**
     * Make sure POCs are added as actors on the incident.
     *
     * @param string $value
     */
    public function setPocId($value)
    {
        // Clear out null values
        $pocId = (int)$value;

        if (empty($pocId)) {
            $this->_set('pocId', null);
            return;
        } else {
            $this->_set('pocId', $pocId);

            // Make sure the POC is an actor
            $found = false;
            foreach ($this->IrIncidentUsers as $iiu) {
                if (((int)$iiu->userId) === $pocId) {
                    // if observer, promote to actor
                    $iiu->accessType = 'ACTOR';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $actor = new IrIncidentUser;
                $actor->accessType = 'ACTOR';
                $actor->userId = $pocId;
                $this->IrIncidentUsers[] = $actor;
            }
            Notification::notify(
                'USER_POC',
                $this,
                CurrentUser::getInstance(),
                array('userId' => $pocId, 'url' => '/incident/view/id/')
            );
        }
    }

    /**
     * setResponseStrategies
     *
     * @param array $value
     * @return void
     */
    public function setResponseStrategies($value)
    {
        // allow JSON strings as input
        if (is_string($value)) {
            try {
                $value = Zend_Json::decode($value);
            } catch (Zend_Json_Exception $e) {
                throw new Fisma_Zend_Exception("Invalid value for response strategies.", $e);
            }
        }
        $value = (is_array($value)) ? $value : array();
        $this->denormalizedResponseStrategies = implode('; ', $value);
        $this->_set('responseStrategies', $value);
    }

    /**
     * setIncidentDateTime
     *
     * @param mixed $value
     * @return void
     */
    public function setIncidentDateTime($value)
    {
        $value = str_replace(' at ', ' ', $value);
        $date = new Zend_Date($value);
        $this->incidentDate = $date->toString(Fisma_Date::FORMAT_DATE);
        $this->incidentTime = $date->toString(Fisma_Date::FORMAT_TIME);
    }
}
