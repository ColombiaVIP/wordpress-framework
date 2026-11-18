<?php
/**
 * Model::describe() no arma SQL si el nombre de tabla no es un identificador.
 */

declare(strict_types=1);

namespace Fw\Tests;

use WordpressFramework\Models\Model;

class TablaDePrueba extends Model
{
	public string $tabla = 'wp_posts';

	public function __construct()
	{
	}

	public function getTable()
	{
		return $this->tabla;
	}

	public function getConnection()
	{
		return new class() {
			public function select( string $sql ): array
			{
				$GLOBALS['wpfw_sql'] = $sql;
				return array( array( 'Field' => 'id' ) );
			}
		};
	}
}

class DescribeTest extends FrameworkTestCase
{
	public function test_un_nombre_de_tabla_inseguro_no_consulta(): void
	{
		unset( $GLOBALS['wpfw_sql'] );
		$modelo        = new TablaDePrueba();
		$modelo->tabla = 'wp_users; DROP TABLE wp_users';

		self::assertSame( array(), $modelo->describe() );
		self::assertArrayNotHasKey( 'wpfw_sql', $GLOBALS );
	}

	public function test_un_nombre_de_tabla_valido_solo_describe_ese_identificador(): void
	{
		$modelo        = new TablaDePrueba();
		$modelo->tabla = 'wp_posts';

		self::assertSame( array( array( 'Field' => 'id' ) ), $modelo->describe() );
		self::assertSame( 'DESCRIBE `wp_posts`', $GLOBALS['wpfw_sql'] );
	}
}
