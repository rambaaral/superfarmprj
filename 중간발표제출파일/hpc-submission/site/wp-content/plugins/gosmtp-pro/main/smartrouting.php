<?php

namespace GOSMTP;

/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

class SmartRouting{

	static function init(){
		global $gosmtp, $phpmailer;

		$header_name = ['to', 'subject', 'from', 'fromname', 'reply-to', 'cc', 'bcc'];
		
		// These datas are already being set in the data, so we don't need to pass them seperately here
		$header_value = [
			implode(', ', array_column($phpmailer->getToAddresses(), 0)),
			$phpmailer->Subject,
			$gosmtp->original_from,
			$phpmailer->FromName,
			implode(', ', array_column($phpmailer->getReplyToAddresses(), 0)),
			implode(', ', array_column($phpmailer->getCcAddresses(), 0)),
			implode(', ', array_column($phpmailer->getBccAddresses(), 0)),
		];

		// For Custom headers
		foreach($phpmailer->getCustomHeaders() as $header){
			$header_value[] = $header[1];
			$header_name[]	= $header[0];
		}
		
		foreach($header_value as $i => $val){
			if($val === ''){
				unset($header_value[$i]);
				unset($header_name[$i]);
			}
		}

		$header_name = array_values($header_name);
		$header_value = array_values($header_value);

		$data = [
			'subject' => $phpmailer->Subject,
			'message' => $phpmailer->Body,
			'to' => array_column($phpmailer->getToAddresses(), 0),
			'from' => $gosmtp->original_from,
			'fromname' => $phpmailer->FromName,
			'cc' => array_column($phpmailer->getCcAddresses(), 0),
			'bcc' => array_column($phpmailer->getBccAddresses(), 0),
			'reply-to' => array_column($phpmailer->getReplyToAddresses(), 0),
			'header_name' => $header_name,
			'header_value' => $header_value
		];

		$routing_key = self::get_connection($data);
		
		// If empty or the connection is same as primary we skip updating anything.
		if(empty($routing_key) || $routing_key === $gosmtp->mailer->conn_id){
			return;
		}
		
		$mail_type = '';
		if(
			!empty($gosmtp->options['mailer']) && 
			!empty($gosmtp->options['mailer'][$routing_key]) && 
			!empty($gosmtp->options['mailer'][$routing_key]['mail_type'])
		){
			$mail_type = sanitize_key($gosmtp->options['mailer'][$routing_key]['mail_type']);
		}
		
		// If mailer class can not be found we exit, and fallback to primary
		if(
			empty($mail_type) || 
			empty($gosmtp->mailer_list) || 
			empty($gosmtp->mailer_list[$mail_type]) || 
			empty($gosmtp->mailer_list[$mail_type]['class'])
		){
			return;
		}
		
		$class = $gosmtp->mailer_list[$mail_type]['class'];
		
		if(class_exists($class)){
			$gosmtp->_mailer = $mail_type;
			$phpmailer->XMailer = 'GOSMTP/Mailer/' . $gosmtp->_mailer . ' ' . GOSMTP_VERSION; // updating the xmailer with new mailer
			$gosmtp->mailer = new $class();
			$gosmtp->mailer->conn_id = $routing_key;
			$gosmtp->mailer->set_from();
		}
	}
	
	static function get_connection($data){
		global $gosmtp;

		foreach($gosmtp->options['smart_routing']['rules'] as $rule){
			if(empty($rule['connection_id']) || empty($rule['groups'])){
				continue;
			}

			foreach($rule['groups'] as $group){
				$group_matched = true;
				foreach($group as $condition){
					$is_matched = self::match_condition($condition, $data);
					if(!$is_matched){
						$group_matched = false;
						break;
					}
				}

				if($group_matched){
					return $rule['connection_id'];
				}
			}
		}

		return false;
	}

	static function match_condition($condition, $data){

		$type = $condition['type'];
		$operator = $condition['operator'];
		$value = !empty($condition['value']) ? $condition['value'] : '';

		if(empty($value)){
			return false;
		}

		$haystack = '';
		switch($type){
			case 'subject':
				$haystack = $data['subject'];
				break;

			case 'message':
				$haystack = wp_strip_all_tags($data['message']);
				break;

			case 'to':
				$haystack = is_array($data['to']) ? implode(',', $data['to']) : $data['to'];
				break;

			case 'from':
				$haystack = $data['from'];
				break;

			case 'fromname':
				$haystack = $data['fromname'];
				break;

			case 'cc':
				$haystack = is_array($data['cc']) ? implode(',', $data['cc']) : $data['cc'];
				break;

			case 'bcc':
				$haystack = is_array($data['bcc']) ? implode(',', $data['bcc']) : $data['bcc'];
				break;

			case 'reply-to':
				$haystack = is_array($data['reply-to']) ? implode(',', $data['reply-to']) : $data['reply-to'];
				break;

			case 'header_name':
				$haystack = is_array($data['header_name']) ? implode(',', $data['header_name']) : $data['header_name'];
				break;

			case 'header_value':
				$haystack = is_array($data['header_value']) ? implode(',', $data['header_value']) : $data['header_value'];
				break;

			default:
			return false;
		}

		if($operator === 'contains'){
			return preg_match('/\b'. preg_quote($value, '/') . '\b/i', $haystack);
		}

		if($operator === 'is'){
		    
			if(in_array($type, ['header_name', 'header_value'])){

				$haystack_values = array_map('trim', explode(",", $haystack));
				foreach($haystack_values as $haystacks){
					if(strtolower($haystacks) === strtolower(trim($value))){
						return true;
					}
				}
				return false;
			}
			return trim(strtolower($haystack)) === trim(strtolower($value));
		}

		if($operator === 'does_not_contain'){
			return stripos($haystack ,$value) === false;
		}

		if($operator === 'is_not'){
		    
			if(in_array($type, ['header_name', 'header_value'])){

				$haystack_values = array_map('trim', explode(',', $haystack));
				foreach($haystack_values as $haystacks){
					if(trim(strtolower($haystacks)) === trim(strtolower($value))){
						return false;
					}
				}
				return true;
			}
			return trim(strtolower($haystack)) !== trim(strtolower($value));
		}

		if($operator === 'starts_with'){
			return strpos(strtolower(trim($haystack)), strtolower(trim($value))) === 0;
		}

		if($operator === 'ends_with'){
			return substr(strtolower(trim($haystack)), -strlen(trim($value))) === strtolower(trim($value));
		}

		return false;
	}
}
