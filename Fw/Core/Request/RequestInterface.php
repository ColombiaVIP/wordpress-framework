<?php
/**
 * Interfaz que sera implementada por los multiples Request.
 */

namespace Fw\Core\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


interface RequestInterface
{
    /**
     * Se valida y ejecuta la solicitud.
     *
     **/
    public function send();
}
