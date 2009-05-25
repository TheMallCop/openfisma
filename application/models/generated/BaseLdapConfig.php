<?php

/**
 * BaseLdapConfig
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $id
 * @property string $host
 * @property integer $port
 * @property string $domainName
 * @property string $domainShort
 * @property string $username
 * @property string $password
 * @property string $basedn
 * @property string $accountFilter
 * @property string $accountCanonical
 * @property boolean $bindRequiresDn
 * @property boolean $useSsl
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BaseLdapConfig extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('ldap_config');
        $this->hasColumn('id', 'integer', null, array('primary' => true, 'autoincrement' => true, 'type' => 'integer'));
        $this->hasColumn('host', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('port', 'integer', 2, array('type' => 'integer', 'length' => '2'));
        $this->hasColumn('domainName', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('domainShort', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('username', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('password', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('basedn', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('accountFilter', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('accountCanonical', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('bindRequiresDn', 'boolean', null, array('type' => 'boolean'));
        $this->hasColumn('useSsl', 'boolean', null, array('type' => 'boolean'));
    }

}