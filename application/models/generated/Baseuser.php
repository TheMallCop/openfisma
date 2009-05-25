<?php

/**
 * BaseUser
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $id
 * @property timestamp $createdTs
 * @property timestamp $modifiedTs
 * @property string $username
 * @property string $password
 * @property timestamp $passwordTs
 * @property string $passwordHistory
 * @property enum $hashType
 * @property timestamp $lastRob
 * @property boolean $locked
 * @property timestamp $lockTs
 * @property enum $lockType
 * @property integer $failureCount
 * @property string $lastLoginIp
 * @property timestamp $lastLoginTs
 * @property string $title
 * @property string $nameFirst
 * @property string $nameLast
 * @property string $email
 * @property boolean $emailValidate
 * @property string $phoneOffice
 * @property string $phoneMobile
 * @property integer $searchColumnsPref
 * @property integer $notifyFrequency
 * @property timestamp $mostRecentNotifyTs
 * @property string $notifyEmail
 * @property Doctrine_Collection $Roles
 * @property Doctrine_Collection $Organizations
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BaseUser extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('user');
        $this->hasColumn('id', 'integer', null, array('primary' => true, 'autoincrement' => true, 'type' => 'integer'));
        $this->hasColumn('createdTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('modifiedTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('username', 'string', 255, array('type' => 'string', 'unique' => true, 'comment' => 'TESTING USERNAME COMMENTS', 'length' => '255'));
        $this->hasColumn('password', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('passwordTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('passwordHistory', 'string', null, array('type' => 'string'));
        $this->hasColumn('hashType', 'enum', null, array('type' => 'enum', 'values' => array(0 => 'md5', 1 => 'sha1', 2 => 'sha224', 3 => 'sha256', 4 => 'sha384', 5 => 'sha512')));
        $this->hasColumn('lastRob', 'timestamp', null, array('type' => 'timestamp', 'comment' => 'The last time this user digitally accepted the Rules of Behavior'));
        $this->hasColumn('locked', 'boolean', null, array('type' => 'boolean', 'default' => false));
        $this->hasColumn('lockTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('lockType', 'enum', null, array('type' => 'enum', 'values' => array(0 => 'manual', 1 => 'password', 2 => 'inactive', 3 => 'expired'), 'comment' => 'Manual lock means the admin locked the account. Password lock means several consecutive password failures. Inactive lock means the user has not logged in recently enough. Expired locked means the password has expired.'));
        $this->hasColumn('failureCount', 'integer', null, array('type' => 'integer', 'default' => 0));
        $this->hasColumn('lastLoginIp', 'string', 15, array('type' => 'string', 'ip' => true, 'length' => '15'));
        $this->hasColumn('lastLoginTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('title', 'string', 255, array('type' => 'string', 'comment' => 'The users position or title within the agency', 'length' => '255'));
        $this->hasColumn('nameFirst', 'string', 255, array('type' => 'string', 'comment' => 'The users first name', 'length' => '255'));
        $this->hasColumn('nameLast', 'string', 255, array('type' => 'string', 'comment' => 'The users last name', 'length' => '255'));
        $this->hasColumn('email', 'string', 255, array('type' => 'string', 'email' => true, 'comment' => 'The users primary e-mail address', 'length' => '255'));
        $this->hasColumn('emailValidate', 'boolean', null, array('type' => 'boolean', 'default' => false, 'comment' => 'Whether the user has validated their e-mail address'));
        $this->hasColumn('phoneOffice', 'string', 10, array('type' => 'string', 'fixed' => 1, 'comment' => 'U.S. 10 digit phone number; stored without punctuation', 'length' => '10'));
        $this->hasColumn('phoneMobile', 'string', 10, array('type' => 'string', 'fixed' => 1, 'comment' => 'U.S. 10 digit phone number; stored without punctuation', 'length' => '10'));
        $this->hasColumn('searchColumnsPref', 'integer', null, array('type' => 'integer', 'comment' => 'A bitmask corresponding to visible columns on the search page'));
        $this->hasColumn('notifyFrequency', 'integer', null, array('type' => 'integer'));
        $this->hasColumn('mostRecentNotifyTs', 'timestamp', null, array('type' => 'timestamp'));
        $this->hasColumn('notifyEmail', 'string', 255, array('type' => 'string', 'email' => true, 'length' => '255'));
    }

    public function setUp()
    {
        $this->hasMany('Role as Roles', array('refClass' => 'UserRole',
                                              'local' => 'userId',
                                              'foreign' => 'roleId'));

        $this->hasMany('Organization as Organizations', array('refClass' => 'UserOrganization',
                                                              'local' => 'userId',
                                                              'foreign' => 'organizationId'));
    }
}