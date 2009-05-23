#!/usr/bin/env php
<?php
/**
 * Copyright (c) 2008 Endeavor Systems, Inc.
 *
 * This file is part of OpenFISMA.
 * This file was adopted from http://ruben.savanne.be/articles/integrating-zend-framework-and-doctrine
 *
 * OpenFISMA is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OpenFISMA is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OpenFISMA.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Mark E. Haase <mhaase@endeavorsystems.com>
 * @copyright (c) Endeavor Systems, Inc. 2008 (http://www.endeavorsystems.com)
 * @license   http://www.openfisma.org/mw/index.php?title=License
 * @version   $Id$
 * @package   Doctrine
 */
 
require_once('../../application/init.php');
$plSetting = new Fisma_Controller_Plugin_Setting();

if ($plSetting->installed()) {
    $cli = new Doctrine_Cli(Zend_Registry::get('doctrine_config'));
    $cli->run($_SERVER['argv']);
} else {
    die('This script cannot run because OpenFISMA has not been configured yet. Run the installer and try again.');
}
