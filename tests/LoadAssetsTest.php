<?php
/**
 * Pedir solo JS o solo CSS usa la configuración de la instancia.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Fw\Init\LoadAssets;

class LoadAssetsTest extends FrameworkTestCase
{
	public function test_load_js_y_load_css_registran_el_tipo_pedido(): void
	{
		$assets = sys_get_temp_dir() . '/wp-content/plugins/demoapp/app/assets-' . uniqid( '', true );
		mkdir( $assets . '/js', 0777, true );
		mkdir( $assets . '/css', 0777, true );
		file_put_contents( $assets . '/js/demo.js', 'console.log(1);' );
		file_put_contents( $assets . '/css/demo.css', 'body{}' );

		$js = new LoadAssets(
			array(
				'load'   => 'js',
				'path'   => $assets,
				'mode'   => 'production',
				'argsJs' => array( 'ajaxurl' => 'https://example.test/ajax' ),
			)
		);
		$js->loadAsset();

		self::assertNotEmpty( $GLOBALS['wpfw_test_scripts'] );
		self::assertEmpty( $GLOBALS['wpfw_test_styles'] );
		self::assertStringContainsString( 'demo.js', $GLOBALS['wpfw_test_scripts'][0]['src'] );

		$GLOBALS['wpfw_test_scripts'] = array();
		$css = new LoadAssets(
			array(
				'load' => 'css',
				'path' => $assets,
				'mode' => 'production',
			)
		);
		$css->loadAsset();

		self::assertNotEmpty( $GLOBALS['wpfw_test_styles'] );
		self::assertEmpty( $GLOBALS['wpfw_test_scripts'] );
		self::assertStringContainsString( 'demo.css', $GLOBALS['wpfw_test_styles'][0]['src'] );
	}
}
