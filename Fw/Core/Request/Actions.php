<?php
/**
 * Lista blanca de métodos que un visitante o un shortcode pueden invocar.
 */

namespace Fw\Core\Request;

use ReflectionClass;
use ReflectionMethod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions
{
	/**
	 * Deja pasar solo un identificador PHP. Rechaza rutas, espacios y métodos mágicos.
	 */
	public static function sanitizeIdentifier( string $name ): ?string
	{
		$name = trim( $name );
		if ( preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $name ) !== 1 ) {
			return null;
		}

		if ( str_starts_with( $name, '__' ) ) {
			return null;
		}

		return $name;
	}

	/**
	 * El método es invocable desde fuera si es public y está declarado.
	 * index y error404 se aceptan sin listarlos.
	 */
	public static function isAllowed( string $controller, string $method ): bool
	{
		$method = self::sanitizeIdentifier( $method );
		if ( $method === null || ! class_exists( $controller ) ) {
			return false;
		}

		if ( ! Request::methodExists( $controller, $method, 'isPublic' ) ) {
			return false;
		}

		if ( $method === 'index' || $method === 'error404' ) {
			return true;
		}

		return in_array( $method, self::declared( $controller ), true );
	}

	/**
	 * Nombres declarados en $webActions o con el atributo WebAction.
	 *
	 * @return list<string>
	 */
	public static function declared( string $controller ): array
	{
		if ( ! class_exists( $controller ) ) {
			return array();
		}

		$names = array();
		if ( property_exists( $controller, 'webActions' ) && is_array( $controller::$webActions ) ) {
			foreach ( $controller::$webActions as $name ) {
				if ( ! is_string( $name ) ) {
					continue;
				}

				$clean = self::sanitizeIdentifier( $name );
				if ( $clean !== null ) {
					$names[] = $clean;
				}
			}
		}

		$reflection = new ReflectionClass( $controller );
		foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( $method->getAttributes( WebAction::class ) === array() ) {
				continue;
			}

			$clean = self::sanitizeIdentifier( $method->getName() );
			if ( $clean !== null ) {
				$names[] = $clean;
			}
		}

		return array_values( array_unique( $names ) );
	}
}
