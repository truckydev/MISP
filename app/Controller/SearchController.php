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
        if (isset($rawData['scoasyncpe'])) {
            if ($rawData['async'] === True){
                $async = True;
            }
        }
        return $async;
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
        // return count($query[0]);
        if (count($query[0]) !== 1){
            // throw new BadRequestException("error : First level of your query key have more than 1 object. Help : Try with only {key:value} like { attributes.tag : type:OSINT }");
        }elseif (count($query[0]) === 1 ){
            if ( strtolower(key($query[0])) !== "or" && strtolower(key($query[0])) !== "and") {
                $countKey = count(explode(".", key($query[0])));
                // no scope specified make events as default
                if ( $countKey === 1 ) {
                    return array("events.".key($query[0]) => $query[0][key($query[0])] );
                }else{
                    return $query[0];
                }
            }
        }
        return False;
    }
    

    /**
     * @param array param from $this->passedArgs
     * @return array return specifique param for pagination (limit, offset)
     * @todo 
     */
    private function __getParamsData($rawData) {
        $limit = 0;
        $offset = 0;
        if (isset($rawData['limit'])) {
            if (is_numeric($rawData['limit']) && $rawData['limit'] > 0){
                $limit = $rawData['limit'];
            }
        }
        if (isset($rawData['offset'])) {
            if (is_numeric($rawData['offset']) && $rawData['offset'] > 0){
                $offset = $rawData['offset'];
            }
        }
        if ($limit === 0 && $offset === 0){
            return false;
        }
        return array('limit' => $limit, 'offset'=> $offset);
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
        // $debug['query_raw'] = $this->request->data['query'];
        $query = $this->__getQueryData($this->request->data);
        // $this->__checkLevelCount($query);


        // $debug['tmp'] = $query[0][key($query[0])];
        $debug['tmp'] = $this->__checkLevelCount($query);



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
        // $debug['scope'] = $scope;
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