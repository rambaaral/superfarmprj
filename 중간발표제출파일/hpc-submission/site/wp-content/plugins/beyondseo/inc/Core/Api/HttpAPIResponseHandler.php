<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use RankingCoach\Inc\Exceptions\UnsupportedContentTypeException;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Exceptions\ResponseValidationException;
use function apply_filters;
use function beyondseo_rceh;

/**
 * Class HttpAPIResponseHandler
 */
class HttpAPIResponseHandler {

	/** @var array $response The response. */
	public array $response;

	/**
	 * Validate the response.
	 *
	 * @param array|null $response The response.
	 *
	 * @throws Exception
	 */
	public function validate(?array $response = null): static
	{
		$response = $response ?? $this->response;
		$statusCode = wp_remote_retrieve_response_code($response);
		if ($statusCode >= 400) {
			$content = wp_remote_retrieve_body($response);
			$data = json_decode($content, true);

			$error = 'Unknown error';
			$message = '';
			$fieldMessages = [];

			if (json_last_error() !== JSON_ERROR_NONE) {
				$error = 'Invalid JSON response';
				$decodedContent = htmlspecialchars_decode($content);
				$doctypePosition = strrpos($decodedContent, '<!DOCTYPE');
				if ($doctypePosition !== false) {
					$decodedContent = substr($decodedContent, 0, $doctypePosition);
				}
				$message = trim($decodedContent);
			} else {
				if (is_array($data)) {
					$error = isset($data['error']) && is_string($data['error']) ? trim($data['error']) : 'Unknown error';
					$message = isset($data['message']) && is_string($data['message']) ? trim($data['message']) : '';
					$fieldMessages = $this->extractFieldMessages($data);
				}
			}

			$parts = array_filter(array_map(static fn($part) => is_string($part) ? trim($part) : '', [ $error, $message ]), static fn($part) => $part !== '');
			$errorText = $fieldMessages
				? implode(' ', $fieldMessages)
				: ($parts ? implode(' - ', array_unique($parts)) : 'Unknown error');
			$exception = new HttpApiException($errorText, $statusCode);

			$errorDetails = [
				'status_code' => $statusCode,
				'error' => $error,
				'message' => $message,
				'field_messages' => $fieldMessages,
				'content' => $content,
			];

			$shouldThrow = apply_filters('rankingcoach_http_api_response_throw_exception', false, $errorDetails, $response);

			if ($statusCode === 401 || $statusCode === 504 || $shouldThrow) {
				throw $exception;
			}

			beyondseo_rceh()->error($exception, $content);
		}
		return $this;
	}

	/**
	 * Extract field-level messages from a RankingCoach API error body.
	 *
	 * Validation failures use a generic `error` and put field-specific reasons in
	 * `validationErrors` and/or `exceptionDetails`.
	 *
	 * @param array $data The decoded JSON error body.
	 *
	 * @return string[] Unique, non-empty messages in body order.
	 */
	private function extractFieldMessages(array $data): array
	{
		$messages = [];
		foreach ($data['validationErrors']['elements'] ?? [] as $validationError) {
			foreach ($validationError['elements'] ?? [] as $validationResult) {
				$messages[] = $validationResult['errorMessage'] ?? '';
			}
		}
		foreach ($data['exceptionDetails']['elements'] ?? [] as $exceptionDetail) {
			$messages[] = $exceptionDetail['message'] ?? '';
		}
		$messages = array_map(static fn($message) => is_string($message) ? trim($message) : '', $messages);

		return array_values(array_unique(array_filter($messages, static fn($message) => $message !== '')));
	}

	/**
	 * Parse the response.
	 *
	 * @param array|null $response The response.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function parse(?array $response = null): array
	{
		$response = $response ?? $this->response;
		$content = wp_remote_retrieve_body($response);
		$contentType = wp_remote_retrieve_header($response, 'content-type');

		if (str_contains($contentType, 'application/json')) {
			$data = json_decode($content);
			if (json_last_error() !== JSON_ERROR_NONE) {
				beyondseo_rceh()->error( new ResponseValidationException('Error decoding JSON response: ' . json_last_error_msg() ), $content );
			}
			return ['content' => $data];
		} elseif (str_contains($contentType, 'text/plain') || str_contains($contentType, 'text/html')) {
			return ['content' => $content];
		} else {
			beyondseo_rceh()->error( new UnsupportedContentTypeException("Unsupported content type: $contentType"), $content );
		}
	}
}
