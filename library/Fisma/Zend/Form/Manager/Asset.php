<?php
/**
 * Copyright (c) 2008 Endeavor Systems, Inc.
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
 * Fisma_Zend_Form_Manager_Asset
 *
 * @uses Fisma_Zend_Form_Manager_Abstract
 * @package Fisma_Zend_Form_Manager
 * @copyright (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @author Josh Boyd <joshua.boyd@endeavorsystems.com>
 * @license http://www.openfisma.org/content/license GPLv3
 */
class Fisma_Zend_Form_Manager_Asset extends Fisma_Zend_Form_Manager_Abstract
{
    /**
     * prepareForm
     *
     * @return void
     */
    public function prepareForm()
    {
        $form = $this->getForm();

        $systems = Doctrine_Query::create()
            ->from('Organization o')
            ->innerJoin('o.OrganizationType ot')
            ->where('ot.nickname = ?', 'system')
            ->execute();
        $selectArray = $this->_view->systemSelect($systems);
        // Add an option to add Network on the fly
        $form->getElement('orgSystemId')->addMultiOption('', '');
        $form->getElement('orgSystemId')->addMultiOptions(array('new' => '-- Add New System --'));
        $form->getElement('orgSystemId')
            ->setOptions(array('onChange' => 'Fisma.Vulnerability.displayCreatePanel(this)'));
        $form->getElement('orgSystemId')->addMultiOptions($selectArray);

        $networks = Doctrine_Query::create()
                    ->from('Network t')
                    ->setHydrationMode(Doctrine::HYDRATE_ARRAY)
                    ->execute();

        $networkList = array();
        foreach ($networks as $network) {
            $networkList[$network['id']] = $network['nickname'].'-'.$network['name'];
        }
        // Add an option to add Network on the fly
        $form->getElement('networkId')->addMultiOption('', '');
        $form->getElement('networkId')->addMultiOptions(array('new' => '-- Add New Network --'));
        $form->getElement('networkId')->setOptions(array('onChange' => 'Fisma.Vulnerability.displayCreatePanel(this)'));

        $form->getElement('networkId')->addMultiOptions($networkList);

        $this->setForm($form);
    }
}
