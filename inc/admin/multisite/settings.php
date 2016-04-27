<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/*------------------------------------------------------------------------------------------------*/
/* !MULTISITE SETTINGS API ====================================================================== */
/*------------------------------------------------------------------------------------------------*/

add_filter( 'secupress_whitelist_network_options', 'secupress_network_option_update_filter' );
/**
 * Whitelist network options added with `secupress_register_setting()`.
 *
 * @since 1.0
 *
 * @param (array) $options Other whitelisted options.
 *
 * @return (array)
 */
function secupress_network_option_update_filter( $options ) {
	$whitelist = secupress_cache_data( 'new_whitelist_network_options' );

	if ( is_array( $whitelist ) ) {
		$options = add_option_whitelist( $whitelist, $options );
	}

	return $options;
}


/*------------------------------------------------------------------------------------------------*/
/* !SAVE SETTINGS ON FORM SUBMIT ================================================================ */
/*------------------------------------------------------------------------------------------------*/

add_action( 'admin_post_update', 'secupress_update_network_option_on_submit' );
/**
 * `options.php` does not handle network options. Let's use admin-post.php for multisite installations.
 *
 * @since 1.0
 */
function secupress_update_network_option_on_submit() {
	$option_groups = array( 'secupress_global_settings' => 1 );
	$modules       = secupress_get_modules();

	foreach ( $modules as $module => $atts ) {
		$option_groups[ "secupress_{$module}_settings" ] = 1;
	}

	if ( ! isset( $_POST['option_page'], $option_groups[ $_POST['option_page'] ] ) ) { // WPCS: CSRF ok.
		return;
	}

	$option_group = $_POST['option_page']; // WPCS: CSRF ok.

	secupress_check_admin_referer( $option_group . '-options' );
	secupress_check_user_capability();

	$whitelist_options = apply_filters( 'secupress_whitelist_network_options', array() );

	if ( ! isset( $whitelist_options[ $option_group ] ) ) {
		wp_die( __( '<strong>ERROR</strong>: options page not found.' ) );
	}

	$options = $whitelist_options[ $option_group ];

	if ( $options ) {
		foreach ( $options as $option ) {
			$option = trim( $option );
			$value  = null;

			if ( isset( $_POST[ $option ] ) ) {
				$value = $_POST[ $option ];
				if ( ! is_array( $value ) ) {
					$value = trim( $value );
				}
				$value = wp_unslash( $value );
			}

			update_site_option( $option, $value );
		}
	}

	/**
	 * Handle settings errors and return to options page.
	 */
	// If no settings errors were registered add a general 'updated' message.
	if ( ! count( get_settings_errors() ) ) {
		add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
	}
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	/**
	 * Redirect back to the settings page that was submitted.
	 */
	$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
	wp_redirect( esc_url_raw( $goback ) );
	exit;
}


/*------------------------------------------------------------------------------------------------*/
/* ADMIN MENU + NOTICE ========================================================================== */
/*------------------------------------------------------------------------------------------------*/

add_action( 'admin_menu', 'secupress_create_subsite_menu' );
/**
 * Create the plugin menu item in sites.
 * Also display an admin notice.
 *
 * @since 1.0
 */
function secupress_create_subsite_menu() {
	global $pagenow;

	if ( is_network_admin() || is_user_admin() || ! current_user_can( secupress_get_capability( true ) ) ) {
		return;
	}

	$site_id = get_current_blog_id();
	$sites   = secupress_get_results_for_ms_scanner_fixes();
	$cap     = secupress_get_capability( true );
	$menu    = false;

	if ( ! $sites ) {
		return;
	}

	foreach ( $sites as $site_data ) {
		if ( isset( $site_data[ $site_id ] ) ) {
			$menu = true;
			break;
		}
	}

	if ( ! $menu ) {
		return;
	}

	// Menu item.
	add_menu_page( SECUPRESS_PLUGIN_NAME, SECUPRESS_PLUGIN_NAME, $cap, 'secupress_scanners', '__secupress_subsite_scanners', 'dashicons-shield-alt' );

	// Display a notice for Administrators.
	if ( 'admin.php' !== $pagenow || empty( $_GET['page'] ) || 'secupress_scanners' !== $_GET['page'] ) {
		/* translators: 1 is an URL, 2 is the plugin name */
		$message = sprintf( __( 'Some security issues must be fixed, please visit <a href="%1$s">%2$s\'s page</a>.', 'secupress' ), esc_url( admin_url( 'admin.php?page=secupress_scanners' ) ), '<strong>' . SECUPRESS_PLUGIN_NAME . '</strong>' );
		secupress_add_notice( $message, null, 'subsite-security-issues' );
	} else {
		// The user is on the plugin page: make sure to not display the notice.
		secupress_dismiss_notice( 'subsite-security-issues' );
	}
}


add_filter( 'secupress_ajax_dismiss_notice_capability', 'secupress_ajax_dismiss_multisite_notice_capability', 10, 2 );
/**
 * Our "security issues" notice must be shown to the site's Administrators: change the capability for the ajax callback.
 *
 * @since 1.0
 *
 * @param (string) $capacity  Capability or user role.
 * @param (string) $notice_id The notice Identifier.
 *
 * @return (string) Capability or user role.
 */
function secupress_ajax_dismiss_multisite_notice_capability( $capacity, $notice_id ) {
	return 'subsite-security-issues' === $notice_id ? secupress_get_capability( true ) : $capacity;
}


add_action( 'secupress_empty_results_for_ms_scanner_fixes', 'secupress_remove_subsite_security_issues_notice_meta' );
/**
 * When all the site's fixes are done, remove the "dismissed notice" value from the users meta.
 * That way, the notice can be shown again later if needed (more fixes to do).
 *
 * @since 1.0
 */
function secupress_remove_subsite_security_issues_notice_meta() {
	global $wpdb;
	// Get all Administrators that have dismissed our notice.
	$users = get_users( array(
		'role'         => secupress_get_capability( true ),
		'meta_key'     => $wpdb->get_blog_prefix() . SecuPress_Admin_Notices::META_NAME,
		'meta_value'   => 'subsite-security-issues',
		'meta_compare' => 'LIKE',
		'fields'       => 'ID',
	) );

	if ( ! $users ) {
		return;
	}

	// Remove the value from the user meta.
	foreach ( $users as $user_id ) {
		SecuPress_Admin_Notices::reinit( 'subsite-security-issues', $user_id );
	}
}


/*------------------------------------------------------------------------------------------------*/
/* SCANS PAGE =================================================================================== */
/*------------------------------------------------------------------------------------------------*/

/**
 * Scanners page.
 *
 * @since 1.0
 */
function __secupress_subsite_scanners() {
	?>
	<div class="wrap">
		<?php secupress_admin_heading( __( 'Scanners', 'secupress' ) ); ?>

		<div class="secupress-wrapper">
			<?php secupress_scanners_template(); ?>
		</div>

	</div>
	<?php
}


/*------------------------------------------------------------------------------------------------*/
/* ACCESSING THE SETTINGS PAGE WHEN IT'S NOT AVAILABLE ========================================== */
/*------------------------------------------------------------------------------------------------*/

add_action( 'admin_page_access_denied', 'secupress_settings_page_access_denied_message' );
/**
 * On each site when all fixes are done, the settings page is not available anymore.
 * If the user refreshes the page, a "You do not have sufficient permissions to access this page" message will be shown: we need to display a better message.
 *
 * @since 1.0
 */
function secupress_settings_page_access_denied_message() {
	global $pagenow;
	if ( is_network_admin() || is_user_admin() || 'admin.php' !== $pagenow || empty( $_GET['page'] ) || 'secupress_scanners' !== $_GET['page'] ) {
		return;
	}
	if ( ! current_user_can( secupress_get_capability( true ) ) ) {
		return;
	}
	/* translators: %s is a link to the dashboard */
	$message = __( 'Since there are no other fixes to be done, this page does not exist anymore.<br/>You can go back to the %s.', 'secupress' );
	$link    = '<a href="' . esc_url( admin_url() ) . '">' . __( 'Dashboard' ) . '</a>';
	$title   = __( 'Back to the Dashboard', 'secupress' );
	// HTTP code 403: "Forbidden".
	secupress_die( sprintf( $message, $link ), $title, array( 'response' => 403 ) );
}
