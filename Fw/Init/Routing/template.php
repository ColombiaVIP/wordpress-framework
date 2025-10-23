<?php
/**
 * Plantilla para renderizar URLs del WordPress Framework.
 *
 */

$template_html = get_the_block_template_html(); //NO SE DEBE COMENTAR, SINO NO SE RENDERIZA LOS FILTROS Y EL CONTENIDO DEL BLOCK TEMPLATE
?>
	
	<?php
	// Ejecutar el request guardado en RoutingProcessor
	global $wp_filter;
	$processor = null;
	if (isset($wp_filter['template_include'])) {
		foreach ($wp_filter['template_include']->callbacks as $priority => $callbacks) {
			foreach ($callbacks as $cb) {
				if (is_array($cb['function']) && isset($cb['function'][0]) && $cb['function'][0] instanceof \Fw\Init\Routing\RoutingProcessor) {
					$processor = $cb['function'][0];
					break 2;
				}
			}
		}
	}
	if ($processor && isset($processor->currentRequest)) {
		$processor->currentRequest->send();
	}
	?>
