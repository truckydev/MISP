<?php
App::uses('AppController', 'Controller');

class SearchController extends AppController {

    public $components = array('Security' ,'RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Security->csrfUseOnce = false;
        $this->Security->validatePost = true;
        $this->Auth->allow('search');
    }

    /**
     * Returns the level of depth of an array
     * @param  array  $array
     * @param  integer $level : do not use, just used for recursivity
     * @return int : depth
     */
    private function __arrayDepth($array, $level = 0) {
        if (!is_array($array)) {
            return 0;
        }
        $current = current($array);
        $level++;
        if ( !is_array($current)) {
            return $level;
        }
        return $this->__arrayDepth($current, $level);
    }


    /**
     * generic funtion to format result from attributes query
     * @param array $results reslut from search
     * @return array formated array form api output
     */
    private function formatResultForApi($rawResults) {
        // return $rawResults;
        $results = array();
        if (!empty($rawResults)) {
            foreach ($rawResults as $k => $v) {
                // return array_keys($v);
                if (isset($rawResults[$k]['AttributeTag'])) {
                    foreach($rawResults[$k]['AttributeTag'] as $tk => $tag) {
                        $rawResults[$k]['Attribute']['Tag'][] = $tag['Tag'];
                    }
                }
                unset($rawResults[$k]['Attribute']['value1']);
                unset($rawResults[$k]['Attribute']['value2']);

                // format result for attributes like v1 :)
                $results['Attribute'][] = $rawResults[$k]['Attribute'];
            }
            return $results;
        }
        return array();
    }
    /**
     * return data from the query 
     * @param request->data $rawData
     * @return array return the query.
     */
    private function __getQueryData($rawData) {
        if (!isset($rawData)) {
            throw new BadRequestException('Your request seems to be misspelled.'); 
        }
        if(isset($rawData['request'])) {
            $rawData['query'] = $rawData['request'];
        }
        if(isset($rawData['query'])) {
            if (count($rawData['query']) > 0 ){
                return $rawData['query'];
            } else {
                throw new BadRequestException('You have an empty Query'); 
            }
            
        }
        return False;
    }

    /**
     * scope define in route params
     * @return String (attributes|events) return scope to search in
     * 
     */
    private function __getScopeData($rawData) {
        $scope = "attributes";
        if (isset($rawData[0])) {
            if ($rawData[0] === "events"){
                return $rawData[0];
            }
        }
        return $scope;
    }

    /**
     * @return bool if true, make this query as async
     * @todo 
     */
    private function __getAsyncData($rawData) {
        $async = false;
        if (isset($rawData['async'])) {
            if ($rawData['async'] === True){
                $async = True;
            }
        }
        return $async;
    }

    /**
     * TODO, to implement in the recursive query
     * check if simple key is like scope.type
     * 
     * @useBy __checkCondition()
     * @param array $key like {$key : values} only & element
     * @return composite key with attributes as default
     */
    private function __checkCompositeKey($key) {
        $keyKey =  explode(".", $key);
        if (count($keyKey) === 1){
            // return "attributes." as default subKey
            return "attributes." . strtolower($keyKey[0]);
        } else {
            // maybe user put more than 2 but we ignore them
            $acceptedScopes = array("attributes", "events");
            if ( in_array( $keyKey[0], $acceptedScopes, true) ) {
                return $keyKey[0] .".". $keyKey[1];
            } else {
                return "attributes." . $keyKey[1];
            }
        }
        return FALSE;
    }

    /**
     * build condition from simple value like : 
     *  {"type" : "as"}
     *  {"type" : ["as", "campaign-name"]}
     */
    private function getUniqCondition($k, $v) {
        $uniqQuery = array();
        // build valid scope like attribute.type
        $k = $this->__checkCompositeKey($k);
        
        //get parameter key
        $parameterKey =  explode(".", $k)[1];

        /** TODO add different condition here
         *      Event:     array('value', 'type', 'category', 'org', 'tags', 'from', 'to', 'last', 'eventid', 'withAttachments', 'uuid', 'publish_timestamp', 'timestamp', 'enforceWarninglist', 'searchall', 'metadata', 'published')
         *      Attribute: array('value', 'type', 'category', 'org', 'tags', 'from', 'to', 'last', 'eventid', 'withAttachments', 'uuid', 'publish_timestamp', 'timestamp', 'enforceWarninglist', 'to_ids', 'deleted')
         */
        $parameters = array('type'); 

        if (in_array($parameterKey, $parameters)){

            // check if type is valid
            $checkFunction = "__isValid" . $parameterKey;
            if(!method_exists($this, $checkFunction)) {
                $message = "Something wrong : Check function for ". $parameterKey ." is not exist";
                throw new BadRequestException($message);
            }
            $this->$checkFunction(explode(".", $k)[0], $v);

            // TODO add strict parameter is !strict : add %value%
            $buildConditionFunction = "__buidCondition" . $parameterKey;
            if(!method_exists($this, $buildConditionFunction)) {
                $message = "Something wrong : query construction function for ". $parameterKey ." is not exist";
                throw new BadRequestException($message);
            }
            if (is_array($v)){
                foreach ($v as  $i) {
                    $uniqQuery[] = $this->$buildConditionFunction($k, $i);
                }
            } else {
                $uniqQuery = $this->$buildConditionFunction($k, $v);
            }
        } else {
            // for debug pass
            // $message =  $parameterKey . " is not a valid field for search"; 
            // throw new BadRequestException($message);                    
        }

        return $uniqQuery;
    }




    /**
     * add first lvl of all $k:$v in and array as restsearchv1
     * 
     * @param array $query
     * @return array
     * @todo limit depth to 5 
     *      $simpleFalse = array('value' , 'type', 'category', 'org', 'tags', 'from', 
     *      'to', 'last', 'eventid', 'withAttachments', 'uuid', 'publish_timestamp', 
     *      'timestamp', 'enforceWarninglist', 'to_ids', 'deleted');
     */
    private function __checkCondition($query, $condition="AND") {
        $branch = array();
        static $max_loop = 5;

        foreach ($query as $k => $v) {
            // $branch["condition"][] = $condition;
            // $branch["k"][]=$k;
            // $branch["ktype"][]=gettype($k);
            // $branch["v"][]=$v;
            // $branch["vtype"][]=gettype($v);
            /**
             * Récursive génération for and/or condition
             */
            if (in_array(strtolower($k), array("and", "or") )) {
                $max_loop--;
                if ($max_loop === 0) {
                    $message = "Your query is too complex and exceeds a depth of 5";
                    throw new BadRequestException($message); 
                }
                if ($this->__arrayDepth($v) > 2) {
                    foreach ($v as $subquery) {
                        $branch[strtoupper($k)] = $this->__checkCondition($subquery, $k);
                    }
                } else {
                    $branch[strtoupper($k)] = $this->__checkCondition($v, $k);
                }
            } else {
                // $branch["k"][]=$k;
                // $branch["ktype"][]=gettype($k);
                // $branch["v"][] = $v;
                // $branch["vtype"][] = gettype($v);
                // $branch["query"] = $query;
                /**
                 * Formate search parameter
                 * @check if input like : 
                 *      {"event_id" : "839", "value" : "ResultForAsType", "type":"AS"}
                 *      [{"event_id" : "839"},{"value" : "ResultForAsType"}]
                 */
                // if (is_array($v)) {
                //     foreach($v as $vk => $vv) {
                //         // $branch["vk"][] = $vk;
                //         $branch[] = array($this->__checkCompositeKey($vk) => $vv);
                //     }
                //     // 
                // } else {
                //     $branch[] = array($this->__checkCompositeKey($k) => $v);
                // }
                // foreach ()

                // return $branch;
                // v0.1
                return $query;
                /**
                 * TODO 
                 *  - call function for eatch type to check validity
                 *  - foreach, check if scope well define
                 *  - 
                 */


                // return $query;
                // $branch["k"][]=$k;
                // $branch["ktype"][]=gettype($k);
                // $branch["vDepth"][] = $this->__arrayDepth($v);
                // $branch["max_loop"][] = $max_loop;
                // $branch["Query"][] = $query;
                // $subQuery = array();
                // if (is_integer($k)) {
                    // $v is a list
                    
                    // foreach($v as $vkey => $vValue) {
                        
                    //     $branch["vkey"][] = $vkey;
                    //     $branch["vkeytype"][] = gettype($vkey);
                    //     $branch["vValue"][] = $vValue;
                    //     $branch["vValuetype"][] = gettype($vValue);
                        // $branch[$k]['vkey'][] = $vkey;
                        // $branch[$k]['vValue'][] = $vValue;
                        // if (is_array($vValue)) {
                        //     foreach ($vValue as $i) {
                        //         $subQuery[] = $this->getUniqCondition($vkey, $i);
                        //     }
                        // } else {
                        //     $subQuery[] = $this->getUniqCondition($vkey, $vValue);
                        // // $branch[$condition] = $this->getUniqCondition($vkey, $vValue);
                        // }
                        // $subQuery[] = $this->getUniqCondition($vkey, $vValue);
                    // }
                    // $branch["subQuery"][] = $subQuery;

                    // if ($max_loop !== 5) {
                    //     $branch[$condition][] = $subQuery;
                    // } else {
                        // $branch = $subQuery;
                    // }

                // } else {
                    // $branch["k"][]=$k;
                    // $branch["ktype"][]=gettype($k);
                    // $subQuery = $this->getUniqCondition($k, $v);
                    // $branch[$condition][] = $this->getUniqCondition($k, $v);
                // }
                // $branch[] = $subQuery;
            }

            // $branch[]




        }
        return $branch;
    }
    

    /**
     * @param array param from $this->passedArgs
     * @return array return specifique param for pagination (limit, offset)
     * @todo 
     */
    private function __getParamsData($rawData) {
        $paramArray = array('limit', 'offset');
        foreach ($paramArray as $p) {
            if (isset($rawData[$p]) && is_numeric($rawData[$p]) ) {
                ${$p} = $rawData[$p];
            } else {
                ${$p} = 0;
            }
        }
        // if limit === 0 there is no result to display :(
        // we just return false
        if ($limit === 0){
            return false;
        }
        return array('limit' => $limit, 'offset'=> $offset);
    }

    /**
     * check if key.value is in type definition
     * @param String @key
     * @return True|False 
     */
    private function __isValidtype($scope, $attributeTypes) {

        if ($scope !== "attributes"){
            $message = "Type can only be applied at the level of attrbut (you enter : " . $scope . ")";
            throw new BadRequestException($message);
        }
        if (is_array($attributeTypes)){
            foreach ($attributeTypes as $aT) {
                if (substr($aT, 0, 1) == '!') {
                    $aT = substr($aT, 1);
                }
                if ( !array_key_exists( $aT, $this->Attribute->typeDefinitions) ) {
                    $message = $aT . " value is not in type definition";
                    throw new BadRequestException($message);
                }
            }
        } else {
            if (substr($attributeTypes, 0, 1) == '!') { 
                $attributeTypes = substr($attributeTypes, 1);
            }
            if (!array_key_exists( $attributeTypes, $this->Attribute->typeDefinitions)){
                $message = $attributeTypes . " value is not in type definition";
                throw new BadRequestException($message);
            }
        }        
        return TRUE;
    }

    /**
     * Build condition for type
     * I use Attribute->setSimpleConditions as model
     * for type, wee use strict value
     * TODO : 
     *      add strict condition
     * @param string $k parameter scope.key
     * @param string $v parameter value
     * @return array  query builder
     */
    private function __buidConditiontype(&$k, &$v){
        $queryScope = 'Event';
        if (explode(".", $k)[0] === 'attributes') {
            $queryScope = 'Attribute';
        }
        if (substr($v, 0, 1) == '!') {
            return array(
                $queryScope .'.'. explode(".", $k)[1] . ' NOT LIKE ' => substr($v, 1));
        } else {
            return array($queryScope .'.'. explode(".", $k)[1] . ' LIKE ' => $v);
        }
    }



    /** 
     * ****** SAMPLE 1
     * $rawData = null
     * return event if $scope == events
     * ****** SAMPLE 2
     * $rawdata = attributes.tag
     * if scope == events
     * return :
     *      event
     *        \_attr
     *          \_tag
     * ****** SAMPLE 3
     * $rawdata = [ events.tag, attributes.tag, attributes.sighting ]
     * if scope == attributes
     * return :
     *      attr
     *        \_tag
     *        \_sighting
     * 
     * @param null|emplty $rawData 
     *  if not set or empty [] return scope
     *  else add expand to scope
     * @return array|false 
     *  if false : scope
     *  else retrun scope and extra option
     * @todo 
     */
    private function __getExpandData($rawData) {
        if(isset($rawData['expand'])) {
            return $rawData['expand'];
        }
        return False;
    }

    /**
     * V2 search api
     * scope is define by route.php
     * "scope":"(events)|(attributes)",
     * access : POST
     * @param version|String $version : verison to use. Here is v2.
     * Check test_searchv2 for sample query
     */
    public function search($version) {
        /////// load util modele
        $this->loadModel('Attribute');        
        ///////
        $debug = array();
        // default scope is "events"
        // $scope = $this->__getScopeData($this->passedArgs);
        // default async is false
        $async = $this->__getAsyncData($this->request->data);
        // get expand extra option
        $expand = $this->__getExpandData($this->request->data);
        // get specifique param 
        $params = $this->__getParamsData($this->passedArgs);
        // get array in query key
        // $debug['query_raw'] = $this->__getQueryData($this->request->data);
        // $query = $this->__checkCondition(
        //     $this->__getQueryData($this->request->data)
        // );
        // $debug['query'] = $query;
        

        // $params = array(
        //     'conditions' => $query,
        //     'fields' => array('Attribute.*', 'Event.org_id', 'Event.distribution'),
        // );
        // todo add strict arg
        
        // $debug['user_restriction'] = $this->Attribute->buildConditions($this->Auth->user());
        // $params['conditions']['AND'][] = $this->Attribute->buildConditions($this->Auth->user());
        // $debug['params'] = $params;
        // $results = $this->Attribute->find('all', $params);
        // $results = $this->Attribute->fetchAttributes($this->Auth->user(), $params);
        // $results = $this->formatResultForApi($results);
        // $debug['response'] = $results;



























        // $debug['data'] = $this->request->data;
        
        // $debug['async'] = $async;
        // $debug['expand'] = $expand;
        // $debug['isJsonHeader'] = $this->response->type();
        // $debug['param'] = $params;
        // $debug['params'] = $this->params;
        // $debug['args'] = func_get_args();
        // $debug['args'] = $this->passedArgs;
        // $debug['version'] = $version;
        // $debug['user'] = $this->Auth->user();
        return $this->RestResponse->viewData($debug, $this->response->type());
    }
}