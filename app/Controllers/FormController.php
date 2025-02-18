<?php
/**
 * 
 * Form Controller.
 * 
 */
namespace WordpressFramework\Controllers;

class FormController {
    public $model=null;
 
    function __construct($args = null) {

        $this->model = $args["model"]??null;

        print("<pre>".print_r($this->model, true)."</pre>");
        // print("<pre>".print_r($this->model, true)."</pre>");
        // print("<pre>".print_r($this->model->toArray(), true)."</pre>");
        
    }
 
}