<?php
/**
 * Lista blanca del despacho web y el query var method.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey\Functions;
use Fw\Core\Request\Actions;
use Fw\Core\Request\RequestWeb;
use Fw\Core\Request\WebAction;
use Fw\Init\Routing\Route;
use Fw\Init\Routing\Router;
use Fw\Init\Routing\RoutingManager;
use Fw\Init\Routing\RoutingProcessor;

class ControladorWeb
{
	public static array $webActions = array( 'saludo' );

	public static string $ultimo = '';

	public function index(): void
	{
		self::$ultimo = 'index';
	}

	public function saludo(): void
	{
		self::$ultimo = 'saludo';
	}

	public function secreto(): void
	{
		self::$ultimo = 'secreto';
	}

	public function error404(): void
	{
		self::$ultimo = 'error404';
	}

	private function oculto(): void
	{
		self::$ultimo = 'oculto';
	}
}

class ControladorSin404
{
	public static string $ultimo = '';

	public function index(): void
	{
		self::$ultimo = 'index';
	}

	public function secreto(): void
	{
		self::$ultimo = 'secreto';
	}
}

class ControladorAtributo
{
	public function index(): void
	{
	}

	#[WebAction]
	public function saludar(): void
	{
	}

	public function otro(): void
	{
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
	}

	public function test_un_metodo_public_no_es_accion_web_si_no_esta_declarado(): void
	{
		self::assertTrue( Actions::isAllowed( ControladorWeb::class, 'index' ) );
		self::assertTrue( Actions::isAllowed( ControladorWeb::class, 'error404' ) );
		self::assertTrue( Actions::isAllowed( ControladorWeb::class, 'saludo' ) );
		self::assertFalse( Actions::isAllowed( ControladorWeb::class, 'secreto' ) );
		self::assertFalse( Actions::isAllowed( ControladorWeb::class, 'oculto' ) );
		self::assertFalse( Actions::isAllowed( ControladorWeb::class, '__construct' ) );
		self::assertFalse( Actions::isAllowed( ControladorWeb::class, '../secreto' ) );
		self::assertTrue( Actions::isAllowed( ControladorAtributo::class, 'saludar' ) );
		self::assertFalse( Actions::isAllowed( ControladorAtributo::class, 'otro' ) );
	}

	public function test_method_por_query_string_no_se_ejecuta_si_enable_uri_es_false(): void
	{
		$processor = $this->processor( false );
		$GLOBALS['wpfw_test_query_vars'] = array(
			'plugin_a_method' => 'secreto',
			'method'          => 'secreto',
		);

		$processor->matchRequest( $this->peticion( 'demo' ) );
		$processor->loadRouteController( 'index.php' );

		self::assertSame( 'index', $processor->currentRequest->getMethod() );
		$processor->currentRequest->send();
		self::assertSame( 'index', ControladorWeb::$ultimo );
	}

	public function test_el_query_var_global_method_no_elige_el_metodo_aunque_enable_uri_sea_true(): void
	{
		$processor = $this->processor( true );
		$GLOBALS['wpfw_test_query_vars'] = array(
			'method' => 'secreto',
		);

		$processor->matchRequest( $this->peticion( 'demo' ) );
		$processor->loadRouteController( 'index.php' );

		self::assertSame( 'index', $processor->currentRequest->getMethod() );
	}

	public function test_el_segmento_del_plugin_solo_ejecuta_la_lista_blanca(): void
	{
		$processor = $this->processor( true );
		$GLOBALS['wpfw_test_query_vars'] = array(
			'plugin_a_method' => 'saludo',
			'method'          => 'secreto',
		);

		$processor->matchRequest( $this->peticion( 'demo' ) );
		$processor->loadRouteController( 'index.php' );
		$processor->currentRequest->send();

		self::assertSame( 'saludo', ControladorWeb::$ultimo );
	}

	public function test_un_metodo_fuera_de_la_lista_cae_en_error404_del_controlador(): void
	{
		$processor = $this->processor( true );
		$GLOBALS['wpfw_test_query_vars'] = array(
			'plugin_a_method' => 'secreto',
		);

		$processor->matchRequest( $this->peticion( 'demo' ) );
		$plantilla = $processor->loadRouteController( 'index.php' );
		$processor->currentRequest->send();

		self::assertStringEndsWith( 'template.php', $plantilla );
		self::assertSame( 'error404', ControladorWeb::$ultimo );
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

		$GLOBALS['wpfw_test_query_vars'] = array( 'plugin_a_method' => 'secreto' );
		$processor->matchRequest( $this->peticion( 'demo' ) );
		$plantilla = $processor->loadRouteController( 'original.php' );

		self::assertSame( '/tmp/tema-404.php', $plantilla );
		self::assertSame( '', ControladorSin404::$ultimo );
		self::assertContains( 404, $GLOBALS['wpfw_test_status'] );
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

	public function test_flush_rewrite_rules_queda_en_la_activacion_y_el_query_var_es_del_plugin(): void
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

		self::assertSame( 0, $GLOBALS['wpfw_test_flushes'] );
		self::assertArrayNotHasKey( '%method%', $GLOBALS['wpfw_test_tags'] );
		self::assertArrayHasKey( '%plugin_a_method%', $GLOBALS['wpfw_test_tags'] );

		$consulta = implode( ' ', array_column( $GLOBALS['wpfw_test_rules'], 'query' ) );
		self::assertStringContainsString( 'plugin_a_method=$matches[1]', $consulta );
		self::assertStringNotContainsString( '&method=', $consulta );
		self::assertStringNotContainsString( '?method=', $consulta );

		$activar = $GLOBALS['wpfw_test_activation'][ $pluginFile ][0];
		$activar();
		self::assertSame( 1, $GLOBALS['wpfw_test_flushes'] );
	}

	public function test_sin_enable_uri_no_se_registra_el_query_var_de_metodo(): void
	{
		$this->processor( false )->registerRoutes();

		self::assertArrayNotHasKey( '%method%', $GLOBALS['wpfw_test_tags'] );
		self::assertArrayNotHasKey( '%plugin_a_method%', $GLOBALS['wpfw_test_tags'] );
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
