<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Acciones AJAX de ejemplo.
 *
 * Todo método public escrito en este controlador es una acción AJAX con
 * gancho {slug}_{metodo}; no hay que listarlo en ningún lado. Lo interno se
 * escribe private o protected. La petición no elige el archivo: el framework
 * lee esta carpeta al iniciar y engancha cada método por su nombre.
 *
 * Permisos (igual que en las Menu Pages):
 *   $capability    aplica a todos los métodos.
 *   $capabilities  la sobrescribe por método. '' = anónimo (wp_ajax_nopriv_).
 *   $nonce         opt-in: true para todos o lista de métodos.
 *
 * admin-ajax.php
 *   action       = wpfw_ajax_hook( $pluginSlug, 'publico' )
 *   _ajax_nonce  = wpfw_ajax_nonce( $pluginSlug, 'publico' )
 *
 * El mapa completo sale de wpfw_ajax_client_config( $pluginSlug ).
 *
 * Si necesitas lógica de otra carpeta (Routes/, Publicos/, Models/...),
 * instancia la clase aquí dentro: los permisos ya se comprobaron al entrar.
 */

# namespace;

class SaludoController
{
    /** Todos los métodos: solo administradores. */
    public static $capability = 'manage_options';

    /** publico: cualquier visitante, también sin sesión. */
    public static array $capabilities = array(
        'publico' => '',
    );

    /** Nonce en los dos métodos. */
    public static $nonce = array( 'publico', 'guardar' );

    public function publico(): array
    {
        return array( 'mensaje' => 'Hola' );
    }

    public function guardar(): array
    {
        return array( 'ok' => true, 'resumen' => $this->resumen() );
    }

    /**
     * private: admin-ajax no lo ejecuta.
     */
    private function resumen(): string
    {
        return 'interno';
    }
}
