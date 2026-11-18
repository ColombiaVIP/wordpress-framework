<?php
/**
 * Cada PHP del plugin corta el acceso directo.
 */

declare(strict_types=1);

namespace Fw\Tests;

class AbspathTest extends FrameworkTestCase
{
	public function test_los_php_del_plugin_tienen_guard_abspath(): void
	{
		$raiz  = dirname( __DIR__ );
		$faltan = array();
		$iterador = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $raiz, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterador as $archivo ) {
			if ( ! $archivo->isFile() || $archivo->getExtension() !== 'php' ) {
				continue;
			}

			$ruta = $archivo->getPathname();
			if (
				str_contains( $ruta, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR )
				|| str_contains( $ruta, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR )
				|| str_contains( $ruta, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR )
			) {
				continue;
			}

			$contenido = (string) file_get_contents( $ruta );
			if ( preg_match( '/defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)/', $contenido ) !== 1 ) {
				$faltan[] = ltrim( str_replace( $raiz, '', $ruta ), '/' );
			}
		}

		self::assertSame( array(), $faltan );
	}
}
