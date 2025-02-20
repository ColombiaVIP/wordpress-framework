<?php
/**
 * 
 * Form Controller.
 * 
 */
namespace WordpressFramework\Controllers\HTML;
use WordpressFramework\Controllers\HTML\HTMLController as HTML;

class FormController {
    public ?array $fields=null;
    public ?array $values=null;
    public ?array $hidden=null;
    public $table;

    public ?array $preparedArray=null;
    public ?array $media=null;
 
    function __construct($args = null) {

        $this->fields = $args["fields"]??wp_die("FORM CONTROLLER: Fields are mandatory");
        $this->values = $args["values"]??null;
        $this->table = $args["table"]??null;
        $this->hidden = $args["hidden"]??["id","created_at","updated_at"];
        $this->media = $args["media"]??["image", "imagen_principal"];
    
        $this->fields?
            $this->prepareArray():
            wp_die("FORM CONTROLLER: Error preparing fields");  
  
        
    }
    function prepareArray() {	
        try {
            foreach ($this->fields as $field) {
 
                $field=is_object($field)?(array)$field: $field;
    
                $this->values?$field["Value"] = $this->values[$field["Field"]]:null;
    
                $this->preparedArray[]=$field;
    
            }
        } catch (\Throwable $th) {
            wp_die ("Error preparing fields: ".$th->getMessage());
        }
       
        
    }

    public function fields() {
        $fields="";
        
        if($this->table):
            $fields.=HTML::hidden('table',$this->table);             
        endif; 
        foreach ($this->preparedArray as $field):                 
            if(in_array($field["Field"], $this->hidden))
            {
                $fields.=HTML::hidden("data[$field[Field]]",$field["Value"]);
            }
            elseif(in_array($field["Field"], $this->media))
            {
                $fields.=HTML::label(
                    $field["Field"], 
                    HTML::media(
                        $field["Field"], 
                        "data[$field[Field]]",
                        $field, 
                        false
                    )
                );

            }
            else
            {   
                $fields.=HTML::label(
                    $field["Field"], 
                    HTML::text("data[$field[Field]]", $field["Value"])
                );
            }   
        endforeach;
        $fields.=HTML::submit('Guardar'); 
        return $fields;
    }

    public function html() {                   
        return HTML::form(
            $this->fields(),
            $this->table,
            "?page=$_REQUEST[page]&option=save",
        );   
    }
 
}