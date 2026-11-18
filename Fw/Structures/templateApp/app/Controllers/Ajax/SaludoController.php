<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Acciones AJAX de ejemplo.
 *
 * Solo se registran las claves de $ajaxActions. interno() es public y aun así
 * no tiene gancho. La petición no elige el archivo: el framework lee esta
 * carpeta al iniciar y engancha cada acción por su nombre.
 *
 * admin-ajax.php
 *   action       = wpfw_ajax_hook( $pluginSlug, 'publico' )
 *   _ajax_nonce  = wpfw_ajax_nonce( $pluginSlug, 'publico' )
 *
 * El mapa completo sale de wpfw_ajax_client_config( $pluginSlug ).
 */

# namespace;

class SaludoController
{
    /**
     * publico: visitantes, con nonce.
     * guardar: solo manage_options, con nonce.
     *
     * @var array<string, array{public?: bool, capability?: string, nonce?: bool}>
     */
    public static array $ajaxActions = array(
        'publico' => array(
            'public' => true,
            'nonce'  => true,
        ),
        'guardar' => array(
            'public'     => false,
            'capability' => 'manage_options',
            'nonce'      => true,
        ),
    );

    public function publico(): array
    {
        return array( 'mensaje' => 'Hola' );
    }

    public function guardar(): array
    {
        return array( 'ok' => true );
    }

    /**
     * No está en $ajaxActions: admin-ajax no lo ejecuta.
     */
    public function interno(): array
    {
        return array( 'no' => 'invocable' );
    }
}
