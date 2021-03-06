<?php
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
 */

/**
 * Search engine backend based on Solr PHP client (http://code.google.com/p/solr-php-client/)
 *
 * @author     Mark E. Haase <mhaase@endeavorystems.com>
 * @copyright  (c) Endeavor Systems, Inc. 2010 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Fisma
 * @subpackage Fisma_Search
 */
class Fisma_Search_Engine
{
    /**
     * True if highlighting should be turned on
     */
    private $_highlightingEnabled = true;

    /**
     * Client object is used for communicating with Solr server
     *
     * @var Apache_Solr_Service
     */
    private $_client;

    /**
     * Constructs a search engine client object
     *
     * The constructor is public to keep the design clean, but in production you probably don't want to instantiate
     * your own instance at runtime. Instead, use a pre-built search engine object from the registry.
     *
     * @param string $hostname Hostname or IP address where Solr's servlet container is running
     * @param int $port The port that Solr's servlet container is listening on
     * @param string $path The path within the servlet container that Solr is running on
     */
    public function __construct($hostname, $port, $path)
    {
        if (!class_exists('Apache_Solr_Service')) {
            throw new Fisma_Search_Exception("Solr PHP Client library is not installed.");
        }

        $this->_client = new Apache_Solr_Service($hostname, $port, $path);

    }

    /**
     * Commit any changes to the index made since the previous commit.
     */
    public function commit()
    {
        $this->_client->commit();
    }

    /**
     * Roll back any changes to the index made since the previous commit.
     */
    public function rollback()
    {
        $this->_client->rollback();
    }

    /**
     * Delete all documents in the index
     */
    public function deleteAll()
    {
        $this->_client->deleteByQuery('*:*');
    }

    /**
     * Delete all documents of the specified type in the index
     *
     * "Type" refers to a model, such as Asset, Finding, Incident, etc.
     *
     * @param string $type
     */
    public function deleteByType($type)
    {
        $this->_client->deleteByQuery('luceneDocumentType:' . $type);
    }

    /**
     * Delete the specified object from the index
     *
     * $type must have a corresponding table class which implements Fisma_Search_Searchable
     *
     * @param string $type The class of the object
     * @param array $object
     */
    public function deleteObject($type, $object)
    {
        $luceneDocumentId = $type . $object['id'];

        $this->_client->deleteById($luceneDocumentId);
    }

    /**
     * Index an array of objects
     *
     * @param string $type The class of the object
     * @param array $collection
     */
    public function indexCollection($type, $collection)
    {
        $documents = $this->_convertCollectionToDocumentArray($type, $collection);

        $this->_client->addDocuments($documents);
    }

    /**
     * Add the specified object (in array format) to the search engine index
     *
     * This will overwrite any existing object with the same luceneDocumentId
     *
     * @param string $type The class of the object
     * @param array $object
     */
    public function indexObject($type, $object)
    {
        $document = $this->_convertObjectToDocument($type, $object);

        $this->_client->addDocument($document);
    }

    /**
     * Returns true if the specified column is sortable
     *
     * This is defined in the search abstraction layer since ultimately the sorting capability is determined by the
     * search engine implementation.
     *
     * In Solr, sorting is only available for stored, un-analyzed, single-valued fields.
     *
     * @param string $type The class containing the column
     * @param string $columnName
     * @return bool
     */
    public function isColumnSortable($type, $columnName)
    {
        $table = Doctrine::getTable($type);

        if (!($table instanceof Fisma_Search_Searchable)) {
            throw new Fisma_Search_Exception("This table is not searchable: $type");
        }

        $searchableFields = $this->_getSearchableFields($type);

        return isset($searchableFields[$columnName]['sortable']) && $searchableFields[$columnName]['sortable'];
    }

    /**
     * Optimize the index (degfragments the index)
     */
    public function optimizeIndex()
    {
        $this->_client->optimize();
    }

    /**
     * Simple search: search all fields for the specified keyword
     *
     * If keyword is null, then this is just a listing of all documents of a specific type
     *
     * @param string $type Name of model index to search
     * @param string $keyword
     * @param string $sortColumn Name of column to sort on
     * @param boolean $sortDirection True for ascending sort, false for descending
     * @param int $start The offset within the result set to begin returning documents from
     * @param int $rows The number of documents to return
     * @param bool $deleted If true, include soft-deleted records in the results
     * @return Fisma_Search_Result
     */
    public function searchByKeyword($type, $keyword, $sortColumn, $sortDirection, $start, $rows, $deleted)
    {
        return $this->search(
            $type, $keyword, new Fisma_Search_Criteria, $sortColumn, $sortDirection, $start, $rows, $deleted
        );
    }

    /**
     * Generate query components
     *
     * @param string $type Name of model index to search
     * @param string $keyword
     * @param Fisma_Search_Criteria $criteria
     * @param string $sortColumn Name of column to sort on
     * @param boolean $sortDirection True for ascending sort, false for descending
     * @param int $start The offset within the result set to begin returning documents from
     * @param int $rows The number of documents to return
     * @param bool $deleted If true, include soft-deleted records in the results
     * @return Fisma_Search_Result Rectangular array of search results
     */

    public function generateQuery($type, $keyword, Fisma_Search_Criteria $criteria, $sortColumn, $sortDirection,
                                     $start, $rows, $deleted)
    {
        $params = array();

        $table = Doctrine::getTable($type);
        $searchableFields = $this->_getSearchableFields($type);

        if (!isset($searchableFields[$sortColumn]) || !$searchableFields[$sortColumn]['sortable']) {
            throw new Fisma_Search_Exception("Not a sortable column: $sortColumn");
        }

        $sortColumnDefinition = $searchableFields[$sortColumn];

        // Text columns have different sorting rules (see design document)
        if ('text' == $sortColumnDefinition['type']) {
            $sortColumnParam = $this->escape($sortColumn) . '_textsort';
        } elseif ('enum' == $sortColumnDefinition['type']) {
            $sortColumnParam = $this->escape($sortColumn) . '_enumsort';
        } else {
            $sortColumnParam = $this->escape($sortColumn) . '_' . $sortColumnDefinition['type'];
        }

        $sortDirectionParam = $sortDirection ? 'asc' : 'desc';

        // Add required fields to query. The rest of the fields are added below.
        $params['fl'] = array('id', 'luceneDocumentId');
        $params['sort'] = $sortColumnParam . ' ' . $sortDirectionParam;

        $trimmedKeyword = trim($keyword);

        $filterQuery = 'luceneDocumentType:' . $this->escape($type);

        // Add ACL constraints to filter query
        $aclQueryFilter = $this->_getAclQueryFilter($table, $searchableFields);

        if (!empty($aclQueryFilter)) {
            $filterQuery .= ' AND ('
                          . $aclQueryFilter
                          . ')';
        }

        // Handle soft delete
        if ($table->hasColumn('deleted_at')) {
            $params['fl'][] = 'deleted_at_datetime';

            if (!$deleted) {
                $filterQuery .= ' AND -deleted_at_datetime:/.*/';
            }
        }

        // Enable highlighting
        if ($this->getHighlightingEnabled()) {
            $params['hl'] = 'true';
            $params['hl.fragsize'] = 0;
            $params['hl.simple.pre'] = '***';
            $params['hl.simple.post'] = '***';
            $params['hl.simple.pre'] = '***';
            $params['hl.requireFieldMatch'] = 'false';
            $params['hl.fl'] = array();
        }

        // The filter query is used for efficiency (parts of the query that don't change can be cached separately)
        $params['fq'] = $filterQuery;

        // Add the fields which should be returned in the result set and indicate which should be highlighted
        foreach ($searchableFields as $fieldName => $fieldDefinition) {

            // Some twiddling to convert Doctrine's field names to Solr's field names
            $documentFieldName = $fieldName . '_' . $fieldDefinition['type'];

            $params['fl'][] = $documentFieldName;

            // Highlighting doesn't work for date, integer, or float fields in Solr 4.1
            if ('date' != $fieldDefinition['type'] &&
                'datetime' != $fieldDefinition['type'] &&
                'integer' != $fieldDefinition['type'] &&
                'float' != $fieldDefinition['type']) {

                $params['hl.fl'][] = $documentFieldName;
            }
        }

        // Add specific query terms based on the user's request
        $searchTerms = array();

        foreach ($criteria as $criterion) {

            $doctrineFieldName = $this->escape($criterion->getField());

            if (!isset($searchableFields[$doctrineFieldName])) {
                throw new Fisma_Search_Exception("Invalid field name: " . $doctrineFieldName);
            }

            $fieldName = $doctrineFieldName . '_' . $searchableFields[$doctrineFieldName]['type'];

            $operands = array_map('addslashes', $criterion->getOperands());

            $operator = $criterion->getOperator();

            switch ($operator) {
                case 'booleanYes':
                    $searchTerms[] = "$fieldName:true";
                    break;
                case 'booleanNo':
                    $searchTerms[] = "$fieldName:false";
                    break;
                case 'dateAfter':
                    try {
                        $afterDate = new Zend_Date($operands[0], Fisma_Date::FORMAT_DATETIME);
                        $afterDate = $afterDate->add(1, Zend_Date::DAY)->toString(Fisma_Date::FORMAT_DATETIME);
                        $afterDate = $this->_convertToSolrDate($afterDate);
                        $searchTerms[] = "$fieldName:[$afterDate TO *]";
                    } catch (Zend_Date_Exception $e) {
                        // The input date is invalid, return an empty set.
                        return new Fisma_Search_Result(0, 0, array());
                    }
                    break;

                case 'dateBefore':
                    try  {
                        $beforeDate = $this->_convertToSolrDate($operands[0]);
                        $searchTerms[] = "$fieldName:[* TO $beforeDate/DAY]";
                    } catch (Zend_Date_Exception $e) {
                        // The input date is invalid, return an empty set.
                        return new Fisma_Search_Result(0, 0, array());
                    }
                    break;

                case 'dateBetween':
                    try {
                        $afterDate = $this->_convertToSolrDate($operands[0]);
                        $beforeDate = new Zend_Date($operands[1], Fisma_Date::FORMAT_DATE);
                        $beforeDate = $beforeDate->add(1, Zend_Date::DAY)->toString(Fisma_Date::FORMAT_DATE);
                        $beforeDate = $this->_convertToSolrDate($beforeDate);
                        $searchTerms[] = "$fieldName:[$afterDate/DAY TO $beforeDate/DAY]";
                    } catch (Zend_Date_Exception $e) {
                        // The input date is invalid, return an empty set.
                        return new Fisma_Search_Result(0, 0, array());
                    }
                    break;

                case 'dateDay':
                    try {
                        $date = $this->_convertToSolrDate($operands[0]);
                        $searchTerms[] = "$fieldName:[$date/DAY TO $date/DAY+1DAY]";
                    } catch (Zend_Date_Exception $e) {
                        // The input date is invalid, return an empty set.
                        return new Fisma_Search_Result(0, 0, array());
                    }
                    break;

                case 'dateThisMonth':
                    $searchTerms[] = "$fieldName:[NOW/MONTH TO NOW/MONTH+1MONTH]";
                    break;

                case 'dateThisYear':
                    $searchTerms[] = "$fieldName:[NOW/YEAR TO NOW/YEAR+1YEAR]";
                    break;

                case 'dateToday':
                    $searchTerms[] = "$fieldName:[NOW/DAY TO NOW/DAY+1DAY]";
                    break;

                case 'floatBetween':
                    if (!is_numeric($operands[0]) || !is_numeric($operands[1])) {
                        throw new Fisma_Search_Exception("Invalid operands to floatBetween criterion.");
                    }

                    if ($operands[0] < $operands[1]) {
                        $searchTerms[] = "$fieldName:[{$operands[0]} TO {$operands[1]}]";
                    } else {
                        $searchTerms[] = "$fieldName:[{$operands[1]} TO {$operands[0]}]";
                    }
                    break;

                case 'floatGreaterThan':
                    if (!is_numeric($operands[0])) {
                        throw new Fisma_Search_Exception("Invalid operands to floatGreaterThan criterion.");
                    }

                    $searchTerms[] = "$fieldName:[{$operands[0]} TO *]";
                    break;

                case 'floatLessThan':
                    if (!is_numeric($operands[0])) {
                        throw new Fisma_Search_Exception("Invalid operands to floatLessThan criterion.");
                    }

                    $searchTerms[] = "$fieldName:[* TO {$operands[0]}]";
                    break;

                case 'integerBetween':
                    $lowEndIntValue = intval($operands[0]);
                    $highEndIntValue = intval($operands[1]);

                    if ($lowEndIntValue < $highEndIntValue) {
                        $searchTerms[] = "$fieldName:[$lowEndIntValue TO $highEndIntValue]";
                    } else {
                        $searchTerms[] = "$fieldName:[$highEndIntValue TO $lowEndIntValue]";
                    }
                    break;

                case 'integerDoesNotEqual':
                    $searchTerms[] = $this->_integerDoesNotEqual($fieldName, $operands[0]);
                    break;

                case 'integerEquals':
                    $searchTerms[] = $this->_integerEquals($fieldName, $operands[0]);
                    break;

                case 'integerGreaterThan':
                    $intValue = intval($operands[0]);
                    $searchTerms[] = "$fieldName:[$intValue TO *]";
                    break;

                case 'integerLessThan':
                    $intValue = intval($operands[0]);
                    $searchTerms[] = "$fieldName:[* TO $intValue]";
                    break;

                // The following cases intentionally fall through
                case 'textContains':
                case 'enumIs':
                    $searchTerms[] = "$fieldName:\"{$operands[0]}\"";
                    break;

                // The following cases intentionally fall through
                case 'textDoesNotContain':
                case 'enumIsNot':
                    $searchTerms[] = "-$fieldName:\"{$operands[0]}\"";
                    break;

                case 'textIn':
                    foreach ($operands as &$operand) {
                        $operand = "{$doctrineFieldName}_textsort:\"{$operand}\"";
                    }
                    $searchTerms[] = '(' . implode($operands, ' OR ') . ')';
                    break;

                case 'textContainsAll':
                    foreach ($operands as &$operand) {
                        $searchTerms[] = "$fieldName:\"{$operand}\"";
                    }
                    break;

                case 'enumIn':
                    $searchTerms[] = "$fieldName:(" . implode($operands, ' OR ') . ")";
                    break;

                case 'enumNotIn':
                    $searchTerms[] = "-$fieldName:(" . implode($operands, ' OR ') . ")";
                    break;

                // Exact text match is a little different. It uses a separate field and it only works for sortable
                // fields. Because the sort field is unanalyzed, this is a case sensitive operator.
                case 'textExactMatch':
                    $searchTerms[] = "{$doctrineFieldName}_textsort:\"{$operands[0]}\"";
                    break;

                case 'textNotExactMatch':
                    $searchTerms[] = "-{$doctrineFieldName}_textsort:\"{$operands[0]}\"";
                    break;

                case 'unspecified':
                    $searchTerms[] = "(*:* AND -$fieldName:/.*/)";
                    break;

                default:
                    // Fields can define custom criteria (that wouldn't match any of the above cases)
                    if (isset($searchableFields[$doctrineFieldName]['extraCriteria'][$operator])) {
                        $callback = $searchableFields[$doctrineFieldName]['extraCriteria'][$operator]['idProvider'];

                        $ids = call_user_func_array($callback, $operands);

                        if ($ids === false) {
                            throw new Fisma_Zend_Exception("Not able to call callback ($callback)");
                        }

                        $idTerms = array();
                        $idField = $searchableFields[$doctrineFieldName]['extraCriteria'][$operator]['idField'];
                        $fieldType = $searchableFields[$idField]['type'];

                        foreach ($ids as $id) {
                            $idTerms[] = "{$idField}_{$fieldType}:$id";
                        }

                        $searchTerms[] = '(' . implode($idTerms, ' OR ') . ')';
                    } else {
                        throw new Fisma_Search_Exception("Undefined search operator: " . $criterion->getOperator());
                    }
            }
        }

        $queryString = implode($searchTerms, ' AND ');

        $noCriteria = true;
        if (empty($queryString)) {
            $queryString = "id:/.*/";
        } else {
            $noCriteria = false;
        }

        if (!empty($trimmedKeyword)) {
            $noCriteria = false;

            // Tokenize keywords and escape all tokens.
            $keywordTokens = $this->_tokenizeBasicQuery($trimmedKeyword);
            $keywordTokens = array_filter($keywordTokens);
            $keywordTokens = array_map(array($this, 'escape'), $keywordTokens);

            // Enumerate all fields so they can be included in search results
            $searchTerms = array();
            foreach ($searchableFields as $fieldName => $fieldDefinition) {
                $documentFieldName = $fieldName . '_' . $fieldDefinition['type'];

                // Add keyword terms and highlighting to all non-date/non-boolean fields
                if (!empty($trimmedKeyword) &&
                    'date' != $fieldDefinition['type'] &&
                    'datetime' != $fieldDefinition['type'] &&
                    'boolean' != $fieldDefinition['type']) {

                    // Solr can't highlight sortable integer fields
                    if ('integer' != $fieldDefinition['type'] && 'float' != $fieldDefinition['type']) {
                        $params['hl.fl'][] = $documentFieldName;
                    }

                    $searchValues = array();
                    foreach ($keywordTokens as $keywordToken) {
                        // Don't search for strings in integer fields (Solr emits an error)
                        $isNumberField = ('integer' == $fieldDefinition['type'] || 'float' == $fieldDefinition['type']);
                        $canSearch = (is_numeric($keywordToken) || !$isNumberField);

                        if ($canSearch) {
                            $searchValues[] = '"' . $keywordToken . '"';
                        }
                    }
                    if (count($searchValues) > 0) {
                        $searchTerms[] = 'text:(' . implode(" OR ", $searchValues) . ')';
                    }
                }
            }
            $keywordQueryString = implode(' OR ', $searchTerms);
        }

        if ($noCriteria) {
            $params['hl'] = 'false';
        }

        if (!empty($trimmedKeyword)) {
            $params['fq'] .= ' AND ' . $queryString;
            $query = $keywordQueryString;
        } else {
            $query = $queryString;
        }

        $params['fl'] = implode(',', $params['fl']);
        $params['hl.fl'] = implode(',', $params['hl.fl']);
        $params['query'] = $query;

        return $params;
    }

    /**
     * Search by an optional keyword and a list of specific field filters
     *
     * @param string $type Name of model index to search
     * @param string $keyword
     * @param Fisma_Search_Criteria $criteria
     * @param string $sortColumn Name of column to sort on
     * @param boolean $sortDirection True for ascending sort, false for descending
     * @param int $start The offset within the result set to begin returning documents from
     * @param int $rows The number of documents to return
     * @param bool $deleted If true, include soft-deleted records in the results
     * @return Fisma_Search_Result Rectangular array of search results
     */
    public function search($type, $keyword, Fisma_Search_Criteria $criteria, $sortColumn, $sortDirection,
                                     $start, $rows, $deleted)
    {
        $params = $this->generateQuery($type, $keyword, $criteria, $sortColumn, $sortDirection,
                                     $start, $rows, $deleted);
        $query = $params['query'];
        unset($params['query']);
        try {
            $response = $this->_client->search($query, $start, $rows, $params);
        } catch (Exception $e) {
            return new Fisma_Search_Result(0, 0, array());
        }

        return $this->_convertSolrResultToStandardResult($type, $response);
    }

    /**
     * Advanced search: search based on a list of specific field criteria
     *
     * @param string $type Name of model index to search
     * @param Fisma_Search_Criteria $criteria
     * @param string $sortColumn Name of column to sort on
     * @param boolean $sortDirection True for ascending sort, false for descending
     * @param int $start The offset within the result set to begin returning documents from
     * @param int $rows The number of documents to return
     * @param bool $deleted If true, include soft-deleted records in the results
     * @return Fisma_Search_Result Rectangular array of search results
     */
    public function searchByCriteria($type, Fisma_Search_Criteria $criteria, $sortColumn, $sortDirection,
                                     $start, $rows, $deleted)
    {
        return $this->search($type, '', $criteria, $sortColumn, $sortDirection, $start, $rows, $deleted, true);
    }

    /**
     * Convert string into array of integers.
     *
     * @param string $operand String to convert
     * @return array
     */
    private function _stringToIntArray($operand)
    {

        return preg_split('/[^\d]+/', $operand);
    }

    /**
     * Return a solr term for the operand of integerDoesNotEqual op
     *
     * @param string $fieldName Name of the solr field.
     * @param string $operand String representation of the operand.
     * @return string Term representation.
     */
    private function _integerDoesNotEqual($fieldName, $operand)
    {
        $subterms = array();
        foreach ($this->_stringToIntArray($operand) as $intValue) {
            if (!is_numeric($intValue)) {
                continue;
            }
            $subterms[] = "$fieldName:$intValue";
        }
        return '-(' . implode(' OR ', $subterms) . ')';
    }

    /**
     * Return a solr term for the operand of integerEquals op
     *
     * @param string $fieldName Name of the solr field.
     * @param string $operand String representation of the operand.
     * @return string Term representation.
     */
    private function _integerEquals($fieldName, $operand)
    {
        $subterms = array();
        foreach ($this->_stringToIntArray($operand) as $intValue) {
            if (!is_numeric($intValue)) {
                continue;
            }
            $subterms[] = "$fieldName:$intValue";
        }
        return '(' . implode(' OR ', $subterms) . ')';
    }

    /**
     * Validate that PECL extension is installed and SOLR server responds to a Solr ping request (not an ICMP)
     *
     * @return mixed Return TRUE if configuration is valid, or a string error message otherwise
     */
    public function validateConfiguration()
    {
        if (!function_exists('solr_get_version')) {
            return "PECL Solr extension is not installed";
        }

        try {
            $this->_client->ping();
        } catch (SolrClientException $e) {
            return 'Not able to reach Solr server: ' . $e->getMessage();
        }

        return true;
    }

    /**
     * Convert an array of objects into an array of indexable Solr documents
     *
     * @param array $collection
     * @return array Array of Apache_Solr_Document
     */
    private function _convertCollectionToDocumentArray($type, $collection)
    {
        $documents = array();

        foreach ($collection as $object) {
            $documents[] = $this->_convertObjectToDocument($type, $object);
        }

        return $documents;
    }

    /**
     * Convert an object (in array format) into an indexable Solr document
     *
     * The object's table must also implement Fisma_Search_Searchable so that this method can get its search metadata.
     *
     * @param string $type The class of the object
     * @param array $object
     * @return Apache_Solr_Document
     */
    private function _convertObjectToDocument($type, $object)
    {
        $document = new Apache_Solr_Document;
        $table = Doctrine::getTable($type);

        // All documents have the following three fields
        if (isset($object['id'])) {
            $document->addField('luceneDocumentId', $type . $object['id']);
            $document->addField('luceneDocumentType', $type);
            $document->addField('id', $object['id']);
        } else {
            throw new Fisma_Search_Exception("Cannot index object type ($type) because it does not have an id field.");
        }

        // Iterate over the model's columns and see which ones need to be indexed
        $searchableFields = $this->_getSearchableFields($type);

        foreach ($searchableFields as $doctrineFieldName => $searchFieldDefinition) {

            if ('luceneDocumentId' == $doctrineFieldName) {
                throw new Fisma_Search_Exception("Model columns cannot be named luceneDocumentId");
            }

            $documentFieldName = $doctrineFieldName . '_' . $searchFieldDefinition['type'];

            $rawValue = $this->_getRawValueForField($table, $object, $doctrineFieldName, $searchFieldDefinition);
            if (is_null($rawValue)) {
                continue;
            }

            $doctrineDefinition = $table->getColumnDefinition($table->getColumnName($doctrineFieldName));

            //Some fields are stored in their join table, for example, description field of system is actually
            //stored in organization table. So, it needs to get doctrine definition from its join table.
            if (isset($searchFieldDefinition['join']['model']) &&
                      $searchFieldDefinition['join']['model']) {
                $joinTable = Doctrine::getTable($searchFieldDefinition['join']['model']);
                $doctrineDefinition = $joinTable
                                      ->getColumnDefinition($joinTable->getColumnName($doctrineFieldName));
            }

            $containsHtml = isset($doctrineDefinition['extra']['purify']['html']) &&
                                  $doctrineDefinition['extra']['purify']['html'];

            //Fetch Timezone from timezone abbreviation field
            if (isset($searchFieldDefinition['timezoneAbbrField'])) {
                $searchFieldDefinition['timezone'] = $object[$searchFieldDefinition['timezoneAbbrField']];
            }
            $documentFieldValue = $this->_getValueForColumn($rawValue, $searchFieldDefinition, $containsHtml);

            $document->addField($documentFieldName, $documentFieldValue);

            if ('text' == $searchFieldDefinition['type'] && $searchFieldDefinition['sortable']) {
                // For sortable text columns, add a separate 'textsort' column (see design document)
                $document->addField($doctrineFieldName . '_textsort', $documentFieldValue);
            } elseif ('enum' == $searchFieldDefinition['type'] && $searchFieldDefinition['sortable']) {

                if (!isset($searchFieldDefinition['enumReverse'])) {
                    $searchFieldDefinition['enumReverse'] = array_flip($searchFieldDefinition['enumValues']);
                }

                $sortOrder = $searchFieldDefinition['enumReverse'][$rawValue];
                $document->addField($doctrineFieldName . '_enumsort', $sortOrder);
            }
        }

        if (
            $table->hasColumn('deleted_at')
            && !empty($object['deleted_at'])
            && $document->getField('deleted_at_datetime') === false
        ) {
            $deletedAt = $this->_convertToSolrDate($object['deleted_at']);

            $document->addField('deleted_at_datetime', $deletedAt);
        }

        return $document;
    }

    /**
     * Converts a Solr query result into the system's standard result format
     *
     * Solr does some weird stuff with object storage, so this method is a little hard to understand. var_dump'ing
     * each variable will help to sort through the structure for debugging purposes.
     *
     * @param string $type
     * @param Apache_Solr_Response $solrResult
     * @return Fisma_Search_Result
     */
    public function _convertSolrResultToStandardResult($type, Apache_Solr_Response $solrResult)
    {
        $numberFound = count($solrResult->response->docs);
        $numberReturned = $solrResult->response->numFound;

        if (isset($solrResult->highlighting)) {
            $highlighting = (array)$solrResult->highlighting;
        } else {
            $highlighting = array();
        }

        $tableData = array();

        $table = Doctrine::getTable($type);
        $searchableFields = $this->_getSearchableFields($type);

        if ($solrResult->response->docs) {
            // Construct initial table data from documents part of the response
            foreach ($solrResult->response->docs as $document) {

                $row = array();

                foreach ($document as $columnName => $columnValue) {
                    $newColumnName = $this->_removeSuffixFromColumnName($columnName);
                    $row[$newColumnName] = $columnValue;
                }

                // Convert any dates or datetimes from Solr's UTC format back to native format
                foreach ($row as $fieldName => $fieldValue) {
                    // Skip fields that are not model-specific like luceneDocumentType, luceneDocumentId, etc.
                    if (!isset($searchableFields[$fieldName])) {
                        continue;
                    }

                    $fieldDefinition = $searchableFields[$fieldName];

                    if ('date' == $fieldDefinition['type'] || 'datetime' == $fieldDefinition['type']) {
                        $date = new Zend_Date($fieldValue, Fisma_Date::FORMAT_SOLR_DATETIME_TIMEZONE);
                        $date->setTimeZone(CurrentUser::getAttribute('timezone'));

                        if ('date' == $fieldDefinition['type']) {
                            $row[$fieldName] = $date->toString(Fisma_Date::FORMAT_DATE);
                        } else {
                            $row[$fieldName] = $date->toString(Fisma_Date::FORMAT_DATETIME);
                        }
                    }
                }

                $luceneDocumentId = $row['luceneDocumentId'];

                $tableData[$luceneDocumentId] = $row;
            }
        }

        // Now merge any highlighted fields into the table data
        foreach ($highlighting as $luceneDocumentId => $row) {
            foreach ($row as $fieldName => $fieldValue) {
                $newFieldName = $this->_removeSuffixFromColumnName($fieldName);

                // Solr stores each field as an array with length 1, so we take index 0
                $tableData[$luceneDocumentId][$newFieldName] = $fieldValue[0];
            }
        }

        // Remove the luceneDocumentId from each field
        foreach ($tableData as &$document) {
            unset($document['luceneDocumentId']);
        }

        // Discard the row IDs
        $tableData = array_values($tableData);

        return new Fisma_Search_Result($numberReturned, $numberFound, $tableData);
    }

    /**
     * Remove the type suffix (e.g. _text, _date, etc.) from a columnName
     *
     * @param string $columnName
     * @return string
     */
    private function _removeSuffixFromColumnName($columnName)
    {
        $suffixPosition = strrpos($columnName, '_');

        if ($suffixPosition) {
            return substr($columnName, 0, $suffixPosition);
        } else {
            return $columnName;
        }
    }

    /**
     * Create the field value for an object based on its type and other metadata
     *
     * This includes transformations such as correctly formatting dates, times, and stripping HTML content
     *
     * @param mixed $value
     * @param array $definition The associative array of searchFieldDefinition
     * @param bool $html True if the value contains HTML
     * @return mixed
     */
    private function _getValueForColumn($rawValue, $definition, $html)
    {
        $type = $definition['type'];
        if ('text' == $type && $html) {
            $value = $this->_convertHtmlToIndexString($rawValue);
        } elseif ('integer' == $type) {
            $value = intval($rawValue);
        } elseif ('float' == $type) {
            $value = (float)$rawValue;
        } elseif ('date' == $type || 'datetime' == $type) {
            if (isset($definition['timezone'])) {
                $timezone = $definition['timezone'];
                $value = $this->_convertToSolrDate($rawValue, $timezone);
            } else {
                $value = $this->_convertToSolrDate($rawValue);
            }
        } else {
            // By default, just index the raw value
            $value = $rawValue;
        }

        return $value;
    }

    /**
     * Convert a database format date or date time (2010-01-01 12:00:00) to Solr's ISO-8601 UTC format
     *
     * @param string $date
     * @param string $timezone Optional
     * @return string
     */
    private function _convertToSolrDate($date, $timezone = null)
    {
        // Date fields need to be converted to UTC
        $currentTz = date_default_timezone_get();
        if ($timezone && $timezone != $currentTz) {
            date_default_timezone_set($timezone);
        }

        $tempDate = new Zend_Date($date, Fisma_Date::FORMAT_DATETIME);
        $tempDate->setTimezone('UTC');

        date_default_timezone_set($currentTz);
        return $tempDate->toString(Fisma_Date::FORMAT_SOLR_DATETIME) . 'Z';
    }

    /**
     * Returns query terms to limit the search results on
     *
     * @param Doctrine_Table $table
     * @param array $searchableFields
     */
    private function _getAclQueryFilter($table, $searchableFields)
    {
        $aclTerms = $this->_getAclTerms($table);

        // If there is no ACL constraint, then return an empty query term
        if (is_null($aclTerms)) {
            return '';
        }

        $ids = array();

        foreach ($aclTerms as $aclTerm) {
            $fieldName = $aclTerm['field'];
            $fieldValue = $aclTerm['value'];

            $aclFieldType = $searchableFields[$fieldName]['type'];

            $ids[] = "{$fieldName}_{$aclFieldType}:$fieldValue";
        }

        if (count($ids)) {
            return implode(' OR ', $ids);
        } else {
            // If no IDs match, then return an impossible condition
            return ('id:0');
        }
    }

    /**
     * Escape a parameter for inclusion in a Lucene query
     *
     * @see http://lucene.apache.org/java/2_4_0/queryparsersyntax.html#Escaping%20Special%20Characters
     *
     * @param string $parameter
     * @return string Escaped parameter
     */
    public function escape($parameter)
    {
        $specialChars = '+-!(){}[]^"~*?:\&|';

        return addcslashes($parameter, $specialChars);
    }

    /**
     * Get whether highlighting is enabled or not
     *
     * @return bool
     */
    public function getHighlightingEnabled()
    {
        return $this->_highlightingEnabled;
    }

    /**
     * Control highlighting behavior
     *
     * @param bool $enabled
     */
    public function setHighlightingEnabled($enabled)
    {
        $this->_highlightingEnabled = $enabled;
    }

    /**
     * Convert HTML string to a form that is ideal for text indexing
     *
     * This removes tags but ensures that the removal of tags does not result in separate words being concatenated
     * together.
     *
     * Notice that malformed HTML inputs may be mangled by this method.
     *
     * @param string $htmlString
     * @return string
     */
    protected function _convertHtmlToIndexString($html)
    {
        // Remove line feeds. They are replaced with spaces to prevent the next word on the next line from adjoining
        // the last word on the previous line, but consecutive spaces are culled out later.
        $html = str_replace(chr(10), ' ', $html);
        $html = str_replace(chr(13), ' ', $html);

        // Remove tags, but be careful not to concatenate together two words that were split by a tag
        $html = preg_replace('/(\w)<.*?>(\W)/', '$1$2', $html);
        $html = preg_replace('/(\W)<.*?>(\w)/', '$1$2', $html);
        $html = preg_replace('/<.*?>/', ' ', $html);

        // Remove excess whitespace
        $html = preg_replace('/[ ]*(?>\r\n|\n|\x0b|\f|\r|\x85)[ ]*/', "\n", $html);
        $html = preg_replace('/^\s+/', '', $html);
        $html = preg_replace('/\s+$/', '', $html);
        $html = preg_replace('/ +/', ' ', $html);

        return $html;
    }

    /**
     * Return an array of ACL terms
     *
     * e.g. the following return value indicates a user has access to any document where the 'id' field is 1 or 2
     *
     * array(
     *     array('field' => 'id', 'value' => 1),
     *     array('field' => 'id', 'value' => 2),
     * )
     *
     * @param Doctrine_Table $table
     * @return mixed Array of acl terms or null if ACL does not apply
     */
    protected function _getAclTerms($table)
    {
        $aclFields = $table->getAclFields();

        // If no ACL fields, then don't return any ACL terms
        if (count($aclFields) == 0) {
            return null;
        }

        $ids = array();

        foreach ($aclFields as $aclFieldName => $callback) {
            $aclIds = call_user_func($callback);

            if ($aclIds === false) {
                $message = "Could not call ACL ID provider ($callback) for ACL field ($name).";

                throw new Fisma_Zend_Exception($message);
            }

            foreach ($aclIds as &$aclId) {
                $aclId = $this->escape($aclId);
            }
            $ids[] = array(
                'field' => $aclFieldName,
                'value' => "(" . implode(' OR ', $aclIds) . ")"
            );
        }

        return $ids;
    }

    /**
     * Returns the raw value for a field based on the search metadata definition.
     *
     * This has the ability to load data from a related model as well.
     *
     * @param Doctrine_Table $table
     * @param array $object
     * @param string $doctrineFieldName Name of field given by Doctrine
     * @param array $searchFieldDefinition
     * return mixed The raw value of the field
     */
    protected function _getRawValueForField($table, $object, $doctrineFieldName, $searchFieldDefinition)
    {
        $rawValue = null;

        if (!isset($searchFieldDefinition['join'])) {
            $rawValue = $object[$table->getFieldName($doctrineFieldName)];
        } else {
            // Handle nested relations
            $relationParts = explode('.', $searchFieldDefinition['join']['relation']);

            $relatedObject = $object;

            foreach ($relationParts as $relationPart) {
                $relatedObject = $relatedObject[$relationPart];
            }

            $rawValue = $relatedObject[$searchFieldDefinition['join']['field']];
        }

        return $rawValue;
    }

    /**
     * Return searchable fields for a particular model
     *
     * @param string $type Name of model
     */
    protected function _getSearchableFields($type)
    {
        $table = Doctrine::getTable($type);

        if (!($table instanceof Fisma_Search_Searchable)) {
            $message = 'Objects which are to be indexed must have a table that implements'
                     . ' the Fisma_Search_Searchable interface';

            throw new Fisma_Zend_Exception($message);
        }

        return $table->getSearchableFields();
    }

    /**
     * Tokenize a basic search query and return an array of tokens
     *
     * @param string $basicQuery
     * @return array
     */
    public function _tokenizeBasicQuery($basicQuery)
    {
        return preg_split("/[\s,]+/", $basicQuery);
    }
}
