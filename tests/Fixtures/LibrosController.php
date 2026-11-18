<?php

namespace CapDemo\Controllers\MenuPages\Libros;

class LibrosController
{
	public function index(): void
	{
	}
}

class EspecialController
{
	public static string $capability = 'edit_pages';

	public function index(): void
	{
	}
}
