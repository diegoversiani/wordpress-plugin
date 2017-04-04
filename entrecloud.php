<?php
/*
Plugin Name: Entrecloud
Plugin URI: https://entrecloud.com
Description: The Entrecloud service plugin
Version: 1.0
Author: Janos Pasztor
Author URI: https://entrecloud.com
License: proprietary
*/

add_action( 'init', 'entrecloud_authenticate' );
add_action( 'init', 'entrecloud_disable_password', 10 );
add_filter('wp_get_attachment_url', 'entrecloud_relative_attachment_url');
add_filter('wp_get_attachment_link', 'entrecloud_relative_attachment_url');
add_action('user_profile_update_errors', 'entrecloud_no_email_change', 10, 3 );

function entrecloud_no_email_change($errors, $update,$user ) {
	$oldUser = get_user_by('id', $user->ID);

	if( $user->user_email != $oldUser->user_email && preg_match('/@entre\.cloud\Z/', $oldUser->user_email)) {
		$errors->add('demo_error',__('The e-mail address cannot be changed for Entrecloud-managed users!'));
	}
}

function entrecloud_authenticate() {
	try {
		if (isset($_GET['entrecloud_token'])) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,"https://app.entrecloud.com/api/authenticate/wordpress");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'?'https://':'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
				'token' => $_GET['entrecloud_token']
			]));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$server_output = curl_exec ($ch);
			$response = @json_decode($server_output, true);
			if ($response && $response['success'] == true) {
				$email = $response['email'];
				$user = get_user_by_email($email);
				if (!$user) {
					$password = wp_generate_password( $length=12, $include_standard_special_chars=false );
					$userId = wp_create_user($email, $password, $email);
				} else {
					$userId = $user->ID;
				}
				wp_update_user(['ID' => $userId, 'role' => 'administrator']);
				wp_set_current_user($userId);
				if (wp_validate_auth_cookie()==FALSE) {
					wp_set_auth_cookie( $userId, false, is_ssl() );
				}
				wp_redirect( admin_url( 'profile.php' ) );
				exit;
			}
		}
	} catch (Exception $e) {
	}
}

function entrecloud_disable_password() {
	if (wp_get_current_user() && preg_match('/@entre\.cloud\Z/', wp_get_current_user()->user_email)) {
		add_filter( 'allow_password_reset', '__return_false' );
		add_filter( 'show_password_fields', '__return_false' );
	}
}

function entrecloud_relative_attachment_url($input) {
	preg_match('/(https?:\/\/[^\/|"]+)/', $input, $matches);
	if (isset($matches[0]) && strpos($matches[0], site_url()) === false) {
		return $input;
	} else {
		return str_replace(end($matches), '', $input);
	}
}
