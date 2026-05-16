<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
