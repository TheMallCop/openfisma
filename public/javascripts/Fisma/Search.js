/**
 * Copyright (c) 2010 Endeavor Systems, Inc.
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
 *
 * @fileoverview Various client side behaviors related to search functionality
 *
 * @author    Mark E. Haase <mhaase@endeavorsystems.com>
 * @copyright (c) Endeavor Systems, Inc. 2010 (http://www.endeavorsystems.com)
 * @license   http://www.openfisma.org/content/license
 */

Fisma.Search = function() {
    return {

        /**
         * A reference to the YUI data table which is used for displaying search results
         */
        yuiDataTable : null,

        /**
         * A callback function which is called when the YUI data table reference is set
         */
        onSetTableCallback : null,

        /**
         * True if the test configuration process is currently running
         */
        testConfigurationActive : false,

        /**
         * The base URL to the controller action for searching
         */
        baseUrl : '',

        /**
         * A URL which builds on the base URL to pass arguments to the search controller action
         *
         * This is used by YUI data table to build its own queries with sorting added
         */
        searchActionUrl : '',

        /**
         * Advanced search panel
         */
        advancedSearchPanel : null,

        /**
         * A spinner that is display while persising the user's column preferences cookie
         */
        columnPreferencesSpinner : null,

        /**
         * Test the current system configuration
         */
        testConfiguration : function () {

            if (Fisma.Search.testConfigurationActive) {
                return;
            }

            Fisma.Search.testConfigurationActive = true;

            var testConfigurationButton = document.getElementById('testConfiguration');
            testConfigurationButton.className = "yui-button yui-push-button yui-button-disabled";

            var spinner = new Fisma.Spinner(testConfigurationButton.parentNode);
            spinner.show();

            var form = document.getElementById('search_config');
            YAHOO.util.Connect.setForm(form);

            YAHOO.util.Connect.asyncRequest(
                'POST',
                '/config/test-search/format/json',
                {
                    success : function (o) {
                        var response = YAHOO.lang.JSON.parse(o.responseText).response;

                        if (response.success) {
                            message("Search configuration is valid", "notice");
                        } else {
                            message(response.message, "warning");
                        }

                        testConfigurationButton.className = "yui-button yui-push-button";
                        Fisma.Search.testConfigurationActive = false;
                        spinner.hide();
                    },

                    failure : function (o) {
                        message('Error: ' + o.statusText, 'warning');

                        spinner.hide();
                    }
                }
            );
        },

        /**
         * Handles a search event. This works in tandem with the search.form and Fisma_Zend_Controller_Action_Object.
         *
         * Two types of query are possible: simple and advanced. A hidden field is used to determine which of the
         * two to use while handling this event.
         *
         * @param form Reference to the search form
         */
        handleSearchEvent : function (form) {
            var dataTable = Fisma.Search.yuiDataTable;

            var onDataTableRefresh = {
                success : function (request, response, payload) {
                    dataTable.onDataReturnReplaceRows(request, response, payload);

                    // Update YUI's visual state to show sort on first column
                    var firstColumn = dataTable.getColumn(0);
                    dataTable.set("sortedBy", firstColumn);
                },
                failure : dataTable.onDataReturnReplaceRows,
                scope : dataTable,
                argument : dataTable.getState()
            }

            // Construct a query URL based on whether this is a simple or advanced search
            var query = this.getQuery(form);
            var postData = this.convertQueryToPostData(query);

            dataTable.showTableMessage("Loading...");
            dataTable.getDataSource().sendRequest(postData, onDataTableRefresh);
        },

        /**
         * Returns a POST request suitable for submitting a search query
         *
         * @var form A reference to the form
         * @return Key value pairs (object) of query data
         */
        getQuery : function (form) {
            var searchType = document.getElementById('searchType').value;
            var query = {queryType : searchType};

            if ('simple' == searchType) {
                query['keywords'] = form.keywords.value
            } else if ('advanced' == searchType) {
                var queryData = this.advancedSearchPanel.getQuery();

                query['query'] = YAHOO.lang.JSON.stringify(queryData);
            } else {
                throw "Invalid value for search type: " + searchType;
            }

            return query;
        },

        /**
         * Convert an array of key value pairs into URL encoded post data
         *
         * @var object
         * @return string
         */
        convertQueryToPostData : function (object) {

            var uriComponents = Array();

            for (var key in object) {
                var value = object[key];

                uriComponents.push(key + "=" + encodeURIComponent(value));
            }

            var postData = uriComponents.join('&');

            return postData;
        },

        /**
         * Download current search results into a file attachment (such as PDF or Excel)
         *
         * This function operates by creating a hidden form on the page and then calling submit() on that form.
         *
         * @var event Provided by YUI
         * @var format Either "pdf" or "xls"
         */
        exportToFile : function (event, format) {
            var searchForm = document.getElementById('searchForm');

            // The form's action is based on the data table's data source
            var table = Fisma.Search.yuiDataTable;
            var dataSource = table.getDataSource();
            var baseUrl = dataSource.liveData;

            // Create a hidden form for submitting the request
            var tempForm = document.createElement('form');

            tempForm.method = 'post';
            tempForm.action = baseUrl + '/format/' + format;
            tempForm.style.display = 'none';

            var query = Fisma.Search.getQuery(searchForm);

            // Create a hidden form element for each piece of post data
            for (var key in query) {
                var value = query[key];

                var hiddenField = document.createElement('input');

                hiddenField.type = 'hidden';
                hiddenField.name = key;
                hiddenField.value = value;

                tempForm.appendChild(hiddenField);
            }

            document.body.appendChild(tempForm);
            tempForm.submit();
        },

        /**
         * Handle YUI data table events (such as sort)
         *
         * @param tableState From YUI
         * @param self From YUI
         * @return string URL encoded post data
         */
        handleYuiDataTableEvent : function (tableState, self) {

            var searchType = document.getElementById('searchType').value;

            var postData = "sort=" + tableState.sortedBy.key +
                           "&dir=" + (tableState.sortedBy.dir == 'yui-dt-asc' ? 'asc' : 'desc') +
                           "&start=" + tableState.pagination.recordOffset +
                           "&count=" + tableState.pagination.rowsPerPage;

            if ('simple' == searchType) {
                postData += "&queryType=simple&keywords=" + document.getElementById('keywords').value;
            } else if ('advanced' == searchType) {
                var queryData = Fisma.Search.advancedSearchPanel.getQuery();

                postData += "&queryType=advanced&query=" + YAHOO.lang.JSON.stringify(queryData);
            } else {
                throw "Invalid value for search type: " + searchType;
            }

            return postData;
        },

        /**
         * Highlight marked words in the search results table
         *
         * Due to a quirk in Solr, highlights are delimited by three asterisks ***. This method just has to go
         * through and find the asterisks, strip them out, and replace the content between them with highlighted text.
         *
         * @param dataTable The YUI data table to perform highlighting on
         */
        highlightSearchResultsTable :  function (dataTable) {
            var dataTable = Fisma.Search.yuiDataTable;

            var tbody = dataTable.getTbodyEl();

            var cells = tbody.getElementsByTagName('td');

            var delimiter = '***';

            Fisma.Highlighter.highlightDelimitedText(cells, delimiter);
        },

        /**
         * Show or hide the advanced search options UI
         */
        toggleAdvancedSearchPanel : function () {
            if (document.getElementById('advancedSearch').style.display == 'none') {

                document.getElementById('advancedSearch').style.display = 'block';
                document.getElementById('keywords').style.visibility = 'hidden';
                document.getElementById('searchType').value = 'advanced';

            } else {

                document.getElementById('advancedSearch').style.display = 'none';
                document.getElementById('keywords').style.visibility = 'visible';
                document.getElementById('searchType').value = 'simple';

            }
        },

        /**
         * Show or hide the search columns UI
         */
        toggleSearchColumnsPanel : function () {
            if (document.getElementById('searchColumns').style.display == 'none') {
                document.getElementById('searchColumns').style.display = 'block';
            } else {
                document.getElementById('searchColumns').style.display = 'none';
            }
        },

        /**
         * Initialize the search columns UI
         *
         * @param container The HTML element to render into
         * @param searchOptions The options defined in Fisma_Search_Searchable interface
         */
        initializeSearchColumnsPanel : function (container, searchOptions) {

            // Set up the cookie used for tracking which columns are visible
            var modelName = document.getElementById('modelName').value;
            var cookieName = modelName + "Columns";
            var cookie = YAHOO.util.Cookie.get(cookieName);
            var currentColumn = 0;

            for (var index in searchOptions) {
                var searchOption = searchOptions[index];

                if (searchOption['hidden'] === true) {
                    continue;
                }

                // Use the cookie to determine which buttons are on, or use the metadata if no cookie exists
                var checked = searchOption.initiallyVisible;

                if (cookie) {
                    checked = (cookie & 1 << currentColumn) != 0;
                }

                currentColumn++;

                // Title elements used for accessibility
                var checkedTitle = "Column is visible. Click to hide column.";
                var uncheckedTitle = "Column is hidden. Click to unhide column.";

                var columnToggleButton = new YAHOO.widget.Button({
                    type : "checkbox",
                    label : searchOption.label,
                    container : container,
                    checked : checked,
                    onclick : {
                        fn : function (event, columnKey) {
                            this.set("title", this.get("checked") ? checkedTitle : uncheckedTitle);

                            var table = Fisma.Search.yuiDataTable;
                            var column = table.getColumn(columnKey);

                            if (this.get('checked')) {
                                table.showColumn(column);
                            } else {
                                table.hideColumn(column);
                            }

                            Fisma.Search.saveColumnCookies();
                        },
                        obj : searchOption.name
                    }
                });

                columnToggleButton.set("title", checked ? checkedTitle : uncheckedTitle);
            }

            var saveDiv = document.createElement('div');
            saveDiv.style.marginLeft = '20px';
            saveDiv.style.marginBottom = '20px';
            saveDiv.style.float = 'right';

            // Create the Save button
            var saveButton = new YAHOO.widget.Button({
                type : "button",
                label : "Save Column Preferences",
                container : saveDiv,
                onclick : {
                    fn : Fisma.Search.persistColumnCookie
                }
            });

            if (!Fisma.Search.columnPreferencesSpinner) {
                Fisma.Search.columnPreferencesSpinner = new Fisma.Spinner(saveDiv);
            }

            container.appendChild(saveDiv);
        },

        /**
         * Toggles the display of the "more" options for search
         *
         * This includes things like help, column toggles, and advanced search
         */
        toggleMoreButton : function () {
            if (document.getElementById('moreSearchOptions').style.display == 'none') {
                document.getElementById('moreSearchOptions').style.display = 'block';
            } else {
                document.getElementById('moreSearchOptions').style.display = 'none';
            }
        },

        /**
         * Save the currently visible columns into a cookie
         *
         * @param table YUI Table
         */
        saveColumnCookies : function () {
            var table = Fisma.Search.yuiDataTable;
            var columnKeys = table.getColumnSet().keys;

            // Column preferences are stored as a bitmap (1=>visible, 0=>hidden)
            var prefBitmap = 0;

            for (var column in columnKeys) {
              if (!columnKeys[column].hidden) {
                prefBitmap |= 1 << column;
              }
            }

            var modelName = document.getElementById('modelName').value;
            var cookieName = modelName + "Columns";

            YAHOO.util.Cookie.set(
                cookieName,
                prefBitmap,
                {
                    path : "/",
                    secure : location.protocol == 'https'
                }
            );
        },

        /**
         * Persist the column cookie into the user's profile
         */
        persistColumnCookie : function () {
            Fisma.Search.saveColumnCookies();

            var modelName = document.getElementById('modelName').value;
            var cookieName = modelName + "Columns";
            var cookie = YAHOO.util.Cookie.get(cookieName);

            Fisma.Search.columnPreferencesSpinner.show();

            YAHOO.util.Connect.asyncRequest(
                'GET',
                '/user/set-cookie/name/' + cookieName + '/value/' + cookie + '/format/json',
                {
                    success : function (o) {
                        Fisma.Search.columnPreferencesSpinner.hide();

                        var response = YAHOO.lang.JSON.parse(o.responseText);

                        if (response.success) {
                            message("Your column preferences have been saved", "notice");
                        } else {
                            message(response.message, "warning");
                        }
                    },

                    failure : function (o) {
                        Fisma.Search.columnPreferencesSpinner.hide();

                        message('Error: ' + o.statusText, 'warning');
                    }
                }
            );
        },

        /**
         * A method to add a YUI table to the "registry" that this object keeps track of
         *
         * @var table A YUI table
         */
        setTable : function (table) {
            this.yuiDataTable = table;

            if (this.onSetTableCallback) {
                this.onSetTableCallback();
            }
        },

        /**
         * Set a callback function to call when the YUI table gets set (see setTable)
         */
        onSetTable : function(callback) {
            this.onSetTableCallback = callback;
        }
    }
}();
