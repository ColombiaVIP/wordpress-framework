<?php
/**
 * Plantilla para renderizar las URLs del WordPress Framework.
 *
 * El processor que coincidió con la petición se resuelve por su cuenta,
 * sin mirar la lista global de filtros, así la URL de un plugin no ejecuta el request de otro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hay que llamarlo: si no, el tema de bloques no arma los filtros ni el contenido.
$template_html = get_the_block_template_html();
unset( $template_html );

\Fw\Init\Routing\RoutingProcessor::dispatchCurrent();
