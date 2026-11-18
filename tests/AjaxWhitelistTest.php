<?php
/**
 * La capa AJAX solo registra lo declarado y no incluye archivos según la petición.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey\Functions;
use Fw\Config\Apps;
use Fw\Init\Ajax\AjaxManager;

class AjaxWhitelistTest extends FrameworkTestCase
{
	private string $directorio = '';

	protected function setUp(): void
	{
		parent::setUp();
		Functions\when( 'current_user_can' )->alias(
			static function ( string $capability ): bool {
				$GLOBALS['wpfw_caps'][] = $capability;
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
	}

	protected function tearDown(): void
	{
		if ( $this->directorio !== '' && is_dir( $this->directorio ) ) {
			foreach ( glob( $this->directorio . '/*.php' ) ?: array() as $archivo ) {
				unlink( $archivo );
			}
			rmdir( $this->directorio );
		}
		parent::tearDown();
	}

	public function test_solo_se_registran_las_acciones_declaradas_y_el_request_no_incluye_archivos(): void
	{
		$this->directorio = sys_get_temp_dir() . '/wpfw-ajax-' . uniqid( '', true );
		mkdir( $this->directorio, 0777, true );

		$clase = $this->directorio . '/ConsultaController.php';
		file_put_contents( $clase, $this->fuente( 'AjaxDemo', 'consulta' ) );
		file_put_contents(
			$this->directorio . '/Evil.php',
			"<?php\n\$GLOBALS['wpfw_evil_incluido'] = true;\n"
		);
		require_once $clase;

		$_REQUEST['controller'] = $this->directorio . '/../../../secreto.php';
		$_REQUEST['action']     = 'interno';
		$_GET['file']           = 'Evil.php';

		Apps::setApp(
			'cvip-loteria',
			array(
				'config' => (object) array( 'namespace' => 'AjaxDemo' ),
				'paths'  => (object) array(
					'controllers' => (object) array( 'ajax' => $this->directorio ),
				),
			)
		);

		AjaxManager::initialize( 'cvip-loteria' );

		self::assertArrayNotHasKey( 'wpfw_evil_incluido', $GLOBALS );
		self::assertArrayHasKey( 'wp_ajax_cvip_loteria_consultar', $GLOBALS['wpfw_test_actions'] );
		self::assertArrayHasKey( 'wp_ajax_nopriv_cvip_loteria_consultar', $GLOBALS['wpfw_test_actions'] );
		self::assertArrayHasKey( 'wp_ajax_cvip_loteria_guardar', $GLOBALS['wpfw_test_actions'] );
		self::assertArrayNotHasKey( 'wp_ajax_nopriv_cvip_loteria_guardar', $GLOBALS['wpfw_test_actions'] );
		self::assertArrayNotHasKey( 'wp_ajax_cvip_loteria_interno', $GLOBALS['wpfw_test_actions'] );

		$GLOBALS['wpfw_ajax_new'] = 0;
		AjaxManager::dispatch( 'cvip_loteria_consultar' );
		self::assertSame( 0, $GLOBALS['wpfw_ajax_new'] );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );

		$_REQUEST['_ajax_nonce'] = 'nonce-wpfw_ajax_cvip_loteria_consultar';
		$_REQUEST['action']      = 'cvip_loteria_guardar';
		$GLOBALS['wpfw_test_actions']['wp_ajax_nopriv_cvip_loteria_consultar'][0]();

		self::assertSame( array( 'origen' => 'consulta' ), $GLOBALS['wpfw_json']['ok'] );
		self::assertSame( 1, $GLOBALS['wpfw_ajax_new'] );

		$GLOBALS['wpfw_can'] = false;
		$_REQUEST['_ajax_nonce'] = 'nonce-wpfw_ajax_cvip_loteria_guardar';
		AjaxManager::dispatch( 'cvip_loteria_guardar' );
		self::assertSame( 403, $GLOBALS['wpfw_json']['status'] );
		self::assertSame( 1, $GLOBALS['wpfw_ajax_new'] );

		$GLOBALS['wpfw_can'] = true;
		AjaxManager::dispatch( 'cvip_loteria_guardar' );
		self::assertSame( array( 'origen' => 'guardar' ), $GLOBALS['wpfw_json']['ok'] );
		self::assertContains( 'manage_options', $GLOBALS['wpfw_caps'] );

		$config = AjaxManager::clientConfig( 'cvip-loteria' );
		self::assertSame( 'cvip_loteria_consultar', $config['actions']['consultar']['action'] );
		self::assertSame( 'nonce-wpfw_ajax_cvip_loteria_consultar', $config['actions']['consultar']['nonce'] );
		self::assertSame( 'cvip_loteria_consultar', wpfw_ajax_hook( 'cvip-loteria', 'consultar' ) );
	}

	public function test_dos_plugins_no_comparten_el_gancho_ajax(): void
	{
		$uno = sys_get_temp_dir() . '/wpfw-ajax-uno-' . uniqid( '', true );
		$dos = sys_get_temp_dir() . '/wpfw-ajax-dos-' . uniqid( '', true );
		mkdir( $uno, 0777, true );
		mkdir( $dos, 0777, true );
		file_put_contents( $uno . '/ConsultaController.php', $this->fuente( 'AjaxUno', 'uno' ) );
		file_put_contents( $dos . '/ConsultaController.php', $this->fuente( 'AjaxDos', 'dos' ) );
		require_once $uno . '/ConsultaController.php';
		require_once $dos . '/ConsultaController.php';

		foreach ( array( 'uno' => array( $uno, 'AjaxUno' ), 'dos' => array( $dos, 'AjaxDos' ) ) as $slug => $datos ) {
			Apps::setApp(
				$slug,
				array(
					'config' => (object) array( 'namespace' => $datos[1] ),
					'paths'  => (object) array(
						'controllers' => (object) array( 'ajax' => $datos[0] ),
					),
				)
			);
			AjaxManager::initialize( $slug );
		}

		$_REQUEST['_ajax_nonce'] = 'nonce-wpfw_ajax_uno_consultar';
		AjaxManager::dispatch( 'uno_consultar' );

		self::assertSame( array( 'origen' => 'uno' ), $GLOBALS['wpfw_json']['ok'] );
		self::assertNotSame( 'dos', $GLOBALS['wpfw_json']['ok']['origen'] ?? '' );

		unlink( $uno . '/ConsultaController.php' );
		unlink( $dos . '/ConsultaController.php' );
		rmdir( $uno );
		rmdir( $dos );
	}

	private function fuente( string $namespace, string $origen ): string
	{
		return <<<PHP
<?php
namespace {$namespace}\\Controllers\\Ajax;

class ConsultaController
{
    public static array \$ajaxActions = [
        'consultar' => ['public' => true, 'nonce' => true],
        'guardar' => ['public' => false, 'capability' => 'manage_options', 'nonce' => true],
    ];

    public function __construct()
    {
        \$GLOBALS['wpfw_ajax_new'] = ( \$GLOBALS['wpfw_ajax_new'] ?? 0 ) + 1;
    }

    public function consultar(): array
    {
        return ['origen' => '{$origen}'];
    }

    public function guardar(): array
    {
        return ['origen' => 'guardar'];
    }

    public function interno(): array
    {
        \$GLOBALS['wpfw_ajax_interno'] = true;
        return ['no' => true];
    }
}
PHP;
	}
}
