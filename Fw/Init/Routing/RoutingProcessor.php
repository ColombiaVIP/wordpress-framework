<?php
/**
 * Se encarga de la interacción entre el sistema de enrutamiento y el resto de WordPress.
 *
 * Cada plugin conserva su processor. La plantilla no recorre $wp_filter:
 * despacha al processor que reclamó esta petición.
 */

namespace Fw\Init\Routing;

use Fw\Core\Request\Actions;
use Fw\Core\Request\Request;
use Fw\Core\Request\RequestWeb;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoutingProcessor
{
	/** @var Route|null $matchedRoute La ruta coincidente encontrada por el router. */
	private $matchedRoute = null;

	/** @var Router $router */
	private $router;

	/** @var Route[] $routes Las rutas que queremos registrar en WordPress. */
	private $routes;

	/** @var RequestWeb|null $currentRequest El request actual para la ruta. */
	public $currentRequest = null;

	private bool $notFound = false;

	/**
	 * Processor que sí coincidió con la URL. La plantilla lo usa directo.
	 */
	private static ?self $current = null;

	public function __construct( Router $router, array $routes = array() )
	{
		$this->router = $router;
		$this->routes = $routes;
	}

	/**
	 * @param Route[] $routes
	 */
	public static function init( Router $router, array $routes = array() ): self
	{
		$self = new self( $router, $routes );

		add_action( 'init', array( $self, 'registerRoutes' ) );
		add_action( 'parse_request', array( $self, 'matchRequest' ) );
		add_action( 'template_include', array( $self, 'loadRouteController' ) );

		return $self;
	}

	/**
	 * Registra las reglas. Como en 1.3.1, si las rutas cambian (o cambia el formato de las reglas
	 * del framework) se regeneran los permalinks una vez; ahora en wp_loaded, cuando todos
	 * los plugins ya registraron sus reglas. También se regeneran al activar el plugin.
	 */
	public function registerRoutes(): void
	{
		foreach ( $this->routes as $name => $route ) {
			if ( $route->enableUri ) {
				$this->router->addRuleEnableUri( $name, $route );
			}
			$this->router->addRoute( $name, $route );
		}

		$this->router->compile();

		if ( function_exists( 'get_option' ) && $this->routesHash() !== get_option( $this->hashOption() ) ) {
			add_action( 'wp_loaded', array( $this, 'flushIfChanged' ) );
		}
	}

	/**
	 * Regenera los permalinks si el hash de las rutas cambió.
	 */
	public function flushIfChanged(): void
	{
		$hash = $this->routesHash();
		if ( $hash === get_option( $this->hashOption() ) ) {
			return;
		}

		flush_rewrite_rules();
		update_option( $this->hashOption(), $hash );
	}

	private function hashOption(): string
	{
		return $this->router->getRouteName() . '_routesHash';
	}

	/**
	 * Incluye el query var de método: al pasar de 1.3.1 (method) a la versión por plugin
	 * las reglas guardadas se regeneran solas una vez.
	 */
	private function routesHash(): string
	{
		return md5( serialize( $this->routes ) . '|' . $this->router->getMethodQueryVar() );
	}

	/**
	 * Intenta hacer coincidir la solicitud actual con una ruta.
	 * Una ruta desconocida no corta la petición con wp_die.
	 */
	public function matchRequest( \WP $environment ): void
	{
		$matchedRoute = $this->router->match( $environment->query_vars );

		if ( $matchedRoute instanceof Route ) {
			$this->matchedRoute = $matchedRoute;
			$this->notFound     = false;
			return;
		}

		$this->matchedRoute = null;
		$this->notFound     = $matchedRoute instanceof \WP_Error
			&& in_array( 'route_not_found', $matchedRoute->get_error_codes(), true );
	}

	/**
	 * Si hay ruta, carga su controlador. El método de la URL solo se lee
	 * cuando esa ruta tiene enableUri, y desde el query var del plugin.
	 */
	public function loadRouteController( string $template ): string
	{
		if ( $this->notFound ) {
			if ( self::$current instanceof self ) {
				return $template;
			}

			ThemeNotFound::mark();
			$notFoundTemplate = ThemeNotFound::template();

			return $notFoundTemplate !== '' ? $notFoundTemplate : $template;
		}

		if ( ! $this->matchedRoute instanceof Route || ! $this->matchedRoute->hasController() ) {
			return $template;
		}

		$controller = $this->matchedRoute->getControllerName();
		if ( ! class_exists( $controller ) ) {
			return $template;
		}

		$method = 'index';
		if ( $this->matchedRoute->enableUri ) {
			// Query var del plugin; "method" se sigue leyendo por compatibilidad con 1.3.1
			// (reglas viejas aún sin regenerar y enlaces ?method=).
			$fromUri = get_query_var( $this->router->getMethodQueryVar() );
			if ( ! is_string( $fromUri ) || $fromUri === '' ) {
				$fromUri = get_query_var( Router::LEGACY_METHOD_VAR );
			}
			if ( is_string( $fromUri ) && $fromUri !== '' ) {
				$method = $fromUri;
			}
		}

		// Métodos public, __call para nombres desconocidos, o error404.
		$resolved = Actions::resolve( $controller, $method, true );
		if ( $resolved === null ) {
			if ( ! self::$current instanceof self ) {
				ThemeNotFound::mark();
				$notFoundTemplate = ThemeNotFound::template();
				if ( $notFoundTemplate !== '' ) {
					return $notFoundTemplate;
				}
			}

			return $template;
		}
		$method = $resolved;

		try {
			$request = new RequestWeb(
				$this->router->pluginPath,
				$controller,
				$method
			);
		} catch ( Throwable $e ) {
			unset( $e );
			ThemeNotFound::mark();
			return $template;
		}

		$this->currentRequest = $request;
		ThemeNotFound::clear();
		self::$current = $this;

		return \Fw\Paths::buildPath( __DIR__, 'template.php' );
	}

	public static function current(): ?self
	{
		return self::$current;
	}

	/**
	 * Lo llama template.php. No mira $wp_filter.
	 */
	public static function dispatchCurrent(): void
	{
		$processor = self::$current;
		if ( $processor instanceof self && $processor->currentRequest instanceof Request ) {
			$processor->currentRequest->send();
		}
	}

	public static function resetCurrent(): void
	{
		self::$current = null;
		ThemeNotFound::reset();
	}
}
