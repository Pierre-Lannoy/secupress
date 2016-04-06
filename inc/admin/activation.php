<?php
defined( 'ABSPATH' ) or die( 'Cheatin\' uh?' );

/*------------------------------------------------------------------------------------------------*/
/* ACTIVATE ===================================================================================== */
/*------------------------------------------------------------------------------------------------*/

/**
 * Tell WP what to do when the plugin is activated
 *
 * @since 1.1.0
 */
register_activation_hook( SECUPRESS_FILE, 'secupress_activation' );

function secupress_activation() {
	// Last constants.
	define( 'SECUPRESS_PLUGIN_NAME', 'SecuPress' );
	define( 'SECUPRESS_PLUGIN_SLUG', sanitize_key( SECUPRESS_PLUGIN_NAME ) );

	// Make sure our texts are translated.
	secupress_load_plugin_textdomain_translations();

	/**
	 * Fires on SecuPress activation.
	 *
	 * @since 1.0
	 */
	do_action( 'secupress_activation' );

	/**
	 * As this activation hook appends before our plugins are loaded (and the page is reloaded right after that),
	 * this transient will trigger a custom activation hook in `secupress_load_plugins()`.
	 */
	set_site_transient( 'secupress_activation', 1 );
}


/**
 * Maybe add rules in `.htaccess` or `web.config` file on SecuPress activation.
 *
 * @since 1.0
 */
add_action( 'secupress.plugins.activation', 'secupress_maybe_write_rules_on_activation', 10000 );

function secupress_maybe_write_rules_on_activation() {
	global $is_apache, $is_iis7, $is_nginx;

	if ( ! $is_apache && ! $is_iis7 && ! $is_nginx ) {
		// System not supported.
		return;
	}

	$rules = array();

	// Banned IPs.
	if ( secupress_write_in_htaccess_on_ban() ) {
		$rules['ban_ip'] = secupress_get_htaccess_ban_ip();
	}

	/**
	 * Rules that must be added to the `.htaccess`, `web.config`, or `nginx.conf` file on SecuPress activation.
	 *
	 * @since 1.0
	 *
	 * @param (array) $rules An array of rules with the modules marker as key and rules (string) as value. For IIS7 it's an array of arguments (each one containing a row with the rules).
	 */
	$rules = apply_filters( 'secupress.plugins.activation.write_rules', $rules );
	$rules = array_filter( $rules );

	if ( ! $rules ) {
		// Meh.
		return;
	}

	// Apache.
	if ( $is_apache ) {
		$wp_filesystem = secupress_get_filesystem();
		$home_path     = secupress_get_home_path();
		$file_path     = $home_path . '.htaccess';
		$file_content  = '';
		$new_content   = '';

		// Get the whole content of the file.
		if ( file_exists( $file_path ) && is_writable( $file_path ) ) {
			$file_content = (string) $wp_filesystem->get_contents( $file_path );
			/**
			 * Filter the `.htaccess` file content before add new rules.
			 *
			 * @since 1.0
			 *
			 * @param (string) $file_content The file content.
			 */
			$file_content = apply_filters( 'secupress.plugins.activation.htaccess_content_before_write_rules', $file_content );
		}

		foreach ( $rules as $marker => $new_rules ) {
			// Remove old content (shouldn't left anything).
			if ( $file_content ) {
				$pattern      = '/# BEGIN SecuPress ' . $marker . '(.*)# END SecuPress\s*?/isU';
				$file_content = preg_replace( $pattern, '', $file_content );
			}
			// Create new content.
			$new_content .= '# BEGIN SecuPress ' . $marker . PHP_EOL;
			$new_content .= trim( $new_rules ) . PHP_EOL;
			$new_content .= '# END SecuPress' . PHP_EOL . PHP_EOL;
		}

		if ( ! secupress_root_file_is_writable( '.htaccess' ) ) {
			$message = sprintf( __( 'Your %1$s file seems not to be writable. Please add the following at the beginning of the file: %2$s', 'secupress' ), '<code>.htaccess</code>', '<pre>' . esc_html( $new_content ) . '</pre>' );
			secupress_add_notice( $message, 'error', 'secupress-activation-file-not-writable' );
			return;
		}

		$file_content = $new_content . $file_content;

		// Save the file.
		$wp_filesystem->put_contents( $file_path, $file_content, FS_CHMOD_FILE );
		return;
	}

	// IIS7.
	if ( $is_iis7 ) {
		$file_path = $home_path . 'web.config';

		// If configuration file does not exist then we create one.
		if ( ! file_exists( $file_path ) ) {
			$fp = fopen( $file_path, 'w' );
			fwrite( $fp, '<configuration/>' );
			fclose( $fp );
		}

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;

		// Load the file.
		$loaded = false;
		if ( false !== $doc->load( $file_path ) ) {
			$loaded = true;
		}

		// Now, if the file failed to load, we'll only store data in an array and display it in a message for the user.
		if ( $loaded ) {
			$xpath = new DOMXPath( $doc );
		} else {
			$data = array();
		}

		foreach ( $rules as $marker => $args ) {
			$args = wp_parse_args( $args, array(
				'nodes_string' => '',
				'node_types'   => false,
				'path'         => '',
				'attribute'    => 'name',
			) );

			$nodes_string = $args['nodes_string'];
			$nodes_string = is_array( $nodes_string ) ? implode( "\n", $nodes_string ) : $nodes_string;
			$nodes_string = trim( $nodes_string, "\r\n\t " );
			$node_types   = $args['node_types'];
			$path         = $args['path'];
			$attribute    = $args['attribute'];

			$path_end = ! $path && strpos( ltrim( $nodes_string ), '<rule ' ) === 0 ? '/rewrite/rules/rule' : '';
			$path     = '/configuration/system.webServer' . ( $path ? '/' . trim( $path, '/' ) : '' ) . $path_end;

			if ( ! $loaded ) {
				/* translators: %s is a folder path */
				$new_data = sprintf( __( 'In %s:', 'secupress' ), "<code>$path</code>" );
			}

			// Remove possible nodes not created by us, but with the same node type.
			if ( $node_types ) {
				$node_types = (array) $node_types;

				foreach ( $node_types as $i => $node_type ) {
					if ( $loaded ) {
						$old_nodes = $xpath->query( $path . '/' . $node_type );

						if ( $old_nodes->length > 0 ) {
							foreach ( $old_nodes as $old_node ) {
								$old_node->parentNode->removeChild( $old_node );
							}
						}
					} else {
						$node_types[ $i ] = "<code>$node_type</code>";
					}
				}

				if ( ! $loaded ) {
					$new_data .= '<br/>' . sprintf( __( 'Remove all existing %s tags.', 'secupress' ), wp_sprintf_l( '%l', $node_types ) );
				}
			}

			// Indentation.
			$spaces = explode( '/', trim( $path, '/' ) );
			$spaces = count( $spaces ) - 1;
			$spaces = str_repeat( ' ', $spaces * 2 );

			if ( $loaded ) {
				// Create fragment.
				$fragment = $doc->createDocumentFragment();
				$fragment->appendXML( "\n$spaces  $nodes_string\n$spaces" );

				// Maybe create child nodes and then, prepend new nodes.
				__secupress_get_iis7_node( $doc, $xpath, $path, $fragment );
			} else {
				$nodes_string = esc_html( $nodes_string );
				$new_data    .= '<br/>' . sprintf( __( 'Add the following: %s', 'secupress' ), "<pre>\n$spaces  $nodes_string\n$spaces</pre>" );
				$data[]       = $new_data;
			}
		}

		if ( ! $loaded ) {
			$message = sprintf( __( 'Your %1$s file seems not to be writable. Please edit this file, following these instructions: %2$s', 'secupress' ), '<code>web.config</code>', implode( '<br/>', $data ) );
			secupress_add_notice( $message, 'error', 'secupress-activation-file-not-writable' );
			return;
		}

		// Save the file.
		$doc->encoding     = 'UTF-8';
		$doc->formatOutput = true;
		saveDomDocument( $doc, $file_path );
		return;
	}

	// Nginx.
	$message = sprintf( __( 'Since your %1$s file cannot be edited directly, please add the following in your file: %2$s', 'secupress' ), '<code>nginx.conf</code>', '<pre>' . implode( "\n", $rules ) . '</pre>' );
	secupress_add_notice( $message, 'error', 'secupress-activation-file-not-writable' );
}


/*------------------------------------------------------------------------------------------------*/
/* DEACTIVATE =================================================================================== */
/*------------------------------------------------------------------------------------------------*/

/**
 * Tell WP what to do when the plugin is deactivated.
 *
 * @since 1.0
 */
register_deactivation_hook( SECUPRESS_FILE, 'secupress_deactivation' );

function secupress_deactivation() {
	// Pause the licence.
	wp_remote_get( SECUPRESS_WEB_MAIN . 'pause-licence.php' );

	// While the plugin is deactivated, some sites may activate or deactivate other plugins, or change their default user role.
	if ( is_multisite() ) {
		delete_site_option( 'secupress_active_plugins' );
		delete_site_option( 'secupress_active_themes' );
		delete_site_option( 'secupress_default_role' );
	}

	// Make sure our texts are translated.
	secupress_load_plugin_textdomain_translations();

	/**
	 * Fires on SecuPress deactivation.
	 *
	 * @since 1.0
	 *
	 * @param (array) An empty array to mimic the `$args` parameter from `secupress_deactivate_submodule()`.
	 */
	do_action( 'secupress_deactivation', array() );
}


/**
 * Maybe remove rules from `.htaccess` or `web.config` file on SecuPress deactivation.
 *
 * @since 1.0
 */
add_action( 'secupress_deactivation', 'secupress_maybe_remove_rules_on_deactivation', 10000 );

function secupress_maybe_remove_rules_on_deactivation() {
	global $is_apache, $is_iis7, $is_nginx;

	if ( ! $is_apache && ! $is_iis7 ) {
		if ( $is_nginx ) {
			// Since we can't edit the file, no other way but to kill the page :s
			$message  = sprintf( __( '%s: ', 'secupress' ), SECUPRESS_PLUGIN_NAME );
			$message .= sprintf(
					/* translators: 1 and 2 are small parts of code, 3 is a file name. */
				__( 'It seems your server uses a <i>Nginx</i> system. You have to edit the configuration file manually. Please remove all rules between %1$s and %2$s from the %3$s file.', 'secupress' ),
				'<code># BEGIN SecuPress move_login</code>',
				'<code># END SecuPress</code>',
				'<code>nginx.conf</code>'
			);
			secupress_create_deactivation_notice_muplugin( 'nginx_remove_rules', $message );
		}
		return;
	}

	$home_path = secupress_get_home_path();

	// Apache.
	if ( $is_apache ) {
		$file_path = $home_path . '.htaccess';

		if ( ! file_exists( $file_path ) ) {
			// RLY?
			return;
		}

		if ( ! is_writable( $file_path ) ) {
			// If the file is not writable, no other way but to kill the page :/
			$message  = sprintf( __( '%s: ', 'secupress' ), SECUPRESS_PLUGIN_NAME );
			$message .= sprintf(
				/* translators: 1 and 2 are small parts of code, 3 is a file name. */
				__( 'It seems your %2$s file is not writable. You have to edit the file manually. Please remove all rules between %1$s and %2$s from the %3$s file.', 'secupress' ),
				'<code># BEGIN SecuPress</code>',
				'<code># END SecuPress</code>',
				'<code>.htaccess</code>'
			);
			secupress_create_deactivation_notice_muplugin( 'apache_remove_rules', $message );
		}

		// Get the whole content of the file.
		$file_content = file_get_contents( $file_path );

		if ( ! $file_content ) {
			// Nothing? OK.
			return;
		}

		// Remove old content.
		$pattern      = '/# BEGIN SecuPress (.*)# END SecuPress\s*?/isU';
		$file_content = preg_replace( $pattern, '', $file_content );

		// Save the file.
		$wp_filesystem = secupress_get_filesystem();
		$wp_filesystem->put_contents( $file_path, $file_content, FS_CHMOD_FILE );
		return;
	}

	// IIS7.
	$file_path = $home_path . 'web.config';

	if ( ! file_exists( $file_path ) ) {
		// RLY?
		return;
	}

	$doc = new DOMDocument();
	$doc->preserveWhiteSpace = false;

	if ( false === $doc->load( $file_path ) ) {
		// If the file is not writable, no other way but to kill the page :/
		$message  = sprintf( __( '%s: ', 'secupress' ), SECUPRESS_PLUGIN_NAME );
		$message .= sprintf(
			/* translators: 1 is a small part of code, 2 is a file name. */
			__( 'It seems your %2$s file is not writable. You have to edit the file manually. Please remove all rules with %1$s from the %2$s file.', 'secupress' ),
			'<code>SecuPress</code>',
			'<code>web.config</code>'
		);
		secupress_create_deactivation_notice_muplugin( 'iis7_remove_rules', $message );
	}

	// Remove old content.
	$xpath = new DOMXPath( $doc );
	$nodes = $xpath->query( "/configuration/system.webServer/*[starts-with(@name,'SecuPress ') or starts-with(@id,'SecuPress ')]" );

	if ( $nodes->length > 0 ) {
		foreach ( $nodes as $node ) {
			$node->parentNode->removeChild( $node );
		}
	}

	// Save the file.
	$doc->formatOutput = true;
	saveDomDocument( $doc, $file_path );
}


/**
 * Create a MU plugin that will display an admin notice. When the user click the button, the MU plugin is destroyed.
 * This is used to display a message after SecuPress is deactivated.
 *
 * @since 1.0
 *
 * @param (string) $plugin_id A unique identifier for the MU plugin.
 * @param (string) $message   The message to display.
 */
function secupress_create_deactivation_notice_muplugin( $plugin_id, $message ) {
	global $wp_filesystem;
	static $authenticated;

	if ( ! function_exists( 'wp_validate_auth_cookie' ) ) {
		return;
	}

	if ( ! isset( $authenticated ) ) {
		$authenticated = wp_validate_auth_cookie();
	}

	$filename = WPMU_PLUGIN_DIR . "/_secupress_deactivation-notice-$plugin_id.php";

	if ( ! $authenticated || file_exists( $filename ) ) {
		return;
	}

	// Filesystem.
	if ( ! $wp_filesystem ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

		$wp_filesystem = new WP_Filesystem_Direct( new StdClass() );
	}

	if ( ! defined( 'FS_CHMOD_DIR' ) ) {
		define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
	}
	if ( ! defined( 'FS_CHMOD_FILE' ) ) {
		define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
	}

	// Plugin contents.
	$contents = $wp_filesystem->get_contents( SECUPRESS_INC_PATH . 'data/deactivation-mu-plugin.data' );

	// Add new contents.
	$args = array(
		'##PLUGIN_ID##'   => $plugin_id,
		'##MESSAGE##'     => addcslashes( $message, "'" ),
		'##USER_ID##'     => get_current_user_id(),
		'##BUTTON_TEXT##' => __( 'OK, got it!', 'secupress' ),
	);

	$contents = str_replace( array_keys( $args ), $args, $contents );

	if ( ! file_exists( WPMU_PLUGIN_DIR ) ) {
		$wp_filesystem->mkdir( WPMU_PLUGIN_DIR );
	}

	if ( ! file_exists( WPMU_PLUGIN_DIR ) ) {
		return;
	}

	$wp_filesystem->put_contents( $filename, $contents );
}
