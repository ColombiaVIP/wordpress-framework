<?php
/**
 * Base de las pruebas. Brain Monkey prepara las funciones de WordPress que cada caso observa.
 */

declare(strict_types=1);

namespace Fw\Tests;

use Brain\Monkey;
use Fw\Init\Ajax\AjaxManager;
use Fw\Init\Routing\RoutingProcessor;
use PHPUnit\Framework\TestCase;

abstract class FrameworkTestCase extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Monkey\setUp();
		RoutingProcessor::resetCurrent();
		AjaxManager::reset();

		$GLOBALS['wpfw_test_actions']         = array();
		$GLOBALS['wpfw_test_tags']            = array();
		$GLOBALS['wpfw_test_rules']           = array();
		$GLOBALS['wpfw_test_flushes']         = 0;
		$GLOBALS['wpfw_test_activation']      = array();
		$GLOBALS['wpfw_test_status']          = array();
		$GLOBALS['wpfw_test_query_vars']      = array();
		$GLOBALS['wpfw_test_menus']           = array();
		$GLOBALS['wpfw_test_submenus']        = array();
		$GLOBALS['wpfw_test_scripts']         = array();
		$GLOBALS['wpfw_test_styles']          = array();
		$GLOBALS['wpfw_test_block_template']  = 0;
		$GLOBALS['wpfw_json']                 = null;
		$GLOBALS['wpfw_can']                  = true;
		$GLOBALS['wpfw_caps']                 = array();
		$GLOBALS['wpfw_test_options']         = array();

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		unset( $GLOBALS['wp_query'] );
	}

	protected function tearDown(): void
	{
		Monkey\tearDown();
		parent::tearDown();
	}
}
