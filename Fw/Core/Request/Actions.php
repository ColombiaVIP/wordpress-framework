<?php
/**
 * Decide qué método de un controlador se puede invocar desde una URL,
 * un shortcode o el parámetro option de un menú.
 *
 * Regla (compatible con 1.3.1): todo método public es superficie pública.
 * Nunca se invocan métodos private o protected, el constructor, el destructor
 * ni los que empiezan por "__". Lo interno se escribe private.
 *
 * En los menús, $capability aplica a todos los métodos y $capabilities
 * (opcional) la sobrescribe por método. El nonce es opcional: $nonce.
 */

namespace Fw\Core\Request;

use ReflectionMethod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions
{
	/** Capability por defecto de una Menu Page sin $capability (igual que 1.3.1). */
	public const DEFAULT_CAPABILITY = 'install_plugins';

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
	 * El método existe y es public, no es constructor, destructor ni mágico.
	 * No hace falta declararlo en ninguna lista.
	 */
	public static function isPublicAction( string $controller, string $method ): bool
	{
		$method = self::sanitizeIdentifier( $method );
		if ( $method === null || ! class_exists( $controller ) || ! method_exists( $controller, $method ) ) {
			return false;
		}

		$reflection = new ReflectionMethod( $controller, $method );

		return $reflection->isPublic()
			&& ! $reflection->isConstructor()
			&& ! $reflection->isDestructor()
			&& ! $reflection->isAbstract();
	}

	/**
	 * Alias de isPublicAction, se conserva el nombre de la versión anterior.
	 */
	public static function isAllowed( string $controller, string $method ): bool
	{
		return self::isPublicAction( $controller, $method );
	}

	/**
	 * El controlador tiene un __call public para resolver nombres desconocidos
	 * (por ejemplo el slug de un proyecto en /proyectos/mi-proyecto).
	 */
	public static function hasMagicCall( string $controller ): bool
	{
		if ( ! class_exists( $controller ) || ! method_exists( $controller, '__call' ) ) {
			return false;
		}

		return ( new ReflectionMethod( $controller, '__call' ) )->isPublic();
	}

	/**
	 * Resuelve el método que se va a ejecutar.
	 *
	 * - Método public válido: ese método.
	 * - Nombre que no existe en la clase y $allowMagicCall con __call public: el nombre tal cual,
	 *   para que lo atienda __call (comportamiento de las rutas en 1.3.1).
	 * - Cualquier otro caso (private, protected, constructor, "__algo", inexistente):
	 *   error404 si el controlador lo ofrece como public; si no, null.
	 */
	public static function resolve( string $controller, string $method, bool $allowMagicCall = false ): ?string
	{
		if ( ! class_exists( $controller ) ) {
			return null;
		}

		$method = trim( $method );
		if ( $method === '' ) {
			$method = 'index';
		}

		if ( self::isPublicAction( $controller, $method ) ) {
			return $method;
		}

		if (
			$allowMagicCall
			&& ! str_starts_with( $method, '__' )
			&& ! method_exists( $controller, $method )
			&& self::hasMagicCall( $controller )
		) {
			return $method;
		}

		if ( self::isPublicAction( $controller, 'error404' ) ) {
			return 'error404';
		}

		return null;
	}

	/**
	 * Capability del controlador ($capability). Si no la declara, la de siempre: install_plugins.
	 */
	public static function controllerCapability( string $controller ): string
	{
		if ( class_exists( $controller ) && property_exists( $controller, 'capability' ) ) {
			$capability = $controller::$capability;
			if ( is_string( $capability ) && $capability !== '' ) {
				return $capability;
			}
		}

		return self::DEFAULT_CAPABILITY;
	}

	/**
	 * El controlador declara $capability.
	 */
	public static function declaresCapability( string $controller ): bool
	{
		if ( ! class_exists( $controller ) || ! property_exists( $controller, 'capability' ) ) {
			return false;
		}

		return is_string( $controller::$capability ) && $controller::$capability !== '';
	}

	/**
	 * Capability del método: $capabilities[metodo] si existe; si no, $capability.
	 * La comparación del nombre no distingue mayúsculas, como PHP con los métodos.
	 */
	public static function capabilityFor( string $controller, string $method ): string
	{
		$map = self::capabilitiesMap( $controller );
		$key = strtolower( $method );

		return $map[ $key ] ?? self::controllerCapability( $controller );
	}

	/**
	 * @return array<string, string> nombre del método en minúsculas => capability
	 */
	public static function capabilitiesMap( string $controller ): array
	{
		if ( ! class_exists( $controller ) || ! property_exists( $controller, 'capabilities' ) ) {
			return array();
		}

		$raw = $controller::$capabilities;
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$map = array();
		foreach ( $raw as $method => $capability ) {
			if ( is_string( $method ) && is_string( $capability ) && $capability !== '' ) {
				$map[ strtolower( $method ) ] = $capability;
			}
		}

		return $map;
	}

	/**
	 * El método exige nonce porque el controlador lo pide en $nonce.
	 * $nonce puede ser una lista de métodos o true para todos los option.
	 */
	public static function requiresNonce( string $controller, string $method ): bool
	{
		if ( ! class_exists( $controller ) || ! property_exists( $controller, 'nonce' ) ) {
			return false;
		}

		$nonce = $controller::$nonce;
		if ( $nonce === true ) {
			return true;
		}

		if ( ! is_array( $nonce ) ) {
			return false;
		}

		$method = strtolower( $method );
		foreach ( $nonce as $name ) {
			if ( is_string( $name ) && strtolower( $name ) === $method ) {
				return true;
			}
		}

		return false;
	}
}
