<?php
/**
 * Plugin Name: Hesaplamaa Profile
 * Plugin URI: https://hesaplamaa.com/
 * Description: Hesaplamaa kullanıcıları için kişisel analiz, profil dashboard ve paylaşılabilir profil kartları eklentisi.
 * Version: 0.1.0
 * Author: Hesaplamaa
 * Text Domain: hesaplamaa-profile
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAP_VERSION', '0.1.0' );
define( 'HAP_PLUGIN_FILE', __FILE__ );
define( 'HAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HAP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'HAP_TABLE_MODULES', 'hap_profile_modules' );
define( 'HAP_TABLE_SHARES', 'hap_profile_shares' );

require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-activator.php';
require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-deactivator.php';
require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-plugin.php';

register_activation_hook( __FILE__, array( 'HAP_Profile_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HAP_Profile_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'HAP_Profile_Plugin', 'get_instance' ) );
