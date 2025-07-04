<?php
/*
@wordpress-plugin
Plugin Name:    		WP Framework
Plugin URI: 			https://github.com/ColombiaVIP/wordpress-framework
Description:    		Framework mvc (modelo-vista-controlador) que ayuda a disminuir el tiempo de desarrollo de sistemas.
Version:        		1.2.0
Author: 				ColombiaVIP
Author URI: 			https://ColombiaVIP.com
Plugin URI: 			https://github.com/ColombiaVIP/wordpress-framework/
License: GPLv2 or later
*/

# Requerimientos.
if ( ! require __DIR__ . '/requirements.php' ) {
	return;
}

# ======================================= #
# ========= WordPress Framework ========= #
# ======================================= #

define('WPFW_NAME', 'WP Framework');
define('WPFW_PATH', __DIR__);
define('WPFW_VERSION', '1.1.9');

# Se carga el autoload del Framework.
require __DIR__ . '/vendor/autoload.php';

# Se carga el Framework.
new Fw\Framework(__FILE__, [
	'mode' => 'production',
	'autoload' => false
]);
