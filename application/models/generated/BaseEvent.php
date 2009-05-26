<?php

/**
 * BaseEvent
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property string $name
 * @property integer $privilegeId
 * @property Privilege $Privilege
 * @property Doctrine_Collection $Users
 * @property Doctrine_Collection $Evaluation
 * @property Doctrine_Collection $Notification
 * @property Doctrine_Collection $User
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BaseEvent extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('event');
        $this->hasColumn('name', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('privilegeId', 'integer', null, array('type' => 'integer', 'comment' => 'The privilege which is required in order to receive this event notification'));
    }

    public function setUp()
    {
        $this->hasOne('Privilege', array('local' => 'privilegeId',
                                         'foreign' => 'id'));

        $this->hasMany('User as Users', array('refClass' => 'UserEvent',
                                              'local' => 'id',
                                              'foreign' => 'userId'));

        $this->hasMany('Evaluation', array('local' => 'id',
                                           'foreign' => 'eventId'));

        $this->hasMany('Notification', array('local' => 'id',
                                             'foreign' => 'eventId'));

        $this->hasMany('User', array('refClass' => 'UserEvent',
                                     'local' => 'eventId',
                                     'foreign' => 'id'));
    }
}