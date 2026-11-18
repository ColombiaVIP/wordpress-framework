<?php
/**
 * Modo automático de la capa AJAX: todo método public escrito en un controlador
 * de app/Controllers/Ajax es una acción, con $capability, $capabilities y $nonce
 * como en las Menu Pages. $ajaxActions sigue funcionando como antes.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey\Functions;
use Fw\Config\Apps;
use Fw\Init\Ajax\AjaxManager;

class AjaxPublicMethodsTest extends FrameworkTestCase
{
	/** @var list<string> */
	private array $directorios = array();

	protected function setUp(): void
	{
		parent::setUp();
		Functions\when( 'current_user_can' )->alias(
			static function ( string $capability ): bool {
				$GLOBALS['wpfw_caps'][] = $capability;
				if ( is_array( $GLOBALS['wpfw_can'] ) ) {
					return in_array( $capability, $GLOBALS['wpfw_can'], true );
				}
				return (bool) $GLOBALS['wpfw_can'];
			}
		);
		Functions\when( 'wp_send_json_success' )->alias(
			static function ( $data = null ): void {
				$GLOBALS['wpfw_json'] = array( 'ok' => $data );
			}
		);
		Functions\when( 'wp_send_json_error' )->alias(
			static function ( $data = null, $status = null ): void {
				$GLOBALS['wpfw_json'] = array(
					'error'  => $data,
					'status' => $status,
				);
			}
		);
		$GLOBALS['wpfw_ajax_llamado'] = array();
	}

	protected function tearDown(): void
	{
		foreach ( $this->directorios as $directorio ) {
			foreach ( glob( $directorio . '/*.php' ) ?: array() as $archivo ) {
				unlink( $archivo );
			}
			rmdir( $directorio );
		}
		parent::tearDown();
	}

	/**
	 * Crea un plugin con los controladores dados (nombre => cuerpo de la clase) y lo inicializa.
	 *
	 * @param array<string, string> $controladores
	 */
	private function plugin( string $slug, array $controladores, string $extra = '' ): string
	{
		$namespace  = 'AjaxAuto' . str_replace( '.', '', uniqid( '', true ) );
		$directorio = sys_get_temp_dir() . '/wpfw-ajax-auto-' . uniqid( '', true );
		mkdir( $directorio, 0777, true );
		$this->directorios[] = $directorio;

		if ( $extra !== '' ) {
			$archivo = $directorio . '/_extra.php';
			file_put_contents( $archivo, "<?php\n" . str_replace( '{NS}', $namespace, $extra ) );
			require_once $archivo;
		}

		foreach ( $controladores as $clase => $cuerpo ) {
			$archivo = $directorio . '/' . $clase . '.php';
			$cuerpo  = str_replace( '{NS}', $namespace, $cuerpo );
			file_put_contents(
				$archivo,
				"<?php\nnamespace {$namespace}\\Controllers\\Ajax;\n\n{$cuerpo}\n"
			);
			require_once $archivo;
		}

		Apps::setApp(
			$slug,
			array(
				'config' => (object) array( 'namespace' => $namespace ),
				'paths'  => (object) array(
					'controllers' => (object) array( 'ajax' => $directorio ),
				),
			)
		);

		AjaxManager::initialize( $slug );

		return $namespace;
	}

	private function registrado( string $gancho ): bool
	{
		return array_key_exists( 'wp_ajax_' . $gancho, $GLOBALS['wpfw_test_actions'] );
	}

	private function anonimo( string $gancho ): bool
	{
		return array_key_exists( 'wp_ajax_nopriv_' . $gancho, $GLOBALS['wpfw_test_actions'] );
	}

	public function test_todo_metodo_public_es_accion_y_lo_demas_no(): void
	{
		$this->plugin(
			'demo-auto',
			array(
				'TodoController' => <<<'PHP'
class TodoController
{
    public static $capability = 'edit_posts';

    public function __construct() {}
    public function listar(): array { $GLOBALS['wpfw_ajax_llamado'][] = 'listar'; return ['m' => 'listar']; }
    public function guardar(): array { return ['m' => 'guardar']; }
    public static function ayudante(): array { return ['no' => true]; }
    protected function protegido(): array { return ['no' => true]; }
    private function privado(): array { return ['no' => true]; }
    public function __call($n, $a) { return ['no' => true]; }
    public function __invoke() { return ['no' => true]; }
    public function __destruct() {}
}
PHP,
			)
		);

		self::assertTrue( $this->registrado( 'demo_auto_listar' ) );
		self::assertTrue( $this->registrado( 'demo_auto_guardar' ) );
		foreach ( array( 'ayudante', 'protegido', 'privado', '__call', '__invoke', '__construct', '__destruct', 'call', 'invoke' ) as $metodo ) {
			self::assertFalse( $this->registrado( 'demo_auto_' . $metodo ), $metodo );
		}
		self::assertFalse( $this->anonimo( 'demo_auto_listar' ) );

		$GLOBALS['wpfw_test_actions']['wp_ajax_demo_auto_listar'][0]();
		self::assertSame( array( 'm' => 'listar' ), $GLOBALS['wpfw_json']['ok'] );
		self::assertSame( array( 'edit_posts' ), $GLOBALS['wpfw_caps'] );

		// Un gancho que no existe no llega a ningún controlador.
		AjaxManager::dispatch( 'demo_auto_privado' );
		self::assertSame( 404, $GLOBALS['wpfw_json']['status'] );
	}

	public function test_lo_heredado_de_fuera_del_plugin_no_se_expone(): void
	{
		$extra = <<<'PHP'
namespace WpfwExterno {
    class BaseExterna
    {
        public function exponerExterno(): array { return ['no' => true]; }
        public function sobrescrito(): array { return ['no' => true]; }
    }
    trait TraitExterno
    {
        public function desdeTraitExterno(): array { return ['no' => true]; }
    }
}
namespace {NS}\Controllers {
    abstract class BasePlugin extends \WpfwExterno\BaseExterna
    {
        public function desdeBasePlugin(): array { return ['m' => 'base']; }
        abstract public function abstracto(): array;
    }
    trait TraitPlugin
    {
        public function desdeTraitPlugin(): array { return ['m' => 'trait'];  }
    }
}
PHP;
		// El archivo extra mezcla namespaces con llaves: no puede tener código global suelto.
		$this->plugin(
			'demo-herencia',
			array(
				'HijoController' => <<<'PHP'
class HijoController extends \{NS}\Controllers\BasePlugin
{
    use \{NS}\Controllers\TraitPlugin;
    use \WpfwExterno\TraitExterno;

    public static $capability = 'edit_posts';

    public function propio(): array { return ['m' => 'propio']; }
    public function abstracto(): array { return ['m' => 'abstracto']; }
    public function sobrescrito(): array { return ['m' => 'sobrescrito']; }
}
PHP,
			),
			$extra
		);

		self::assertTrue( $this->registrado( 'demo_herencia_propio' ) );
		self::assertTrue( $this->registrado( 'demo_herencia_abstracto' ) );
		self::assertTrue( $this->registrado( 'demo_herencia_sobrescrito' ), 'sobrescrito en el plugin: es del plugin' );
		self::assertTrue( $this->registrado( 'demo_herencia_desdeBasePlugin' ), 'base propia del plugin' );
		self::assertTrue( $this->registrado( 'demo_herencia_desdeTraitPlugin' ), 'trait propio del plugin' );
		self::assertFalse( $this->registrado( 'demo_herencia_exponerExterno' ), 'heredado de fuera del plugin' );
		self::assertFalse( $this->registrado( 'demo_herencia_desdeTraitExterno' ), 'trait de fuera del plugin' );
	}

	public function test_capability_de_todo_el_controlador(): void
	{
		$this->plugin(
			'demo-cap',
			array(
				'CapController' => <<<'PHP'
class CapController
{
    public static $capability = 'loteria_sorteos';
    public function uno(): array { return ['m' => 'uno']; }
    public function dos(): array { return ['m' => 'dos']; }
}
PHP,
			)
		);

		$GLOBALS['wpfw_can'] = array( 'read' );
		AjaxManager::dispatch( 'demo_cap_uno' );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );
		AjaxManager::dispatch( 'demo_cap_dos' );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );

		$GLOBALS['wpfw_can'] = array( 'loteria_sorteos' );
		AjaxManager::dispatch( 'demo_cap_dos' );
		self::assertSame( array( 'm' => 'dos' ), $GLOBALS['wpfw_json']['ok'] );
		self::assertSame( 'loteria_sorteos', AjaxManager::registered( 'demo_cap_uno' )['capability'] );
	}

	public function test_capabilities_por_metodo_sobrescribe_y_vacio_es_anonimo(): void
	{
		$this->plugin(
			'demo-mix',
			array(
				'MixController' => <<<'PHP'
class MixController
{
    public static $capability = 'edit_posts';
    public static array $capabilities = [
        'Borrar'   => 'manage_options',
        'consultar' => '',
        'raro'     => 'cap con espacios',
    ];
    public function listar(): array { return ['m' => 'listar']; }
    public function borrar(): array { return ['m' => 'borrar']; }
    public function consultar(): array { return ['m' => 'consultar']; }
    public function raro(): array { return ['m' => 'raro']; }
}
PHP,
			)
		);

		self::assertSame( 'edit_posts', AjaxManager::registered( 'demo_mix_listar' )['capability'] );
		self::assertSame( 'manage_options', AjaxManager::registered( 'demo_mix_borrar' )['capability'], 'sin distinguir mayúsculas' );
		self::assertSame( '', AjaxManager::registered( 'demo_mix_consultar' )['capability'] );
		self::assertSame( 'install_plugins', AjaxManager::registered( 'demo_mix_raro' )['capability'], 'capability inválida: cerrado' );

		self::assertTrue( $this->anonimo( 'demo_mix_consultar' ) );
		self::assertFalse( $this->anonimo( 'demo_mix_listar' ) );
		self::assertFalse( $this->anonimo( 'demo_mix_borrar' ) );

		$GLOBALS['wpfw_can'] = array( 'edit_posts' );
		AjaxManager::dispatch( 'demo_mix_borrar' );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );
		AjaxManager::dispatch( 'demo_mix_listar' );
		self::assertSame( array( 'm' => 'listar' ), $GLOBALS['wpfw_json']['ok'] );

		// Anónimo: no se consulta current_user_can.
		$GLOBALS['wpfw_can']  = false;
		$GLOBALS['wpfw_caps'] = array();
		$GLOBALS['wpfw_test_actions']['wp_ajax_nopriv_demo_mix_consultar'][0]();
		self::assertSame( array( 'm' => 'consultar' ), $GLOBALS['wpfw_json']['ok'] );
		self::assertSame( array(), $GLOBALS['wpfw_caps'] );

		// El mapa para JS de un visitante solo trae lo anónimo.
		$config = AjaxManager::clientConfig( 'demo-mix' );
		self::assertSame( array( 'consultar' ), array_keys( $config['actions'] ) );
		self::assertTrue( $config['actions']['consultar']['anonimo'] );
		self::assertFalse( $config['actions']['consultar']['requiereNonce'] );
		self::assertSame( 'demo_mix_consultar', $config['actions']['consultar']['action'] );
		self::assertSame( 'nonce-wpfw_ajax_demo_mix_consultar', $config['actions']['consultar']['nonce'] );

		$GLOBALS['wpfw_can'] = true;
		$config = AjaxManager::clientConfig( 'demo-mix' );
		self::assertSame( array( 'listar', 'borrar', 'consultar', 'raro' ), array_keys( $config['actions'] ) );
	}

	public function test_capability_vacia_en_el_controlador_abre_todo(): void
	{
		$this->plugin(
			'demo-abierto',
			array(
				'AbiertoController' => <<<'PHP'
class AbiertoController
{
    public static $capability = '';
    public static array $capabilities = ['cerrar' => 'manage_options'];
    public function ver(): array { return ['m' => 'ver']; }
    public function cerrar(): array { return ['m' => 'cerrar']; }
}
PHP,
			)
		);

		self::assertTrue( $this->anonimo( 'demo_abierto_ver' ) );
		self::assertFalse( $this->anonimo( 'demo_abierto_cerrar' ) );
		self::assertSame( array(), AjaxManager::controllersWithoutCapability() );
	}

	public function test_sin_capability_usa_install_plugins_solo_con_sesion(): void
	{
		$namespace = $this->plugin(
			'demo-default',
			array(
				'SinCapController' => <<<'PHP'
class SinCapController
{
    public function hacer(): array { return ['m' => 'hacer']; }
}
PHP,
			)
		);

		self::assertTrue( $this->registrado( 'demo_default_hacer' ) );
		self::assertFalse( $this->anonimo( 'demo_default_hacer' ) );
		self::assertSame( 'install_plugins', AjaxManager::registered( 'demo_default_hacer' )['capability'] );
		self::assertSame(
			array( $namespace . '\\Controllers\\Ajax\\SinCapController' ),
			AjaxManager::controllersWithoutCapability()
		);
		// Sin WP_DEBUG (bootstrap) no hay aviso.
		self::assertFalse( AjaxManager::noticeMissingCapability( $namespace . '\\Controllers\\Ajax\\SinCapController' ) );

		$GLOBALS['wpfw_can'] = array( 'edit_posts' );
		AjaxManager::dispatch( 'demo_default_hacer' );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );
	}

	public function test_nonce_es_opt_in(): void
	{
		$this->plugin(
			'demo-nonce',
			array(
				'ListaNonceController' => <<<'PHP'
class ListaNonceController
{
    public static $capability = 'edit_posts';
    public static $nonce = ['Guardar'];
    public function guardar(): array { return ['m' => 'guardar']; }
    public function leer(): array { return ['m' => 'leer']; }
}
PHP,
				'TodoNonceController' => <<<'PHP'
class TodoNonceController
{
    public static $capability = '';
    public static $nonce = true;
    public function radicar(): array { return ['m' => 'radicar']; }
}
PHP,
			)
		);

		// Sin $nonce para leer: pasa sin nonce.
		AjaxManager::dispatch( 'demo_nonce_leer' );
		self::assertSame( array( 'm' => 'leer' ), $GLOBALS['wpfw_json']['ok'] );

		// guardar está en la lista (sin distinguir mayúsculas).
		AjaxManager::dispatch( 'demo_nonce_guardar' );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );
		$_REQUEST['_ajax_nonce'] = 'nonce-wpfw_ajax_demo_nonce_leer';
		AjaxManager::dispatch( 'demo_nonce_guardar' );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'], 'el nonce de otra acción no sirve' );
		$_REQUEST['_ajax_nonce'] = 'nonce-wpfw_ajax_demo_nonce_guardar';
		AjaxManager::dispatch( 'demo_nonce_guardar' );
		self::assertSame( array( 'm' => 'guardar' ), $GLOBALS['wpfw_json']['ok'] );

		// true: todos los métodos, también los anónimos.
		unset( $_REQUEST['_ajax_nonce'] );
		$GLOBALS['wpfw_test_actions']['wp_ajax_nopriv_demo_nonce_radicar'][0]();
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );
		$_REQUEST['_ajax_nonce'] = 'nonce-wpfw_ajax_demo_nonce_radicar';
		$GLOBALS['wpfw_test_actions']['wp_ajax_nopriv_demo_nonce_radicar'][0]();
		self::assertSame( array( 'm' => 'radicar' ), $GLOBALS['wpfw_json']['ok'] );

		$config = AjaxManager::clientConfig( 'demo-nonce' );
		self::assertTrue( $config['actions']['guardar']['requiereNonce'] );
		self::assertFalse( $config['actions']['leer']['requiereNonce'] );
		self::assertTrue( $config['actions']['radicar']['requiereNonce'] );
	}

	public function test_ajax_actions_heredado_sigue_igual_y_convive_con_el_modo_nuevo(): void
	{
		$this->plugin(
			'demo-legado',
			array(
				'LegadoController' => <<<'PHP'
class LegadoController
{
    public static $capability = 'read';
    public static array $ajaxActions = [
        'consultar' => ['public' => true, 'nonce' => false],
        'guardar'   => ['capability' => 'manage_options'],
        'oculto'    => ['public' => true],
    ];
    public function consultar(): array { return ['m' => 'consultar']; }
    public function guardar(): array { return ['m' => 'guardar']; }
    public function interno(): array { return ['no' => true]; }
    private function oculto(): array { return ['no' => true]; }
}
PHP,
				'NuevoController' => <<<'PHP'
class NuevoController
{
    public static $capability = 'edit_posts';
    public function listar(): array { return ['m' => 'listar']; }
}
PHP,
			)
		);

		// Legado: solo lo listado y public; sus ajustes ganan sobre $capability.
		self::assertTrue( $this->anonimo( 'demo_legado_consultar' ) );
		self::assertTrue( $this->registrado( 'demo_legado_guardar' ) );
		self::assertFalse( $this->anonimo( 'demo_legado_guardar' ) );
		self::assertFalse( $this->registrado( 'demo_legado_interno' ) );
		self::assertFalse( $this->registrado( 'demo_legado_oculto' ) );
		self::assertSame( 'manage_options', AjaxManager::registered( 'demo_legado_guardar' )['capability'] );
		self::assertTrue( AjaxManager::registered( 'demo_legado_guardar' )['nonce'], 'en el legado el nonce sigue activo por defecto' );
		self::assertFalse( AjaxManager::registered( 'demo_legado_consultar' )['nonce'] );

		AjaxManager::dispatch( 'demo_legado_consultar' );
		self::assertSame( array( 'm' => 'consultar' ), $GLOBALS['wpfw_json']['ok'] );

		// El otro controlador, en modo nuevo.
		self::assertTrue( $this->registrado( 'demo_legado_listar' ) );
		self::assertSame( 'edit_posts', AjaxManager::registered( 'demo_legado_listar' )['capability'] );
	}

	public function test_la_plantilla_saludo_funciona_en_modo_nuevo(): void
	{
		$fuente = (string) file_get_contents( dirname( __DIR__ ) . '/Fw/Structures/templateApp/app/Controllers/Ajax/SaludoController.php' );
		$fuente = trim( (string) preg_replace( '/^<\?php\s*/', '', $fuente ) );
		$fuente = str_replace( '# namespace;', '', $fuente );
		$fuente = (string) preg_replace( '/if \( ! defined\( \'ABSPATH\' \) \) \{\s*exit;\s*\}/', '', $fuente );

		$this->plugin( 'demo-saludo', array( 'SaludoController' => $fuente ) );

		self::assertTrue( $this->anonimo( 'demo_saludo_publico' ) );
		self::assertTrue( $this->registrado( 'demo_saludo_guardar' ) );
		self::assertFalse( $this->anonimo( 'demo_saludo_guardar' ) );
		self::assertFalse( $this->registrado( 'demo_saludo_resumen' ) );
		self::assertSame( 'manage_options', AjaxManager::registered( 'demo_saludo_guardar' )['capability'] );
		self::assertTrue( AjaxManager::registered( 'demo_saludo_publico' )['nonce'] );
	}
}
