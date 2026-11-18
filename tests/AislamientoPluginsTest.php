<?php
/**
 * Dos plugins del framework no comparten el request ni el query var de método.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Fw\Core\Request\RequestWeb;
use Fw\Init\Routing\Route;
use Fw\Init\Routing\Router;
use Fw\Init\Routing\RoutingProcessor;

class ControladorPluginA
{
	public static int $ejecuciones = 0;

	public function index(): void
	{
		self::$ejecuciones++;
	}
}

class ControladorPluginB
{
	public static int $ejecuciones = 0;

	public function index(): void
	{
		self::$ejecuciones++;
	}
}

class AislamientoPluginsTest extends FrameworkTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		ControladorPluginA::$ejecuciones = 0;
		ControladorPluginB::$ejecuciones = 0;
	}

	public function test_los_query_var_de_metodo_no_coinciden(): void
	{
		$loteria = new Router( 'cvip-loteria' );
		$otro    = new Router( 'otro-plugin' );

		self::assertSame( 'cvip_loteria_method', $loteria->getMethodQueryVar() );
		self::assertSame( 'otro_plugin_method', $otro->getMethodQueryVar() );
		self::assertNotSame( $loteria->getMethodQueryVar(), $otro->getMethodQueryVar() );
	}

	public function test_la_plantilla_no_recorre_wp_filter_y_ejecuta_solo_el_plugin_que_coincidio(): void
	{
		$fuente = (string) file_get_contents( dirname( __DIR__ ) . '/Fw/Init/Routing/template.php' );
		self::assertStringNotContainsString( '$wp_filter', $fuente );

		$procesadorA = $this->processor( 'plugin-a', ControladorPluginA::class );
		$procesadorB = $this->processor( 'plugin-b', ControladorPluginB::class );

		$procesadorB->currentRequest = new RequestWeb( sys_get_temp_dir(), ControladorPluginB::class, 'index' );
		$GLOBALS['wp_filter'] = array(
			'template_include' => (object) array(
				'callbacks' => array(
					10 => array(
						'b' => array( 'function' => array( $procesadorB, 'loadRouteController' ) ),
						'a' => array( 'function' => array( $procesadorA, 'loadRouteController' ) ),
					),
				),
			),
		);

		$procesadorA->matchRequest( $this->peticion( 'plugin-a', 'home' ) );
		$procesadorA->loadRouteController( 'index.php' );

		include dirname( __DIR__ ) . '/Fw/Init/Routing/template.php';

		self::assertSame( 1, ControladorPluginA::$ejecuciones );
		self::assertSame( 0, ControladorPluginB::$ejecuciones );
		self::assertSame( 1, $GLOBALS['wpfw_test_block_template'] );
	}

	public function test_un_plugin_que_no_encuentra_su_ruta_no_pisa_al_que_si_coincidio(): void
	{
		$procesadorA = $this->processor( 'plugin-a', ControladorPluginA::class );
		$procesadorB = $this->processor( 'plugin-b', ControladorPluginB::class );

		$wp = new \WP();
		$wp->query_vars = array(
			'plugin-a' => 'home',
			'plugin-b' => 'fantasma',
		);
		$GLOBALS['wp_query'] = new class() {
			public bool $is_404 = false;

			public function set_404(): void
			{
				$this->is_404 = true;
			}
		};

		$procesadorB->matchRequest( $wp );
		$primera = $procesadorB->loadRouteController( 'original.php' );
		$procesadorA->matchRequest( $wp );
		$segunda = $procesadorA->loadRouteController( $primera );
		$despues = $procesadorB->loadRouteController( $segunda );
		self::assertSame( $segunda, $despues );
		RoutingProcessor::dispatchCurrent();

		self::assertStringEndsWith( 'template.php', $segunda );
		self::assertSame( 1, ControladorPluginA::$ejecuciones );
		self::assertSame( 0, ControladorPluginB::$ejecuciones );
		self::assertFalse( $GLOBALS['wp_query']->is_404 );
	}

	private function processor( string $nombre, string $controlador ): RoutingProcessor
	{
		$route = new Route( '/home', $controlador );
		$router = new Router( $nombre, false );
		$router->pluginPath = sys_get_temp_dir();
		$processor = new RoutingProcessor( $router, array( 'home' => $route ) );
		$processor->registerRoutes();

		return $processor;
	}

	private function peticion( string $plugin, string $ruta ): \WP
	{
		$wp = new \WP();
		$wp->query_vars = array( $plugin => $ruta );

		return $wp;
	}
}
