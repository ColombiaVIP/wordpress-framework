<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$path = __DIR__ . '/composer/autoload_real.php';
if ( file_exists($path) ) {
    require_once $path;
}

if ( class_exists('ComposerAutoloaderInit') ) {
    return ComposerAutoloaderInit::getLoader();
}

