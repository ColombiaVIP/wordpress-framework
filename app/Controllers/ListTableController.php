<?php
/**
 * List Table Controller.
 * USE:
         $listTable = new \WordpressFramework\Controllers\ListTableController; 
        #Instanciar y preparar
        $listTable->prepare_items(
            [
                "model"=>"\Models\Path",
                "columns"=>[#Columnas visibles
                    "id"=> "Id",
                    "nombre"=> "Nombre",
                    "descripcion"=> "Descripcion",
                ],
                "hiden"=>["id"],#Columnas ocultas
                "sortable"=>[#Columnas ordenables
                    "nombre"=>["nombre",true]
                ]
            ]            
        );
        
        $listTable->display();
 */
namespace WordpressFramework\Controllers;

if(!class_exists('WP_List_Table'))
    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');


class ListTableController extends \WP_List_Table{
    private $options;  
    public $main;


    public function prepare_items($args = []){

        $items =  $args['items'];
        
        $this->main= $args['main']??'id';

        $this->options= $args['options']??[
            'add'     => 'Add',
            'edit'    => 'Edit',
            'delete'  => 'Delete',
            'view'=> 'View',
        ];


        $columns = $args['columns']??$this->get_columns($items);

        $hidden = $args['hiden']??['id'];

        $sortable = $args['sortable']??[
            $this->main => [$this->main],

        ];

        usort( $items, array( &$this, 'sort_data' ) );
        
        $perPage = 5;
        $currentPage = $this->get_pagenum();
        $totalItems = count($items);

        
        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage,
            ) );
            
        $items = array_slice($items,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = [$columns, $hidden, $sortable,""];
            
        $this->items = $items;
    }

    public function get_columns($items=[]){
        $columns = array_keys($items[0]);
        $columnsName = array_map(function($column){
            return ucfirst($column);
        },$columns);
        return array_combine($columns,$columnsName);
    }



    public function column_default($item, $column_name){
        {
            if ($column_name == 'options' OR $column_name ==$this->main){    
                $actions = array();

                foreach ($this->options as $key => $value)
                {
                $actions[$key] = sprintf('<a href="?page=%s&option=%s&%s=%s">%s</a>',$_REQUEST['page'], $key, "id",$item['id'], $value);
                }
    
                return sprintf('%1$s %2$s', $item[$column_name]??"➤", $this->row_actions($actions));
            }
            return $item[$column_name];
         }
    }



    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return mixed
     */
    private function sort_data( $a, $b )
    {
        // Set defaults
        $orderby = 'id';
        $order = 'asc';

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }


        $result = strcmp( $a[$orderby], $b[$orderby] );

        if($order === 'asc')
        {
            return $result;
        }

        return -$result;
    }
}