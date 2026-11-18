<?php

namespace PluginA\Controllers\Routes;

class DemoController
{
	public static bool $enableUri = true;

	public static array $webActions = array( 'saludo' );

	public function index(): void
	{
	}

	public function saludo(): void
	{
	}

	public function secreto(): void
	{
	}
}
