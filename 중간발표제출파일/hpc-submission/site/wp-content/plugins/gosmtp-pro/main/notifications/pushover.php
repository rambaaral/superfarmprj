<?php

namespace GOSMTP\Notifications;

use GOSMTP\Notifications\Manager;

class Pushover extends Manager{

	var $title = 'Pushover';
	var $service = 'pushover';

	public function send($message){

		$pushover_user_key = $this->get_option('pushover_user_key', $this->service);
		$pushover_api_token = $this->get_option('pushover_api_token', $this->service);

		if(empty($pushover_user_key) || empty($pushover_api_token)){
			return false;
		};

		$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

		$body = [
			'user' => $pushover_user_key,
			'token' => $pushover_api_token,
			'message' => $message
		];

		$args = [
			'body' => $body
		];
		$pushover_api = 'https://api.pushover.net/1/messages.json';

		return wp_remote_post($pushover_api, $args);
	}

	public function load_services_field(){

		$fields = [
			'pushover_user_key' => [
				'title' => __('Pushover User key', 'gosmtp-pro'),
				'type' => 'password',
				'desc' => __('Enter your Pushover User Key', 'gosmtp-pro')
			],

			'pushover_api_token' => [
				'title' => __('Pushover Api Token', 'gosmtp-pro'),
				'type'  => 'password',
				'desc'  => __('Enter your Pushover application token', 'gosmtp-pro')
			]
		];

		return $fields;
	}

}
