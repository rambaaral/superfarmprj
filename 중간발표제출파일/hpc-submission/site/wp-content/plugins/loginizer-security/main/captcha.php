<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

global $loginizer;

// For Contact Form 7
if(!empty($loginizer['captcha_contactform7'])){
	add_filter('wpcf7_form_elements', 'loginizer_pro_cf7_enable_shortcodes');
	add_action('wpcf7_before_send_mail', 'loginizer_pro_cf7_check_if_shortcode_exists', 10, 3);
}

// For WPForms
if(!empty($loginizer['captcha_wpforms'])){
	add_action('wpforms_display_submit_before', 'loginizer_pro_wpforms_add_captcha', 10, 1);
	add_action('wpforms_process', 'loginizer_pro_wpforms_validation', 10, 3);
}

// For Contact Form 7
add_shortcode('loginizer_pro_recaptcha', 'loginizer_pro_recaptcha_shortcode');

function loginizer_pro_recaptcha_shortcode(){

	ob_start();
	loginizer_cap_form();

	return ob_get_clean();
}

function loginizer_pro_cf7_enable_shortcodes($content){
	return do_shortcode($content);
}

// Captcha Verify
function loginizer_pro_cf7_check_if_shortcode_exists($contact_form, &$abort, $submission){

	if(!is_object($contact_form) || !method_exists($contact_form, 'prop') || !is_object($submission) || !method_exists($submission, 'set_response')){
		return;
	}

	$form_html = $contact_form->prop('form');

	if(strpos($form_html, '[loginizer_pro_recaptcha]') === false || loginizer_cap_verify()){
		return;
	}

	// Abort the form submission
	$abort = true;

	// Set an error response message to show in Contact Form 7
	$submission->set_response(__('The CAPTCHA verification failed. Please try again.', 'loginizer'));
}

// For WPForms

function loginizer_pro_wpforms_add_captcha($form_data){

	static $rendered = [];

	if(isset($rendered[$form_data['id']])){
		return;
	}

	echo '<div class="wpforms-field wpforms-field-recaptcha">
			<div class="wpforms-recaptcha-container">';

	loginizer_cap_form();

	echo '</div>
	</div>';

	$rendered[$form_data['id']] = true;
}

// Captcha Verify
function loginizer_pro_wpforms_validation($fields, $entry, $form_data){
	if(function_exists('wpforms') && is_object(wpforms()->obj('process')) && isset(wpforms()->obj('process')->errors)){
		if(!loginizer_cap_verify()){
			wpforms()->process->errors[$form_data['id']]['recaptcha'] = __('The CAPTCHA verification failed. Please try again.', 'loginizer');
		}
	}
}