<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode para mostrar posts.
 */

# namespace;

class PostsController
{
    /** @var string $type posts o pages. No se acepta otro valor. */
    private string $type = 'posts';

    /** @var int $limit */
    private int $limit = 5;

    /**
     * El atributo type no elige un método: solo posts o pages.
     *
     * @param array $attrs Atributos del Shortcode.
     **/
    public function __construct(array $attrs)
    {
        $type = ( isset( $attrs['type'] ) && is_string( $attrs['type'] ) ) ? $attrs['type'] : 'posts';
        if ( $type !== 'posts' && $type !== 'pages' ) {
            throw new \Exception( 'The type does not exist.' );
        }

        $limit = ( isset( $attrs['limit'] ) && is_numeric( $attrs['limit'] ) ) ? (int) $attrs['limit'] : 5;
        if ( $limit < 1 ) {
            $limit = 1;
        }

        $this->type  = $type;
        $this->limit = $limit;
    }
    
    /**
     * Metodo por default.
     *
     * @param array $attrs Atributos del Shortcode.
     **/
    public function index($attrs) 
    {
        unset( $attrs['type'] );

        $items = $this->type === 'pages' ? $this->pages( $attrs ) : $this->posts( $attrs );

        return view('shortcodes/posts/posts', [
            'posts' => $items,
        ]);
    }

    /**
     * Se obtienen los posts de acuerdo a los parámetros.
     *
     * @param array $attrs Atributos del Shortcode.
     * @return array
     * 
     * Ejemplo de uso del Shortcode: [carpeta_plugin Posts=Posts type="posts" limit=10]
     * Para más información de los parámetros de la función, ver: https://developer.wordpress.org/reference/functions/get_posts
     **/
    private function posts(array $attrs) : array
    {
        return get_posts([
            'numberposts' => $this->limit     
        ]); 
    }

    /**
     * Se obtienen las pages de acuerdo a los parámetros.
     *
     * @param array $attrs Atributos del Shortcode.
     * @return array
     * 
     * Ejemplo de uso del Shortcode: [carpeta_plugin Posts=Posts type="pages" limit=10]
     * Para más información de los parámetros de la función, ver: https://developer.wordpress.org/reference/functions/get_pages/
     **/
    private  function pages(array $atts) : array
    {
        return get_pages(array (
            'number' => $this->limit
        ));
    }

}
