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
     * Quién puede entrar a esta Menu Page y ejecutar TODOS sus métodos public
     * (index, saludo y cualquier otro que se llame con ?page=…&option=metodo).
     * Si falta, se usa install_plugins.
     */
    public static $capability = 'manage_options';

    /**
     * Opcional: capability distinta para métodos concretos. Los demás usan $capability.
     *
     * @var array<string, string>
     */
    public static array $capabilities = array(
        // 'borrar' => 'delete_posts',
    );

    /**
     * Opcional: métodos que exigen nonce (_wpfw_nonce). Los enlaces de ListTableController,
     * FormController y wpfw_admin_action_url( $page, 'metodo' ) ya lo incluyen.
     *
     * @var list<string>
     */
    public static array $nonce = array(
        // 'borrar',
    );

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
     * Método saludo. Es public, así que responde a ?page=…&option=saludo
     * (o wpfw_admin_action_url( $page, 'saludo' )) sin declararlo en ninguna lista.
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
