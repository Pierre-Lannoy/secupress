<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );


$this->set_current_section( 'wordpress_updates' );
$this->add_section( __( 'WordPress Updates', 'secupress' ) );


$plugin = $this->get_current_plugin();

$field_name = $this->get_field_name( 'minor' );

$this->add_field(
	__( 'Minor Updates', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( 'Let WordPress updates itself when a minor version is available.<br>4.3.<b>1</b> is a minor version.', 'secupress' ),
	),
	array(
		array(
			'type'         => 'checkbox',
			'name'         => $field_name,
			'value'        => (int) secupress_is_submodule_active( 'wordpress-core', 'minor-updates' ),
			'label'        => __( 'Try to force WordPress to allow auto updates for <strong>minor</strong> versions.', 'secupress' ),
			'label_for'    => $field_name,
			'label_screen' => __( 'Allow minor versions updates', 'secupress' ),
		),
		array(
			'type'         => 'helper_warning',
			'name'         => $field_name . '-help',
			'description'  => __( 'Not allowing this may result using a vulnerable version of WordPress. Usually, minor versions are safe to update and contains security fixes.', 'secupress' ),
		),
	)
);


$field_name = $this->get_field_name( 'major' );

$this->add_field(
	__( 'Major Updates', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( 'Let WordPress updates itself when a major version is available.<br>4.<b>4</b> is a major version.', 'secupress' ),
	),
	array(
		array(
			'type'         => 'checkbox',
			'name'         => $field_name,
			'value'        => (int) secupress_is_submodule_active( 'wordpress-core', 'major-updates' ),
			'label'        => __( 'Try to force WordPress to allow auto updates for <strong>major</strong> versions.', 'secupress' ),
			'label_for'    => $field_name,
			'label_screen' => __( 'Allow major versions updates', 'secupress' ),
		),
		array(
			'type'         => 'helper_help',
			'name'         => $field_name . '-help',
			'description'  => __( 'This is not mandatory but recommended since a major version also contains security fixes sometimes.', 'secupress' ),
		),
	)
);
