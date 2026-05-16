<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'hap_profile_delete_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hap_profile_modules" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hap_profile_shares" );

$options = array(
	'hap_profile_settings',
	'hap_profile_fields',
	'hap_profile_ai_templates',
	'hap_profile_share_settings',
	'hap_profile_updater_settings',
	'hap_profile_db_version',
	'hap_profile_delete_on_uninstall',
);
foreach ( $options as $option ) {
	delete_option( $option );
}

$meta_keys = array(
	'_hap_profile_nickname',
	'_hap_profile_birth_date',
	'_hap_profile_birth_time',
	'_hap_profile_birth_place',
	'_hap_profile_gender',
	'_hap_profile_height',
	'_hap_profile_weight',
	'_hap_profile_city',
	'_hap_profile_activity_level',
	'_hap_profile_sleep_hours',
	'_hap_profile_daily_steps',
	'_hap_profile_relationship_status',
	'_hap_profile_partner_birth_date',
	'_hap_profile_home_number',
	'_hap_profile_phone_number',
	'_hap_profile_plate_number',
	'_hap_profile_company_name',
	'_hap_profile_baby_name',
	'_hap_profile_career_goal',
);
foreach ( $meta_keys as $key ) {
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $key ) );
}
