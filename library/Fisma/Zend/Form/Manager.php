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
 * Provides an interface for loading forms that takes care of setting up the
 * common aspects of all forms used in OpenFISMA, such as standard decorators,
 * validators, and filters.
 *
 * @author     Mark E. Haase <mhaase@endeavorsystems.com>
 * @copyright  (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Fisma
 * @subpackage Fisma_Zend_Form
 */
class Fisma_Zend_Form_Manager
{
    /**
     * Loads a specified form by looking in the standard forms
     * directory.
     *
     * @param string $formName The name of the form to load. This form should
     * exist inside the forms directory. (Do not include the '.form' file
     * extension.)
     * @return Zend_Form The loaded zend form
     */
    static function loadForm($formName)
    {
        // Load the form from a .form file
        $front = Zend_Controller_Front::getInstance()->getRequest();
        $module = $front->getModuleName();
        $path = Fisma::getPath('form');
        $path = str_replace('default', $module, $path);

        // Try loading the form in the module specific form location first, if it's not there, load from the default
        // module form location
        if (file_exists($path . "/{$formName}.form")) {
            $config = new Zend_Config_Ini($path . "/{$formName}.form", $formName);
        } else {
            $config = new Zend_Config_Ini(Fisma::getPath('form') . "/{$formName}.form", $formName);
        }

        $form = new Fisma_Zend_Form();

        // Configure this form to use custom form elements
        $form->addPrefixPath('Fisma_Zend_Form', 'Fisma/Zend/Form', 'element');
        $form->addPrefixPath('Fisma_Zend_Form_Decorator', 'Fisma/Zend/Form/Decorator', 'decorator');
        $form->addPrefixPath('Fisma_Yui', 'Fisma/Yui', 'element');

        $form->addElementPrefixPath('Fisma_Zend_Form_Validate', 'Fisma/Zend/Form/Validate', 'validate');

        $form->setConfig($config);

        return $form;
    }

   /**
     * Adds the standard decorators and filters to the specified form.
     *
     * @param Zend_Form $form The specifed zend form to be decorated
     * @return Zend_Form The modified form
     */
    static function prepareForm(Fisma_Zend_Form $form, array $options = null)
    {
        $form->setMethod('post');

        // Use the Decorator for all Display Groups and Elements
        $form->setDecorators(
            array(
                new Zend_Form_Decorator_FormElements(),
                new Fisma_Zend_Form_Decorator()
            )
        );

        $form->setDisplayGroupDecorators(
            array(
                new Zend_Form_Decorator_FormElements(),
                new Fisma_Zend_Form_Decorator()
            )
        );

        $form->setElementDecorators(array(new Fisma_Zend_Form_Decorator()));

        $className = join("_", array_map("ucwords", explode('_', $options['formName'])));

        if ((!empty($options['formName'])) && class_exists('Fisma_Zend_Form_Manager_' . $className)) {
            $className = 'Fisma_Zend_Form_Manager_' . $className;
            $prepareForm = new $className($options['view'], $options['request'], $options['acl'], $options['user']);
            $prepareForm->setForm($form);
            $prepareForm->prepareForm();
            $form = $prepareForm->getForm();
            unset($prepareForm);
        }

        $form = self::addDefaultElementDecorators($form);

        return $form;
    }

    /**
     * Specifically handle some special element types.
     *
     * Extracted from prepareForm to be used with customized forms e.g. Indicent steps
     *
     * @param mixed $form
     * @return Fisma_Zend_Form The decorated form
     */
    static function addDefaultElementDecorators(Fisma_Zend_Form $form)
    {
        foreach ($form->getElements() as $element) {
            // By default, all input is trimmed of extraneous white space
            if (!$element->getFilter('StringTrim')) {
                $element->addFilter('StringTrim');
            }
            // Add decorator for select element
            if ($element->getType() == 'Zend_Form_Element_Select') {
                $element->viewScript = 'yui/select-menu.phtml';
                $element->addDecorator('ViewScript', array('placement' => false));
            }
        }
        return $form;
    }

    /**
     * Get form errors if form validate false
     *
     * @param Zend_From $form The zend form which fails to be validated and includes error messages
     * @return string The form errors
     * @todo this error display code needs to go into the decorator,
     */
    static function getErrors($form)
    {
        $errorString = '';
        foreach ($form->getMessages() as $field => $fieldErrors) {
            if (count($fieldErrors) > 0) {
                foreach ($fieldErrors as $error) {
                    $label = $form->getElement($field)->getLabel();
                    $errorString .= "$label: $error<br>";
                }
            }
        }
        return $errorString;
    }

}
