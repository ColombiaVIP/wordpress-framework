<?php
/**
 * El HTML de formularios, tablas y vistas no imprime datos crudos.
 */

declare(strict_types=1);

namespace Fw\Tests;

use WordpressFramework\Controllers\HTML\FormController;
use WordpressFramework\Controllers\HTML\HTMLController;
use WordpressFramework\Controllers\ListTableController;

class EscapeHtmlTest extends FrameworkTestCase
{
	public function test_los_atributos_del_formulario_salen_escapados(): void
	{
		$html = HTMLController::text( 'nombre', '" onfocus="alert(1)', 'required onfocus=alert(1)', 'readonly onclick=alert(1)' );

		self::assertStringNotContainsString( '" onfocus=', $html );
		self::assertStringContainsString( '&quot; onfocus=&quot;alert(1)', $html );
		self::assertStringNotContainsString( ' readonly', $html );
		self::assertStringNotContainsString( ' required', $html );
	}

	public function test_el_formulario_de_guardado_lleva_nonce_y_escapa_el_valor(): void
	{
		$_GET['page'] = 'libros';
		$form = new FormController(
			array(
				'fields' => array(
					array(
						'Field' => 'nombre',
						'Type'  => 'text',
					),
				),
				'values' => array( 'nombre' => '<script>alert(1)</script>' ),
				'model'  => 'Libro',
			)
		);

		$html = $form->html();

		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );
		self::assertStringContainsString( '_wpfw_nonce', $html );
		self::assertStringContainsString( 'option=save', $html );
		self::assertStringContainsString( 'nonce-wpfw_option_libros_save', $html );
	}

	public function test_la_tabla_escapa_celdas_y_exige_nonce_en_cada_option(): void
	{
		$_GET['page']    = 'libros';
		$_GET['orderby'] = 'nombre;drop';
		$_GET['order']   = 'asc';

		$list = new ListTableController();
		set_error_handler(
			static function ( int $severity, string $message ): never {
				throw new \ErrorException( $message, 0, $severity );
			}
		);

		try {
			$list->prepare_items(
				array(
					'items'   => array(
						array(
							'id'     => '2',
							'nombre' => '<script>b</script>',
						),
						array(
							'id'     => '1',
							'nombre' => '<script>a</script>',
						),
					),
					'columns' => array(
						'id'     => 'Id',
						'nombre' => 'Nombre',
					),
					'main'    => 'id',
					'search'  => false,
					'options' => array( 'delete' => '<b>Borrar</b>' ),
				)
			);
		} finally {
			restore_error_handler();
		}

		self::assertSame( '1', $list->items[0]['id'] );

		$celda = $list->column_default( $list->items[0], 'nombre' );
		self::assertStringNotContainsString( '<script>', $celda );
		self::assertStringContainsString( '&lt;script&gt;', $celda );

		$acciones = $list->column_default( $list->items[0], 'id' );
		self::assertStringContainsString( '_wpfw_nonce', $acciones );
		self::assertStringContainsString( 'option=delete', $acciones );
		self::assertStringNotContainsString( '<b>', $acciones );
		self::assertStringContainsString( '&lt;b&gt;Borrar&lt;/b&gt;', $acciones );
	}

	public function test_orderby_solo_acepta_una_columna_real(): void
	{
		$_GET['page']    = 'libros';
		$_GET['orderby'] = 'nombre';
		$_GET['order']   = 'asc';

		$list = new ListTableController();
		$list->prepare_items(
			array(
				'items'   => array(
					array( 'id' => '2', 'nombre' => 'b' ),
					array( 'id' => '1', 'nombre' => 'a' ),
				),
				'columns' => array(
					'id'     => 'Id',
					'nombre' => 'Nombre',
				),
				'main'    => 'id',
				'search'  => false,
				'options' => array(),
			)
		);

		self::assertSame( 'a', $list->items[0]['nombre'] );
	}

	public function test_las_vistas_escapan_el_texto_y_el_enlace(): void
	{
		$title = '<script>alert(1)</script>';
		ob_start();
		include dirname( __DIR__ ) . '/Fw/Structures/templateApp/app/views/index.php';
		$html = (string) ob_get_clean();

		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );

		$backLink = 'javascript:alert(1)';
		$backText = '<b>volver</b>';
		ob_start();
		include dirname( __DIR__ ) . '/app/views/404.php';
		$error = (string) ob_get_clean();

		self::assertStringNotContainsString( 'javascript:', $error );
		self::assertStringNotContainsString( '<b>', $error );
		self::assertStringContainsString( '&lt;b&gt;volver&lt;/b&gt;', $error );
	}
}
