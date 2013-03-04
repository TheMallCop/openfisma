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
 * Application_Migration_030104_Commentable
 *
 * @uses Fisma
 * @uses _Migration_Abstract
 * @package
 * @copyright (c) Endeavor Systems, Inc. 2011 {@link http://www.endeavorsystems.com}
 * @author Andrew Reeves <andrew.reeves@endeavorsystems.com>
 * @license http://www.openfisma.org/content/license GPLv3
 */
class Application_Migration_030104_Commentable extends Fisma_Migration_Abstract
{
    /**
     * Migrate.
     */
    public function migrate()
    {
        $this->getHelper()->modifyColumn('finding', 'jsoncomments', 'longtext', 'modifiedts');
        $this->getHelper()->exec("UPDATE finding SET jsoncomments = NULL WHERE LENGTH(jsoncomments) = 65535");
        $this->getHelper()->modifyColumn('vulnerability', 'jsoncomments', 'longtext', 'modifiedts');
        $this->getHelper()->exec("UPDATE vulnerability SET jsoncomments = NULL WHERE LENGTH(jsoncomments) = 65535");
        $this->getHelper()->modifyColumn('incident', 'jsoncomments', 'longtext', 'deleted_at');
        $this->getHelper()->exec("UPDATE incident SET jsoncomments = NULL WHERE LENGTH(jsoncomments) = 65535");
        $this->getHelper()->modifyColumn('user', 'jsoncomments', 'longtext', 'timezoneauto');
        $this->getHelper()->exec("UPDATE user SET jsoncomments = NULL WHERE LENGTH(jsoncomments) = 65535");
    }
}
