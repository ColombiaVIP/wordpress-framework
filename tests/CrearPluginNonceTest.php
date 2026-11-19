<?php
/**
 * Menú WP Framework: el formulario de creación de plugins exige
 * install_plugins y un nonce válido (CSRF) antes de crear nada.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey\Functions;
use WordpressFramework\Controllers\MenuPages\WpFramework\WpFramework;

class CrearPluginNonceTest extends FrameworkTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$GLOBALS['wpfw_user_caps'] = array( 'install_plugins' );
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap ): bool => in_array( $cap, $GLOBALS['wpfw_user_caps'], true )
		);
		Functions\when( 'wp_die' )->alias(
			static function ( $message = '' ): void {
				throw new \RuntimeException( 'wp_die: ' . $message );
			}
		);
		// Si la validación deja pasar, createPlugin consulta get_plugins: lo registramos.
		$GLOBALS['wpfw_get_plugins_calls'] = 0;
		Functions\when( 'get_plugins' )->alias(
			static function (): array {
				$GLOBALS['wpfw_get_plugins_calls']++;
				return array( 'existente/existente.php' => array( 'Name' => 'Plugin Existente' ) );
			}
		);
	}

	protected function tearDown(): void
	{
		unset( $_SERVER['REQUEST_METHOD'] );
		parent::tearDown();
	}

	private function validar(): ?array
	{
		$method = new \ReflectionMethod( WpFramework::class, 'validatedPluginData' );
		$method->setAccessible( true );
		return $method->invoke( new WpFramework() );
	}

	private function crear(): string
	{
		ob_start();
		try {
			$data = $this->validar();
			if ( $data !== null ) {
				$method = new \ReflectionMethod( WpFramework::class, 'createPlugin' );
				$method->setAccessible( true );
				$method->invoke( new WpFramework(), $data );
			}
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	public function test_sin_nonce_se_rechaza_y_no_crea_nada(): void
	{
		$_POST['data'] = array( 'pluginName' => 'Plugin Existente' );

		try {
			$this->crear();
			self::fail( 'Sin nonce debía llamar a wp_die.' );
		} catch ( \RuntimeException $e ) {
			self::assertStringContainsString( 'Invalid nonce', $e->getMessage() );
		}
		self::assertSame( 0, $GLOBALS['wpfw_get_plugins_calls'] );
	}

	public function test_nonce_invalido_se_rechaza(): void
	{
		$_POST['data']    = array( 'pluginName' => 'Plugin Existente' );
		$_POST['fwNonce'] = 'nonce-otra-accion';

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid nonce' );
		$this->crear();
	}

	public function test_nonce_en_get_no_cuenta(): void
	{
		$_POST['data']        = array( 'pluginName' => 'Plugin Existente' );
		$_GET['fwNonce']      = 'nonce-create-plugin-nonce';
		$_REQUEST['fwNonce']  = 'nonce-create-plugin-nonce';

		$this->expectException( \RuntimeException::class );
		$this->crear();
	}

	public function test_sin_install_plugins_se_rechaza_aunque_haya_nonce(): void
	{
		$GLOBALS['wpfw_user_caps'] = array( 'edit_posts' );
		$_POST['data']             = array( 'pluginName' => 'Plugin Existente' );
		$_POST['fwNonce']          = 'nonce-create-plugin-nonce';

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'permiso' );
		$this->crear();
	}

	public function test_con_nonce_valido_llega_a_crear(): void
	{
		$_POST['data']    = array( 'pluginName' => 'Plugin Existente', 'otro' => array( 'x' ) );
		$_POST['fwNonce'] = 'nonce-create-plugin-nonce';

		$html = $this->crear();

		// Llegó a BuildStructures (consultó get_plugins); el nombre ya existe, así que no crea carpetas.
		self::assertSame( 1, $GLOBALS['wpfw_get_plugins_calls'] );
		self::assertStringContainsString( 'Create Component: Plugin Existente', $html );
		self::assertStringContainsString( 'Error en la creación del plugin', $html );
	}

	public function test_el_formulario_imprime_el_nonce_una_sola_vez(): void
	{
		$vista = (string) file_get_contents( dirname( __DIR__ ) . '/app/views/menuPages/frameworkWp/frameworkWp/index.php' );
		self::assertStringContainsString( "wp_nonce_field( 'create-plugin-nonce', 'fwNonce' )", $vista );
		self::assertStringNotContainsString( '<?=wp_nonce_field', $vista );
	}

	public function test_la_validacion_no_es_un_metodo_publico_despachable(): void
	{
		self::assertTrue( ( new \ReflectionMethod( WpFramework::class, 'validatedPluginData' ) )->isPrivate() );
		self::assertTrue( ( new \ReflectionMethod( WpFramework::class, 'createPlugin' ) )->isPrivate() );
	}
}
