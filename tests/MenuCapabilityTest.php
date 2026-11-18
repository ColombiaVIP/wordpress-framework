<?php
/**
 * Menu Pages: métodos public por defecto, $capability para todos,
 * $capabilities por método (opcional) y nonce opt-in con $nonce.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey\Functions;
use Fw\Core\Request\Actions;
use Fw\Core\Request\Request;
use Fw\Core\Request\RequestMenuPage;
use Fw\Init\MenuPage\MenuPagesManager;
use WordpressFramework\Controllers\ListTableController;

/**
 * Como Proyectos\Controllers\MenuPages\Ingeurbe\IngeurbeController (1.3.1, sin cambios):
 * solo $capability, métodos public que reciben array $params.
 */
class ControladorIngeurbe
{
	public static $pageTitle  = 'Proyectos INGEURBE';
	public static $capability = 'edit_posts';
	public static $menuSlug   = 'listado';

	public static string $ultimo = '';

	public function index( array $params ) { self::$ultimo = 'index'; }
	public function verPreguntas( array $params ) { self::$ultimo = 'verPreguntas'; }
	public function verInmuebles( array $params ) { self::$ultimo = 'verInmuebles'; }
	public function editarProyecto( array $params ) { self::$ultimo = 'editarProyecto'; }
	public function save( array $params ) { self::$ultimo = 'save'; }
	public function trash( array $params ) { self::$ultimo = 'trash'; }
	public function error404( array $params ) { self::$ultimo = 'error404'; }
	private function interno() { self::$ultimo = 'interno'; }
	protected function protegido() { self::$ultimo = 'protegido'; }
}

/**
 * La versión endurecida, opt-in: borrar exige delete_posts y nonce.
 */
class ControladorConCapabilities
{
	public static $capability = 'edit_posts';

	public static array $capabilities = array( 'trash' => 'delete_posts' );

	public static array $nonce = array( 'save', 'trash' );

	public static string $ultimo = '';

	public function index() { self::$ultimo = 'index'; }
	public function editar() { self::$ultimo = 'editar'; }
	public function save() { self::$ultimo = 'save'; }
	public function trash() { self::$ultimo = 'trash'; }
}

class ControladorSinCapability
{
	public static string $ultimo = '';

	public function index() { self::$ultimo = 'index'; }
	public function otro() { self::$ultimo = 'otro'; }
}

class MenuCapabilityTest extends FrameworkTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		ControladorIngeurbe::$ultimo        = '';
		ControladorConCapabilities::$ultimo = '';
		ControladorSinCapability::$ultimo   = '';
		$GLOBALS['wpfw_user_caps'] = array( 'edit_posts' );
		Functions\when( 'current_user_can' )->alias(
			function ( string $capability ): bool {
				$GLOBALS['wpfw_caps'][] = $capability;
				return in_array( $capability, $GLOBALS['wpfw_user_caps'], true );
			}
		);
	}

	public function test_ingeurbe_todos_los_metodos_public_por_option_sin_declarar_nada(): void
	{
		foreach ( array( 'verPreguntas', 'verInmuebles', 'editarProyecto', 'save', 'trash' ) as $metodo ) {
			ControladorIngeurbe::$ultimo = '';
			$this->ejecutar( ControladorIngeurbe::class, 'listado', $metodo );
			self::assertSame( $metodo, ControladorIngeurbe::$ultimo, $metodo );
		}
	}

	public function test_ingeurbe_enlace_viejo_de_borrar_sin_nonce_sigue_funcionando(): void
	{
		// ?page=…&option=trash&model=Proyectos&id=3, tal como lo arma ListTableController en 1.3.1.
		$_GET['model'] = 'Proyectos';
		$_GET['id']    = '3';
		$this->ejecutar( ControladorIngeurbe::class, 'listado', 'trash' );
		self::assertSame( 'trash', ControladorIngeurbe::$ultimo );
	}

	public function test_sin_option_corre_index(): void
	{
		$this->ejecutar( ControladorIngeurbe::class, 'listado', null );
		self::assertSame( 'index', ControladorIngeurbe::$ultimo );
	}

	public function test_private_protected_y_magicos_caen_en_error404(): void
	{
		foreach ( array( 'interno', 'protegido', '__construct', 'no_existe', '../x' ) as $metodo ) {
			ControladorIngeurbe::$ultimo = '';
			$this->ejecutar( ControladorIngeurbe::class, 'listado', $metodo );
			self::assertSame( 'error404', ControladorIngeurbe::$ultimo, $metodo );
		}
	}

	public function test_sin_error404_un_metodo_privado_no_corre(): void
	{
		$GLOBALS['wpfw_user_caps'] = array( 'install_plugins' );
		$_GET['page']   = 'x';
		$_GET['option'] = 'no_existe';
		$request = new RequestMenuPage( sys_get_temp_dir(), ControladorSinCapability::class, 'index' );
		self::assertNull( $request->validations() );
	}

	public function test_capability_aplica_a_todos_los_metodos(): void
	{
		$GLOBALS['wpfw_user_caps'] = array( 'read' );
		foreach ( array( 'index', 'save', 'trash' ) as $metodo ) {
			$_GET['page']   = 'listado';
			$_GET['option'] = $metodo;
			$request = new RequestMenuPage( sys_get_temp_dir(), ControladorIngeurbe::class, 'index' );
			self::assertNull( $request->validations(), $metodo );
		}
		self::assertSame( array( 'edit_posts' ), array_values( array_unique( $GLOBALS['wpfw_caps'] ) ) );
		self::assertSame( '', ControladorIngeurbe::$ultimo );
	}

	public function test_sin_capabilities_todo_usa_capability_como_antes(): void
	{
		foreach ( array( 'index', 'verPreguntas', 'save', 'trash', 'error404' ) as $metodo ) {
			self::assertSame( 'edit_posts', Actions::capabilityFor( ControladorIngeurbe::class, $metodo ) );
		}
		self::assertSame( array(), Actions::capabilitiesMap( ControladorIngeurbe::class ) );
	}

	public function test_capabilities_sobrescribe_solo_los_metodos_listados(): void
	{
		self::assertSame( 'delete_posts', Actions::capabilityFor( ControladorConCapabilities::class, 'trash' ) );
		self::assertSame( 'delete_posts', Actions::capabilityFor( ControladorConCapabilities::class, 'TRASH' ) );
		self::assertSame( 'edit_posts', Actions::capabilityFor( ControladorConCapabilities::class, 'editar' ) );
		self::assertSame( 'edit_posts', Actions::capabilityFor( ControladorConCapabilities::class, 'index' ) );

		// Con edit_posts: editar sí, trash no (403 porque no hay error404).
		$this->ejecutar( ControladorConCapabilities::class, 'panel', 'editar' );
		self::assertSame( 'editar', ControladorConCapabilities::$ultimo );

		ControladorConCapabilities::$ultimo = '';
		$this->conNonce( 'panel', 'trash' );
		$request = new RequestMenuPage( sys_get_temp_dir(), ControladorConCapabilities::class, 'index' );
		self::assertNull( $request->validations() );
		self::assertContains( 'delete_posts', $GLOBALS['wpfw_caps'] );
		self::assertSame( '', ControladorConCapabilities::$ultimo );

		// Con delete_posts también: trash corre.
		$GLOBALS['wpfw_user_caps'] = array( 'edit_posts', 'delete_posts' );
		$this->conNonce( 'panel', 'trash' );
		$this->ejecutarRequest( ControladorConCapabilities::class );
		self::assertSame( 'trash', ControladorConCapabilities::$ultimo );
	}

	public function test_permiso_denegado_usa_error404_del_controlador_si_existe(): void
	{
		$this->ejecutarConMapa();
		self::assertSame( 'error404', ControladorConMapa404::$ultimo );
	}

	public function test_nonce_es_opt_in(): void
	{
		// Sin $nonce (Ingeurbe): no se exige.
		$this->ejecutar( ControladorIngeurbe::class, 'listado', 'save' );
		self::assertSame( 'save', ControladorIngeurbe::$ultimo );

		// Con $nonce: save sin nonce no corre.
		$_GET['page']   = 'panel';
		$_GET['option'] = 'save';
		$request = new RequestMenuPage( sys_get_temp_dir(), ControladorConCapabilities::class, 'index' );
		self::assertNull( $request->validations() );

		// Con nonce válido sí.
		$this->conNonce( 'panel', 'save' );
		$this->ejecutarRequest( ControladorConCapabilities::class );
		self::assertSame( 'save', ControladorConCapabilities::$ultimo );

		// Un método fuera de $nonce no lo pide.
		ControladorConCapabilities::$ultimo = '';
		$this->ejecutar( ControladorConCapabilities::class, 'panel', 'editar' );
		self::assertSame( 'editar', ControladorConCapabilities::$ultimo );
	}

	public function test_la_capability_por_defecto_sigue_siendo_install_plugins(): void
	{
		require_once __DIR__ . '/Fixtures/LibrosController.php';

		self::assertSame(
			'install_plugins',
			Request::controllerCapability( \CapDemo\Controllers\MenuPages\Libros\LibrosController::class )
		);
		self::assertSame(
			'edit_pages',
			Request::controllerCapability( \CapDemo\Controllers\MenuPages\Libros\EspecialController::class )
		);

		$manager = new MenuPagesManager( 'CapDemo', sys_get_temp_dir() );
		$pagina  = $manager->prepareMenuPage( \CapDemo\Controllers\MenuPages\Libros\LibrosController::class );
		self::assertSame( 'install_plugins', $pagina['capability'] );
		self::assertSame( 'edit_posts', Request::controllerCapability( ControladorIngeurbe::class ) );
	}

	public function test_sin_capability_funciona_igual_y_avisa_solo_con_wp_debug(): void
	{
		// WP_DEBUG es false en las pruebas: no hay aviso, y el menú usa install_plugins.
		self::assertFalse( MenuPagesManager::noticeMissingCapability( ControladorSinCapability::class ) );
		self::assertFalse( MenuPagesManager::noticeMissingCapability( ControladorIngeurbe::class ) );
		self::assertFalse( Actions::declaresCapability( ControladorSinCapability::class ) );
		self::assertTrue( Actions::declaresCapability( ControladorIngeurbe::class ) );

		$GLOBALS['wpfw_user_caps'] = array( 'install_plugins' );
		$this->ejecutar( ControladorSinCapability::class, 'x', 'otro' );
		self::assertSame( 'otro', ControladorSinCapability::$ultimo );
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
		self::assertSame( 'install_plugins', $GLOBALS['wpfw_test_menus'][0][2] );
		self::assertSame( 'capdemo-alpha-alpha', $GLOBALS['wpfw_test_menus'][0][3] );

		$slugs = array_column( $GLOBALS['wpfw_test_submenus'], 4 );
		self::assertContains( 'capdemo-alpha-zed', $slugs );
	}

	public function test_list_table_conserva_las_claves_con_parametros_de_1_3_1(): void
	{
		$_GET['page'] = 'listado';

		$parsed = ListTableController::parseOptionKey( 'trash&model=Proyectos' );
		self::assertSame( array( 'option' => 'trash', 'args' => array( 'model' => 'Proyectos' ) ), $parsed );
		self::assertNull( ListTableController::parseOptionKey( '../x' ) );

		$list = new ListTableController();
		$list->prepare_items(
			array(
				'items'   => array( array( 'id' => '3', 'nombre' => 'Torre' ) ),
				'columns' => array( 'id' => 'Id', 'nombre' => 'Nombre' ),
				'main'    => 'nombre',
				'search'  => false,
				'options' => array(
					'editarProyecto'        => 'Editar Proyecto',
					'trash&model=Proyectos' => 'Eliminar',
				),
			)
		);

		$html = html_entity_decode( $list->column_default( $list->items[0], 'nombre' ) );
		self::assertStringContainsString( 'option=editarProyecto', $html );
		self::assertStringContainsString( 'option=trash', $html );
		self::assertStringContainsString( 'model=Proyectos', $html );
		self::assertStringContainsString( 'id=3', $html );
		self::assertStringContainsString( 'Eliminar', $html );
		// El nonce va siempre; solo se verifica si el controlador lo pide en $nonce.
		self::assertStringContainsString( '_wpfw_nonce=nonce-wpfw_option_listado_trash', $html );
	}

	private function ejecutar( string $controller, string $page, ?string $option ): void
	{
		$_GET['page'] = $page;
		if ( $option !== null ) {
			$_GET['option'] = $option;
		} else {
			unset( $_GET['option'] );
		}
		$this->ejecutarRequest( $controller );
	}

	private function ejecutarRequest( string $controller ): void
	{
		$request  = new RequestMenuPage( sys_get_temp_dir(), $controller, 'index' );
		$callback = $request->validations();
		self::assertIsArray( $callback );
		$callback[0]->{$callback[1]}( array() );
	}

	private function ejecutarConMapa(): void
	{
		$this->ejecutar( ControladorConMapa404::class, 'panel', 'trash' );
	}

	private function conNonce( string $page, string $option ): void
	{
		$_GET['page']            = $page;
		$_GET['option']          = $option;
		$_REQUEST['_wpfw_nonce'] = 'nonce-wpfw_option_' . $page . '_' . $option;
	}
}

class ControladorConMapa404
{
	public static $capability = 'edit_posts';

	public static array $capabilities = array( 'trash' => 'delete_posts' );

	public static string $ultimo = '';

	public function trash() { self::$ultimo = 'trash'; }
	public function error404() { self::$ultimo = 'error404'; }
}
