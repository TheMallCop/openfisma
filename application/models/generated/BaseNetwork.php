<?php

/**
 * BaseNetwork
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property string $name
 * @property string $nickname
 * @property string $description
 * @property Doctrine_Collection $Asset
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 5441 2009-01-30 22:58:43Z jwage $
 */
abstract class BaseNetwork extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('network');
        $this->hasColumn('name', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('nickname', 'string', 255, array('type' => 'string', 'length' => '255'));
        $this->hasColumn('description', 'string', null, array('type' => 'string'));
    }

    public function setUp()
    {
        $this->hasMany('Asset', array('local' => 'id',
                                      'foreign' => 'networkId'));
    }
}