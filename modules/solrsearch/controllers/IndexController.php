<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    View, SolrSearch
 * @author      Julian Heise <heise@zib.de>
 * @copyright   Copyright (c) 2008-2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id:$
 */

/**
 * Controller for Solr search module
 */
class Solrsearch_IndexController extends Controller_Action {

    const SIMPLE_SEARCH = 'simple';
    const ADVANCED_SEARCH = 'advanced';
    const AUTHOR_SEARCH = 'authorsearch';
    
    private $searcher;
    private $log;
    private $query;
    private $numOfHits;
    private $searchtype;
    private $resultList;

    public function  init() {
        $this->log = Zend_Registry::get('Zend_Log');
    }

    public function indexAction() {
        $this->view->title = $this->view->translate('solrsearch_title_simple');
    }

    public function advancedAction() {
        $this->view->title = $this->view->translate('solrsearch_title_advanced');
    }

    public function nohitsAction() {
        $this->view->title = $this->view->translate('solrsearch_title_nohits');
    }

    public function resultsAction() {
        $this->view->title = $this->view->translate('solrsearch_title_results');
    }

    public function invalidsearchtermAction() {
        $this->view->title = $this->view->translate('solrsearch_title_invalidsearchterm');
        $params = $this->_request->isPost() ? $this->_request->getPost() : $this->_request->getParams();
        $searchtype = array_key_exists('searchtype', $params) ? $params['searchtype'] : Solrsearch_IndexController::SIMPLE_SEARCH;
        $this->view->__set('searchType', $searchtype);
    }

    public function searchdispatchAction() {
        $this->log->debug('Received new search request. Redirecting to search action.');
        $requestData = null;
        $url = '';

        if ($this->_request->isPost() === true)
            $requestData = $this->_request->getPost();
        else
            $requestData = $this->_request->getParams();

        $searchtype = $requestData['searchtype'];
        if($searchtype === Solrsearch_IndexController::SIMPLE_SEARCH) {
            $url = $this->createSimpleSearchUrl($requestData);            
            if(!$this->isSimpleSearchRequestValid($requestData)) {
                $url = $this->view->url(array('module'=>'solrsearch','controller'=>'index','action'=>'invalidsearchterm','searchtype'=>Solrsearch_IndexController::SIMPLE_SEARCH), null, true);
            }
        } 
        else if($searchtype === Solrsearch_IndexController::ADVANCED_SEARCH || $searchtype === Solrsearch_IndexController::AUTHOR_SEARCH) {
            $url = $this->createAdvancedSearchUrl($requestData);            
            if(!$this->isAdvancedSearchRequestValid($requestData)) {
                $url = $this->view->url(array('module'=>'solrsearch','controller'=>'index','action'=>'invalidsearchterm','searchtype'=>$searchtype), null, true);
            }
        }

        $this->log->debug("URL is: " . $url);
        $this->redirectTo($url);
    }

    private function isSimpleSearchRequestValid($data) {
        if ($this->_getFieldValue($data, 'query') === '') {
            return false;
        }
        return true;
    }

    private function isAdvancedSearchRequestValid($data) {
        foreach (array('author', 'title', 'referee', 'abstract', 'fulltext',  'year') as $fieldname) {
            if ($this->_getFieldValue($data, $fieldname) !== '') {
                return true;
            }
        }
        return false;
    }

    private function createSimpleSearchUrl($data) {
        $params = array(
                'searchtype'=> $this->_getFieldValue($data, 'searchtype', Solrsearch_IndexController::SIMPLE_SEARCH),
                'start'=> $this->_getFieldValue($data, 'start', '0'),
                'rows'=> $this->_getFieldValue($data, 'rows', '10'),
                'query'=> $this->_getFieldValue($data, 'query', '*:*'),
                'sortfield'=> $this->_getFieldValue($data, 'sortfield', 'score'),
                'sortorder'=> $this->_getFieldValue($data, 'sortorder', 'desc')
            );
        return $this->view->url(Solrsearch_IndexController::createSearchUrlArray($params), null, true);
    }

    private function createAdvancedSearchUrl($data) {
        $params = array (
            'searchtype'=> $this->_getFieldValue($data, 'searchtype', Solrsearch_IndexController::ADVANCED_SEARCH),
            'start'=> $this->_getFieldValue($data, 'start', '0'),
            'rows'=> $this->_getFieldValue($data, 'rows', '10'),
            'sortfield'=> $this->_getFieldValue($data, 'sortfield', 'score'),
            'sortorder'=> $this->_getFieldValue($data, 'sortorder', 'desc')
        );
        $urlArray = Solrsearch_IndexController::createSearchUrlArray($params);

        foreach (array('author', 'title', 'abstract', 'fulltext', 'year', 'referee') as $fieldname) {
            if($this->_getFieldValue($data, $fieldname) !== '') {
                $urlArray[$fieldname] = $data[$fieldname];
                $urlArray[$fieldname . 'modifier'] = $this->_getFieldValue($data, $fieldname . 'modifier', Opus_SolrSearch_Query::SEARCH_MODIFIER_CONTAINS_ALL);
            }
        }

        return $this->view->url($urlArray, null, true);
    }

    public function searchAction() {
        $data = null;
        if ($this->_request->isPost() === true) {
            $this->log->debug("Request is post. Extracting data.");
            $data = $this->_request->getPost();
        } else {
            $this->log->debug("Request is non post. Trying to extract data. Request should be post normally.");
            $data = $this->_request->getParams();
        }

        $this->query = $this->buildQuery($data);
        $this->performSearch();
        $this->setViewValues();
        $this->setViewFacets($data);

        if($this->numOfHits === 0 || $this->query->getStart() >= $this->numOfHits) {
            $this->render('nohits');
            return;
        }
        $this->render('results');            
    }

    private function performSearch() {
        $this->log->debug('performing search');
        $this->searcher = new Opus_SolrSearch_Searcher();
        $this->resultList = $this->searcher->search($this->query);
        $this->numOfHits = $this->resultList->getNumberOfHits();
        $this->log->debug("resultlist: $this->resultList");
    }

    private function setViewValues() {
        $this->view->results = $this->resultList->getResults();
        $this->view->searchType = $this->searchtype;
        $this->view->numOfHits = $this->numOfHits;
        $this->view->queryTime = $this->resultList->getQueryTime();
        $this->view->start = $this->query->getStart();
        $this->view->numOfPages = (int) ($this->numOfHits / $this->query->getRows()) + 1;
        $this->view->rows = $this->query->getRows();
        $this->view->authorSearch = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>Solrsearch_IndexController::ADVANCED_SEARCH));

        if($this->searchtype === Solrsearch_IndexController::SIMPLE_SEARCH) {
            $this->view->q = $this->query->getCatchAll();
            $this->view->nextPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'query'=>$this->query->getCatchAll(),'start'=>(int)($this->query->getStart()) + (int)($this->query->getRows()),'rows'=>$this->query->getRows()));
            $this->view->prevPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'query'=>$this->query->getCatchAll(),'start'=>(int)($this->query->getStart()) - (int)($this->query->getRows()),'rows'=>$this->query->getRows()));
            $this->view->lastPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'query'=>$this->query->getCatchAll(),'start'=>(int)($this->numOfHits / $this->query->getRows()) * $this->query->getRows(),'rows'=>$this->query->getRows()));
            $this->view->firstPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'query'=>$this->query->getCatchAll(),'start'=>'0','rows'=>$this->query->getRows()));
        } else if($this->searchtype === Solrsearch_IndexController::ADVANCED_SEARCH || $this->searchtype === Solrsearch_IndexController::AUTHOR_SEARCH) {
            $this->view->nextPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'start'=>(int)($this->query->getStart()) + (int)($this->query->getRows()),'rows'=>$this->query->getRows()));
            $this->view->prevPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'start'=>(int)($this->query->getStart()) - (int)($this->query->getRows()),'rows'=>$this->query->getRows()));
            $this->view->lastPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'start'=>(int)($this->numOfHits / $this->query->getRows()) * $this->query->getRows(),'rows'=>$this->query->getRows()));
            $this->view->firstPage = Solrsearch_IndexController::createSearchUrlArray(array('searchtype'=>$this->searchtype,'start'=>'0','rows'=>$this->query->getRows()));
            $this->view->authorQuery = $this->query->getField('author');
            $this->view->titleQuery = $this->query->getField('title');
            $this->view->abstractQuery = $this->query->getField('abstract');
            $this->view->fulltextQuery = $this->query->getField('fulltext');
            $this->view->yearQuery = $this->query->getfield('year');
            $this->view->authorQueryModifier = $this->query->getModifier('author');
            $this->view->titleQueryModifier = $this->query->getModifier('title');
            $this->view->abstractQueryModifier = $this->query->getModifier('abstract');
            $this->view->yearQueryModifier = $this->query->getModifier('year');
            $this->view->refereeQuery = $this->query->getField('referee');
            $this->view->refereeQueryModifier = $this->query->getModifier('referee');
        }
    }

    private function setViewFacets($data) {
        $facets = $this->resultList->getFacets();
        $facetArray = array();
        $selectedFacets = array();

        foreach($facets as $key=>$facet) {
            $this->log->debug("found $key facet in search results");

            $facetIsActive = $this->_getFieldValue($data, $key.'fq');
            if($facetIsActive !== '') {
                $selectedFacets[$key] = $data[$key.'fq'];
            }

            if(count($facets[$key]) > 1 || $facetIsActive !== '') {
                $facetArray[$key] = $facet;
            }
        }
        
        $this->view->__set('facets', $facetArray);
        $this->view->__set('selectedFacets', $selectedFacets);
    }

    private function buildQuery($data) {

        if (is_null($data)) 
            throw new Application_Exception("Unable to read request data. Search cannot be performed.");

        if (!array_key_exists('searchtype', $data)) 
            throw new Application_Exception("Unable to create query for unspecified searchtype");
        
        $data = $this->validateParameterValues($data);

        $this->searchtype = $data['searchtype'];
        if ($this->searchtype === Solrsearch_IndexController::SIMPLE_SEARCH)
            return $this->createSimpleSearchQuery($data);
        if ($this->searchtype === Solrsearch_IndexController::ADVANCED_SEARCH || $this->searchtype === Solrsearch_IndexController::AUTHOR_SEARCH)
            return $this->createAdvancedSearchQuery($data);

        throw new Application_Exception("Unable to create query for searchtype " . $this->searchtype);
    }

    private function validateParameterValues($data) {
        if(isset($data['rows']) && (int)$data['rows'] > 100) {
            $this->log->warn("Values greater than 100 are currently not allowed for the rows paramter.");
            $data['rows'] = '100';
        }
        if(isset($data['rows']) && (int)$data['rows'] <= 0) {
            $this->log->warn("row parameter is smaller than 1: adjust it to 1 ");
            $data['rows'] = '1';
        }
        if (isset($data['start']) && (int)$data['start'] < 0) {
            $this->log->warn("a negative start parameter is ignored");
            $data['start'] = '0';
        }
        if($data['searchtype'] === Solrsearch_IndexController::ADVANCED_SEARCH || $data['searchtype'] === Solrsearch_IndexController::AUTHOR_SEARCH) {
            if (isset($data['author'])) {
                $data['author'] = str_replace(array(',', ';'), '', $data['author']);
            }
        }
        return $data;
    }

    private function createSimpleSearchQuery($data) {

        $this->log->debug("Constructing query for simple search.");

        $start = array_key_exists('start', $data) ? $data['start'] : '0';
        $rows = array_key_exists('rows', $data) ? $data['rows'] : '10';
        $catchAll = array_key_exists('query', $data) ? $data['query'] : '*:*';
        $sortfield = array_key_exists('sortfield', $data) ? $data['sortfield'] : 'score';
        $sortorder = array_key_exists('sortorder', $data) ? $data['sortorder'] : 'desc';

        $query = new Opus_SolrSearch_Query(Opus_SolrSearch_Query::SIMPLE);
        $query->setStart($start);
        $query->setCatchAll($catchAll);
        $query->setRows($rows);
        $query->setSortField($sortfield);
        $query->setSortOrder($sortorder);

        $this->addFiltersToQuery($data, $query);
        $this->log->debug("Query $query complete");
        return $query;
    }

    private function addFiltersToQuery($data, $query) {
        $config = Zend_Registry::get("Zend_Config");

        if(!isset($config->searchengine->solr->facets)){
            $this->log->debug("key searchengine.solr.facets is not present in config. skipping filter queries");
            return;
        }
        
        $facets = $config->searchengine->solr->facets;
        $this->log->debug("searchengine.solr.facets is set to " . $facets);
        $facetsArray = explode(",", $facets);

        foreach($facetsArray as $facet) {
            $facet = trim($facet);
            $facetKey = $facet."fq";
            if(array_key_exists($facetKey, $data)) {
                $this->log->debug("request has facet key: ".$facetKey." value is: ".$data[$facetKey]." corresponding facet is: ".$facet);
                if($data[$facetKey] === '')
                    continue;
                $query->addFilterQuery($facet.":".$data[$facetKey]);
            }
        }
    }

    private function createAdvancedSearchQuery($data) {
        $this->log->debug("constructing query for advanced search");

        $query = new Opus_SolrSearch_Query(Opus_SolrSearch_Query::ADVANCED);
        $query->setStart($this->_getFieldValue($data, 'start', '0'));
        $query->setRows($this->_getFieldValue($data, 'rows', '10'));
        $query->setSortField($this->_getFieldValue($data, 'sortfield', 'score'));
        $query->setSortOrder($this->_getFieldValue($data, 'sortorder', 'desc'));

        foreach (array('author', 'title', 'referee', 'abstract', 'fulltext', 'year' ) as $fieldname) {
            $fieldvalue = $this->_getFieldValue($data, $fieldname);
            if (!empty($fieldvalue)) {
                $fieldmodifier = $this->_getFieldValue($data, $fieldname . 'modifier', Opus_SolrSearch_Query::SEARCH_MODIFIER_CONTAINS_ALL);
                $query->setField($fieldname, $fieldvalue, $fieldmodifier);
            }
        }

        $this->addFiltersToQuery($data, $query);
        $this->log->debug("Query $query complete");
        return $query;
    }

    private function _getFieldValue($data, $fieldname, $default = '') {
        if (array_key_exists($fieldname, $data)) {
            $this->log->debug("field $fieldname");
            return $data[$fieldname];
        }
        return $default;
    }

    /**
     * Creates an URl to execute a search. The URL will be mapped to: module=solrsearch,
     * controller=solrsearch, action=search, plus given parameters
     * @param array $params parameters for searching
     */
    public static function createSearchUrlArray($params = array()) {
        $url = array('module'=>'solrsearch','controller'=>'index','action'=>'search');
        foreach($params as $key=>$value)
            $url[$key]=$value;
        return $url;
    }
}
?>
