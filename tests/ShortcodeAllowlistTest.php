<?php
/**
 * El shortcode solo ejecuta métodos de la lista y la categoría es un identificador.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Fw\Config\Apps;
use Fw\Core\Request\RequestShortcode;

class ShortcodeAllowlistTest extends FrameworkTestCase
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

	public function test_clase_arroba_metodo_no_ejecuta_un_metodo_publico_sin_declarar(): void
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

		$GLOBALS['wpfw_short'] = '';
		self::assertSame( '', $shortcode->prepare( array( 'Posts' => 'Posts@secreto' ) ) );
		self::assertSame( '', $GLOBALS['wpfw_short'] );

		self::assertNull( $shortcode->prepare( array( '../X' => 'Posts@saludo' ) ) );
		self::assertArrayNotHasKey( 'wpfw_canario', $GLOBALS );

		self::assertNull( $shortcode->prepare( array( 'Posts' => 'Posts@__construct' ) ) );
		self::assertSame( '', $GLOBALS['wpfw_short'] );

		$shortcode->prepare( array( 'Posts' => 'Posts@saludo', 'type' => 'posts' ) );
		self::assertSame( 'saludo', $GLOBALS['wpfw_short'] );

		unlink( $canario );
	}

	private function fuente(): string
	{
		return <<<'PHP'
<?php
namespace ShortDemo\Controllers\Shortcodes\Posts;

class PostsController
{
    public static array $webActions = ['saludo'];

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

    public function secreto(): void
    {
        $GLOBALS['wpfw_short'] = 'secreto';
    }
}
PHP;
	}
}
