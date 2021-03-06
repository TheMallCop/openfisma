<?php
/**
 * Copyright (c) 2012 Endeavor Systems, Inc.
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
 * Add FINDING_IMPORT event to the event table.
 *
 * @author     Xue-Wei Tang <xue-wei.tang@endeavorsystems.com>
 * @copyright  (c) Endeavor Systems, Inc. 2012 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Migration
 */
class Application_Migration_021801_AddEvent extends Fisma_Migration_Abstract
{
    /**
     * Migrate.
     */
    public function migrate()
    {
        $this->message("Adding FINDING_IMPORT event...");
        $this->getHelper()->exec(
            "INSERT INTO event (
                 name
                ,description
                ,privilegeid
                ,urlpath
                ,category
                ,defaultactive
                ,deleted_at
             )
             SELECT
                'FINDING_IMPORTED'
                ,'findings are imported'
                ,id
                ,'/finding/remediation/list'
                ,'finding'
                ,1
                ,null
            FROM privilege
           WHERE resource = 'notification'
             AND action   = 'finding'"
        );
    }
}

