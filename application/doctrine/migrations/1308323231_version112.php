<?php
/**
 * Copyright (c) 2011 Endeavor Systems, Inc.
 *
 * This file is part of OpenFISMA.
 *
 * OpenFISMA is free software: you can redistribute it and/or modify it under the terms of the GNU General Public 
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * OpenFISMA is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more 
 * details.
 *
 * You should have received a copy of the GNU General Public License along with OpenFISMA.  If not, see 
 * {@link http://www.gnu.org/licenses/}.
 */

/**
 * Remove configuration fields for Search -- this is basically the reverse of migration 81
 *
 * @codingStandardsIgnoreFile
 *
 * @package Migration
 * @copyright (c) Endeavor Systems, Inc. 2011 {@link http://www.endeavorsystems.com}
 * @author Dale Frey <dale.frey@endeavorsystems.com>
 * @license http://www.openfisma.org/content/license GPLv3
 */
class Version112 extends Doctrine_Migration_Base
{
    public function up()
    {
        $conn = Doctrine_Manager::connection();
        $updateSql = "
                    UPDATE evaluation
                    SET daysuntildue = 7
                    WHERE daysuntildue IS NULL";
        $conn->exec($updateSql);
        
        $this->changeColumn(
            'evaluation',
            'daysuntildue',
            null,
            'int',
            array(
                'default' => 7,
                'notnull' => true
            )
        );
    }
    
    public function down()
    {
        $this->changeColumn('evaluation', 'daysuntildue', null, 'int', array('notnull' => false));
    }
}
