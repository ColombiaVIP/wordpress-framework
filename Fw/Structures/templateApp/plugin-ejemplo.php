<?php
/*
@wordpress-plugin
# Data
* License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! require_once(__DIR__ . '/requirements.php') ) {
    return;
}

new Fw\Framework(__FILE__);
