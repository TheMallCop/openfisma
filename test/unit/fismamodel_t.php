<?php
/**
 * fismamodel_t.php
 *
 * @package Test_Unit
 * @author     Xhorse   xhorse at users.sourceforge.net
 * @copyright  (c) Endeavor Systems, Inc. 2008 (http://www.endeavorsystems.com)
 * @license    http://www.openfisma.org/mw/index.php?title=License
 * @version $Id$
*/

require_once MODELS . DS . 'system.php';
/**
 * Test function search in poam model
 * @package Test_Unit
 * @author     Xhorse   xhorse at users.sourceforge.net
 * @copyright  (c) Endeavor Systems, Inc. 2008 (http://www.endeavorsystems.com)
 * @license    http://www.openfisma.org/mw/index.php?title=License
*/

class  TestFismaModel extends UnitTestCase{
        function setUp(){
            $db = Zend_Registry::get('db');
            $this->db = $db;
        }
        
       function testSystem1(){            
            $query = $this->db->select()->distinct()->from(array('s'=>'SYSTEMS'),'*');
            $result = $this->db->fetchAll($query);
            foreach($result as $row){
                foreach(array_keys($row) as $v){
                    if($v !='system_id'){
                        $systems[$row['system_id']][$v] = $row[$v];
                    }
                }
            }
            $system = new system();
            $system_list = $system->getList();
            $this->assertTrue($systems === $system_list);
        }

        function testSystem2(){
            $fields = array('system_id','system_nickname');
            $query = $this->db->select()->distinct()->from(array('s'=>'SYSTEMS'),$fields);
            $result = $this->db->fetchAll($query);
            foreach($result as $row){
                foreach(array_keys($row) as $v){
                    if($v !='system_id'){
                        $systems[$row['system_id']][$v] = $row[$v];
                    }
                }
            }
            $system = new system();
            $system_list = $system->getList($fields);
            $this->assertTrue($systems === $system_list);
        }

        function testSystem3(){
            $fields = array('id'=>'system_id','name'=>'system_nickname');
            $query = $this->db->select()->distinct()->from(array('s'=>'SYSTEMS'),$fields);
            $result = $this->db->fetchAll($query);
            foreach($result as $row){
                foreach(array_keys($row) as $v){
                    if($v !='id'){
                        $systems[$row['id']][$v] = $row[$v];
                    }
                }
            }
            $system = new system();
            $system_list = $system->getList($fields);
            $this->assertTrue($systems === $system_list);
        }
        
        function testSystem4(){
            $fields = array('system_id'=>'system_id','system_nickname'=>'system_nickname');
            $query = $this->db->select()->distinct()->from(array('s'=>'SYSTEMS'),$fields);
            $result = $this->db->fetchAll($query);
            foreach($result as $row){
                foreach(array_keys($row) as $v){
                    if($v !='system_id'){
                        $systems[$row['system_id']][$v] = $row[$v];
                    }
                }
            }
            $system = new system();
            $system_list = $system->getList($fields);
            $this->assertTrue($systems === $system_list);
        }

        function testSystem5(){
            $query = $this->db->select()->distinct()->from(array('s'=>'SYSTEMS'),'*');
            $result = $this->db->fetchAll($query);
            foreach($result as $row){
                foreach(array_keys($row) as $v){
                    if($v !='system_id'){
                        $systems[$row['system_id']][$v] = $row[$v];
                    }
                }
            }
            $ids = array_keys($systems);
            $system = new system();
            $system_list = $system->getList('*',$ids);
            $this->assertTrue($systems === $system_list);
        }
}




