<?php

/**
 * BaseLdapConfig
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
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
        $this->hasColumn('host', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'plaintext')));
        $this->hasColumn('port', 'integer', 2, array('type' => 'integer', 'length' => '2'));
        $this->hasColumn('domainName', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'plaintext')));
        $this->hasColumn('domainShort', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'plaintext')));
        $this->hasColumn('username', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'plaintext')));
        $this->hasColumn('password', 'string', null, array('type' => 'string'));
        $this->hasColumn('basedn', 'string', null, array('type' => 'string', 'extra' => array('purify' => 'plaintext')));
        $this->hasColumn('accountFilter', 'string', null, array('type' => 'string'));
        $this->hasColumn('accountCanonical', 'string', null, array('type' => 'string'));
        $this->hasColumn('bindRequiresDn', 'boolean', null, array('type' => 'boolean'));
        $this->hasColumn('useSsl', 'boolean', null, array('type' => 'boolean'));
    }

    public function setUp()
    {
        $this->addListener(new XssListener(), 'XssListener');
    }
}