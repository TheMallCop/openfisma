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
 * PocController
 *
 * @author     Andrew Reeves <andrew.reeves@endeavorsystems.com>
 * @copyright  (c) Endeavor Systems, Inc. 2011 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Controller
 */
class PocController extends Fisma_Zend_Controller_Action_Object
{
    /**
     * The main name of the model.
     * 
     * @var string
     */
    protected $_modelName = 'Poc';

    /**
     * Override to provide a better singular name
     * 
     * @return string
     */
    public function getSingularModelName()
    {
        return 'Point of Contact';
    }
   
    /**
     * Override base class to prevent deletion of POC objects
     *
     * @return boolean
     */
    protected function _isDeletable()
    {
        return false;
    }

    /**
     * Set up context switch
     */
    public function init()
    {
        parent::init();

        $this->_helper->fismaContextSwitch()
                      ->addActionContext('create', 'json')
                      ->addActionContext('autocomplete', 'json')
                      ->addActionContext('tree-data', 'json')
                      ->initContext();
    }

    /**
     * Override to fill in option values for the select elements, etc.
     *
     * @param string|null $formName The name of the specified form
     * @return Zend_Form The specified form of the subject model
     */
    public function getForm($formName = null)
    {
        $form = parent::getForm($formName);

        $authType = Fisma::configuration()->getConfig('auth_type');
        if ($authType == 'database') {
            // Remove the "Check Account" button if we're not using external authentication
            $form->removeElement('checkAccount');
        } elseif ($authType == 'ldap') {
            $form->getElement('nameFirst')->setAttrib("readonly", "readonly");
            $form->getElement('nameLast')->setAttrib("readonly", "readonly");
            $form->getElement('email')->setAttrib("readonly", "readonly");
        }
        
        // Populate <select> for responsible organization
        $organizations = Doctrine::getTable('Organization')->getOrganizationSelectQuery()->execute();
        $selectArray = $this->view->systemSelect($organizations);
        $form->getElement('reportingOrganizationId')->addMultiOptions($selectArray);

        return $form;
    }

    /**
     * A helper action for autocomplete text boxes
     */
    public function autocompleteAction()
    {
        $keyword = $this->getRequest()->getParam('keyword');

        $pocQuery = Doctrine_Query::create()
                    ->from('Poc p')
                    ->select("p.id")
                    ->addSelect("CONCAT(p.username, ' [', p.nameFirst, ' ', p.nameLast, ']') AS name")
                    ->where('p.nameLast LIKE ?', "$keyword%")
                    ->orWhere('p.nameFirst LIKE ?', "$keyword%")
                    ->orWhere('p.username LIKE ?', "$keyword%")
                    ->orderBy("p.nameFirst")
                    ->setHydrationMode(Doctrine::HYDRATE_ARRAY);

        $this->view->pointsOfContact = $pocQuery->execute();
    }

    /**
     * Display the POC form without any layout
     */
    public function formAction()
    {
        $this->_helper->layout()->disableLayout();
        
        // The standard form needs to be modified to work inside a modal yui dialog
        $form = $this->getForm();
        $submit = $form->getElement('save');
        $submit->onClickFunction = 'Fisma.Finding.createPoc';

        $this->view->form = $form;
    }

    /**
     * Override _viewObject to work around the permission wonkiness.
     * 
     * A POC can also be a User. If a person has the read/poc privilege but not read/user, then the person won't be
     * able to view a User object. So we work around that right here.
     */
    protected function _viewObject()
    {
        $this->_acl->requirePrivilegeForClass('read', 'Poc');

        $this->_enforceAcl = false;
        parent::_viewObject();
        $this->_enforceAcl = true;
    }

    /**
     * Override _editObject to work around the permission wonkiness.
     * 
     * A POC can also be a User. If a person has the update/poc privilege but not update/user, then the person won't be
     * able to modify a User object. So we work around that right here.
     */
    protected function _editObject()
    {
        $this->_acl->requirePrivilegeForClass('update', 'Poc');

        $this->_enforceAcl = false;
        parent::_editObject();
        $this->_enforceAcl = true;
    }

    /**
     * Add the "POC Hierarchy" button
     *
     * @return array Array of Fisma_Yui_Form_Button
     */
    public function getToolbarButtons()
    {
        $buttons = array();

        if ($this->_acl->hasPrivilegeForClass('read', $this->getAclResourceName())) {
            $buttons['tree'] = new Fisma_Yui_Form_Button_Link(
                'pocTreeButton',
                array(
                    'value' => 'View POC Hierarchy',
                    'href' => $this->getBaseUrl() . '/tree'
                )
            );
        }

        $buttons = array_merge($buttons, parent::getToolbarButtons());

        return $buttons;
    }

    /**
     * Display organizations and POCs in tree mode for quick restructuring of the
     * POC hierarchy.
     */
    public function treeAction()
    {
        $this->_acl->requirePrivilegeForClass('read', 'Poc');

        $this->view->toolbarButtons = $this->getToolbarButtons();
        
        // "Return To Search Results" doesn't make sense on this screen, so rename that button:
        $this->view->toolbarButtons['list']->setValue("View POC List");
        
        // We're already on the tree screen, so don't show a "view tree" button
        unset($this->view->toolbarButtons['tree']);
    }

    /**
     * Returns a JSON object that describes the POC tree
     */
    public function treeDataAction()
    {
        $this->_acl->requirePrivilegeForClass('read', 'Poc');
        
        $this->view->treeData = $this->_getPocTree();
    }

    /**
     * Gets the organization tree for the current user.
     *
     * @return array The array representation of organization tree
     */
    protected function _getPocTree()
    {
        // Get a list of POCs
        $pocQuery = Doctrine_Query::create()
                    ->select('p.id, p.username, p.nameFirst, p.nameLast, p.type, p.reportingOrganizationId')
                    ->from('Poc p')
                    ->orderBy('p.reportingOrganizationId, p.username')
                    ->where('p.reportingOrganizationId IS NOT NULL')
                    ->setHydrationMode(Doctrine::HYDRATE_SCALAR);
        $pocs = $pocQuery->execute();
        
        // Group POCs by organization ID
        $pocsByOrgId = array();
        
        foreach ($pocs as $poc) {
            $orgId = $poc['p_reportingOrganizationId'];

            if (isset($pocsByOrgId[$orgId])) {
                $pocsByOrgId[$orgId][] = $poc;
            } else {
                $pocsByOrgId[$orgId] = array($poc);
            }
        }

        // Get a tree of organizations
        $orgBaseQuery = Doctrine_Query::create()
                        ->from('Organization o')
                        ->select('o.name, o.nickname, ot.nickname, s.type, s.sdlcPhase')
                        ->leftJoin('o.OrganizationType ot')
                        ->where('ot.nickname <> ?', 'system')
                        ->orderBy('o.lft');

        $orgTree = Doctrine::getTable('Organization')->getTree();
        $orgTree->setBaseQuery($orgBaseQuery);
        $organizations = $orgTree->fetchTree();
        $orgTree->resetBaseQuery();

        // Merge organizations and POCs and return.
        $organizationTree = $this->toHierarchy($organizations, $pocsByOrgId);

        return $organizationTree;
    }

    /**
     * Transform the flat array returned from Doctrine's nested set into a nested array
     *
     * Doctrine should provide this functionality in a future
     *
     * @param Doctrine_Collection $collection The collection of organization record to hierarchy
     * @param array $pocsByOrgId Nested array of POCs indexed by the POCs' reporting organization ID
     * @return array The array representation of organization tree
     * @todo review the need for this function in the future
     */
    public function toHierarchy($collection, $pocsByOrgId)
    {
        // Trees mapped
        $trees = array();
        $l = 0;

        // Ensure collection is a tree
        if (!empty($collection)) {
            // Node Stack. Used to help building the hierarchy
            $rootLevel = $collection[0]->level;

            $stack = array();
            foreach ($collection as $node) {
                $item = ($node instanceof Doctrine_Record) ? $node->toArray() : $node;
                $item['level'] -= $rootLevel;
                $item['label'] = $item['nickname'] . ' - ' . $item['name'];
                $item['orgType'] = $node->getType();
                $item['orgTypeLabel'] = $node->getOrgTypeLabel();
                $item['children'] = array();

                // Merge in any POCs that report to this organization
                if (isset($pocsByOrgId[$node->id])) {
                    $item['children'] += $pocsByOrgId[$node->id];
                }

                // Number of stack items
                $l = count($stack);
                // Check if we're dealing with different levels
                while ($l > 0 && $stack[$l - 1]['level'] >= $item['level']) {
                    array_pop($stack);
                    $l--;
                }

                if ($l != 0) {
                    if ($node->getNode()->getParent()->name == $stack[$l-1]['name']) {
                        // Add node to parent
                        $i = count($stack[$l - 1]['children']);
                        $stack[$l - 1]['children'][$i] = $item;
                        $stack[] = & $stack[$l - 1]['children'][$i];
                    } else {
                        // Find where the node belongs
                        for ($j = $l; $j >= 0; $j--) {
                            if ($j == 0) {
                                $i = count($trees);
                                $trees[$i] = $item;
                                $stack[] = &$trees[$i];
                            } elseif ($node->getNode()->getParent()->name == $stack[$j-1]['name']) {
                                // Add node to parent
                                $i = count($stack[$j-1]['children']);
                                $stack[$j-1]['children'][$i] = $item;
                                $stack[] = &$stack[$j-1]['children'][$i];
                                break;
                            }
                        }
                    }
                } else {
                    // Assigning the root node
                    $i = count($trees);
                    $trees[$i] = $item;
                    $stack[] = &$trees[$i];
                }
            }
        }

        return $trees;
    }

    /**
     * Moves a POC node from one organization to another.
     * 
     * This is used by the YUI tree node to handle drag and drop of organization nodes. It replies with a JSON object.
     */
    public function moveNodeAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();

        $response = new Fisma_AsyncResponse;

        // Find the source and destination objects from the tree
        $srcId = $this->getRequest()->getParam('src');
        $src = Doctrine::getTable('Poc')->find($srcId);

        $destPocId = $this->getRequest()->getParam('destPoc');
        if ($destPocId) {
            $destPoc = Doctrine::getTable('Poc')->find($destPocId);
            $destOrg = $destPoc->ReportingOrganization;
        } else {
            $destId = $this->getRequest()->getParam('destOrg');
            $destOrg = Doctrine::getTable('Organization')->find($destId);            
        }

        if ($src && $destOrg) {
            $src->ReportingOrganization = $destOrg;
            $src->save();
        } else {
            $response->fail("Invalid src, destPoc or destOrg parameter ($srcId, $destPocId, $destOrgId)");
        }

        print Zend_Json::encode($response);
    }
}