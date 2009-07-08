<?php

/**
 * BaseFinding
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property timestamp $createdTs
 * @property timestamp $modifiedTs
 * @property date $discoveredDate
 * @property timestamp $closedTs
 * @property date $nextDueDate
 * @property string $legacyFindingKey
 * @property enum $type
 * @property enum $status
 * @property integer $currentEvaluationId
 * @property string $description
 * @property string $recommendation
 * @property string $mitigationStrategy
 * @property string $resourcesRequired
 * @property date $expectedCompletionDate
 * @property boolean $ecdLocked
 * @property string $threat
 * @property enum $threatLevel
 * @property string $countermeasures
 * @property enum $countermeasuresEffectiveness
 * @property integer $duplicateFindingId
 * @property integer $responsibleOrganizationId
 * @property integer $assetId
 * @property integer $sourceId
 * @property integer $securityControlId
 * @property integer $createdByUserId
 * @property integer $assignedToUserId
 * @property integer $uploadId
 * @property Finding $DuplicateFinding
 * @property Asset $Asset
 * @property Organization $ResponsibleOrganization
 * @property Source $Source
 * @property SecurityControl $SecurityControl
 * @property User $CreatedBy
 * @property User $AssignedTo
 * @property Evaluation $CurrentEvaluation
 * @property Upload $Upload
 * @property Doctrine_Collection $Evidence
 * @property Doctrine_Collection $Finding
 * @property Doctrine_Collection $AuditLogs
 * @property Doctrine_Collection $FindingEvaluations
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BaseFinding extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('finding');
        $this->hasColumn('createdTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('modifiedTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('discoveredDate', 'date', null, array('type' => 'date', 'comment' => 'The when the finding was discovered. This is self-reported by users'));
        $this->hasColumn('closedTs', 'timestamp', null, array('type' => 'timestamp', 'comment' => 'The timestamp when this finding was closed'));
        $this->hasColumn('nextDueDate', 'date', null, array('type' => 'date', 'comment' => 'The deadline date for the next action that needs to be taken on this finding. After this date, the finding is considered to be overdue.'));
        $this->hasColumn('legacyFindingKey', 'string', 255, array('type' => 'string', 'unique' => true, 'extra' => array('purify' => 'plaintext'), 'comment' => 'This field can be used by end clients to track findings under a legacy tracking system', 'length' => '255'));
        $this->hasColumn('type', 'enum', null, array('type' => 'enum', 'values' => array(0 => 'NONE', 1 => 'CAP', 2 => 'AR', 3 => 'FP'), 'default' => 'NONE', 'notnull' => true, 'comment' => 'The mitigation type (Corrective Action Plan, Accepted Risk, or False Positive)'));
        $this->hasColumn('status', 'enum', null, array('type' => 'enum', 'values' => array(0 => 'PEND', 1 => 'NEW', 2 => 'DRAFT', 3 => 'MSA', 4 => 'EN', 5 => 'EA', 6 => 'CLOSED'), 'comment' => 'The current status. MSA and EA are physical status codes that need to be translated into logical status codes before displaying to the user'));
        $this->hasColumn('currentEvaluationId', 'integer', null, array('type' => 'integer', 'comment' => 'Points to the current evaluation level when the status is MSA or EA. Null otherwise.'));
        $this->hasColumn('description', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'html'), 'comment' => 'Description of the finding'));
        $this->hasColumn('recommendation', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'html'), 'comment' => 'The auditors recommendation to remediate this finding'));
        $this->hasColumn('mitigationStrategy', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'html'), 'comment' => 'The ISSOs plan to handle this finding. This can be a course of action (for CAPs or FPs) or a business case (for ARs)'));
        $this->hasColumn('resourcesRequired', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'html'), 'comment' => 'Any additional resources (financial) required to complete this course of action'));
        $this->hasColumn('expectedCompletionDate', 'date', null, array('type' => 'date', 'comment' => 'The date when the course of action or business case is planned to be completed'));
        $this->hasColumn('currentEcd', 'date', null, array('type' => 'date', 'comment' => ''));
        $this->hasColumn('actualCompletionDate', 'date', null, array('type' => 'date', 'comment' => ''));
        $this->hasColumn('ecdLocked', 'boolean', null, array('type' => 'boolean', 'comment' => 'If false, then no user is allowed to modify the ECD.'));
        $this->hasColumn('threat', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'html'), 'comment' => 'Description of the threat source which affects this finding'));
        $this->hasColumn('threatLevel', 'enum', null, array('type' => 'enum', 'values' => array(0 => 'LOW', 1 => 'MODERATE', 2 => 'HIGH'), 'comment' => 'A subjective assessment of the probability and impact of exploiting this finding'));
        $this->hasColumn('countermeasures', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'html'), 'comment' => 'The countermeasures in place against the threat source'));
        $this->hasColumn('countermeasuresEffectiveness', 'enum', null, array('type' => 'enum', 'values' => array(0 => 'LOW', 1 => 'MODERATE', 2 => 'HIGH'), 'comment' => 'A subjective assessment of the effectivness of the in-place countermeasures against the described threat'));
        $this->hasColumn('duplicateFindingId', 'integer', null, array('type' => 'integer', 'comment' => 'If this finding is a duplicate of an existing finding, then this is a foreign key to that finding; otherwise its null'));
        $this->hasColumn('responsibleOrganizationId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the organization which is responsible for addressing this finding'));
        $this->hasColumn('assetId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the asset which this finding is against'));
        $this->hasColumn('sourceId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the source of this finding. For example, was it certification and accreditation? Continous monitoring?'));
        $this->hasColumn('securityControlId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the security control associated with this finding'));
        $this->hasColumn('createdByUserId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the user who created this finding'));
        $this->hasColumn('assignedToUserId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the user who is assigned to this finding'));
        $this->hasColumn('uploadId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the upload log'));
    }

    public function setUp()
    {
        $this->hasOne('Finding as DuplicateFinding', array('local' => 'duplicateFindingId',
                                                           'foreign' => 'id'));

        $this->hasOne('Asset', array('local' => 'assetId',
                                     'foreign' => 'id'));

        $this->hasOne('Organization as ResponsibleOrganization', array('local' => 'responsibleOrganizationId',
                                                                       'foreign' => 'id'));

        $this->hasOne('Source', array('local' => 'sourceId',
                                      'foreign' => 'id'));

        $this->hasOne('SecurityControl', array('local' => 'securityControlId',
                                               'foreign' => 'id'));

        $this->hasOne('User as CreatedBy', array('local' => 'createdByUserId',
                                                 'foreign' => 'id'));

        $this->hasOne('User as AssignedTo', array('local' => 'assignedToUserId',
                                                  'foreign' => 'id'));

        $this->hasOne('Evaluation as CurrentEvaluation', array('local' => 'currentEvaluationId',
                                                               'foreign' => 'id'));

        $this->hasOne('Upload', array('local' => 'uploadId',
                                      'foreign' => 'id'));

        $this->hasMany('Evidence', array('local' => 'id',
                                         'foreign' => 'findingId'));

        $this->hasMany('Finding', array('local' => 'id',
                                        'foreign' => 'duplicateFindingId'));

        $this->hasMany('AuditLog as AuditLogs', array('local' => 'id',
                                                      'foreign' => 'findingId'));

        $this->hasMany('FindingEvaluation as FindingEvaluations', array('local' => 'id',
                                                                        'foreign' => 'findingId'));

        $timestampable0 = new Doctrine_Template_Timestampable(array('created' => array('name' => 'createdTs', 'type' => 'timestamp'), 'updated' => array('name' => 'modifiedTs', 'type' => 'timestamp')));
        $this->actAs($timestampable0);

    $this->addListener(new XssListener(), 'XssListener');
    $this->addListener(new FindingListener(), 'FindingListener');
    }
}