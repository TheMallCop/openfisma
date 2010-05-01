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
 * along with OpenFISMA.  If not, see {@link http://www.gnu.org/licenses/}.
 *
 * @fileoverview AutoComplete namespace 
 *
 * @author    Josh Boyd <joshua.boyd@endeavorsystems.com>
 * @copyright (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license   http://www.openfisma.org/content/license
 * @package   Fisma
 * @requires  YAHOO.widget.AutoComplete
 * @requires  YAHOO.widget.DS_XHR
 * @requires  Fisma
 * @version   $Id$
 */

Fisma.AutoComplete = function() {
    return {
        /**
         * Initializes the AutoComplete widget
         *
         * @param oEvent
         * @param aArgs
         * @param {Array} params 
         */
        init : function(oEvent, aArgs, params) {
            var acRDS = new YAHOO.widget.DS_XHR(params.xhr, params.schema);

            acRDS.responseType = YAHOO.widget.DS_XHR.TYPE_JSON;
            acRDS.maxCacheEntries = 500;
            acRDS.queryMatchContains = true;

            var ac = new YAHOO.widget.AutoComplete(params.fieldId, params.containerId, acRDS);

            ac.maxResultsDisplayed = 20;
            ac.forceSelection = true;

            /**
             * Override generateRequest method of YAHOO.widget.AutoComplete
             *
             * @param {String} query Query terms
             * @returns {String}
             */
            ac.generateRequest = function(query) {
                return params.queryPrepend + query;
            };

            ac.itemSelectEvent.subscribe(Fisma.AutoComplete.subscribe, { hiddenFieldId: params.hiddenFieldId } );
        },
        /**
         * Sets value of hiddenField to item selected
         *
         * @param sType
         * @param aArgs
         * @param {Array} params
         */
        subscribe : function(sType, aArgs, params) {
            document.getElementById(params.hiddenFieldId).value = aArgs[2][1]['id'];
        }
    };
}();
