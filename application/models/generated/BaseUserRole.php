<?php

/**
 * BaseUserRole
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $userId
 * @property integer $roleId
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BaseUserRole extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('user_role');
        $this->hasColumn('userId', 'integer', null, array('type' => 'integer', 'primary' => true));
        $this->hasColumn('roleId', 'integer', null, array('type' => 'integer', 'primary' => true));
    }

}