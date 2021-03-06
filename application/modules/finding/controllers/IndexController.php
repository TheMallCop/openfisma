<?php
/**
 * Copyright (c) 2009 Endeavor Systems, Inc.
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
 * The finding controller is used for searching, displaying, and updating
 * findings.
 *
 * @author     Ryan Yang <ryan@users.sourceforge.net>
 * @copyright  (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Controller
 */
class Finding_IndexController extends Fisma_Zend_Controller_Action_Security
{
    /**
     * Invokes a contract with Fisma_Zend_Controller_Action_Object regarding privileges
     *
     * @var string
     * @link http://jira.openfisma.org/browse/OFJ-24
     */
    protected $_organizations = '*';

    public function init()
    {
        parent::init();

        $this->_helper->fismaContextSwitch
             ->addActionContext('template', 'xls')
             ->initContext();
    }

    /**
     * Allow the user to upload an XML Excel spreadsheet file containing finding data for multiple findings
     *
     * @GETAllowed
     * @return void
     */
    public function injectionAction()
    {
        $this->_acl->requirePrivilegeForClass('inject', 'Finding');

        // Set up the form for downloading template files
        $downloadForm = Fisma_Zend_Form_Manager::loadForm('finding_spreadsheet_download');

        Fisma_Zend_Form_Manager::prepareForm($downloadForm);

        $this->view->downloadForm = $downloadForm;

        // Set up the form for uploading files
        $uploadForm = Fisma_Zend_Form_Manager::loadForm('finding_spreadsheet_upload');

        Fisma_Zend_Form_Manager::prepareForm($uploadForm);

        $this->view->uploadForm = $uploadForm;
    }

    /**
     * Handle upload of a spreadsheet template file
     */
    public function uploadSpreadsheetAction()
    {
        $file = $_FILES['excelFile'];

        if (!is_array($file)) {
            $this->view->priorityMessenger("The file upload failed.", 'error');
            return;
        } elseif (Fisma_FileManager::getUploadFileError($file)) {
            $error = Fisma_FileManager::getUploadFileError($file);
            $this->view->priorityMessenger($error, 'error');
        } else {
            // Load the findings from the spreadsheet upload. Return a user error if the parser fails.
            try {
                Doctrine_Manager::connection()->beginTransaction();

                // organize upload data
                $upload = new Upload();
                $upload->save();

                $injectExcel = new Fisma_Inject_Excel();

                $rowsProcessed = $injectExcel->inject($file['tmp_name'], $upload->id);
                // upload file after the file parsed
                $upload->instantiate($file);
                $upload->save();

                Doctrine_Manager::connection()->commit();
                $error = "$rowsProcessed findings were created.";
                $type  = 'success';

                // Create finding injection notification
                $url = "?q=/uploadid/integerEquals/" . $upload->id;
                Notification::notify("FINDING_IMPORTED",
                                     null,
                                     CurrentUser::getInstance(),
                                     array('appUrl' => $url,
                                           'rowsProcessed' => $rowsProcessed)
                                    );

            } catch (Fisma_Zend_Exception_InvalidFileFormat $e) {
                Doctrine_Manager::connection()->rollback();
                $error = "The file cannot be processed due to an error: {$e->getMessage()}";
                $type  = 'error';
            }
            $this->view->priorityMessenger($error, $type);
        }

        $this->_redirect('/finding/index/injection');
    }

    /**
     * Downloading a excel file which is used as a template for uploading findings.
     *
     * Systems, networks and sources are extracted from the database dynamically.
     *
     * @GETAllowed
     * @return void
     */
    public function templateAction()
    {
        $this->_acl->requirePrivilegeForClass('inject', 'Finding');

        // set the filename for the browser to save the file as
        $this->_helper->fismaContextSwitch->setFilename(Fisma_Inject_Excel::TEMPLATE_NAME);

        /* The spreadsheet won't open in Excel if any of these tables are
         * empty. So we explicitly check for that condition, and if it
         * exists then we show the user an error message explaining why
         * the spreadsheet isn't available.
         */
        try {
            $systems = $this->_me
                ->getOrganizationsByPrivilegeQuery('finding', 'inject')
                ->leftJoin('o.System s')
                ->leftJoin('o.OrganizationType orgType')
                ->select('o.id, o.nickname')
                ->andWhere('orgType.nickname <> ? OR s.sdlcPhase <> ?', array('system', 'disposal'))
                ->execute()
                ->toKeyValueArray('id', 'nickname');
            if (count($systems) == 0) {
                throw new Fisma_Zend_Exception("The spreadsheet template can not be
                    prepared because there are no systems defined.");
            } else {
                /**
                 * @todo This really needs to be reconstructed. We shouldn't sort in PHP when the DBMS
                 * already has this field (nickname) indexed for us. Ideally, the user object would be
                 * able to return a query object that we could then modify.
                 */
                sort($systems);
                $this->view->systems = $systems;
            }

            $sources = Doctrine::getTable('Source')->findAll()->toArray();
            $this->view->sources = array();
            foreach ($sources as $source) {
                $this->view->sources[$source['id']] = $source['nickname'];
            }
            if (count($this->view->sources) == 0) {
                throw new Fisma_Zend_Exception("The spreadsheet template can
                    not be prepared because there are no finding sources
                    defined.");
            }

            $securityControls = Doctrine::getTable('SecurityControl')->getPublishedQuery()->execute();

            $this->view->securityControls = array();
            foreach ($securityControls as $sc) {
                $this->view->securityControls[$sc->id] = $sc->code . ' ' . $sc->name . ' (' . $sc->Catalog->name . ')';
            }
            if (count($this->view->securityControls) == 0) {
                 throw new Fisma_Zend_Exception('The spreadsheet template can not be ' .
                                                   'prepared because there are no security controls defined.');
            }
            $this->view->risk = array('HIGH', 'MODERATE', 'LOW');
            $this->view->templateVersion = Fisma_Inject_Excel::TEMPLATE_VERSION;
        } catch(Fisma_Zend_Exception $fe) {
            // error condition, remove all stuff from the context switch
            $this->getResponse()->clearAllHeaders();
            $this->_helper->viewRenderer->setViewSuffix('phtml');
            Zend_Layout::getMvcInstance()->enableLayout();
            $this->view->priorityMessenger($fe->getMessage(), 'error');
            $this->_forward('injection', 'index', 'finding');
        }
    }

    /**
     * Forward to the remediation view action, since view isn't actually implemented in finding (wtf?).
     *
     * @GETAllowed
     * @access public
     * @return void
     */
    public function viewAction()
    {
        $this->_forward('view', 'remediation', 'finding');
    }

    /**
     * Forward to the remediation list action.
     *
     * @GETAllowed
     * @access public
     * @return void
     */
    public function listAction()
    {
        $this->_forward('list', 'remediation');
    }
}
