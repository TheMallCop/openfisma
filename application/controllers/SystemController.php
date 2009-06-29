<?php
/**
 * Copyright (c) 2008 Endeavor Systems, Inc.
 *
 * This file is part of OpenFISMA.
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
 * @author    Ryan Yang <ryan@users.sourceforge.net>
 * @copyright (c) Endeavor Systems, Inc. 2008 (http://www.endeavorsystems.com)
 * @license   http://www.openfisma.org/mw/index.php?title=License
 * @version   $Id$
 * @package   Controller
 */

/**
 * Handles CRUD for "system" objects.
 *
 * @package   Controller
 * @copyright (c) Endeavor Systems, Inc. 2008 (http://www.endeavorsystems.com)
 * @license   http://www.openfisma.org/mw/index.php?title=License
 */
class SystemController extends BaseController
{
    protected $_modelName = 'System';

    /**
     * list the systems from the search, 
     * if search none, it list all systems
     *
     */
    public function searchAction()
    {
        Fisma_Acl::requirePrivilege('Organization', 'read');
        
        $value = trim($this->_request->getParam('keywords'));
        
        $sortBy = $this->_request->getParam('sortby', 'name');
        // Replace the HYDRATE_SCALAR alias syntax with the regular Doctrine alias syntax
        $sortBy = str_replace('_', '.', $sortBy);
        $order = $this->_request->getParam('order', 'ASC');
        
        if (!in_array(strtolower($order), array('asc', 'desc'))) {
            /** 
             * @todo english 
             */
            throw new Fisma_Exception('invalid page');
        }
        
        $q = Doctrine_Query::create()
             ->select('o.id, o.name, o.nickname, s.type, s.confidentiality, s.integrity, s.availability, s.fipsCategory')
             ->from('Organization o')
             ->leftJoin('o.System s')
             ->where('o.orgType = ?', 'system')
             ->orderBy("$sortBy $order")
             ->limit($this->_paging['count'])
             ->offset($this->_paging['startIndex'])
             ->setHydrationMode(Doctrine::HYDRATE_SCALAR);

        if (!empty($value)) {
            $this->_helper->searchQuery($value, 'system');
            $cache = $this->getHelper('SearchQuery')->getCacheInstance();
            // get search results in ids
            $systemIds = $cache->load($this->_me->id . '_system');
            if (empty($systemIds)) {
                // set ids as a not exist value in database if search results is none.
                $systemIds = array(-1);
            }
            $q->whereIn('u.id', $systemIds);
        }

        $totalRecords = $q->count();
        $organizations = $q->execute();

        $tableData = array('table' => array(
            'recordsReturned' => count($organizations),
            'totalRecords' => $totalRecords,
            'startIndex' => $this->_paging['startIndex'],
            'sort' => $sortBy,
            'dir' => $order,
            'pageSize' => $this->_paging['count'],
            'records' => $organizations
        ));

        $this->_helper->json($tableData);
    }
    
    public function viewAction() 
    {
        $id = $this->getRequest()->getParam('id');        
        Fisma_Acl::requirePrivilege('Organization', 'read', $id);
        
        $organization = Doctrine::getTable('Organization')->find($id);
        $this->view->organization = $organization;
        $this->view->system = $organization->System;

        $this->render();
    }
    
    /**
     * Display basic system properties such as name, creation date, etc.
     */
    public function systemAction() 
    {
        $id = $this->getRequest()->getParam('id');
        Fisma_Acl::requirePrivilege('Organization', 'read', $id);
        $this->_helper->layout()->disableLayout();
        
        $this->view->organization = Doctrine::getTable('Organization')->find($id);
        $this->view->system = $this->view->organization->System;

        // Assign the parent organization link
        $parentOrganization = $this->view->organization->getNode()->getParent();
        if (isset($parentOrganization)) {
            if (Fisma_Acl::hasPrivilege('Organization', 'read', $parentOrganization->id)) {
                if ('system' == $this->parentOrganization->type) {
                    $this->view->parentOrganization = "<a href='/panel/system/sub/view/id/"
                                                    . $parentOrganization->id
                                                    . "'>"
                                                    . "$parentOrganization->nickname - $parentOrganization->name"
                                                    . "</a>";
                } else {
                    $this->view->parentOrganization = "<a href='/panel/organization/sub/view/id/"
                                                    . $parentOrganization->id
                                                    . "'>"
                                                    . "$parentOrganization->nickname - $parentOrganization->name"
                                                    . "</a>";
                
                }
            } else {
                $this->view->parentOrganization = "$parentOrganization->nickname - $parentOrganization->name";
            }
        } else {
            $this->view->parentOrganization = "<i>None</i>";
        }
        
        $this->render();
    }
    
    /**
     * Display CIA criteria and FIPS-199 categorization
     */
    public function fipsAction() 
    {
        $id = $this->getRequest()->getParam('id');
        Fisma_Acl::requirePrivilege('Organization', 'read', $id);
        $this->_helper->layout()->disableLayout();

        $this->view->organization = Doctrine::getTable('Organization')->find($id);
        $this->view->system = $this->view->organization->System;
        
        $this->render();
    }
    
    /**
     * Display FISMA attributes for the system
     */
    public function fismaAction() 
    {
        $id = $this->getRequest()->getParam('id');
        Fisma_Acl::requirePrivilege('Organization', 'read', $id);
        $this->_helper->layout()->disableLayout();

        $this->view->organization = Doctrine::getTable('Organization')->find($id);
        $this->view->system = $this->view->organization->System;
        
        $this->render();        
    }

    /**
     * Display FISMA attributes for the system
     */
    public function artifactsAction() 
    {
        $id = $this->getRequest()->getParam('id');
        Fisma_Acl::requirePrivilege('Organization', 'read', $id);
        $this->_helper->layout()->disableLayout();

        $organization = Doctrine::getTable('Organization')->find($id);
        $system = $organization->System;
        $documents = $system->Documents;
        
        $this->view->organization = $organization;
        $this->view->system = $system;
        
        // Get all documents for current system, sorted alphabetically on the document type name
        $documentQuery = Doctrine_Query::create()
                         ->from('SystemDocument d INNER JOIN d.DocumentType t')
                         ->where('d.systemId = ?', $system->id)
                         ->orderBy('t.name');
        $this->view->documents = $documentQuery->execute();
        
        $this->render();        
    }

    /**
     * Edit the system data
     */
    public function editAction()
    {
        $id = $this->getRequest()->getParam('id');
        Fisma_Acl::requirePrivilege('Organization', 'update', $id);
        $this->_helper->layout()->disableLayout();

        $organization = Doctrine::getTable('Organization')->find($id);
        $system = $organization->System;

        $post = $this->_request->getPost();
        if ($post) {
            $organization->merge($post);
            $organization->save();

            $system->merge($post);
            $system->save();
        }
        
        $this->_redirect("/panel/system/sub/view/id/$id");
    }

    /**
     * Upload file artifacts for a system
     */
    public function attachFileAction() 
    {
        $id = $this->getRequest()->getParam('id');
        Fisma_Acl::requirePrivilege('Organization', 'update', $id);
        $this->_helper->layout()->disableLayout();

        $this->view->organization = Doctrine::getTable('Organization')->find($id);
        $this->view->system = $this->view->organization->System;
    }
    
    /**
     * Display a form inside a panel for uploading a document
     */
    public function uploadDocumentFormAction()
    {
        $id = $this->getRequest()->getParam('id');
        Fisma_Acl::requirePrivilege('Organization', 'update', $id);
        $this->_helper->layout()->disableLayout();

        $this->view->organizationId = $id;        
        $this->view->documentTypes = Doctrine::getTable('DocumentType')->findAll();
    }
  
    /**
     * Display a form inside a panel for uploading a document
     */
    public function uploadDocumentAction()
    {
        $id = $this->getRequest()->getParam('id');

        Fisma_Acl::requirePrivilege('Organization', 'update', $id);
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $organization = Doctrine::getTable('Organization')->find($id);
        $documentTypeId = $this->getRequest()->getParam('documentTypeId');
        $description = $this->getRequest()->getParam('description');

        // Get the existing document
        $documentQuery = Doctrine_Query::create()
                         ->from('SystemDocument sd')
                         ->where('sd.systemId = ? AND sd.documentTypeId = ?',
                                 array($organization->System->id, $documentTypeId))
                         ->limit(1);
        $documents = $documentQuery->execute();
    
        // If no existing document, then create a new one
        if (count($documents) == 0) {
            $document = new SystemDocument();
            $document->documentTypeId = $documentTypeId;
            $document->System = $organization->System;
            $document->User = User::currentUser();
        } else {
            $document = $documents[0];
        }

        // Move file into its correct place
        $error = '';
        $file = $_FILES['Filedata'];
        $destinationPath = Fisma::getPath('systemDocument') . "/$id";
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath);
        }
        $fileName = preg_replace('/^(.*)\.(.*)$/', '$1-' . date('Ymd-His') . '.$2', $file['name'], 2, $count);
        $filePath = "$destinationPath/$fileName";
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $error = 'Cannot move uploaded file.';
        }
    
        // Update the document object and save
        $document->description = $description;
        $document->fileName = $fileName;
        $document->mimeType = $file['type'];
        $document->save();
        
        // Send back a JSON status
        $success = empty($error) ? true : false;
        echo(json_encode(array('success' => $success, 'error' => $error)));
    }  
    
    /**
     * Download the specified system document
     */
    public function downloadDocumentAction()
    {
        $id = $this->getRequest()->getParam('id');
        $document = Doctrine::getTable('SystemDocument')->find($id);
        if (is_null($document)) {
            throw new Fisma_Exception("Requested file does not exist.");
        }

        Fisma_Acl::requirePrivilege('Organization', 'update', $document->System->Organization->id);
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        /** @todo better error checking */
        $path = $document->getPath();
        $this->getResponse()
             ->setHeader('Content-Type', $document->mimeType)
             ->setHeader('Content-Disposition', "attachment; filename=\"$document->fileName\"");
         readfile($path);
    }
}
