<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu Page principal 
 * 
 */

# namespace;

class AlohaMundoController
{
    /**
     * saludo se abre con wpfw_admin_action_url( $page, 'saludo' ).
     * Ese enlace trae el nonce _wpfw_nonce. Sin nonce la acción no corre.
     *
     * @var list<string>
     */
    public static array $webActions = array( 'saludo' );

    /**
     * Método defaul.
     * 
     * @return Response
     */
    public function index()
    { 
        return view('layout', [
            'content' => view('index', [
                'title' => get_admin_page_title(),
            ]),
        ]);
    }

    /**
     * Método saludo.
     * URL: wpfw_admin_action_url( $page, 'saludo' ).
     * 
     * @return Response
     */
    public function saludo()
    {
        $currentUser = wp_get_current_user();
        return view('layout', [
            'content' => view('index', [
                'title' => "Aloha Mundo, {$currentUser->display_name}",
            ]),
        ]);
    }

    /**
     * Método que se ejecuta cuando no se encuentra una ruta.
     * 
     * @return Response
     */
    public function error404()
    {
        return view('layout', [
            'content' => view('404', [
                'backText' => 'GO BACK',
                'backLink' => menu_page_url( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '', false )
            ]),
        ]);
    }

}
