<?php

/**
 * BasePrivilege
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property string $resource
 * @property string $action
 * @property string $description
 * @property boolean $orgSpecific
 * @property Doctrine_Collection $Evaluations
 * @property Doctrine_Collection $Events
 * @property Doctrine_Collection $Role
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BasePrivilege extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('privilege');
        $this->hasColumn('resource', 'string', 255, array('type' => 'string', 'comment' => 'The resource to which this privilege pertains', 'length' => '255'));
        $this->hasColumn('action', 'string', 255, array('type' => 'string', 'comment' => 'The logical name of the action that this privilege provides, such as create, read, update, or delete, etc.', 'length' => '255'));
        $this->hasColumn('description', 'string', 255, array('type' => 'string', 'comment' => 'A human-readable description of what this privilege means.', 'length' => '255'));
        $this->hasColumn('orgSpecific', 'boolean', null, array('type' => 'boolean', 'notnull' => true, 'comment' => 'Set to true if this privilege applies to individual organizations; false means this privilege applies to an object which is not organization-specific, such as a finding source or network object'));
    }

    public function setUp()
    {
        $this->hasMany('Evaluation as Evaluations', array('local' => 'id',
                                                          'foreign' => 'privilegeId'));

        $this->hasMany('Event as Events', array('local' => 'id',
                                                'foreign' => 'privilegeId'));

        $this->hasMany('Role', array('refClass' => 'RolePrivilege',
                                     'local' => 'privilegeId',
                                     'foreign' => 'roleId'));
    }
}