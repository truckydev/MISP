<?php
App::uses('AppController', 'Controller');

class SearchController extends AppController {

    public $components = array('Security' ,'RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Security->csrfUseOnce = false;
        $this->Security->validatePost = true;
    }

    /**
     * return data from the query
     * @param request->data $rawData
     * @return array return the query.
     */
    private function __getQueryData($rawData) {
        if(isset($rawData['query'])) {
            return $rawData['query'];
        }
        return False;
    }

    /**
     * @return String (attributes|events) return scope to search in
     * 
     */
    private function __getScopeData($rawData) {
        $scope = "events";
        if (isset($rawData['scope'])) {
            if ($rawData['scope'] === "attributes"){
                $scope = "attributes";
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
     * check if simple key is like scope.type
     * @param object $obj like {$key : values}
     * @return composite key with events as default
     */
    private function __checkCompositeKey($obj) {
        if ( count(explode(".", key($obj))) === 1 ) {
            // return "events." as subKey
            return array("events." . strtolower( key($obj) ) => $obj[ key($obj) ] );
        } else {
            // check if subKey is in events or attributes
            $acceptedScopes = array("attributes", "events");
            $keyscope = strtolower( explode(".", key($obj))[0] );
            if ( in_array( $keyscope, $acceptedScopes, true) ) {
                return array( strtolower( key($obj) ) => $obj[key($obj)] );
            } else {
                $keyValue = strtolower( explode(".", key($obj))[1] );
                return array("events." . $keyValue => $obj[key($obj)] );
            }
        }
    }

    /**
     * check the first level of array
     * if count != 1 it's bad ^^
     * array[1] key  will be AND or OR or 
     * 
     * @param array
     * @return bool if true, make this query as async
     * @todo 
     */
    private function __checkLevelCount($query) {
        if (count($query) !== 1) {
            $message = "error : First level of your query key have more than 1 object."
                . " Try with only [{key:value}] like [{ attributes.tag : type:OSINT }]"
                . " or [{OR:[{key:value},{key:value}]";
            throw new BadRequestException($message);
        } else {
            if (in_array(strtolower( key($query[0]) ), array("and", "or") )) {
                return $this->__checkCompositeKey($query[0]);
            } else {
                return array( strtolower( key($query[0]) ) => $query[0][ key($query[0]) ]);
            }
        }
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
    private function __isValidtype($attributeTypes) {
        foreach ($attributeTypes as $aT) {
            if ( in_array( $aT, $this->Attribute->typeDefinitions, true) ) {
                throw new BadRequestException("Type in value is not a valide type");
            }
        }
        return TRUE;
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
     * access : POST
     * @param version|String $version : verison to use. Here is v2.
     * Sample json : 
     *  {
     *      "scope":"events|attributes",
     *      "async":"true|false",
     *      "expand":[
     *          "events.Tag",
     *          "events.Galaxy", 
     *          "events.RelatedEvent",
     *          "attributes.tags",
     *          "attributes.sighting",
     *          "Object" // for now, nothing
     *      ]
     *      "query" : [
     * *************** SAMPLE 1
     *          "scope.key" : "value"
     * *************** SAMPLE 2
     *          "OR" : [
     *              "scope.key" : "value",
     *              "OR" : [
     *                  "AND" : ["scope.key":"value", "scope.key":"value"],
     *                  "scope.key":"value",
     *                  "AND" : ["scope.key":"value", "scope.key":"value", "scope.key":"value"]
     *              ]
     *          ]
     *      ]
     * }
     */
    public function search($version) {
        $debug = array();
        // only v2 for now
        if (isset($version) &&  $version != "v2") {
            throw new UnauthorizedException('The version of the search with API is not correct.');
        }
        // default scope is "events"
        $scope = $this->__getScopeData($this->request->data);
        // default async is false
        $async = $this->__getAsyncData($this->request->data);
        // get expand extra option
        $expand = $this->__getExpandData($this->request->data);
        // get specifique param 
        $params = $this->__getParamsData($this->passedArgs);
        // get array in query key
        $query = $this->__checkLevelCount(
            $this->__getQueryData($this->request->data)
        );
        
        // $debug['query.key.scope'] = explode(".", key($query))[0];
        // $debug['query.key.value'] = explode(".", key($query))[1];
        // $debug['query.value'] = $query[key($query)];
        // key  is not and/or 
        $this->loadModel('Attribute');
        if( !in_array(key($query), array("and", "or")) ) {
            // check for valide type
            $parameters = array('type');
		    foreach ($parameters as $param) {
                if( explode(".", key($query))[1] === $param ) {
                    // use dynamique valid function from param
                    $checkFunction = "__isValid" . $param;
                    if(function_exists($checkFunction)) {
                        $this->$checkFunction( $query[key($query)] );
                      }
                    

                }
            }




            // if( explode(".", key($query))[1] === "type" ) {
            //     // if not valid, retrun a exception
            //     $this->__isValidType( $query[key($query)] );
            //     // $debug['tmp'] = $this->__isValidType( $query[key($query)] );
            //     $conditions['AND'] = array();

            // }
        }

        // $this->loadModel('Attribute');
        // $debug['tmp'] = in_array( explode(".", key($query))[1], $this->Attribute->typeDefinitions, true);

        // todo update for each key
        // if( !in_array(key($query), array("and", "or")) ) {
        //     if( explode(".", key($query))[1] === "type" ) {
        //         $debug['tmp'] = $this->__isValidType( $query[key($query)] );

        //     }
            // if ( $this->__isValidType( key($query) ) ) {
            //     $debug['tmp'] = key($query);
            // }            
        // }
        
        // generate condition recursively
        // $debug['tmp'] = key($query);
        
        // if ( strtolower(key($query[0])) !== "or" && strtolower(key($query[0])) !== "and" ) {
        //     if ( $this->__isValidType( key($query[0]) ) ) {

        //     }
        // }
        // $this->loadModel('Attribute');
        // $debug['tmp'] = $this->Attribute->typeDefinitions;
        









        // $debug['query_count'] = count($query);
        // foreach ($query as $k => $v)
        //     $debug['elt'][$k] = $v;
            // $newArray[$element["type"]][] = $element["value"];
        
        




        // if ($error = json_last_error_msg()) {
        //     throw new \LogicException(sprintf("Failed to parse json string '%s', error: '%s'", $this->data, $error));
        // }
        // $simpleFalse = array('value' , 'type', 'category', 'org', 'tags', 'from', 
        //     'to', 'last', 'eventid', 'withAttachments', 'uuid', 'publish_timestamp', 
        //     'timestamp', 'enforceWarninglist', 'to_ids', 'deleted');













        // $debug['data'] = $this->request->data;
        $debug['scope'] = $scope;
        // $debug['async'] = $async;
        // $debug['expand'] = $expand;
        // $debug['isJsonHeader'] = $this->response->type();
        $debug['query'] = $query;
        // $debug['param'] = $params;
        // $debug['args'] = func_get_args();
        // $debug['version'] = $version;
        // $debug['user'] = $this->Auth->user();
        return $this->RestResponse->viewData($debug, $this->response->type());
    }    
}