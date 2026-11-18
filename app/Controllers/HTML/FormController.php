<?php
/**
 * 
 * Form Controller.
 * 
 */
namespace WordpressFramework\Controllers\HTML;

use Fw\Core\Request\AdminActions;
use WordpressFramework\Controllers\HTML\HTMLController as HTML;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormController {
    public $model;
    public ?array $fields=null;
    public ?array $values=null;
    public array $hidden=["id","created_at","updated_at"];
    public ?array $media=["image","imagen", "imagenes","imagen_principal","pdf","file","archivo"];
    public ?array $textArea=["descripcion"];
    public ?array $required=[];
    public ?array $readonly=[];
    public ?array $preparedArray=null;
 
    function __construct($args = null) {

        $this->fields = $args["fields"] ?? wp_die( esc_html( 'Los campos del formulario son obligatorios.' ) );
        $this->values = $args["values"]??null;
        $this->model = $args["model"]??null;
        
        array_push($this->hidden , ...$args["hidden"]??[]);
        array_push($this->media , ...$args["media"]??[]);
        array_push($this->textArea , ...$args["textArea"]??[]);
        array_push($this->required , ...$args["required"]??[]);
        array_push($this->readonly , ...$args["readonly"]??[]);


    
        $this->fields?
            $this->prepareArray():
            wp_die( esc_html( 'No se pudieron preparar los campos del formulario.' ) );  
  
        
    }
    function prepareArray() {	
        try {
            foreach ($this->fields as $field) {
 
                $field=is_object($field)?(array)$field: $field;
    
                $incoming = '';
                if (
                    ! isset( $this->values[ $field['Field'] ] )
                    && isset( $_REQUEST[ $field['Field'] ] )
                    && is_string( $_REQUEST[ $field['Field'] ] )
                ) {
                    $incoming = wp_unslash( $_REQUEST[ $field['Field'] ] );
                }

                $field['Value'] = $this->values[ $field['Field'] ] ?? $incoming;
    
                $this->preparedArray[]=$field;
    
            }
        } catch (\Throwable $th) {
            wp_die( esc_html( 'No se pudieron preparar los campos del formulario.' ) );
        }
       
        
    }

    public function fields() {
        $fields="";
        
        if($this->model):
            $fields.=HTML::hidden('model',$this->model);             
        endif; 
        foreach ($this->preparedArray as $field):

            $required=false;
            if(in_array($field["Field"], $this->required))
            {
                $required="required";
            }               

            $readonly=false;
            if(in_array($field["Field"], $this->readonly))
            {
                $readonly="readonly";
            }

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
                        $required,
                    )
                );

            }
            elseif (in_array($field["Field"], $this->textArea)) {
                # code...
                $fields.=HTML::label(
                $field["Field"], 
                HTML::textArea(
                    "data[$field[Field]]", 
                    $field["Value"],
                    $required,
                    $readonly,
                    
                    )
                );
            }
            else
            {   
                $fields.=HTML::label(
                    $field["Field"], 
                    HTML::text(
                        "data[$field[Field]]", 
                        $field["Value"],
                        $required,
                        $readonly,
                        
                        )
                );
            }   
        endforeach;
        $fields.=HTML::submit('Guardar'); 
        return $fields;
    }

    public function html() {
        $page   = AdminActions::currentPage() ?? '';
        $fields = AdminActions::field( $page, 'save' ) . $this->fields();

        return HTML::form(
            $fields,
            (string) $this->model,
            AdminActions::postUrl( $page, 'save' ),
        );
    }
 
}