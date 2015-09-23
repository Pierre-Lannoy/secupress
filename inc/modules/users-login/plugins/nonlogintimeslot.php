<?php
/*
Module Name: Non-Login Time Slot
Description: Define a time slot when noone can log in.
Main Module: users_login
Author: SecuPress
Version: 1.0
*/
defined( 'SECUPRESS_VERSION' ) or die( 'Cheatin&#8217; uh?' );

add_action( 'plugins_loaded', 'secupress_donttrytologin' );
function secupress_donttrytologin() {
	$timings = secupress_get_module_option( 'bad_logins_nonlogintimeslot', 10, 'users_login' );
	// from
	$setting_from = strtotime( date( sprintf( 'Y-m-d %s:%s:00', $timings['from_hour'], $timings['from_minute'] ) ) );
	$UTC = new DateTimeZone( 'UTC' );
	$newTZ = new DateTimeZone( ini_get( 'date.timezone' ) );
	$date = new DateTime( '', $UTC );
	$date->setTimezone( $newTZ );
	$server_hour_from = strtotime( $date->format( 'Y-m-d H:i:s' ) );

	// to
	$setting_to = strtotime( date( sprintf( 'Y-m-d %s:%s:00', $timings['to_hour'], $timings['to_minute'] ) ) );
	$UTC = new DateTimeZone( 'UTC' );
	$newTZ = new DateTimeZone( ini_get( 'date.timezone' ) );
	$date = new DateTime( '', $UTC );
	$date->setTimezone( $newTZ );
	$server_hour_to = strtotime( $date->format( 'Y-m-d H:i:s' ) );
	
	if ( $server_hour_from > $setting_from || $server_hour_to < $setting_to ) {
		add_action( 'login_form_login', '__secupress_cant_login_now' );
		remove_all_filters( 'authenticate' );
		add_filter( 'authenticate', '__return_false', PHP_INT_MAX );
	}
}

function __secupress_cant_login_now() {
	login_header( __( 'You can\'t log in now.', 'secupress' ), '<p class="message">' . __( 'For security reasons, the login page is disabled for the moment, please come back later.', 'secupress' ) . '</p>' );
	login_footer();
	die();
}


function secupress_nonlogintimeslot( $raw_user ) {
	if ( ! empty( $_POST ) && is_a( $raw_user, 'WP_User' ) ) {
		$IP = secupress_get_ip();
		$bad_logins_number_attempts = secupress_get_module_option( 'bad_logins_number_attempts', 10, 'users_login' );
		$attempts = (int) get_user_meta( $uid, '_secupress_limitloginattempts', true );
		++$attempts;
		if ( $attempts < $bad_logins_number_attempts ) {
			update_user_meta( $uid, '_secupress_limitloginattempts', $attempts );
			$attempts_left = $bad_logins_number_attempts - $attempts;
			if ( $attempts_left <= 3 ) {
				add_filter( 'login_message', function( $message ) use( $attempts_left ) { return _secupress_limitloginattempts_error_message( $message, $attempts_left ); } );
			}
		} else {
			delete_user_meta( $uid, '_secupress_limitloginattempts' );
			secupress_ban_ip();
			die();
		}
	}
	if ( isset( $raw_user->ID ) ) {
		delete_user_meta( $raw_user->ID, '_secupress_limitloginattempts' );
	}
	return $raw_user;
}