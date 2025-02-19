<?php
/**
 * 
 * Form Controller.
 * 
 */
namespace WordpressFramework\Controllers\HTML;


class FormController {
    public ?array $fields=null;
    public ?array $values=null;
    public ?array $hidden=null;
    public $table;

    public ?array $preparedArray=null;
 
    function __construct($args = null) {

        $this->fields = $args["fields"]??wp_die("FORM CONTROLLER: Fields are mandatory");
        $this->values = $args["values"]??null;
        $this->table = $args["table"]??null;
        $this->hidden = $args["hidden"]??["id","created_at","updated_at"];
    
        $this->fields?
            $this->prepareFields():
            wp_die("FORM CONTROLLER: Error preparing fields");  
  
        
    }
    function prepareFields() {	
        try {
            foreach ($this->fields as $field) {
 
                $field=is_object($field)?(array)$field: $field;
    
                $this->values?$field["Value"] = $this->values[$field["Field"]]:null;
    
                $this->preparedArray[]=$field;
    
            }
        } catch (\Throwable $th) {
            wp_die ("Error preparing fields: ".$th->getMessage());
        }
 
        // printPre( $this->preparedArray);

        
        
    }

    function makeHtml(): string {
        $html = "<form method='post' action='?page=$_REQUEST[page]&option=save'>";
        $this->table?$html .= "<input type='hidden' name='table' value='".$this->table."'>":null;
        foreach ($this->preparedArray as $field) {
            if(in_array($field["Field"],$this->hidden)):
                $html .= "<input type='hidden' name='data[$field[Field]]' value='$field[Value]'>";
            else:
                $html .= "<label for='".$field["Field"]."'>".$field["Field"]."</label>";
                $html .= "<input type='text' name=data[".$field["Field"]."] value='".$field["Value"]."'>";
            endif;
        }
        $html .= "<input type='submit' value='Submit'>";
        $html .= "</form>";
        ?>
        

        <?php
        return $html;


    }
 
}