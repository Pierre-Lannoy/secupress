<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/**
 * Inactive Plugins Themes scan class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Scan
 * @since 1.0
 */

class SecuPress_Scan_Inactive_Plugins_Themes extends SecuPress_Scan implements iSecuPress_Scan {

	const VERSION = '1.0';

	/**
	 * @var Singleton The reference to *Singleton* instance of this class
	 */
	protected static $_instance;
	public    static $prio = 'medium';


	protected static function init() {
		self::$type  = 'WordPress';
		self::$title = __( 'Check if you have some deactivated plugins or themes.', 'secupress' );
		self::$more  = __( 'Even deactivated plugins or themes can potentially be exploited to some vulnerabilities. Don\'t take the risk to keep them on your website.', 'secupress' );
	}


	public static function get_messages( $message_id = null ) {
		$messages = array(
			// good
			0   => __( 'You don\'t have any deactivated plugins or themes.', 'secupress' ),
			1   => __( 'All inactive plugins have been deleted.', 'secupress' ),
			2   => __( 'All inactive themes have been deleted.', 'secupress' ),
			// wraning
			100 => __( 'No plugins selected.', 'secupress' ),
			101 => __( 'All selected plugins have been deleted (but some are still there).', 'secupress' ),
			102 => _n_noop( 'Sorry, the following plugin could not be deleted: %s.', 'Sorry, the following plugins could not be deleted: %s.', 'secupress' ),
			103 => __( 'No themes selected.', 'secupress' ),
			104 => __( 'All selected themes have been deleted (but some are still there).', 'secupress' ),
			105 => _n_noop( 'Sorry, the following theme could not be deleted: %s.', 'Sorry, the following themes could not be deleted: %s.', 'secupress' ),
			// bad
			200 => _n_noop( '<strong>%1$d deactivated plugin</strong>, if you don\'t need it, delete it: %2$s.', '<strong>%1$d deactivated plugins</strong>, if you don\'t need them, delete them: %2$s.', 'secupress' ),
			201 => _n_noop( '<strong>%1$d deactivated theme</strong>, if you don\'t need it, delete it: %2$s.', '<strong>%1$d deactivated themes</strong>, if you don\'t need them, delete them: %2$s.', 'secupress' ),
			// 202 => __( '<strong>%1$d deactivated plugins</strong>, if you don\'t need them, delete them: %2$s... and %3$d others.', 'secupress' ),
			// 203 => __( '<strong>%1$d deactivated themes</strong>, if you don\'t need them, delete them: %2$s... and %3$d others.', 'secupress' ),
			204 => _n_noop( 'Sorry, this plugin could not be deleted.', 'Sorry, those plugins could not be deleted.', 'secupress' ),
			205 => _n_noop( 'Sorry, this theme could not be deleted.', 'Sorry, those themes could not be deleted.', 'secupress' ),
			// cantfix
			300 => _n_noop( '%d plugin is deactivated.', '%d plugins are deactivated.', 'secupress' ),
			301 => _n_noop( '%d theme is deactivated.', '%d themes are deactivated.', 'secupress' ),
			302 => __( 'Unable to locate WordPress Plugin directory.' ), // WPi18n
			303 => __( 'Unable to locate WordPress theme directory.' ), // WPi18n
			304 => __( 'No plugins nor themes selected.', 'secupress' ),
		);

		if ( isset( $message_id ) ) {
			return isset( $messages[ $message_id ] ) ? $messages[ $message_id ] : __( 'Unknown message', 'secupress' );
		}

		return $messages;
	}


	public function scan() {
		$lists = static::get_inactive_plugins_and_themes();
		$glue  = sprintf( __('%s, %s'), '', '' ); // WP i18n

		// Inactive plugins
		if ( $count = count( $lists['plugins'] ) ) {
			// bad
			$lists['plugins'] = wp_list_pluck( $lists['plugins'], 'Name' ); // do not translate 'Name'
			$lists['plugins'] = self::wrap_in_tag( $lists['plugins'], 'code' );
			$this->slice_and_dice( $lists['plugins'], 8 );
			$this->add_message( 200, array( $count, $count, $lists['plugins'] ) );
		}

		// Inactive themes
		if ( $count = count( $lists['themes'] ) ) {
			// bad
			$lists['themes'] = wp_list_pluck( $lists['themes'], 'Name' ); // do not translate 'Name'
			$lists['themes'] = self::wrap_in_tag( $lists['themes'], 'code' );
			$this->slice_and_dice( $lists['themes'], 8 );
			$this->add_message( 201, array( $count, $count, $lists['themes'] ) );
		}

		// good
		$this->maybe_set_status( 0 );

		return parent::scan();
	}


	public function fix() {
		$lists = static::get_inactive_plugins_and_themes();

		// Inactive plugins
		if ( $count = count( $lists['plugins'] ) ) {
			$this->add_fix_message( 300, array( $count, $count ) );
			$this->add_fix_action( 'delete-inactive-plugins' );
		}

		// Inactive themes
		if ( $count = count( $lists['themes'] ) ) {
			$this->add_fix_message( 301, array( $count, $count ) );
			$this->add_fix_action( 'delete-inactive-themes' );
		}

		// good
		$this->maybe_set_fix_status( 0 );

		return parent::fix();
	}


	public function manual_fix() {
		$wp_filesystem = secupress_get_filesystem();
		$inactive      = static::get_inactive_plugins_and_themes();

		ob_start();

		// PLUGINS
		if ( $this->has_fix_action_part( 'delete-inactive-plugins' ) ) {
			$plugins = $this->manual_fix_plugins( $wp_filesystem, $inactive );
		}

		// THEMES
		if ( $this->has_fix_action_part( 'delete-inactive-themes' ) ) {
			$themes = $this->manual_fix_themes( $wp_filesystem, $inactive );
		}

		ob_end_clean();

		if ( ! empty( $plugins ) && ! empty( $themes ) ) {
			// cantfix: nothing selected in both lists.
			$this->add_fix_message( 304 );
		} elseif ( ! empty( $plugins ) ) {
			// warning: no plugins selected.
			$this->add_fix_message( $delete );
		} elseif ( ! empty( $themes ) ) {
			// warning: no themes selected.
			$this->add_fix_message( $themes );
		}

		// good
		$this->maybe_set_fix_status( 0 );

		return parent::manual_fix();
	}


	protected function manual_fix_plugins( $wp_filesystem, $inactive ) {
		// Get the list of plugins to uninstall.
		$selected_plugins = ! empty( $_POST['secupress-fix-delete-inactive-plugins'] ) && is_array( $_POST['secupress-fix-delete-inactive-plugins'] ) ? array_filter( array_map( 'esc_attr', $_POST['secupress-fix-delete-inactive-plugins'] ) ) : array();
		$selected_plugins = $selected_plugins ? array_fill_keys( $selected_plugins, 1 ) : array();
		$selected_plugins = $selected_plugins ? array_intersect_key( $inactive['plugins'], $selected_plugins ) : array();

		if ( ! $selected_plugins ) {
			if ( $this->has_fix_action_part( 'delete-inactive-themes' ) ) {
				/*
				 * warning: no plugins selected.
				 * No `add_fix_message()`, we need to change the status from warning to cantfix if both lists have no selection.
				 */
				return 100;
			}
			// cantfix: no plugins selected.
			return $this->add_fix_message( 304 );
		}

		//Get the base plugin folder
		$plugins_dir = $wp_filesystem->wp_plugins_dir();

		if ( empty( $plugins_dir ) ) {
			// cantfix: plugins dir not located.
			return $this->add_fix_message( 302 );
		}

		$plugins_dir = trailingslashit( $plugins_dir );

		$plugin_translations = wp_get_installed_translations( 'plugins' );

		$deleted_plugins = array();
		$count_inactive  = count( $inactive['plugins'] );
		$count_selected  = count( $selected_plugins );

		foreach ( $selected_plugins as $plugin_file => $plugin_data ) {
			// Run Uninstall hook
			if ( is_uninstallable_plugin( $plugin_file ) ) {
				uninstall_plugin( $plugin_file );
			}

			/**
			 * Fires immediately before a plugin deletion attempt.
			 *
			 * @since 1.0
			 * @since WP 4.4.0
			 *
			 * @param string $plugin_file Plugin file name.
			 */
			do_action( 'delete_plugin', $plugin_file );

			$this_plugin_dir = trailingslashit( dirname( $plugins_dir . $plugin_file ) );

			// If plugin is in its own directory, recursively delete the directory.
			if ( strpos( $plugin_file, '/' ) && $this_plugin_dir !== $plugins_dir ) { // base check on if plugin includes directory separator AND that its not the root plugin folder.
				$deleted = $wp_filesystem->delete( $this_plugin_dir, true );
			}
			else {
				$deleted = $wp_filesystem->delete( $plugins_dir . $plugin_file );
			}

			/**
			 * Fires immediately after a plugin deletion attempt.
			 *
			 * @since 1.0
			 * @since WP 4.4.0
			 *
			 * @param string $plugin_file Plugin file name.
			 * @param bool   $deleted     Whether the plugin deletion was successful.
			 */
			do_action( 'deleted_plugin', $plugin_file, $deleted );

			if ( $deleted ) {
				$deleted_plugins[ $plugin_file ] = 1;

				// Remove language files, silently.
				$plugin_slug = dirname( $plugin_file );
				if ( '.' !== $plugin_slug && ! empty( $plugin_translations[ $plugin_slug ] ) ) {
					$translations = $plugin_translations[ $plugin_slug ];

					foreach ( $translations as $translation => $data ) {
						$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.po' );
						$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.mo' );
					}
				}
			}
		}

		$count_deleted = count( $deleted_plugins );

		// Everything's deleted, no plugins left.
		if ( $count_deleted === $count_inactive ) {
			// good
			$this->add_fix_message( 1 );
		}
		// All selected plugins deleted.
		elseif ( $count_deleted === $count_selected ) {
			// "partial": some plugins still need to be deleted.
			$this->add_fix_message( 101 );
		}
		// No plugins deleted.
		elseif ( ! $count_deleted ) {
			// bad
			$this->add_fix_message( 204, array( $count_inactive ) );
		}
		// Some plugins could not be deleted.
		else {
			// cantfix
			$not_removed = array_diff_key( $selected_plugins, $deleted_plugins );
			$not_removed = wp_list_pluck( $not_removed, 'Name' );
			$this->add_fix_message( 102, array( count( $not_removed ), $not_removed ) );
		}

		// Force refresh of plugin update information.
		if ( $count_deleted && $current = get_site_transient( 'update_plugins' ) ) {
			$current->response = array_diff_key( $current->response, $deleted_plugins );
			set_site_transient( 'update_plugins', $current );
		}
	}


	protected function manual_fix_themes( $wp_filesystem, $inactive ) {
		// Get the list of themes to uninstall.
		$selected_themes = ! empty( $_POST['secupress-fix-delete-inactive-themes'] ) && is_array( $_POST['secupress-fix-delete-inactive-themes'] ) ? array_filter( array_map( 'esc_attr', $_POST['secupress-fix-delete-inactive-themes'] ) ) : array();
		$selected_themes = $selected_themes ? array_fill_keys( $selected_themes, 1 ) : array();
		$selected_themes = $selected_themes ? array_intersect_key( $inactive['themes'], $selected_themes ) : array();

		if ( ! $selected_themes ) {
			if ( $this->has_fix_action_part( 'delete-inactive-plugins' ) ) {
				/*
				 * warning: no themes selected.
				 * No `add_fix_message()`, we need to change the status from warning to cantfix if both lists have no selection.
				 */
				return 103;
			}
			// cantfix: no themes selected.
			return $this->add_fix_message( 304 );
		}

		//Get the base theme folder
		$themes_dir = $wp_filesystem->wp_themes_dir();

		if ( empty( $themes_dir ) ) {
			// cantfix: themes dir not located.
			return $this->add_fix_message( 303 );
		}

		$themes_dir      = trailingslashit( $themes_dir );
		$deleted_themes  = array();
		$count_inactive  = count( $inactive['themes'] );
		$count_selected  = count( $selected_themes );

		foreach ( $selected_themes as $theme_file => $theme_data ) {
			$this_theme_dir  = trailingslashit( $themes_dir . $theme_file );

			if ( $wp_filesystem->delete( $this_theme_dir, true ) ) {
				$deleted_themes[ $theme_file ] = 1;
			}
		}

		$count_deleted = count( $deleted_themes );

		// Everything's deleted, no themes left.
		if ( $count_deleted === $count_inactive ) {
			// good
			$this->add_fix_message( 2 );
		}
		// All selected themes deleted.
		elseif ( $count_deleted === $count_selected ) {
			// "partial": some themes still need to be deleted.
			$this->add_fix_message( 104 );
		}
		// No themes deleted.
		elseif ( ! $count_deleted ) {
			// bad
			$this->add_fix_message( 205, array( $count_inactive ) );
		}
		// Some themes could not be deleted.
		else {
			// cantfix
			$not_removed = array_diff_key( $selected_themes, $deleted_themes );
			$not_removed = wp_list_pluck( $not_removed, 'Name' );
			$this->add_fix_message( 105, array( count( $not_removed ), $not_removed ) );
		}

		// Force refresh of theme update information
		delete_site_transient( 'update_themes' );
	}


	protected function get_fix_action_template_parts() {
		$forms = array();
		$lists = static::get_inactive_plugins_and_themes();

		if ( $lists['plugins'] ) {
			$form  = '<h4 id="secupress-fix-inactive-plugins">' . __( 'Checked plugins will be deleted:', 'secupress' ) . '</h4>';
			$form .= '<fieldset aria-labelledby="secupress-fix-inactive-plugins" class="secupress-boxed-group">';

				foreach ( $lists['plugins'] as $plugin_file => $plugin_data ) {
					$is_symlinked = secupress_is_plugin_symlinked( $plugin_file );
					$form .= '<input type="checkbox" id="secupress-fix-delete-inactive-plugins-' . sanitize_html_class( $plugin_file ) . '" name="secupress-fix-delete-inactive-plugins[]" value="' . esc_attr( $plugin_file ) . '" ' . ( $is_symlinked ? 'disabled="disabled"' : 'checked="checked"' ) . '/> ';
					$form .= '<label for="secupress-fix-delete-inactive-plugins-' . sanitize_html_class( $plugin_file ) . '">';
						if ( $is_symlinked ) {
							$form .= '<del>' . esc_html( $plugin_data['Name'] ) . '</del> <span class="description">(' . __( 'symlinked', 'secupress' ) . ')</span>';
						} else {
							$form .= esc_html( $plugin_data['Name'] );
						}
					$form .= '</label><br/>';
				}

			$form .= '</fieldset>';
		}
		else {
			$form = __( 'No inactive plugins', 'secupress' );
		}

		$forms['delete-inactive-plugins'] = $form;

		if ( $lists['themes'] ) {
			$form  = '<h4 id="secupress-fix-inactive-themes">' . __( 'Checked themes will be deleted:', 'secupress' ) . '</h4>';
			$form .= '<fieldset aria-labelledby="secupress-fix-inactive-themes" class="secupress-boxed-group">';

				foreach ( $lists['themes'] as $theme_file => $theme_data ) {
					$is_symlinked = secupress_is_theme_symlinked( $theme_file );
					$form .= '<input type="checkbox" id="secupress-fix-delete-inactive-themes-' . sanitize_html_class( $theme_file ) . '" name="secupress-fix-delete-inactive-themes[]" value="' . esc_attr( $theme_file ) . '" ' . ( $is_symlinked ? 'disabled="disabled"' : 'checked="checked"' ) . '/> ';
					$form .= '<label for="secupress-fix-delete-inactive-themes-' . sanitize_html_class( $theme_file ) . '">';
						if ( $is_symlinked ) {
							$form .= '<del>' . esc_html( $theme_data->Name ) . '</del> <span class="description">(' . __( 'symlinked', 'secupress' ) . ')</span>';
						} else {
							$form .= esc_html( $theme_data->Name );
						}
					$form .= '</label><br/>';
				}

			$form .= '</fieldset>';
		}
		else {
			$form = __( 'No inactive themes', 'secupress' );
		}

		$forms['delete-inactive-themes'] = $form;

		return $forms;
	}


	// Return the inactive plugins and themes.

	protected static function get_inactive_plugins_and_themes() {
		$out = array();

		if ( is_multisite() ) {
			// For multisite we need to get active plugins and themes for each blog. Here, we'll fetch both.
			$plugins = get_site_option( 'secupress_active_plugins' );
			$themes  = get_site_option( 'secupress_active_themes' );
			$active  = array( 'plugins' => array(), 'themes' => array(), );

			foreach ( $plugins as $site_id => $site_plugins ) {
				if ( $site_plugins ) {
					$active['plugins'] = array_merge( $active['plugins'], $site_plugins );
				}
			}

			foreach ( $themes as $site_id => $theme ) {
				$active['themes'][ $theme ] = $theme;
			}
		}

		// INACTIVE PLUGINS
		$out['plugins'] = get_plugins();

		if ( is_multisite() ) {
			$network_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$network_active_plugins = is_array( $network_active_plugins ) ? $network_active_plugins : array();
			$active_plugins         = array_merge( $active['plugins'], $network_active_plugins );
		} else {
			$active_plugins = get_option( 'active_plugins', array() );
			$active_plugins = is_array( $active_plugins ) ? $active_plugins : array();
			$active_plugins = array_fill_keys( $active_plugins, 1 );
		}

		$out['plugins'] = array_diff_key( $out['plugins'], $active_plugins );

		// INACTIVE THEMES
		$out['themes'] = wp_get_themes();

		if ( is_multisite() ) {
			$active_themes = $active['themes'];
		} else {
			$active_themes   = array();
			$this_blog_theme = get_stylesheet();

			if ( $this_blog_theme ) {
				$active_themes[ $this_blog_theme ] = $this_blog_theme;
			}
		}

		// We may have child themes, we need to add their parent to the "active themes" list.
		if ( $active_themes ) {
			foreach ( $active_themes as $stylesheet ) {
				if ( isset( $out['themes'][ $stylesheet ] ) && $out['themes'][ $stylesheet ]->parent() ) {
					$parent_stylesheet = $out['themes'][ $stylesheet ]->parent()->get_stylesheet();
					$active_themes[ $parent_stylesheet ] = $parent_stylesheet;
				}
			}
		}

		$out['themes'] = array_diff_key( $out['themes'], $active_themes );

		return $out;
	}
}
