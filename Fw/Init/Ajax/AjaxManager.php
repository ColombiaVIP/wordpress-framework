<?php
/**
 * Capa AJAX (admin-ajax).
 *
 * Al arrancar se leen los controladores de app/Controllers/Ajax y cada método
 * public declarado en el plugin queda registrado como acción {slug}_{metodo}.
 * La petición no elige archivo, clase ni método: solo dispara un gancho que ya
 * se registró.
 *
 * Permisos, igual que en las Menu Pages:
 * - public static $capability: aplica a todos los métodos del controlador.
 * - public static array $capabilities = ['metodo' => 'cap']: la sobrescribe por método.
 * - Sin $capability: install_plugins (y aviso con WP_DEBUG).
 * - Capability '' (cadena vacía) = acción anónima: también wp_ajax_nopriv_ y sin current_user_can.
 * - public static $nonce: opt-in, true para todos o lista de métodos.
 *
 * Retrocompatibilidad: si el controlador declara $ajaxActions se usa el modo
 * de antes (1.4.0-dev): solo se registran las claves listadas, con sus ajustes.
 */

namespace Fw\Init\Ajax;

use Fw\Config\Apps;
use Fw\Core\Request\Actions;
use Fw\Core\Request\Request;
use ReflectionClass;
use ReflectionMethod;
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

	/**
	 * Controladores sin $capability (modo automático), para el aviso con WP_DEBUG.
	 *
	 * @var array<string, true>
	 */
	private static array $missingCapability = array();

	public static function initialize( string $pluginSlug ): void
	{
		foreach ( self::discover( $pluginSlug ) as $action ) {
			self::register( $action );
		}

		if ( self::$missingCapability !== array() && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action(
				'admin_init',
				static function (): void {
					foreach ( array_keys( self::$missingCapability ) as $class ) {
						self::noticeMissingCapability( $class );
					}
				}
			);
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
	 * Datos para localizar en JavaScript. Cada acción trae su action, su nonce,
	 * si es anónima y si exige nonce.
	 *
	 * Solo se incluyen las acciones que el usuario actual puede ejecutar
	 * (anónimas o con su capability), así un visitante no recibe la lista de
	 * acciones de administración.
	 *
	 * @return array{url: string, actions: array<string, array{action: string, nonce: string, anonimo: bool, requiereNonce: bool}>}
	 */
	public static function clientConfig( string $pluginSlug ): array
	{
		$actions = array();
		foreach ( self::$registered as $action ) {
			if ( ( $action['plugin'] ?? '' ) !== $pluginSlug ) {
				continue;
			}

			$capability = (string) $action['capability'];
			$anonymous  = ! empty( $action['public'] );
			if ( ! $anonymous && $capability !== '' && ! current_user_can( $capability ) ) {
				continue;
			}

			$name             = (string) $action['name'];
			$actions[ $name ] = array(
				'action'        => (string) $action['hook'],
				'nonce'         => wp_create_nonce( self::nonceAction( $pluginSlug, $name ) ),
				'anonimo'       => $anonymous,
				'requiereNonce' => ! empty( $action['nonce'] ),
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
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$reflection = new ReflectionClass( $class );
			if ( ! $reflection->isInstantiable() ) {
				continue;
			}

			# Modo anterior: solo lo que lista $ajaxActions, con sus ajustes.
			if ( property_exists( $class, 'ajaxActions' ) && is_array( $class::$ajaxActions ) ) {
				foreach ( self::legacyActions( $pluginSlug, $class ) as $action ) {
					$found[] = $action;
				}
				continue;
			}

			foreach ( self::publicMethods( $class, $namespace ) as $name ) {
				$found[] = self::fromController( $pluginSlug, $class, $name );
			}
		}

		return $found;
	}

	/**
	 * Acciones de un controlador con $ajaxActions (modo de antes, sin cambios).
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function legacyActions( string $pluginSlug, string $class ): array
	{
		$found = array();
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

		return $found;
	}

	/**
	 * Métodos que el modo automático registra.
	 *
	 * Entra un método si:
	 * - es public, no static y no abstract;
	 * - no es constructor ni destructor y no empieza por "__";
	 * - su nombre es un identificador válido;
	 * - está escrito en el plugin: la clase que lo declara (o el trait de donde sale)
	 *   está en el namespace del plugin. Lo heredado del framework (Fw\...),
	 *   de vendor o de WordPress no se expone, aunque sea public.
	 *
	 * Un controlador base propio del plugin (namespace del plugin) sí aporta sus
	 * métodos public: está escrito en el plugin igual que el hijo.
	 *
	 * @return list<string>
	 */
	public static function publicMethods( string $class, string $namespace ): array
	{
		if ( ! class_exists( $class ) ) {
			return array();
		}

		$prefix  = trim( $namespace, '\\' ) . '\\';
		$methods = array();

		foreach ( ( new ReflectionClass( $class ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if (
				$method->isStatic()
				|| $method->isAbstract()
				|| $method->isConstructor()
				|| $method->isDestructor()
				|| Actions::sanitizeIdentifier( $method->getName() ) === null
			) {
				continue;
			}

			if ( ! self::declaredInPlugin( $method, $prefix ) ) {
				continue;
			}

			$methods[] = $method->getName();
		}

		return $methods;
	}

	/**
	 * La clase que declara el método está en el plugin y, si el método viene de un
	 * trait, el trait también. Se compara el archivo porque ReflectionMethod
	 * informa como clase declarante a la que usa el trait.
	 */
	private static function declaredInPlugin( ReflectionMethod $method, string $prefix ): bool
	{
		$declaring = $method->getDeclaringClass();
		if ( ! str_starts_with( $declaring->getName(), $prefix ) ) {
			return false;
		}

		$file = $method->getFileName();
		if ( $file === $declaring->getFileName() ) {
			return true;
		}

		foreach ( self::traitsOf( $declaring ) as $trait ) {
			if ( $trait->getFileName() === $file && $trait->hasMethod( $method->getName() ) ) {
				return str_starts_with( $trait->getName(), $prefix );
			}
		}

		return false;
	}

	/**
	 * @return list<ReflectionClass>
	 */
	private static function traitsOf( ReflectionClass $class ): array
	{
		$traits = array();
		foreach ( $class->getTraits() as $trait ) {
			$traits[] = $trait;
			foreach ( self::traitsOf( $trait ) as $inner ) {
				$traits[] = $inner;
			}
		}

		return $traits;
	}

	/**
	 * Capability efectiva de un método en el modo automático.
	 *
	 * 1. $capabilities[metodo] si existe (sin distinguir mayúsculas), aunque sea ''.
	 * 2. $capability del controlador si es string, aunque sea ''.
	 * 3. install_plugins.
	 *
	 * '' significa acción anónima. Un valor con caracteres raros se trata como
	 * install_plugins: ante la duda, cerrado.
	 */
	public static function capabilityFor( string $class, string $method ): string
	{
		$capability = null;

		if ( property_exists( $class, 'capabilities' ) && is_array( $class::$capabilities ) ) {
			foreach ( $class::$capabilities as $name => $value ) {
				if ( is_string( $name ) && strtolower( $name ) === strtolower( $method ) && is_string( $value ) ) {
					$capability = $value;
					break;
				}
			}
		}

		if ( $capability === null && property_exists( $class, 'capability' ) && is_string( $class::$capability ) ) {
			$capability = $class::$capability;
		}

		if ( $capability === null ) {
			return Actions::DEFAULT_CAPABILITY;
		}

		$capability = trim( $capability );
		if ( $capability !== '' && preg_match( '/^[A-Za-z0-9_]+$/', $capability ) !== 1 ) {
			return Actions::DEFAULT_CAPABILITY;
		}

		return $capability;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function fromController( string $pluginSlug, string $class, string $name ): array
	{
		if ( ! property_exists( $class, 'capability' ) || ! is_string( $class::$capability ) ) {
			self::$missingCapability[ $class ] = true;
		}

		$capability = self::capabilityFor( $class, $name );

		return array(
			'plugin'     => $pluginSlug,
			'name'       => $name,
			'controller' => $class,
			'method'     => $name,
			'public'     => $capability === '',
			'nonce'      => Actions::requiresNonce( $class, $name ),
			'capability' => $capability,
			'hook'       => self::hook( $pluginSlug, $name ),
		);
	}

	/**
	 * Aviso (solo con WP_DEBUG y para quien puede activar plugins) cuando un
	 * controlador AJAX no declara $capability. No cambia el comportamiento:
	 * sus acciones siguen cerradas con install_plugins.
	 */
	public static function noticeMissingCapability( string $class ): bool
	{
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}

		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		if ( ! class_exists( 'AdminNotice' ) ) {
			require_once dirname( __DIR__, 2 ) . '/helpers/AdminNotice.php';
		}

		\AdminNotice::generalAdminNotice(
			'WP Framework',
			sprintf(
				'El controlador AJAX <code>%s</code> no declara <code>public static $capability</code>. Sus métodos public usan <code>%s</code> por defecto; decláralo para elegir quién puede llamarlos (<code>\'\'</code> = anónimo).',
				esc_html( $class ),
				esc_html( Actions::DEFAULT_CAPABILITY )
			),
			'warning',
			true
		);

		return true;
	}

	/**
	 * Controladores sin $capability detectados en el arranque.
	 *
	 * @return list<string>
	 */
	public static function controllersWithoutCapability(): array
	{
		return array_keys( self::$missingCapability );
	}

	/**
	 * Registra la acción. Si dos controladores del mismo plugin tienen un método con el
	 * mismo nombre, el gancho es el mismo y gana el primero (orden alfabético del
	 * archivo); con WP_DEBUG se avisa.
	 *
	 * @param array<string, mixed> $action
	 */
	public static function register( array $action ): void
	{
		$hook = (string) $action['hook'];
		if ( isset( self::$registered[ $hook ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && self::$registered[ $hook ]['controller'] !== $action['controller'] ) {
				trigger_error(
					sprintf(
						'WP Framework: la acción AJAX %s ya la registra %s; se ignora %s::%s.',
						$hook,
						(string) self::$registered[ $hook ]['controller'],
						(string) $action['controller'],
						(string) $action['method']
					),
					E_USER_NOTICE
				);
			}
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
		self::$registered        = array();
		self::$missingCapability = array();
	}

	/**
	 * Acción registrada para un gancho (o null). Útil en pruebas y diagnósticos.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function registered( string $hook ): ?array
	{
		return self::$registered[ $hook ] ?? null;
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
