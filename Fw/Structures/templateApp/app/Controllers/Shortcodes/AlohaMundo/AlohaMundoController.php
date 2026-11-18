<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode
 * 
 */

# namespace;

class AlohaMundoController
{
    /**
     * saludo solo responde si está en esta lista.
     * Shortcode: [tag Alohamundo=AlohaMundo@saludo]
     *
     * @var list<string>
     */
    public static array $webActions = array( 'saludo' );

    /**
     * Método defaul.
     * 
     * Ejemplo 1: 
     * Nombre del plugin: aloha-mundo
     * Shortcode: [carpeta_plugin Alohamundo=AlohaMundo]
     *            [tag                C.C=Controller]
     * 
     * Ejemplo 2:
     * Nombre del plugin: alohaMundo
     * Shortcode: [carpetaplugin Alohamundo=AlohaMundo]
     *            [tag               C.C=Controller]
     * 
     * C.C = Carpeta Contenedora.
     * 
     * @return Response
     */
    public function index()
    { 
        return view('layout', [
            'content' => view('index', [
                'title' => 'Shortcode Aloha Mundo',
            ]),
        ]);
    }

    /**
     * Método saludo.
     * 
     * Ejemplo 1: 
     * Nombre del plugin: aloha-mundo
     * Shortcode: [carpeta_plugin Alohamundo=AlohaMundo@saludo]
     *            [tag                C.C=Controller@method]
     * 
     * Ejemplo 2:
     * Nombre del plugin: alohaMundo
     * Shortcode: [carpetaplugin Alohamundo=AlohaMundo@saludo]
     *            [tag               C.C=Controller@method]
     * 
     * C.C = Carpeta Contenedora.
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

}
