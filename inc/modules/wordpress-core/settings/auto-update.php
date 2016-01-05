<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );


$this->set_current_section( 'wordpress_updates' );
$this->add_section( __( 'WordPress Updates', 'secupress' ) );


$plugin = $this->get_current_plugin();

$this->add_field( array(
	'title'        => __( 'Minor Updates', 'secupress' ),
	'description'  => __( 'Let WordPress updates itself when a minor version is available.<br/>4.3.<strong>1</strong> is a minor version.', 'secupress' ),
	'label_for'    => $this->get_field_name( 'minor' ),
	'type'         => 'checkbox',
	'value'        => (int) secupress_is_submodule_active( 'wordpress-core', 'minor-updates' ),
	'label'        => __( 'Try to force WordPress to allow auto updates for <strong>minor</strong> versions.', 'secupress' ),
	'helpers'      => array(
		array(
			'type'        => 'warning',
			'description' => __( 'Not allowing this may result using a vulnerable version of WordPress. Usually, minor versions are safe to update and contains security fixes.', 'secupress' ),
		),
	),
) );


$this->add_field( array(
	'title'        => __( 'Major Updates', 'secupress' ),
	'description'  => __( 'Let WordPress updates itself when a major version is available.<br/>4.<strong>4</strong> is a major version.', 'secupress' ),
	'label_for'    => $this->get_field_name( 'major' ),
	'type'         => 'checkbox',
	'value'        => (int) secupress_is_submodule_active( 'wordpress-core', 'major-updates' ),
	'label'        => __( 'Try to force WordPress to allow auto updates for <strong>major</strong> versions.', 'secupress' ),
	'helpers'      => array(
		array(
			'type'        => 'help',
			'description' => __( 'This is not mandatory but recommended since a major version also contains security fixes sometimes.', 'secupress' ),
		),
	),
) );
