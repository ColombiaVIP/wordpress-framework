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

use Fw\Core\Request\Actions;
use Fw\Core\Request\AdminActions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if(!class_exists('WP_List_Table'))
    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');


class ListTableController extends \WP_List_Table{
    private $options;  
    public $main;
    public string|bool $search;


    public function prepare_items($args = []){

        $items =  $args['items'];
        // console_log($items);
              
        $this->main= $args['main']??'id';
        
        $this->search = $args['search']??$this->main;

        $term = '';
        if ( isset( $_GET['s'] ) && is_string( $_GET['s'] ) ) {
            $term = sanitize_text_field( wp_unslash( $_GET['s'] ) );
        }

        if(!empty($this->search) && $term !== '') {
            $items = array_filter($items, function ($var) use ($term) {
                $haystack = is_array($var) ? (string) ($var[$this->search] ?? '') : '';
                return stristr ($haystack , $term);
            });
        }
        

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
                $actions = $this->actionLinks( is_array( $item ) ? $item : array() );
                $label   = esc_html( (string) ( $item[ $column_name ] ?? '➤' ) );

                return sprintf('%1$s %2$s', $label, $this->row_actions($actions));
            }
            return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
         }
    }

    /**
     * Enlaces de fila. La clave puede traer parámetros extra como en 1.3.1:
     * 'trash&model=Proyectos' => 'Eliminar' genera ?page=…&option=trash&model=Proyectos&id=N.
     * Cada enlace lleva el nonce de su option; solo se verifica si el controlador lo pide en $nonce.
     *
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private function actionLinks( array $item ): array
    {
        $actions = array();
        $page    = AdminActions::currentPage();
        if ( $page === null ) {
            return $actions;
        }

        $id = isset( $item['id'] ) && ( is_scalar( $item['id'] ) ) ? (string) $item['id'] : '';

        foreach ( $this->options as $key => $value ) {
            $parsed = self::parseOptionKey( (string) $key );
            if ( $parsed === null ) {
                continue;
            }

            $args       = $parsed['args'];
            $args['id'] = $id;
            $url        = AdminActions::url( $page, $parsed['option'], $args );
            // La clave es la clase CSS del span en row_actions: solo el option.
            $slot = $parsed['option'];
            for ( $n = 2; isset( $actions[ $slot ] ); $n++ ) {
                $slot = $parsed['option'] . '-' . $n;
            }
            $actions[ $slot ] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $url ),
                esc_html( (string) $value )
            );
        }

        return $actions;
    }

    /**
     * Separa 'metodo&clave=valor&…' en el option y sus parámetros extra.
     * El option tiene que ser un identificador PHP (nombre de método).
     *
     * @return array{option: string, args: array<string, string>}|null
     */
    public static function parseOptionKey( string $key ): ?array
    {
        $parts  = explode( '&', $key, 2 );
        $option = Actions::sanitizeIdentifier( $parts[0] );
        if ( $option === null ) {
            return null;
        }

        $args = array();
        if ( isset( $parts[1] ) && $parts[1] !== '' ) {
            $extra = array();
            parse_str( $parts[1], $extra );
            foreach ( $extra as $name => $val ) {
                if ( is_string( $name ) && $name !== '' && is_scalar( $val ) && ! in_array( $name, array( 'page', 'option' ), true ) ) {
                    $args[ $name ] = (string) $val;
                }
            }
        }

        return array(
            'option' => $option,
            'args'   => $args,
        );
    }
    /**
     * Returns the HTML of the table
     * @param array $args
     * @return bool|string
     */
    public function createTable($args){
        $title=$args['title']??'';

        $this->prepare_items($args);

        ob_start();        
        if (!empty($this->search)):?>
            <form action="" method="GET">
                <?php $this->search_box( __( 'Search' ), sanitize_title($title) ); ?>
                <?php if (isset($_GET)): unset($_GET['s']);?>
                <?php foreach ( $_GET as $name => $value ) : ?>
                    <?php if ( ! is_string( $name ) || is_array( $value ) ) { continue; } ?>
                    <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( wp_unslash( $value ) ); ?>"/>
                <?php endforeach; ?>
                <?php endif; ?>
            </form>        
        <?php endif;
        echo "<h1 class='wp-heading-inline'>" . esc_html( (string) $title ) . "</h1>";

        $page = AdminActions::currentPage();
        if ( ( $args['actions'] ?? false ) && $page !== null ) {
            foreach ( $args['actions'] as $key => $value ) {
                $parsed = self::parseOptionKey( (string) $key );
                if ( $parsed === null ) {
                    continue;
                }
                echo '<a href="' . esc_url( AdminActions::url( $page, $parsed['option'], $parsed['args'] ) ) . '" class="page-title-action">' . esc_html( (string) $value ) . '</a>';
            }
        }

        $this->display() ;
        $table = ob_get_clean();
        return $table;

    }


    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return mixed
     */
    private function sort_data( $a, $b )
    {
        $orderby = 'id';
        $order   = 'desc';

        if ( isset( $_GET['orderby'] ) && is_string( $_GET['orderby'] ) ) {
            $candidate = Actions::sanitizeIdentifier( wp_unslash( $_GET['orderby'] ) );
            if (
                $candidate !== null
                && is_array( $a )
                && array_key_exists( $candidate, $a )
                && is_array( $b )
                && array_key_exists( $candidate, $b )
            ) {
                $orderby = $candidate;
            }
        }

        if ( isset( $_GET['order'] ) && is_string( $_GET['order'] ) && strtolower( wp_unslash( $_GET['order'] ) ) === 'asc' ) {
            $order = 'asc';
        }

        $left   = is_array( $a ) ? (string) ( $a[ $orderby ] ?? '' ) : '';
        $right  = is_array( $b ) ? (string) ( $b[ $orderby ] ?? '' ) : '';
        $result = strnatcmp( $left, $right );

        return $order === 'asc' ? $result : -$result;
    }
}