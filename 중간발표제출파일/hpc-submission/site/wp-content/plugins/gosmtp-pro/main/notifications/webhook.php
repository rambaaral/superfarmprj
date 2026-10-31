<?php

namespace GOSMTP\Notifications;

use GOSMTP\Notifications\Manager;

class Webhook extends Manager{

	var $title = 'Webhook';
	var $service = 'webhook';

	public function send($message){

		$webhook_url = $this->get_option('webhook_url', $this->service);

		if(empty($webhook_url)){
			return false;
		};

		$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

		$body = [
			'text' => $message
		];

		$headers = [
			'content-type' => 'application/json'
		];

		return wp_remote_post($webhook_url,[
			'body' => json_encode($body),
			'headers' => $headers
		]);
	}

	public function load_services_field(){

		$fields = [
			'webhook_url' => [
				'title' => __('Webhook URL', 'gosmtp-pro'),
				'type' => 'password',
				'desc' => __('Enter your Webhook URL', 'gosmtp-pro')
			]
		];

		return $fields;
	}
}