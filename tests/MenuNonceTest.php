<?php
/**
 * Nonce, capability y el controlador de menú en el índice 0.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey\Functions;
use Fw\Core\Request\Request;
use Fw\Core\Request\RequestMenuPage;
use Fw\Init\MenuPage\MenuPagesManager;

class ControladorMenu
{
	public static string $capability = 'manage_options';

	public static array $webActions = array( 'guardar' );

	public static int $construidos = 0;

	public static string $ultimo = '';

	public function __construct()
	{
		self::$construidos++;
	}

	public function index(): void
	{
		self::$ultimo = 'index';
	}

	public function guardar(): void
	{
		self::$ultimo = 'guardar';
	}

	public function secreto(): void
	{
		self::$ultimo = 'secreto';
	}
}

class MenuNonceTest extends FrameworkTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		ControladorMenu::$construidos = 0;
		ControladorMenu::$ultimo      = '';
		Functions\when( 'current_user_can' )->alias(
			function ( string $capability ): bool {
				$GLOBALS['wpfw_caps'][] = $capability;
				return (bool) $GLOBALS['wpfw_can'];
			}
		);
	}

	public function test_option_sin_nonce_no_ejecuta_el_metodo(): void
	{
		$_GET['page']   = 'panel';
		$_GET['option'] = 'guardar';
		$GLOBALS['wpfw_can'] = true;

		$request = new RequestMenuPage( sys_get_temp_dir(), ControladorMenu::class, 'index' );
		self::assertNull( $request->validations() );
		self::assertSame( 0, ControladorMenu::$construidos );
		self::assertSame( '', ControladorMenu::$ultimo );
	}

	public function test_option_con_nonce_y_capability_ejecuta_solo_la_accion_declarada(): void
	{
		$this->nonce( 'panel', 'guardar' );
		$GLOBALS['wpfw_can'] = true;

		$request  = new RequestMenuPage( sys_get_temp_dir(), ControladorMenu::class, 'index' );
		$callback = $request->validations();

		self::assertIsArray( $callback );
		$callback[0]->{$callback[1]}();
		self::assertSame( 'guardar', ControladorMenu::$ultimo );
		self::assertContains( 'manage_options', $GLOBALS['wpfw_caps'] );
	}

	public function test_sin_capability_no_hay_accion(): void
	{
		$this->nonce( 'panel', 'guardar' );
		$GLOBALS['wpfw_can'] = false;

		$request = new RequestMenuPage( sys_get_temp_dir(), ControladorMenu::class, 'index' );
		self::assertNull( $request->validations() );
		self::assertSame( 0, ControladorMenu::$construidos );
	}

	public function test_option_fuera_de_la_lista_no_se_ejecuta_aunque_el_nonce_sea_valido(): void
	{
		$this->nonce( 'panel', 'secreto' );
		$GLOBALS['wpfw_can'] = true;

		$request = new RequestMenuPage( sys_get_temp_dir(), ControladorMenu::class, 'index' );
		self::assertNull( $request->validations() );
		self::assertSame( '', ControladorMenu::$ultimo );
	}

	public function test_la_pantalla_principal_no_exige_nonce(): void
	{
		$_GET['page'] = 'panel';
		$GLOBALS['wpfw_can'] = true;

		$request  = new RequestMenuPage( sys_get_temp_dir(), ControladorMenu::class, 'index' );
		$callback = $request->validations();
		self::assertIsArray( $callback );
		self::assertSame( 'index', $callback[1] );
	}

	public function test_la_capability_por_defecto_es_manage_options(): void
	{
		require_once __DIR__ . '/Fixtures/LibrosController.php';

		self::assertSame(
			'manage_options',
			Request::controllerCapability( \CapDemo\Controllers\MenuPages\Libros\LibrosController::class )
		);
		self::assertSame(
			'edit_pages',
			Request::controllerCapability( \CapDemo\Controllers\MenuPages\Libros\EspecialController::class )
		);

		$manager = new MenuPagesManager( 'CapDemo', sys_get_temp_dir() );
		$pagina  = $manager->prepareMenuPage( \CapDemo\Controllers\MenuPages\Libros\LibrosController::class );
		self::assertSame( 'manage_options', $pagina['capability'] );
	}

	public function test_el_controlador_de_menu_en_el_indice_cero_sigue_siendo_el_principal(): void
	{
		$root  = sys_get_temp_dir() . '/wp-content/plugins/demomenu-' . uniqid( '', true ) . '/app/Controllers/MenuPages';
		$alpha = $root . '/Alpha';
		mkdir( $alpha, 0777, true );
		file_put_contents( $alpha . '/AlphaController.php', "<?php\n" );
		file_put_contents( $alpha . '/ZedController.php', "<?php\n" );

		$manager = new MenuPagesManager( 'CapDemo', $root );
		$manager->prepare();

		self::assertNotEmpty( $GLOBALS['wpfw_test_menus'] );
		self::assertSame( 'manage_options', $GLOBALS['wpfw_test_menus'][0][2] );
		self::assertSame( 'capdemo-alpha-alpha', $GLOBALS['wpfw_test_menus'][0][3] );

		$slugs = array_column( $GLOBALS['wpfw_test_submenus'], 4 );
		self::assertContains( 'capdemo-alpha-zed', $slugs );
		self::assertNotContains( 'capdemo-alpha-zed', array( $GLOBALS['wpfw_test_menus'][0][3] ) );
	}

	private function nonce( string $page, string $option ): void
	{
		$_GET['page']   = $page;
		$_GET['option'] = $option;
		$_REQUEST['_wpfw_nonce'] = 'nonce-wpfw_option_' . $page . '_' . $option;
	}
}
