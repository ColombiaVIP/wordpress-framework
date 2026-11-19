<?php
/*
@wordpress-plugin
Plugin Name:    		WP Framework
Plugin URI: 			https://github.com/ColombiaVIP/wordpress-framework
Description:    		Framework mvc (modelo-vista-controlador) que ayuda a disminuir el tiempo de desarrollo de sistemas.
Version:        		1.5.0
Author: 				ColombiaVIP
Author URI: 			https://ColombiaVIP.com
Plugin URI: 			https://github.com/ColombiaVIP/wordpress-framework/
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

# Requerimientos.
if ( ! require __DIR__ . '/requirements.php' ) {
	return;
}

# ======================================= #
# ========= WordPress Framework ========= #
# ======================================= #

define('WPFW_NAME', 'WP Framework');
define('WPFW_PATH', __DIR__);

$wpfwPluginData = get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' );
define( 'WPFW_VERSION', $wpfwPluginData['Version'] !== '' ? $wpfwPluginData['Version'] : '0.0.0' );

# Se carga el autoload del Framework. El ORM (Dbout, Illuminate, Carbon) y los helpers
# globales de Laravel solo se cargan cuando un plugin usa un modelo; ver Fw/Cargador.php.
# Para cargarlos siempre: define( 'WPFW_ORM', true ) en wp-config.php.
require_once __DIR__ . '/Fw/Cargador.php';
Fw\Cargador::iniciar( __DIR__ . '/vendor' );

# Se carga el Framework.
new Fw\Framework(__FILE__, [
	'mode' => 'production',
	'autoload' => false
]);
