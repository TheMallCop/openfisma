<?php
/**
 * Copyright (c) 2013 Endeavor Systems, Inc.
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
 * @author     Andrew Reeves <andrew.reeves@endeavorsystems.com>
 * @copyright  (c) Endeavor Systems, Inc. 2013 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Migration
 */
class Application_Migration_030200_Incident extends Fisma_Migration_Abstract
{
    /**
     * Migrate.
     */
    public function migrate()
    {
        $this->getHelper()->addColumn('incident', 'responsestrategies', 'text NULL', 'impact');
        $this->getHelper()->addColumn('incident', 'denormalizedresponsestrategies', 'text NULL', 'responsestrategies');

        $this->getHelper()->insert('privilege', array('resource' => 'incident', 'action' => 'delete'));
        $this->getHelper()->insert(
            'privilege',
            array('resource' => 'incident', 'action' => 'manage_response_strategies')
        );
        $this->getHelper()->exec(
            'INSERT INTO role_privilege '
            . 'SELECT r.id, p.id '
            . 'FROM role r, privilege p '
            . 'WHERE r.nickname = ? AND p.resource = ? AND (p.action = ? OR p.action = ?)',
            array('ADMIN', 'incident', 'manage_response_strategies', 'delete')
        );
    }
}
