<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

global $is_apache, $is_nginx, $is_iis7;

$this->set_current_section( 'move-login' );
$this->add_section( __( 'Move Login', 'secupress' ) );


$main_field_name  = $this->get_field_name( 'activated' );
$is_plugin_active = secupress_is_submodule_active( 'users-login', 'move-login' );

$this->add_field( array(
	'title'        => __( 'Move the login page', 'secupress' ),
	'description'  => __( 'This will not totally hide the login form from humans, the main goal is to avoid bots to hit this URL.', 'secupress' ),
	'label_for'    => $main_field_name,
	'type'         => 'checkbox',
	'value'        => (int) $is_plugin_active,
	'label'        => __( 'Yes, move the login page to avoid bad login attempts', 'secupress' ),
	'helpers'      => array(
		array(
			'type'        => 'warning',
			'description' => secupress_get_deactivate_plugin_string( 'sf-move-login/sf-move-login.php' ),
		),
	),
) );


$labels = secupress_move_login_slug_labels();

foreach( $labels as $slug => $label ) {
	$slug = esc_attr( $slug );

	$this->add_field( array(
		'title'        => esc_html( $label ),
		'depends'      => $main_field_name,
		'label_for'    => $this->get_field_name( 'slug-' . $slug ),
		'type'         => 'text',
		'default'      => $slug,
		'label_before' => '<span class="screen-reader-text">' . __( 'URL' ) . '</span>',
		'label_after'  => '<em>(' . sprintf( __( 'Default: %s', 'secupress' ), $slug ) . ')</em>',
	) );
}


$this->add_field( array(
	'title'        => sprintf( __( 'Access to %s', 'secupress' ), '<code>wp-login.php</code>' ),
	'description'  => __( 'When a not connected user attempts to access the old login page.', 'secupress' ),
	'depends'      => $main_field_name,
	'name'         => $this->get_field_name( 'wp-login-access' ),
	'type'         => 'radios',
	'options'      => secupress_move_login_wplogin_access_labels(),
	'default'      => 'error',
	'label_screen' => sprintf( __( 'Choose how to deny access to %s', 'secupress' ), '<code>wp-login.php</code>' ),
) );


$this->add_field( array(
	'title'        => __( 'Access to the administration area', 'secupress' ),
	'description'  => __( 'When a not connected user attempts to access the old login page.', 'secupress' ),
	'depends'      => $main_field_name,
	'name'         => $this->get_field_name( 'admin-access' ),
	'type'         => 'radios',
	'options'      => secupress_move_login_admin_access_labels(),
	'default'      => 'redir-login',
	'label_screen' => __( 'Choose how to deny access to the administration area', 'secupress' ),
) );


// If nginx or if `.htaccess`/`web.config` is not writable, display a textarea containing the rewrite rules.

if ( $is_plugin_active && function_exists( 'secupress_move_login_file_is_writable' ) ) {
	$message = false;

	// Nginx
	if ( $is_nginx ) {
		/* translators: 1 is a file name, 2 is a tag name */
		$message = sprintf( __( 'You need to add the following code into your %1$s file, inside the %2$s block:', 'secupress' ), '<code>nginx.conf</code>', '<code>server</code>' );
		$rules   = secupress_move_login_get_nginx_rules( secupress_move_login_get_rules() );
		$rules   = "# BEGIN SecuPress move_login\n$rules\n# END SecuPress";
	}
	// Apache
	elseif ( $is_apache && ! secupress_move_login_file_is_writable( '.htaccess' ) ) {
		/* translators: %s is a file name */
		$message = sprintf( __( 'Your %s file is not writable, you need to add the following code inside:', 'secupress' ), '<code>.htaccess</code>' );
		$rules   = secupress_move_login_get_apache_rules( secupress_move_login_get_rules() );
		$rules   = "# BEGIN SecuPress move_login\n$rules\n# END SecuPress";
	}
	// IIS7
	elseif ( $is_iis7 && ! secupress_move_login_file_is_writable( 'web.config' ) ) {
		/* translators: %s is a file name */
		$message = sprintf( __( 'Your %s file is not writable, you need to add the following code inside:', 'secupress' ), '<code>web.config</code>' );
		$rules   = secupress_move_login_get_iis7_rules( secupress_move_login_get_rules() );
	}

	if ( $message ) {
		$this->add_field( array(
			'title'        => __( 'Rules', 'secupress' ),
			'description'  => $message,
			'depends'      => $main_field_name,
			'label_for'    => $this->get_field_name( 'rules' ),
			'type'         => 'textarea',
			'value'        => $rules,
			'attributes'   => array(
				'readonly' => 'readonly',
				'rows'     => count( explode( "\n", $rules ) ) + 1,
			),
		) );
	}
}

unset( $options, $field_name, $main_field_name, $is_plugin_active, $labels, $message, $rules, $home_path );
