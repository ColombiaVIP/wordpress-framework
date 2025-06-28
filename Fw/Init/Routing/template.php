<?php
/**
 * Plantilla para renderizar URLs del WordPress Framework.
 *
 */

$template_html = get_the_block_template_html(); //NO SE DEBE COMENTAR, SINO NO SE RENDERIZA LOS FILTROS Y EL CONTENIDO DEL BLOCK TEMPLATE
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head(); ?>



</head>

<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	
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
	<?php 
	// echo $template_html; //SE COMENTA PARA QUE NO SE REPITA EL FRONTEND
	?>
	<?php wp_footer(); ?>
</body>

</html>