<?php
/**
 * Marca un método como acción web.
 *
 * Un método public no se puede llamar desde una URL, un shortcode
 * ni el parámetro option de un menú. Hay que listarlo en $webActions
 * o anotarlo con este atributo. index y error404 quedan permitidos.
 */

namespace Fw\Core\Request;

use Attribute;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[Attribute( Attribute::TARGET_METHOD )]
class WebAction
{
}
