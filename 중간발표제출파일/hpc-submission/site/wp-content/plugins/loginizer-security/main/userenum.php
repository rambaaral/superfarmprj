<?php

namespace LoginizerPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class UserEnum {

	// Block unauthenticated requests to the WP REST API users endpoint
	public static function block_users_request($response, $handler, $request){
		global $loginizer;

		if(empty($loginizer['user_enum_disable_rest_users'])){
			return $response;
		}

		if(is_user_logged_in()){
			return $response;
		}

		$route = $request->get_route();

		if(strpos($route, '/wp/v2/users') !== false){
			return new \WP_Error('loginizer_user_enum', __('You are not allowed to access this resource.', 'loginizer'), ['status' => 403]);
		}

		return $response;
	}

	// Block ?author=N and ?author_name= enumeration via 404 and prevent canonical redirect (unauthenticated only)
	public static function disable_author_enum($query){
		global $loginizer;

		if(empty($loginizer['user_enum_disable_author_enum'])){
			return;
		}

		if(is_admin() || is_user_logged_in()){
			return;
		}

		$author = $query->get('author');
		$author_name = $query->get('author_name');

		if((!empty($author) && is_numeric($author)) || !empty($author_name)){
			// Prevent redirect_canonical from leaking the username via /author/username/
			remove_action('template_redirect', 'redirect_canonical', 10);

			// Clear the author query vars so the author archive never resolves
			$query->set('author', '');
			$query->set('author_name', '');

			// Force a 404 response
			$query->set_404();
			$query->is_404 = true;
			$query->is_author = false;
		}
	}

	// Mask login form errors regardless of brute force status
	public static function hide_login_errors($error){
		global $loginizer;

		if(empty($loginizer['user_enum_hide_login_errors'])){
			return $error;
		}

		if(!is_wp_error($error)){
			return $error;
		}

		$codes = $error->get_error_codes();

		// Already masked
		if(in_array('invalid_userpass', $codes, true)){
			return $error;
		}

		if(!self::mask_codes($error, $codes)){
			return $error;
		}

		$error->add('invalid_userpass', '<b>ERROR:</b> ' . __('Invalid username or password.', 'loginizer'));

		return $error;
	}

	// Removes the error codes which tell the visitor whether the username exists
	// Returns true only if one of them was actually there, so that the callers do
	// not replace errors like empty_username, or a logged out / registered notice,
	// with an invalid credentials error
	private static function mask_codes($error, $codes){

		$mask = ['invalid_username', 'incorrect_password', 'invalid_email'];
		$masked = false;

		foreach($mask as $c){
			if(in_array($c, $codes, true)){
				$masked = true;
			}

			$error->remove($c);
		}

		return $masked;
	}

	// Mask errors during authenticate when brute force is off
	public static function mask_authenticate($user, $username, $password){
		global $loginizer;

		if(empty($loginizer['user_enum_hide_login_errors'])){
			return $user;
		}

		if(!is_wp_error($user)){
			return $user;
		}

		$codes = $user->get_error_codes();

		// Already masked
		if(in_array('invalid_userpass', $codes, true)){
			return $user;
		}

		if(!self::mask_codes($user, $codes)){
			return $user;
		}

		$user->add('invalid_userpass', '<b>ERROR:</b> ' . __('Invalid username or password.', 'loginizer'));

		return $user;
	}

	// Mask lost password form errors
	// Hooked to the lost_password action as WordPress adds the invalidcombo error
	// after the lostpassword_errors filter. WP_Error is an object, so what we
	// remove / add here is what wp-login.php is about to render
	public static function hide_lostpass_errors($error = null){
		global $loginizer;

		if(empty($loginizer['user_enum_hide_lostpass_errors'])){
			return $error;
		}

		// The $errors param was added to this action in WordPress 5.1
		if(!is_wp_error($error)){
			return $error;
		}

		// These codes tell the visitor whether the account exists
		$mask = ['invalidcombo', 'invalid_email', 'invalid_combo', 'email_not_registered'];
		$codes = $error->get_error_codes();
		$masked = 0;

		foreach($mask as $c){
			if(in_array($c, $codes, true)){
				$masked = 1;
			}

			$error->remove($c);
		}

		// Nothing that leaks, so do not touch the form
		if(empty($masked)){
			return $error;
		}

		$generic = __('If that account exists, an email has been sent with instructions to reset the password.', 'loginizer');

		// 'message' so WordPress shows it as a notice and not as a red error
		$error->add('loginizer_user_enum_lostpass', $generic, 'message');

		return $error;
	}

	// Strip author info from oEmbed response data
	public static function disable_oembed_author($data, $post, $width, $height){
		global $loginizer;

		if(empty($loginizer['user_enum_disable_oembed_author'])){
			return $data;
		}

		if(is_array($data)){
			unset($data['author_name'], $data['author_url']);
		}

		return $data;
	}

	// Strip author name from oEmbed REST XML/JSON responses
	public static function oembed_strip_author($result, $server, $request, $response){
		global $loginizer;

		if(empty($loginizer['user_enum_disable_oembed_author'])){
			return $result;
		}

		$route = $request->get_route();

		if(strpos($route, '/oembed/1.0/embed') === false){
			return $result;
		}

		if(!($response instanceof \WP_REST_Response)){
			return $result;
		}

		$data = $response->get_data();

		if(is_array($data)){
			unset($data['author_name'], $data['author_url']);
			$response->set_data($data);
		}

		return $result;
	}

}