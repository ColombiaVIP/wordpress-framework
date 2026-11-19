<?php
/**
 * ORM opcional: el arranque no carga Dbout/Illuminate/Carbon ni los helpers globales de Laravel;
 * se cargan la primera vez que se usa un modelo, o siempre con WPFW_ORM.
 * Cada caso corre en un proceso PHP aparte porque el bootstrap de PHPUnit ya incluye vendor/autoload.php.
 */

declare(strict_types=1);

namespace Fw\Tests;

use PHPUnit\Framework\TestCase;

class CargadorOrmTest extends TestCase
{
	private function sonda( string $modo ): array
	{
		$cmd    = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/Fixtures/orm/sonda.php' ) . ' ' . escapeshellarg( $modo ) . ' 2>&1';
		$salida = shell_exec( $cmd );
		$datos  = json_decode( (string) $salida, true );
		self::assertIsArray( $datos, 'Salida de la sonda: ' . $salida );
		return $datos;
	}

	public function test_el_arranque_no_carga_el_orm_ni_los_helpers_de_laravel(): void
	{
		$r = $this->sonda( 'arranque' )['despues'];

		self::assertFalse( $r['orm'] );
		self::assertFalse( $r['collect'] );
		self::assertFalse( $r['env'] );
		self::assertFalse( $r['value'] );
		self::assertFalse( $r['e'] );
		self::assertFalse( $r['event'] );
		self::assertSame( 0, $r['archivosVendorOrm'] );
		self::assertTrue( $r['helpersFw'], 'Los helpers propios del framework sí se cargan al arrancar.' );
	}

	public function test_las_clases_del_framework_no_disparan_el_orm(): void
	{
		$r = $this->sonda( 'clase-fw' )['despues'];

		self::assertFalse( $r['orm'] );
		self::assertSame( 0, $r['archivosVendorOrm'] );
	}

	public function test_un_modelo_carga_el_orm_y_los_helpers(): void
	{
		$r = $this->sonda( 'modelo' );

		self::assertFalse( $r['antes']['orm'] );
		self::assertTrue( $r['despues']['orm'] );
		self::assertTrue( $r['despues']['collect'] );
		self::assertTrue( $r['despues']['event'] );
		self::assertTrue( $r['despues']['model'] );
		self::assertGreaterThan( 0, $r['despues']['archivosVendorOrm'] );
		self::assertSame( 6, $r['suma'] );
		self::assertTrue( $r['softDel'] );
	}

	public function test_wpfw_orm_carga_todo_desde_el_arranque(): void
	{
		$r = $this->sonda( 'constante' )['antes'];

		self::assertTrue( $r['orm'] );
		self::assertTrue( $r['collect'] );
		self::assertTrue( $r['value'] );
		self::assertTrue( $r['e'] );
	}

	public function test_convive_con_el_autoload_clasico_ya_cargado(): void
	{
		$r = $this->sonda( 'composer-antes' )['despues'];

		self::assertTrue( $r['collect'] );
		self::assertTrue( $r['helpersFw'] );
	}
}
