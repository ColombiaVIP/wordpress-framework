<?php
/**
 * Despacho web (rutas con enableUri): métodos public por defecto, como en 1.3.1,
 * con la comprobación isPublic restaurada.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey\Functions;
use Fw\Core\Request\Actions;
use Fw\Core\Request\RequestWeb;
use Fw\Init\Routing\Route;
use Fw\Init\Routing\Router;
use Fw\Init\Routing\RoutingManager;
use Fw\Init\Routing\RoutingProcessor;

class ControladorWeb
{
	public static string $ultimo = '';

	public function index(): void
	{
		self::$ultimo = 'index';
	}

	public function saludo(): void
	{
		self::$ultimo = 'saludo';
	}

	public static function estatico(): void
	{
		self::$ultimo = 'estatico';
	}

	public function error404(): void
	{
		self::$ultimo = 'error404';
	}

	private function oculto(): void
	{
		self::$ultimo = 'oculto';
	}

	protected function protegido(): void
	{
		self::$ultimo = 'protegido';
	}

	public function __destruct()
	{
	}
}

class ControladorSin404
{
	public static string $ultimo = '';

	public function index(): void
	{
		self::$ultimo = 'index';
	}

	private function oculto(): void
	{
		self::$ultimo = 'oculto';
	}
}

/**
 * Igual que Proyectos\Controllers\Routes\ProyectosController de ingeurbe:
 * enableUri, __call para los slugs, error404 con parámetro opcional y métodos private/protected.
 */
class ControladorSlugs
{
	public static bool $enableUri = true;

	public static string $ultimo = '';

	public function __construct()
	{
	}

	public function index(): void
	{
		self::$ultimo = 'index';
	}

	public function __call( $metodo, $arguments )
	{
		self::$ultimo = '__call:' . $metodo;
	}

	public function error404( $metodo = 'VACIO' ): void
	{
		self::$ultimo = 'error404';
	}

	private function private(): void
	{
		self::$ultimo = 'private';
	}

	protected function protected(): void
	{
		self::$ultimo = 'protected';
	}
}

class ControladorExplota
{
	public function index(): void
	{
		throw new \RuntimeException( 'boom-interno' );
	}
}

class DespachoRutasTest extends FrameworkTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		ControladorWeb::$ultimo    = '';
		ControladorSin404::$ultimo = '';
		ControladorSlugs::$ultimo  = '';
	}

	public function test_un_metodo_public_es_accion_web_sin_declararlo(): void
	{
		self::assertTrue( Actions::isPublicAction( ControladorWeb::class, 'index' ) );
		self::assertTrue( Actions::isPublicAction( ControladorWeb::class, 'saludo' ) );
		self::assertTrue( Actions::isPublicAction( ControladorWeb::class, 'estatico' ) );
		self::assertTrue( Actions::isPublicAction( ControladorWeb::class, 'error404' ) );
	}

	public function test_private_protected_y_magicos_nunca_son_accion_web(): void
	{
		self::assertFalse( Actions::isPublicAction( ControladorWeb::class, 'oculto' ) );
		self::assertFalse( Actions::isPublicAction( ControladorWeb::class, 'protegido' ) );
		self::assertFalse( Actions::isPublicAction( ControladorWeb::class, '__construct' ) );
		self::assertFalse( Actions::isPublicAction( ControladorWeb::class, '__destruct' ) );
		self::assertFalse( Actions::isPublicAction( ControladorSlugs::class, '__call' ) );
		self::assertFalse( Actions::isPublicAction( ControladorSlugs::class, '__construct' ) );
		self::assertFalse( Actions::isPublicAction( ControladorWeb::class, '../saludo' ) );
		self::assertFalse( Actions::isPublicAction( ControladorWeb::class, 'no_existe' ) );
	}

	public function test_el_segmento_de_la_url_ejecuta_cualquier_metodo_public(): void
	{
		$this->visitar( true, array( 'plugin_a_method' => 'saludo' ) )->currentRequest->send();
		self::assertSame( 'saludo', ControladorWeb::$ultimo );
	}

	public function test_el_query_var_method_de_1_3_1_sigue_funcionando(): void
	{
		// Reglas guardadas por 1.3.1 (&method=$matches[1]) antes de regenerarse.
		$this->visitar( true, array( 'method' => 'saludo' ) )->currentRequest->send();
		self::assertSame( 'saludo', ControladorWeb::$ultimo );
	}

	public function test_sin_enable_uri_el_metodo_de_la_url_se_ignora(): void
	{
		$processor = $this->visitar(
			false,
			array(
				'plugin_a_method' => 'saludo',
				'method'          => 'saludo',
			)
		);

		self::assertSame( 'index', $processor->currentRequest->getMethod() );
		$processor->currentRequest->send();
		self::assertSame( 'index', ControladorWeb::$ultimo );
	}

	public function test_private_y_protected_por_url_caen_en_error404(): void
	{
		foreach ( array( 'oculto', 'protegido', '__construct', '__destruct' ) as $metodo ) {
			ControladorWeb::$ultimo = '';
			RoutingProcessor::resetCurrent();
			$this->visitar( true, array( 'plugin_a_method' => $metodo ) )->currentRequest->send();
			self::assertSame( 'error404', ControladorWeb::$ultimo, $metodo );
		}
	}

	public function test_sin_error404_la_ruta_devuelve_la_plantilla_del_tema_y_no_wp_die(): void
	{
		Functions\expect( 'wp_die' )->never();
		Functions\when( 'get_404_template' )->justReturn( '/tmp/tema-404.php' );

		$route = new Route( '/demo', ControladorSin404::class );
		$route->enableUri = true;
		$router = new Router( 'plugin-a', false );
		$router->pluginPath = sys_get_temp_dir();
		$processor = new RoutingProcessor( $router, array( 'demo' => $route ) );
		$processor->registerRoutes();

		$GLOBALS['wpfw_test_query_vars'] = array( 'plugin_a_method' => 'oculto' );
		$processor->matchRequest( $this->peticion( 'demo' ) );
		$plantilla = $processor->loadRouteController( 'original.php' );

		self::assertSame( '/tmp/tema-404.php', $plantilla );
		self::assertSame( '', ControladorSin404::$ultimo );
		self::assertContains( 404, $GLOBALS['wpfw_test_status'] );
	}

	public function test_slugs_desconocidos_los_atiende_call_como_en_ingeurbe(): void
	{
		$this->visitarSlugs( 'torre-nogal-etapa-2' )->currentRequest->send();
		self::assertSame( '__call:torre-nogal-etapa-2', ControladorSlugs::$ultimo );
	}

	public function test_con_call_los_private_y_protected_no_llegan_a_call_sino_a_error404(): void
	{
		foreach ( array( 'private', 'protected', '__construct', '__call', '__get' ) as $metodo ) {
			ControladorSlugs::$ultimo = '';
			RoutingProcessor::resetCurrent();
			$this->visitarSlugs( $metodo )->currentRequest->send();
			self::assertSame( 'error404', ControladorSlugs::$ultimo, $metodo );
		}
	}

	public function test_index_sigue_siendo_el_metodo_por_defecto(): void
	{
		$this->visitarSlugs( '' )->currentRequest->send();
		self::assertSame( 'index', ControladorSlugs::$ultimo );
	}

	public function test_una_ruta_desconocida_no_llama_a_wp_die(): void
	{
		Functions\expect( 'wp_die' )->never();
		Functions\when( 'get_404_template' )->justReturn( '/tmp/tema-404.php' );

		$processor = $this->processor( false );
		$processor->matchRequest( $this->peticion( 'no-existe' ) );
		$plantilla = $processor->loadRouteController( 'original.php' );

		self::assertSame( '/tmp/tema-404.php', $plantilla );
		self::assertNull( $processor->currentRequest );
	}

	public function test_un_throwable_del_controlador_no_deja_la_peticion_en_blanco(): void
	{
		$archivo = tempnam( sys_get_temp_dir(), 'wpfw404' );
		file_put_contents( $archivo, '<?php $GLOBALS["wpfw_404_incluido"] = true;' );
		Functions\when( 'get_404_template' )->justReturn( $archivo );

		$request = new RequestWeb( sys_get_temp_dir(), ControladorExplota::class, 'index' );
		$request->send();

		self::assertTrue( $GLOBALS['wpfw_404_incluido'] ?? false );
		unlink( $archivo );
	}

	public function test_reglas_con_query_var_del_plugin_method_compatible_y_flush_una_vez(): void
	{
		require_once __DIR__ . '/Fixtures/DemoController.php';

		$dir = sys_get_temp_dir() . '/wpfw-rutas-' . uniqid( '', true ) . '/app/Controllers/Routes';
		mkdir( $dir, 0777, true );
		file_put_contents( $dir . '/DemoController.php', "<?php\n" );
		$pluginFile = dirname( $dir, 3 ) . '/demo.php';
		file_put_contents( $pluginFile, "<?php\n" );

		RoutingManager::initialize( 'plugin-a', $dir, array( 'force' => false ), $pluginFile );

		$init = $GLOBALS['wpfw_test_actions']['init'][0];
		$init();

		self::assertArrayHasKey( '%plugin_a_method%', $GLOBALS['wpfw_test_tags'] );
		self::assertArrayHasKey( '%method%', $GLOBALS['wpfw_test_tags'] );

		$consulta = implode( ' ', array_column( $GLOBALS['wpfw_test_rules'], 'query' ) );
		self::assertStringContainsString( 'plugin_a_method=$matches[1]', $consulta );

		// Primera carga tras actualizar: se regeneran los permalinks una vez, en wp_loaded.
		self::assertSame( 0, $GLOBALS['wpfw_test_flushes'] );
		$loaded = $GLOBALS['wpfw_test_actions']['wp_loaded'][0];
		$loaded();
		self::assertSame( 1, $GLOBALS['wpfw_test_flushes'] );
		$loaded();
		self::assertSame( 1, $GLOBALS['wpfw_test_flushes'] );

		// Segunda carga: el hash ya está guardado, no se programa otro flush.
		$GLOBALS['wpfw_test_actions']['wp_loaded'] = array();
		$init();
		self::assertArrayNotHasKey( 0, $GLOBALS['wpfw_test_actions']['wp_loaded'] );

		$activar = $GLOBALS['wpfw_test_activation'][ $pluginFile ][0];
		$activar();
		self::assertSame( 2, $GLOBALS['wpfw_test_flushes'] );
	}

	public function test_sin_enable_uri_no_se_registra_el_query_var_de_metodo(): void
	{
		$this->processor( false )->registerRoutes();

		self::assertArrayNotHasKey( '%method%', $GLOBALS['wpfw_test_tags'] );
		self::assertArrayNotHasKey( '%plugin_a_method%', $GLOBALS['wpfw_test_tags'] );
	}

	/**
	 * @param array<string, string> $queryVars
	 */
	private function visitar( bool $enableUri, array $queryVars ): RoutingProcessor
	{
		$processor = $this->processor( $enableUri );
		$GLOBALS['wpfw_test_query_vars'] = $queryVars;
		$processor->matchRequest( $this->peticion( 'demo' ) );
		$processor->loadRouteController( 'index.php' );

		return $processor;
	}

	private function visitarSlugs( string $slug ): RoutingProcessor
	{
		$route = new Route( '/proyectos', ControladorSlugs::class );
		$route->enableUri = true;
		$router = new Router( 'proyectos', false );
		$router->pluginPath = sys_get_temp_dir();
		$processor = new RoutingProcessor( $router, array( 'proyectos' => $route ) );
		$processor->registerRoutes();

		$GLOBALS['wpfw_test_query_vars'] = array( 'proyectos_method' => $slug );
		$wp = new \WP();
		$wp->query_vars = array( 'proyectos' => 'proyectos' );
		$processor->matchRequest( $wp );
		$processor->loadRouteController( 'index.php' );

		return $processor;
	}

	private function processor( bool $enableUri ): RoutingProcessor
	{
		$route = new Route( '/demo', ControladorWeb::class );
		$route->enableUri = $enableUri;
		$router = new Router( 'plugin-a', false );
		$router->pluginPath = sys_get_temp_dir();
		$processor = new RoutingProcessor( $router, array( 'demo' => $route ) );
		$processor->registerRoutes();

		return $processor;
	}

	private function peticion( string $ruta ): \WP
	{
		$wp = new \WP();
		$wp->query_vars = array( 'plugin-a' => $ruta );

		return $wp;
	}
}
