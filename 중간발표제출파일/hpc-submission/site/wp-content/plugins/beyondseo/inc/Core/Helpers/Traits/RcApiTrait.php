<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use Throwable;
use WP_REST_Response;

/**
 * Trait RcApiTrait
 */
trait RcApiTrait
{
    use RcLoggerTrait;

	/**
	 * Retrieves the API base for routes
	 * @return string
	 */
	protected function getLegacyApiBase(): string {
		return RANKINGCOACH_REST_API_BASE;
	}

    /**
     * Retrieves the App API sub-base for routes
     * @return string
     */
    protected function getAppApiBase(): string {
        return RANKINGCOACH_REST_APP_BASE;
    }

	/**
	 * Main method to generate a standardized API response.
	 *
	 * @param mixed $responseData The detailed data to include in the response.
	 * @param string $status The status of the response ('success' or 'error').
	 * @param Throwable|null $error Optional error information.
	 * @param string|null $message Optional message for the response.
	 * @param int $code HTTP status code for the response.
	 *
	 * @return WP_REST_Response The generated API response.
	 */
	protected function generateApiResponse(
		mixed $responseData,
		string $status = 'success',
		?Throwable $error = null,
		?string $message = null,
		int $code = 200
	): WP_REST_Response {
		$isSuccess = ($status === 'success');

		$response = [
			'success' => $isSuccess,
			'message' => $message ?? ($isSuccess ? 'Request completed successfully.' : 'An error occurred.'),
			'response' => null,
			'error' => [],
			'meta' => $this->getMeta(),
			'request' => $this->getRequestMetadata($code),
		];

		if ($isSuccess) {
			$response['response'] = $responseData;
		} else {
			$response['error'] = $error instanceof Throwable ?
				[
					'code'    => $error->getCode(),
					'message' => $error->getMessage(),
					'file'    => $error->getFile(),
					'line'    => $error->getLine(),
					'trace'   => $error->getTrace(),
					'additional' => json_decode($error->getMessage(), true),
				] : [
					'code' => $error['code'] ?? 'UNKNOWN_ERROR',
					'message' => $error['details'] ?? 'An unknown error occurred.',
					'file' => $error['file'] ?? '',
					'line' => $error['line'] ?? '',
					'trace'   => [],
					'additional' => $error['additional'] ?? null
				];
		}

		return new WP_REST_Response($response, $code);
	}

	/**
	 * Handle success response generation.
	 *
	 * @param mixed $responseData
	 * @param string|null $message Optional message for the response.
	 * @param int $code HTTP status code for the response.
	 *
	 * @return WP_REST_Response
	 */
	protected function generateSuccessResponse(
		mixed $responseData,
		?string $message = null,
		int $code = 200
	): WP_REST_Response {
		return $this->generateApiResponse($responseData, 'success', null, $message, $code);
	}

	/**
	 * Handle error response generation.
	 *
	 * @param string|null $message The error message.
	 * @param Throwable|null $error
	 * @param int $code HTTP status code for the error.
	 *
	 * @return WP_REST_Response
	 */
	protected function generateErrorResponse(
		?Throwable $error = null,
		?string $message = '',
		int $code = 400
	): WP_REST_Response {
		return $this->generateApiResponse(null, 'error', $error, $message, $code);
	}

	/**
	 * Generate metadata for the response.
	 *
	 * @return array
	 */
    private function getMeta(): array {

        // Retrieve request time safely
        $request_time = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_TIME_FLOAT'
        );

        $request_time = $request_time ? (float) wp_unslash($request_time) : microtime(true);

        return [
            'api_version'      => defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : '1.0.0',
            'processing_time'  => number_format( microtime(true) - $request_time, 4 ) . 's',
            'user_context'     => [
                'user_id' => get_current_user_id(),
                'role'    => implode(', ', wp_get_current_user()->roles ?? []),
            ],
        ];
    }

	/**
	 * Generate request metadata.
	 * @param int|null $code
	 * @return array
	 */
    private function getRequestMetadata(?int $code): array {

// REQUEST_URI
        $request_uri = WordpressHelpers::sanitize_input('SERVER', 'REQUEST_URI');

// REQUEST_METHOD
        $request_method = WordpressHelpers::sanitize_input('SERVER', 'REQUEST_METHOD');
        $request_method = $request_method ?: 'UNKNOWN';

// REQUEST_TIME (int)
        $request_time = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_TIME',
            filters: [FILTER_SANITIZE_NUMBER_INT],
            validate: FILTER_VALIDATE_INT,
            return: 'int'
        );
        $request_time = $request_time ?: time();

        // REQUEST PARAMETERS (GET + POST)
        $request_params = array_merge(
            WordpressHelpers::sanitize_input('GET')  ?: [],
            WordpressHelpers::sanitize_input('POST') ?: []
        );


        // HEADERS
        $headers = function_exists('beondseodeps_getallheaders') ? beondseodeps_getallheaders() : [];


        return [
            'endpoint'             => $request_uri,
            'http_method'          => $request_method,
            'http_headers'         => $headers,
            'http_status'          => $code ?? http_response_code(),
            'request_params'       => $request_params,
            'request_received_at'  => gmdate('Y-m-d H:i:s', $request_time),
        ];
    }
}
