<?php

/**
 * BaseEvidence
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property timestamp $createdTs
 * @property string $filename
 * @property integer $findingId
 * @property integer $userId
 * @property Finding $Finding
 * @property User $User
 * @property Doctrine_Collection $FindingEvaluations
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BaseEvidence extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('evidence');
        $this->hasColumn('createdTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('filename', 'string', null, array('type' => 'string'));
        $this->hasColumn('findingId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the finding which this evidence is attached to'));
        $this->hasColumn('userId', 'integer', null, array('type' => 'integer', 'comment' => 'Foreign key to the use who uploaded this evidence'));
    }

    public function setUp()
    {
        $this->hasOne('Finding', array('local' => 'findingId',
                                       'foreign' => 'id'));

        $this->hasOne('User', array('local' => 'userId',
                                    'foreign' => 'id'));

        $this->hasMany('FindingEvaluation as FindingEvaluations', array('local' => 'id',
                                                                        'foreign' => 'evidenceId'));

        $timestampable0 = new Doctrine_Template_Timestampable(array('created' => array('name' => 'createdTs', 'type' => 'timestamp'), 'updated' => array('disabled' => true)));
        $this->actAs($timestampable0);
    }
}