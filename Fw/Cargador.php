<?php
/**
 * Cargador del framework con ORM perezoso.
 *
 * Reemplaza a vendor/autoload.php en el arranque. Registra el mismo ClassLoader de Composer
 * (psr-4, classmap y namespaces generados por `composer install`), pero de los archivos
 * "files" solo incluye de inmediato los del framework (Fw/helpers). Los de las dependencias
 * (helpers globales de Laravel como collect(), env(), e(), value(), now(), event()...,
 * y funciones de Symfony Clock/Translation) quedan en espera y se incluyen
 * la primera vez que se pide una clase de vendor/ (Dbout, Illuminate, Carbon...).
 *
 * En la práctica: un plugin que extiende WordpressFramework\Models\Model carga el ORM
 * al declarar su primer modelo; un sitio que no usa modelos no carga nada de eso.
 *
 * Para cargarlo siempre (p. ej. un plugin que llama collect() sin tocar un modelo antes):
 * define( 'WPFW_ORM', true ) en wp-config.php o llama Fw\Cargador::cargarOrm().
 */

namespace Fw;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cargador
{
	/** @var \Composer\Autoload\ClassLoader|null */
	private static $loader = null;

	private static string $vendorDir = '';

	/** @var array<string,string> Archivos "files" de dependencias pendientes (id => ruta). */
	private static array $pendientes = array();

	private static bool $ormCargado = false;

	/**
	 * Registra el autoload del framework sin incluir los helpers de las dependencias.
	 *
	 * @param string $vendorDir Carpeta vendor del framework.
	 * @return \Composer\Autoload\ClassLoader
	 */
	public static function iniciar( string $vendorDir )
	{
		if ( self::$loader !== null ) {
			return self::$loader;
		}

		$vendorDir = rtrim( $vendorDir, '/\\' );
		$composer  = $vendorDir . '/composer';

		// Instalación sin los mapas de Composer: se usa el autoload clásico (todo de una vez).
		if ( ! is_file( $composer . '/autoload_psr4.php' ) || ! is_file( $composer . '/ClassLoader.php' ) ) {
			self::$ormCargado = true;
			self::$loader     = require $vendorDir . '/autoload.php';
			return self::$loader;
		}

		if ( is_file( $composer . '/platform_check.php' ) ) {
			require $composer . '/platform_check.php';
		}

		if ( ! class_exists( \Composer\Autoload\ClassLoader::class, false ) ) {
			require $composer . '/ClassLoader.php';
		}

		self::$vendorDir = $vendorDir;
		$loader          = new \Composer\Autoload\ClassLoader( $vendorDir );

		foreach ( (array) require $composer . '/autoload_namespaces.php' as $namespace => $rutas ) {
			$loader->set( $namespace, $rutas );
		}
		foreach ( (array) require $composer . '/autoload_psr4.php' as $namespace => $rutas ) {
			$loader->setPsr4( $namespace, $rutas );
		}
		$classmap = is_file( $composer . '/autoload_classmap.php' ) ? (array) require $composer . '/autoload_classmap.php' : array();
		if ( $classmap ) {
			$loader->addClassMap( $classmap );
		}
		$loader->register( true );
		self::$loader = $loader;

		$archivos = is_file( $composer . '/autoload_files.php' ) ? (array) require $composer . '/autoload_files.php' : array();
		$prefijo  = self::normalizar( $vendorDir ) . '/';
		foreach ( $archivos as $id => $archivo ) {
			$ruta = self::normalizar( $archivo );
			// Los polyfills de Symfony se quedan en el arranque: no traen nada con PHP 8.3+ y mbstring.
			if ( str_starts_with( $ruta, $prefijo ) && ! str_contains( $ruta, '/symfony/polyfill-' ) ) {
				self::$pendientes[ $id ] = $archivo;
			} else {
				self::incluir( (string) $id, $archivo );
			}
		}

		if ( ( defined( 'WPFW_ORM' ) && WPFW_ORM ) || self::$pendientes === array() ) {
			self::cargarOrm();
		} else {
			// Antes que el loader de Composer: si la clase vive en vendor/, primero los helpers.
			spl_autoload_register( array( self::class, 'antesDeVendor' ), true, true );
		}

		return $loader;
	}

	/**
	 * Autoloader centinela: cuando la clase pedida está en vendor/, carga los helpers
	 * pendientes (cargarOrm) y luego la clase con el loader de Composer.
	 *
	 * @param string $clase
	 * @return void
	 */
	public static function antesDeVendor( string $clase ): void
	{
		if ( self::$ormCargado || self::$loader === null ) {
			return;
		}

		$archivo = self::$loader->findFile( $clase );
		if ( is_string( $archivo ) && str_starts_with( self::normalizar( $archivo ), self::normalizar( self::$vendorDir ) . '/' ) ) {
			self::cargarOrm();
			// Se carga aquí mismo: PHP no garantiza seguir la cadena de autoloaders si cambió durante la llamada.
			self::$loader->loadClass( $clase );
		}
	}

	/**
	 * Incluye los helpers de las dependencias (Laravel, Symfony, Dbout).
	 * Idempotente.
	 *
	 * @return void
	 */
	public static function cargarOrm(): void
	{
		if ( self::$ormCargado ) {
			return;
		}
		self::$ormCargado = true;
		// El centinela queda registrado pero ya no hace nada (retorna en la primera línea).

		foreach ( self::$pendientes as $id => $archivo ) {
			self::incluir( (string) $id, $archivo );
		}
		self::$pendientes = array();

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wpfw_orm_cargado' );
		}
	}

	/**
	 * ¿Ya se cargaron los helpers y el ORM?
	 *
	 * @return bool
	 */
	public static function ormCargado(): bool
	{
		return self::$ormCargado;
	}

	/**
	 * @return \Composer\Autoload\ClassLoader|null
	 */
	public static function loader()
	{
		return self::$loader;
	}

	/**
	 * Igual que Composer: un archivo "files" se incluye una sola vez por id, aunque otro
	 * plugin traiga el mismo paquete con su propio vendor/autoload.php.
	 */
	private static function incluir( string $id, string $archivo ): void
	{
		if ( empty( $GLOBALS['__composer_autoload_files'][ $id ] ) ) {
			$GLOBALS['__composer_autoload_files'][ $id ] = true;
			require $archivo;
		}
	}

	private static function normalizar( string $ruta ): string
	{
		return str_replace( '\\', '/', $ruta );
	}
}
