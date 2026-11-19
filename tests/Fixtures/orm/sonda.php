<?php
/**
 * Sonda para CargadorOrmTest: arranca el cargador en un proceso PHP limpio
 * (sin el bootstrap de PHPUnit, que ya incluye vendor/autoload.php) y reporta en JSON.
 * Uso: php sonda.php <modo>   modo: arranque | modelo | clase-fw | constante | composer-antes
 */

define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
$modo   = $argv[1] ?? 'arranque';
$vendor = dirname( __DIR__, 3 ) . '/vendor';

if ( $modo === 'constante' ) {
	define( 'WPFW_ORM', true );
}
if ( $modo === 'composer-antes' ) {
	// Otro plugin ya cargó el autoload clásico del framework.
	require $vendor . '/autoload.php';
}

require_once dirname( __DIR__, 3 ) . '/Fw/Cargador.php';
Fw\Cargador::iniciar( $vendor );

$estado = static function (): array {
	$illuminate = 0;
	foreach ( get_included_files() as $f ) {
		if ( preg_match( '#/vendor/(illuminate|nesbot|dbout|carbonphp)/#', str_replace( '\\', '/', $f ) ) ) {
			$illuminate++;
		}
	}
	return array(
		'orm'        => Fw\Cargador::ormCargado(),
		'collect'    => function_exists( 'collect' ),
		'env'        => function_exists( 'env' ),
		'value'      => function_exists( 'value' ),
		'e'          => function_exists( 'e' ),
		'event'      => function_exists( 'event' ),
		'helpersFw'  => function_exists( 'wpfw_escape_html' ) || function_exists( 'routeUrl' ) || function_exists( 'spaceUpper' ),
		'archivosVendorOrm' => $illuminate,
		'model'      => class_exists( 'WordpressFramework\\Models\\Model', false ),
	);
};

$salida = array( 'antes' => $estado() );

if ( $modo === 'modelo' ) {
	eval( 'class SondaLibro extends WordpressFramework\\Models\\Model { use Illuminate\\Database\\Eloquent\\SoftDeletes; protected $table = "libros"; }' );
	$salida['suma']    = collect( array( 1, 2, 3 ) )->sum();
	$salida['softDel'] = in_array( 'Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses( 'SondaLibro' ), true );
}
if ( $modo === 'clase-fw' ) {
	class_exists( 'Fw\\Paths' );
	class_exists( 'Fw\\Config\\Apps' );
	class_exists( 'Clase\\Que\\No\\Existe' );
}

$salida['despues'] = $estado();
echo json_encode( $salida );
