<?php
App::uses('AppController', 'Controller');

class SearchController extends AppController {

    public $components = array('Security' ,'RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Security->csrfUseOnce = false;
        $this->Security->validatePost = true;
    }

    public function search($version) {



        // if ($error = json_last_error_msg()) {
        //     throw new \LogicException(sprintf("Failed to parse json string '%s', error: '%s'", $this->data, $error));
        // }
        $debug = array();
        $debug['data'] = $this->request->data;
        $debug['param'] = $this->request->param;
        $debug['args'] = func_get_args();
        return $this->RestResponse->viewData($debug, $this->response->type());
    }
    
}