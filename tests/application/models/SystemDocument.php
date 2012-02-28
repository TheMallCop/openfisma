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

require_once(realpath(dirname(__FILE__) . '/../../Case/Unit.php'));

/**
 * Test_Application_Models_SystemDocument
 * 
 * @uses Test_Case_Unit
 * @package Test 
 * @copyright (c) Endeavor Systems, Inc. 2011 {@link http://www.endeavorsystems.com}
 * @author Josh Boyd <joshua.boyd@endeavorsystems.com> 
 * @license http://www.openfisma.org/content/license GPLv3
 */
class Test_Application_Models_SystemDocument extends Test_Case_Unit
{
    /**
     * For files
     * 
     * @access public
     * @return void
     */
    public function testGetSizeKb()
    {
        $doc = new SystemDocument();

        @$upload = $this->getMock('Upload', array('getFileSize')); // Bypassing the error "Mock Table does not exist"
        $upload->expects($this->once())->method('getFileSize')->will($this->returnValue(10485760));
        $doc->Upload = $upload;

        $this->assertEquals('10240 KB', $doc->getSizeKb());
    }

    /**
     * testGetIconUrlForUnknownFile 
     * 
     * @access public
     * @return void
     */
    public function testGetIconUrlForUnknownFile()
    {
        $doc = new SystemDocument();

        $upload = new Upload();
        $upload->fileName = 'lolwat';
        $doc->Upload = $upload;

        $this->assertEquals('/images/mimetypes/unknown.png', $doc->getIconUrl());
    }

    /**
     * testGetIconUrlForKnownFile 
     * 
     * @access public
     * @return void
     */
    public function testGetIconUrlForKnownFile()
    {
        $doc = new SystemDocument();

        $upload = new Upload();
        $upload->fileName = 'lolwat.doc';
        $doc->Upload = $upload;

        $this->assertEquals('/images/mimetypes/doc.png', $doc->getIconUrl());
    }
}
