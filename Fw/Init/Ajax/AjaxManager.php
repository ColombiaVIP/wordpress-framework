<?php
/**
 * Capa AJAX (admin-ajax) con lista blanca.
 *
 * Las acciones se leen de $ajaxActions al arrancar. La petición no elige
 * archivo, clase ni método: solo dispara el gancho que ya se registró.
 */

namespace Fw\Init\Ajax;

use Fw\Config\Apps;
use Fw\Core\Request\Actions;
use Fw\Core\Request\Request;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AjaxManager
{
	/**
	 * Acciones registradas en este proceso, por gancho.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $registered = array();

	public static function initialize( string $pluginSlug ): void
	{
		foreach ( self::discover( $pluginSlug ) as $action ) {
			self::register( $action );
		}
	}

	/**
	 * Gancho de WordPress: {slug}_{metodo}. El slug pasa los guiones a guion bajo.
	 */
	public static function hook( string $pluginSlug, string $action ): string
	{
		$slug = strtolower( str_replace( '-', '_', $pluginSlug ) );
		$slug = (string) preg_replace( '/[^a-z0-9_]/', '', $slug );
		$slug = trim( $slug, '_' );
		if ( $slug === '' ) {
			$slug = 'wpfw';
		}

		return $slug . '_' . $action;
	}

	public static function nonceAction( string $pluginSlug, string $action ): string
	{
		return 'wpfw_ajax_' . self::hook( $pluginSlug, $action );
	}

	/**
	 * Datos para localizar en JavaScript. Cada acción trae su action y su nonce.
	 *
	 * @return array{url: string, actions: array<string, array{action: string, nonce: string}>}
	 */
	public static function clientConfig( string $pluginSlug ): array
	{
		$actions = array();
		foreach ( self::$registered as $action ) {
			if ( ( $action['plugin'] ?? '' ) !== $pluginSlug ) {
				continue;
			}

			$name             = (string) $action['name'];
			$actions[ $name ] = array(
				'action' => (string) $action['hook'],
				'nonce'  => wp_create_nonce( self::nonceAction( $pluginSlug, $name ) ),
			);
		}

		return array(
			'url'     => admin_url( 'admin-ajax.php' ),
			'actions' => $actions,
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function discover( string $pluginSlug ): array
	{
		$paths  = Apps::getConfig( $pluginSlug, 'paths' );
		$config = Apps::getConfig( $pluginSlug, 'config' );
		if ( ! is_object( $paths ) || ! isset( $paths->controllers->ajax ) ) {
			return array();
		}

		$directory = $paths->controllers->ajax;
		if ( ! is_string( $directory ) || ! is_dir( $directory ) ) {
			return array();
		}

		$namespace = ( is_object( $config ) && isset( $config->namespace ) ) ? (string) $config->namespace : '';
		if ( $namespace === '' ) {
			return array();
		}

		$found = array();
		$files = glob( $directory . '/*.php' );
		if ( ! is_array( $files ) ) {
			return array();
		}

		foreach ( $files as $file ) {
			$base = basename( $file, '.php' );
			if ( Actions::sanitizeIdentifier( $base ) === null ) {
				continue;
			}

			$class = $namespace . '\\Controllers\\Ajax\\' . $base;
			if ( ! class_exists( $class ) || ! property_exists( $class, 'ajaxActions' ) || ! is_array( $class::$ajaxActions ) ) {
				continue;
			}

			foreach ( $class::$ajaxActions as $name => $settings ) {
				if ( ! is_string( $name ) || Actions::sanitizeIdentifier( $name ) === null ) {
					continue;
				}

				if ( ! Request::methodExists( $class, $name, 'isPublic' ) ) {
					continue;
				}

				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				$found[] = self::normalize( $pluginSlug, $class, $name, $settings );
			}
		}

		return $found;
	}

	/**
	 * @param array<string, mixed> $action
	 */
	public static function register( array $action ): void
	{
		$hook = (string) $action['hook'];
		if ( isset( self::$registered[ $hook ] ) ) {
			return;
		}

		self::$registered[ $hook ] = $action;

		add_action(
			'wp_ajax_' . $hook,
			static function () use ( $hook ): void {
				self::dispatch( $hook );
			}
		);

		if ( ! empty( $action['public'] ) ) {
			add_action(
				'wp_ajax_nopriv_' . $hook,
				static function () use ( $hook ): void {
					self::dispatch( $hook );
				}
			);
		}
	}

	/**
	 * El gancho llega cerrado en el callback. No se lee de la petición para elegir el método.
	 */
	public static function dispatch( string $hook ): void
	{
		$action = self::$registered[ $hook ] ?? null;
		if ( ! is_array( $action ) ) {
			self::fail( 'Acción no permitida.', 404 );
			return;
		}

		$plugin = (string) $action['plugin'];
		$name   = (string) $action['name'];

		if ( ! empty( $action['nonce'] ) && ! self::verifyNonce( $plugin, $name ) ) {
			self::fail( 'El nonce no es válido.', 403 );
			return;
		}

		$capability = (string) $action['capability'];
		if ( $capability !== '' && ! current_user_can( $capability ) ) {
			self::fail( 'No tienes permiso para esta acción.', 403 );
			return;
		}

		try {
			$controller = (string) $action['controller'];
			$method     = (string) $action['method'];
			$result     = ( new $controller() )->$method();
		} catch ( Throwable $e ) {
			$message = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				? $e->getMessage()
				: 'No se pudo completar la acción.';
			self::fail( $message, 500 );
			return;
		}

		$payload = is_array( $result ) ? $result : array( 'datos' => $result );
		self::ok( $payload );
	}

	public static function verifyNonce( string $pluginSlug, string $action ): bool
	{
		if ( ! isset( $_REQUEST['_ajax_nonce'] ) || ! is_string( $_REQUEST['_ajax_nonce'] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );

		return (bool) wp_verify_nonce( $nonce, self::nonceAction( $pluginSlug, $action ) );
	}

	/**
	 * Vacía el registro. Pensado para las pruebas; los ganchos de WordPress no se quitan solos.
	 */
	public static function reset(): void
	{
		self::$registered = array();
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function normalize( string $pluginSlug, string $class, string $name, array $settings ): array
	{
		$public = (bool) ( $settings['public'] ?? false );
		$nonce  = array_key_exists( 'nonce', $settings ) ? (bool) $settings['nonce'] : true;

		if ( isset( $settings['capability'] ) && is_string( $settings['capability'] ) && $settings['capability'] !== '' ) {
			$capability = $settings['capability'];
		} else {
			$capability = $public ? '' : 'manage_options';
		}

		if ( preg_match( '/^[A-Za-z0-9_]+$/', $capability ) !== 1 ) {
			$capability = $public ? '' : 'manage_options';
		}

		if ( ! $public && $capability === '' ) {
			$capability = 'manage_options';
		}

		return array(
			'plugin'     => $pluginSlug,
			'name'       => $name,
			'controller' => $class,
			'method'     => $name,
			'public'     => $public,
			'nonce'      => $nonce,
			'capability' => $capability,
			'hook'       => self::hook( $pluginSlug, $name ),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function ok( array $data ): void
	{
		wp_send_json_success( $data );
	}

	private static function fail( string $message, int $status ): void
	{
		wp_send_json_error( array( 'mensaje' => $message ), $status );
	}
}
