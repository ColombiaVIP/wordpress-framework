<?php
/**
 * List Table Controller.
 * USE:
         $listTable = new \WordpressFramework\Controllers\ListTableController; 

        $listTable->prepare_items(
            [
                "model"=>"\Proyectos\Models\Proyectos",
                "columns"=>[
                    "id"=> "Id",
                    "nombre"=> "Nombre",
                    "descripcion"=> "Descripcion",
                ],
                "hiden"=>["id"],
                "sortable"=>[
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
    public function __construct(){
        parent::__construct([
            // 'singular' => 'proyecto',
            // 'plural' => 'proyectos',
            'ajax' => true
        ]);
    }

    public function get_columns($items=[]){
        $columns = array_keys($items[0]);
        $columnsName = array_map(function($column){
            return ucfirst($column);
        },$columns);
        return array_combine($columns,$columnsName);

        return $columns;
    }

    public function prepare_items($args = []){
        $model = new $args['model'];
        $items = $model::all()->toArray();

        $columns = $args['columns']??$this->get_columns($items);

        $hidden = $args['hiden']??['id'];

        $sortable = $args['sortable']??[
            'nombre' => ['nombre', true],
            'name' => ['name', true],
        ];

        usort( $items, array( &$this, 'sort_data' ) );
        
        $perPage = 5;
        $currentPage = $this->get_pagenum();
        $totalItems = count($items);

        
        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
            ) );
            
        $items = array_slice($items,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = [$columns, $hidden, $sortable,""];
            
        $this->items = $items;
    }



    public function column_default($item, $column_name){
        return $item[$column_name];
    }

    public function column_nombre($item){
        $actions = [
            'edit' => sprintf('<a href="?page=%s&action=%s&project=%s">Editar</a>', $_REQUEST['page'], 'edit', $item['id']),
            'delete' => sprintf('<a href="?page=%s&action=%s&project=%s">Eliminar</a>', $_REQUEST['page'], 'delete', $item['id'])
        ];
        return sprintf('%1$s %2$s', $item['nombre'], $this->row_actions($actions));
    }
    public function column_name($item){
        $actions = [
            'edit' => sprintf('<a href="?page=%s&action=%s&project=%s">Editar</a>', $_REQUEST['page'], 'edit', $item['id']),
            'delete' => sprintf('<a href="?page=%s&action=%s&project=%s">Eliminar</a>', $_REQUEST['page'], 'delete', $item['id'])
        ];
        return sprintf('%1$s %2$s', $item['name'], $this->row_actions($actions));
    }



    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
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