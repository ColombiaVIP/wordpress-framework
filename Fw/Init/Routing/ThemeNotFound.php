<?php
/**
 * 404 del tema. Sustituye a wp_die cuando la ruta o el método no existen.
 */

namespace Fw\Init\Routing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ThemeNotFound
{
	private static bool $marked = false;

	public static function mark(): void
	{
		global $wp_query;

		self::$marked = true;

		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}

		if ( function_exists( 'status_header' ) ) {
			status_header( 404 );
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	/**
	 * Quita la marca solo si este ciclo la puso. Así un plugin que sí
	 * coincidió puede recuperar la petición después de otro que no la encontró.
	 */
	public static function clear(): void
	{
		if ( ! self::$marked ) {
			return;
		}

		self::$marked = false;

		global $wp_query;
		if ( is_object( $wp_query ) && property_exists( $wp_query, 'is_404' ) ) {
			$wp_query->is_404 = false;
		}
	}

	public static function template(): string
	{
		if ( ! function_exists( 'get_404_template' ) ) {
			return '';
		}

		$template = get_404_template();

		return is_string( $template ) ? $template : '';
	}

	public static function render(): void
	{
		self::mark();
		$template = self::template();
		if ( $template !== '' && is_file( $template ) ) {
			include $template;
		}
	}

	public static function reset(): void
	{
		self::$marked = false;
	}
}
