<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Full-width profile dashboard page template.
 * Loaded via HAP_Profile_Page_Template (template_include filter).
 * Bypasses the theme's narrow content container.
 *
 * Header and footer remain from the active theme; only the content
 * area is replaced with a full-width <main> shell.
 */

get_header();
?>
<main class="hap-profile-page-shell">
	<?php echo do_shortcode( '[hap_user_profile_dashboard]' ); ?>
</main>
<?php
get_footer();
