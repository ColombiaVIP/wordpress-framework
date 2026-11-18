<?php
/**
 * El shortcode ejecuta cualquier método public (como 1.3.1); nunca private, protected ni mágicos.
 * La categoría, la clase y el método tienen que ser identificadores.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Fw\Config\Apps;
use Fw\Core\Request\RequestShortcode;

class ShortcodePublicTest extends FrameworkTestCase
{
	private string $root = '';

	protected function tearDown(): void
	{
		if ( $this->root !== '' && is_dir( $this->root ) ) {
			$archivo = $this->root . '/Posts/PostsController.php';
			if ( is_file( $archivo ) ) {
				unlink( $archivo );
			}
			if ( is_dir( $this->root . '/Posts' ) ) {
				rmdir( $this->root . '/Posts' );
			}
			rmdir( $this->root );
		}
		parent::tearDown();
	}

	public function test_clase_arroba_metodo_ejecuta_cualquier_metodo_public_y_nada_mas(): void
	{
		$this->root = sys_get_temp_dir() . '/wpfw-short-' . uniqid( '', true );
		mkdir( $this->root . '/Posts', 0777, true );
		$archivo = $this->root . '/Posts/PostsController.php';
		file_put_contents( $archivo, $this->fuente() );
		require_once $archivo;

		$canario = dirname( $this->root ) . '/canario-shortcode-' . uniqid( '', true ) . '.php';
		file_put_contents( $canario, "<?php\n\$GLOBALS['wpfw_canario'] = true;\n" );

		Apps::setApp(
			'demo-short',
			array(
				'config' => (object) array( 'namespace' => 'ShortDemo' ),
				'paths'  => (object) array(
					'app'         => $this->root,
					'controllers' => (object) array( 'shortcodes' => $this->root ),
				),
			)
		);

		$shortcode = new RequestShortcode( 'demo-short' );

		// Public sin declarar: corre.
		$GLOBALS['wpfw_short'] = '';
		$shortcode->prepare( array( 'Posts' => 'Posts@saludo' ) );
		self::assertSame( 'saludo', $GLOBALS['wpfw_short'] );

		// Sin @metodo: index.
		$shortcode->prepare( array( 'Posts' => 'Posts' ) );
		self::assertSame( 'index', $GLOBALS['wpfw_short'] );

		// private y protected: no corren (sin error404 no se imprime nada).
		foreach ( array( 'oculto', 'protegido', 'no_existe' ) as $metodo ) {
			$GLOBALS['wpfw_short'] = '';
			$shortcode->prepare( array( 'Posts' => 'Posts@' . $metodo ) );
			self::assertSame( '', $GLOBALS['wpfw_short'], $metodo );
		}

		self::assertNull( $shortcode->prepare( array( '../X' => 'Posts@saludo' ) ) );
		self::assertArrayNotHasKey( 'wpfw_canario', $GLOBALS );

		self::assertNull( $shortcode->prepare( array( 'Posts' => 'Posts@__construct' ) ) );
		self::assertSame( '', $GLOBALS['wpfw_short'] );

		// Los demás atributos llegan completos al controlador, como en 1.3.1.
		$shortcode->prepare( array( 'Posts' => 'Posts@atributos', 'type' => 'posts', 'data-id' => '7', 0 => 'suelto' ) );
		self::assertSame( array( 'type' => 'posts', 'data-id' => '7', 0 => 'suelto' ), $GLOBALS['wpfw_short'] );

		unlink( $canario );
	}

	private function fuente(): string
	{
		return <<<'PHP'
<?php
namespace ShortDemo\Controllers\Shortcodes\Posts;

class PostsController
{
    public function __construct(array $params = [])
    {
    }

    public function index(): void
    {
        $GLOBALS['wpfw_short'] = 'index';
    }

    public function saludo(): void
    {
        $GLOBALS['wpfw_short'] = 'saludo';
    }

    public function atributos(array $params): void
    {
        $GLOBALS['wpfw_short'] = $params;
    }

    private function oculto(): void
    {
        $GLOBALS['wpfw_short'] = 'oculto';
    }

    protected function protegido(): void
    {
        $GLOBALS['wpfw_short'] = 'protegido';
    }
}
PHP;
	}
}
