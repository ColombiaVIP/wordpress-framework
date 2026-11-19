<?php
/**
 * Menu Page principal del Framework.
 * 
 */

namespace WordpressFramework\Controllers\MenuPages\WpFramework;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WpFramework
{

    # Opcionales.
    # https://developer.wordpress.org/reference/functions/add_menu_page
    public static $pageTitle = 'WP Framework';
    public static $menuTitle = 'WP Framework';
    public static $capability = 'install_plugins';

    /**
     * Método por default.
     *
     **/
    public function index()
    { 
        # Si llegan datos del formulario (solo por POST).
        if ( isset( $_POST['data'] ) && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            $data = $this->validatedPluginData();
            if ( $data !== null ) {
                $this->createPlugin( $data );
            }
        }

        return view('layout', [
            'content' => view('menuPages/frameworkWp/frameworkWp/index', [
                'title' => get_admin_page_title()
            ]),
        ]);
    }

    /**
     * Valida la petición de creación de plugin (CSRF y permisos)
     * y devuelve los datos del formulario sin barras.
     *
     * Sin permiso install_plugins o sin nonce válido la petición se corta
     * con wp_die (403) antes de crear nada.
     *
     * @return array|null
     **/
    private function validatedPluginData() : ?array
    {
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( esc_html( 'No tienes permiso para crear plugins.' ), '', array( 'response' => 403 ) );
            return null;
        }

        $nonce = isset( $_POST['fwNonce'] ) && is_string( $_POST['fwNonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['fwNonce'] ) )
            : '';

        if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'create-plugin-nonce' ) ) {
            wp_die( esc_html( 'Error: Invalid nonce check.' ), '', array( 'response' => 403 ) );
            return null;
        }

        if ( ! is_array( $_POST['data'] ) ) {
            return null;
        }

        $data = array();
        foreach ( $_POST['data'] as $key => $value ) {
            if ( is_string( $key ) && is_string( $value ) ) {
                $data[ $key ] = wp_unslash( $value );
            }
        }

        if ( trim( (string) ( $data['pluginName'] ?? '' ) ) === '' ) {
            return null;
        }

        return $data;
    }

    /**
     * Creación del plugin. Solo se llama después de validatedPluginData().
     *
     * @param array $data
     * @return void
     **/
    private function createPlugin(array $data) : void
    {
        echo '<h1>Create Component: ' . esc_html( (string) ($data['pluginName'] ?? '') ) . '.</h1>';
        
        if ( !\Fw\Structures\BuildStructures::basePlugin($data) ) {
            echo "<h3>Error en la creación del plugin.</h3>";

        } else {
            echo "<h3>Plugin creado con exito.</h3>";
        }
    }

    /**
     * Página de error 404.
     *
     **/
    public function error404()
    {
        return view('layout', [
            'content' => view('404', [
                'backText' => 'GO MENU PAGE',
                'backLink' => menu_page_url( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '', false )
            ]),
        ]);
    }

}
