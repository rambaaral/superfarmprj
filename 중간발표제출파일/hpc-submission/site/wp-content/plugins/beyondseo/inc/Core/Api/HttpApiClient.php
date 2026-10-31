<?php /** @noinspection PhpLackOfCohesionInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\CommunicationOptInDisabledException;
use RankingCoach\Inc\Exceptions\UnsupportedHttpMethodException;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Exceptions\InvalidUrlException;

/**
 * Class ExternalApi
 */
class HttpApiClient {

	use RcLoggerTrait;

	public const CALL_METHOD_GET = 'GET';
	public const CALL_METHOD_POST = 'POST';
	public const CALL_METHOD_PUT = 'PUT';
	public const CALL_METHOD_PATCH = 'PATCH';
	public const CALL_METHOD_DELETE = 'DELETE';

	/** @var string|null $url The URL for the API. */
	protected ?string $url = null;

	/** @var array|null $configuration The external integrations configuration. */
	protected ?array $configuration = null;

	/** @var array $defaultHeaders The default headers to include with each request. */
	protected array $defaultHeaders;

	/** @var array $securityHeaders The security headers to include with each request. */
	protected array $securityHeaders;

	/** @var bool $bypassOptIn When true, skip the communication opt-in check for this client. */
	protected bool $bypassOptIn = false;

	/** @var string $methodType The method type. */
	protected string $methodType = self::CALL_METHOD_GET;

	protected array $allowedMethods = [
		self::CALL_METHOD_GET,
		self::CALL_METHOD_POST,
		self::CALL_METHOD_PUT,
		self::CALL_METHOD_PATCH,
		self::CALL_METHOD_DELETE
	];

	/** @var RetryHandler $retriesHandler The retry handler. */
	private RetryHandler $retriesHandler;

	/** @var HttpAPIResponseHandler $responseHandler The response handler. */
	private HttpAPIResponseHandler $responseHandler;
	private int $maxRetries = 3;
	private int $backoffFactor = 5;

	/**
	 * ExternalApi constructor.
	 *
	 * @param array $defaultHeaders Default headers to include with each request.
	 */
	public function __construct( array $defaultHeaders = [], ?string $accessToken = null ) {
		$this->defaultHeaders = array_merge( [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		], $defaultHeaders );
		$this->securityHeaders = [];

		if ( $accessToken ) {
			$this->setBearerToken( $accessToken );
		}

		$this->retriesHandler  = new RetryHandler( $this->maxRetries, $this->backoffFactor );
		$this->responseHandler = new HttpAPIResponseHandler();
	}

	/**
	 * Set the security headers for the API client.
	 *
	 * @param array $headers The headers to set.
	 */
	public function setSecurityHeaders( array $headers ): void {
		$this->securityHeaders = array_merge( $this->securityHeaders, $headers );
	}

	/**
	 * Set the retry strategy for the API client.
	 *
	 * @param int $maxRetries The maximum number of retries.
	 * @param int $backoffFactor The backoff factor.
	 */
	public function setRetryStrategy( int $maxRetries, int $backoffFactor ): void {
		$this->maxRetries     = $maxRetries;
		$this->backoffFactor  = $backoffFactor;
		$this->retriesHandler = new RetryHandler( $maxRetries, $backoffFactor );
	}

	/**
	 * Send a GET request to the API.
	 *
	 * @param array $queryParams Query parameters to include in the request.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function get( array $queryParams = [], array $jsonParams = [] ): array {
		$this->methodType = self::CALL_METHOD_GET;
		$options          = [];

        if (!empty($queryParams)) {
            $options['query'] = $queryParams;
        }

        if (!empty($jsonParams)) {
            $options['json'] = $jsonParams;
        }

		return $this->executeWithRecovery($options);
	}

	/**
	 * Send a POST request to the API.
	 *
	 * @param array $data The data to include in the request body.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function post( array $data = [], ?int $timeout = null ): array {
		$this->methodType = self::CALL_METHOD_POST;
		return $this->executeWithRecovery([ 'json' => $data, 'timeout' => $timeout ]);
	}

	/**
	 * Send a PUT request to the API.
	 *
	 * @param array $data The data to include in the request body.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function put( array $data = [] ): array {
		$this->methodType = self::CALL_METHOD_PUT;
		return $this->executeWithRecovery([ 'json' => $data ]);
	}

	/**
	 * Send a DELETE request to the API.
	 *
	 * @return array The HTTP response parsed.
	 * @throws HttpApiException
	 */
	public function delete(): array {
		$this->methodType = self::CALL_METHOD_DELETE;
		return $this->executeWithRecovery([]);
	}

	/**
	 * Execute the request with retry and 401 recovery logic.
	 *
	 * @param array $options
	 * @return array
	 * @throws HttpApiException
	 */
	protected function executeWithRecovery( array $options = [] ): array {
		try {
			return $this->retriesHandler->execute( function () use ( $options ) {
				// Input validation
				$this->validateUrlAndMethod();
				// Request
				$this->sendClientRequest( $options, $this->securityHeaders );

				// Response
				return $this->responseHandler->validate()->parse();
			} ) ?? [];
		} catch ( HttpApiException $e ) {
			$this->log( 'HTTP Exception ' . $e->getCode() . ' occurred: ' . $e->getMessage(), 'ERROR' );
			$recovered = $this->handleUnauthorized($e);

			if ($recovered) {
				// Re-prepare security headers with NEW token and retry once
				$this->prepareSecurityHeaders($this->getBearerToken());
				try {
					$this->sendClientRequest( $options, $this->securityHeaders );
					return $this->responseHandler->validate()->parse();
				} catch (Exception $retryEx) {
					$this->log('Retry after recovery failed: ' . $retryEx->getMessage(), 'ERROR');
				}
			}

			throw $e;
		}
	}

	/**
	 * Build the full URL for a given API endpoint.
	 *
	 * @param string $endpoint The API endpoint.
	 *
	 * @return string The full URL.
	 * @throws Exception
	 */
	public function buildUrl( string $endpoint ): string {
		if ( empty( $endpoint ) ) {
			throw new Exception( esc_html__('API endpoint cannot be empty.', 'beyondseo') );
		}

		return $this->url . '/' . ltrim( $endpoint, '/' );
	}

	/**
	 * Set the base URL for the API.
	 *
	 * When $url is a fully-qualified URL it is validated and set directly.
	 * When $url is an endpoint path, the URL is built from the loaded configuration
	 * using $urlType to select the base and optionally appending debug params.
	 *
	 * @param string $url            A full URL or an endpoint path.
	 * @param string $urlType        The configuration key: 'baseUrl', 'publicApi', 'collectorsApi', 'register', 'refreshUrl'.
	 * @param bool   $addDebugParams Whether to append '?debug=1&noCache=1'.
	 *
	 * @throws Exception
	 */
	public function setUrl( string $url, string $urlType = 'baseUrl', bool $addDebugParams = true ): void {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$this->url = $url;
			return;
		}

		$this->loadConfiguration();

		$baseUrl = match ( $urlType ) {
			'publicApi'     => sprintf( $this->configuration['publicApi'], $this->configuration['prefix'] ),
			'collectorsApi' => sprintf( $this->configuration['collectorsApi'], rtrim( home_url(), '/' ) ),
			'register'      => sprintf( $this->configuration['register'], $this->configuration['prefix'] ),
			'refreshUrl'    => sprintf( $this->configuration['refreshUrl'], $this->configuration['prefix'] ),
			default         => sprintf( $this->configuration['baseUrl'], $this->configuration['prefix'] ),
		};

		$finalUrl = $baseUrl . ltrim( $url, '/' );

		if ( $addDebugParams ) {
			$finalUrl .= ( str_contains( $finalUrl, '?' ) ? '&' : '?' ) . 'debug=1&noCache=1';
		}

		if ( ! filter_var( $finalUrl, FILTER_VALIDATE_URL ) ) {
			throw new InvalidUrlException( esc_html__( 'Invalid URL format: ', 'beyondseo' ) . esc_url( $finalUrl ) );
		}

		$this->url = $finalUrl;
	}

	/**
	 * Load and cache the external integrations configuration, resolving the environment prefix.
	 *
	 * @return void
	 */
	protected function loadConfiguration(): void {
		if ( $this->configuration !== null ) {
			return;
		}

		$configPath = __DIR__ . '/externalIntegrations.php';

		$this->configuration = require $configPath;

		if ( RankingCoachPlugin::isProductionMode() ) {
			$this->configuration['prefix'] = $this->configuration['liveEnv'];
		} else {
			$this->configuration['prefix'] = get_option( 'testing_environment', $this->configuration['devEnv'] );
		}
	}

	/**
	 * Format exception details into a loggable array.
	 *
	 * @param Exception $e
	 * @return array<string,mixed>
	 */
	protected function formatExceptionContext( Exception $e ): array {
		return [
			'exception_message' => $e->getMessage(),
			'exception_code'    => $e->getCode(),
			'exception_file'    => $e->getFile(),
			'exception_line'    => $e->getLine(),
		];
	}

	/**
	 * Mask a token for safe logging: prefix + length only.
	 *
	 * @param string $token
	 * @return string
	 */
	protected function maskToken( string $token ): string {
		$prefix = substr( $token, 0, 6 );
		$len    = strlen( $token );
		return $prefix . '… len=' . $len;
	}

	/**
	 * Proxy to CoreHelper::generateCommonSecurityPayload().
	 *
	 * @param array $additionalData
	 * @return array
	 */
	protected function generateCommonSecurityPayload( array $additionalData = [] ): array {
		return CoreHelper::generateCommonSecurityPayload( $additionalData );
	}

	/**
	 * Validate the stored access token, refresh if expired, and return the current token.
	 *
	 * @return string|null
	 * @throws Exception
	 */
	protected static function handleTokenValidation(): ?string {
		$tokensManager = TokensManager::instance();
		$accessToken   = $tokensManager->getStoredAccessToken();
		if ( TokensManager::validateToken( $accessToken ) === false ) {
			$refreshToken = $tokensManager->getStoredRefreshToken();
			if ( ! empty( $refreshToken ) ) {
				$tokensManager->generateAndSaveAccessToken( $refreshToken );
			}
			$accessToken = $tokensManager->getStoredAccessToken();
		}
		return $accessToken;
	}

	/**
	 * Set default headers for the API client.
	 *
	 * @param array $headers The headers to set.
	 */
	public function setDefaultHeaders( array $headers ): void {
		$this->defaultHeaders = array_merge( $this->defaultHeaders, $headers );
	}

	/**
	 * Handle 401 Unauthorized error by trying to revalidate with activation code.
	 *
	 * @param HttpApiException $e
	 * @return bool True if recovery was successful, false otherwise.
	 */
	protected function handleUnauthorized(HttpApiException $e): bool {
		if ($e->getCode() !== 401) {
			return false;
		}

		$activationCode = get_option(BaseConstants::OPTION_ACTIVATION_CODE);
		if (empty($activationCode)) {
			return false;
		}

		// Prevent infinite recursion if the revalidation itself returns 401
		static $isRefreshing = false;
		if ($isRefreshing) {
			return false;
		}
		$isRefreshing = true;

		try {
			return $this->revalidateWithActivationCode($activationCode);
		} catch (Exception $ex) {
			$this->log('Failed to revalidate with activation code: ' . $ex->getMessage(), 'ERROR');
			return false;
		} finally {
			$isRefreshing = false;
		}
	}

	/**
	 * Revalidate the activation code to get new tokens.
	 *
	 * @param string $activationCode
	 * @return bool
	 * @throws Exception
	 */
	public function revalidateWithActivationCode(string $activationCode): bool {
		$refreshClient = new HttpApiClient();
		$refreshClient->setUrl('activation/check', 'publicApi');

		$payload = CoreHelper::generateCommonSecurityPayload([
			'activationCode' => $activationCode,
		]);
		$refreshClient->prepareSecurityHeaders($activationCode, $payload);

		$response = $refreshClient->post($payload);

		if (empty($response['content']) || empty($response['content']->success)) {
			$this->log('Activation code revalidation failed or returned unsuccessful. Resetting activation data.', 'WARNING');
			TokensManager::instance()->resetActivationData();
			return false;
		}

		$result = $response['content'];
		$refreshToken = $result->refreshToken ?? null;

		if (!$refreshToken) {
			$this->log('No refresh token returned during activation code revalidation.', 'WARNING');
			return false;
		}

		// Save new tokens
		$tokensManager = TokensManager::instance();
		$tokensManager->generateAndSaveAccessToken($refreshToken);

		// Update account ID and settings if they are provided
		if (!empty($result->accountId)) {
			update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $result->accountId);
		}
		if (isset($result->resellerAccount)) {
			update_option(BaseConstants::OPTION_IS_RESELLER_ACCOUNT, $result->resellerAccount ? 1 : 0);
		}
		if (!empty($result->locationSetupSetting)) {
			update_option(BaseConstants::OPTION_LOCATION_SETUP_SETTINGS, $result->locationSetupSetting);
		}

		return true;
	}

	/**
	 * Add a single header to the default headers.
	 *
	 * @param string $key The header name.
	 * @param string $value The header value.
	 */
	public function addDefaultHeader( string $key, string $value ): void {
		$this->defaultHeaders[ $key ] = $value;
	}

	/**
	 * Set the Bearer token for authorization.
	 *
	 * @param string $token The Bearer token.
	 *
	 * @return void
	 */
	public function setBearerToken( string $token ): void {
		$this->addDefaultHeader( 'Authorization', "Bearer $token" );
	}

	/**
	 * Get the Bearer token from authorization.
	 * @return string
	 */
	public function getBearerToken(): string {
		$authorization = $this->defaultHeaders['Authorization'] ?? '';
		return str_replace( 'Bearer ', '', $authorization );
	}

	/**
	 * Remove the Bearer token from authorization.
	 *
	 * @return void
	 */
	public function removeBearerToken(): void {
		unset( $this->defaultHeaders['Authorization'] );
	}

    /**
     * @param array $options
     * @param array $securityHeaders
     *
     * @return array
     * @throws HttpApiException
     * @throws CommunicationOptInDisabledException
     */
	public function sendClientRequest( array $options = [], array $securityHeaders = [] ): array {
        if ( !$this->bypassOptIn && !SettingsManager::instance()->get_option('beyondseo_comm_opt_in', false)) {
            throw new CommunicationOptInDisabledException( __( 'Communication opt-in is disabled.', 'beyondseo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $startTime = microtime(true);

		$this->setDefaultHeaders( $securityHeaders );

		$wpArgs = [
			'method'      => $this->methodType,
			'headers'     => $this->defaultHeaders,
			'timeout'     => $options['timeout'] ?? 60,
			'sslverify'   => true,
			'redirection' => 5,
		];

		if ( ! empty( $options['json'] ) ) {
			$wpArgs['body'] = wp_json_encode( $options['json'] );
		}

		$url = $this->url;
		if ( ! empty( $options['query'] ) ) {
			$url = add_query_arg( $options['query'], $url );
		}

		$response = wp_remote_request( $url, $wpArgs );

		if ( is_wp_error( $response ) ) {
			$executionTime = round( ( microtime( true ) - $startTime ) * 1000, 2 );
			$errorMessage  = $response->get_error_message();
			$this->log_json( [
				'operation_type'   => 'http_request',
				'operation_status' => 'error',
				'api_calls'        => null,
				'context_entity'   => 'http_client',
				'context_id'       => null,
				'context_type'     => $this->methodType,
				'execution_time'   => $executionTime,
				'error_details'    => [
					'exception_type'    => 'TransportException',
					'exception_message' => $errorMessage,
					'exception_code'    => $response->get_error_code(),
					'exception_file'    => null,
					'exception_line'    => null,
					'exception_trace'   => null,
				],
				'metadata'         => [
					'url'            => $url,
					'method'         => $this->methodType,
					'payload'        => $options,
					'error_category' => 'transport_error',
				],
			], 'api' );
			/* translators: %s: transport error message */
			throw new HttpApiException( sprintf( __( 'Transport error occurred during request: %s', 'beyondseo' ), esc_html( $errorMessage ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$statusCode    = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$executionTime = round( ( microtime( true ) - $startTime ) * 1000, 2 );

		$this->responseHandler->response = $response;

		$this->log_json( [
			'operation_type'   => 'http_request',
			'operation_status' => 'success',
			'api_calls'        => null,
			'context_entity'   => 'http_client',
			'context_id'       => null,
			'context_type'     => $this->methodType,
			'execution_time'   => $executionTime,
			'error_details'    => null,
			'metadata'         => [
				'url'          => $url,
				'method'       => $this->methodType,
				'payload'      => $options,
				'response'     => $body,
				'status_code'  => $statusCode,
				'request_headers' => $this->defaultHeaders,
				'request_size' => json_encode( $options ) ? strlen( json_encode( $options ) ) : 0,
			],
		], 'api' );

		return $response;
	}

	/**
	 * @param array $response
	 *
	 * @return void
	 * @throws Exception
	 */
	public function validateResponse( array $response ): void {
		$content     = wp_remote_retrieve_body( $response );
		$contentType = wp_remote_retrieve_header( $response, 'content-type' );
		$data        = null;
		if ( str_contains( $contentType, 'application/json' ) ) {
			$data = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				/* translators: %s: JSON error message */
				throw new Exception( sprintf(esc_html__('Error decoding JSON response: %s', 'beyondseo'), esc_html(json_last_error_msg())) );
			}
		} elseif ( str_contains( $contentType, 'text/plain' ) ) {
			$data = $content;
		} else {
            /* translators: %s: unsupported content type */
			throw new Exception( esc_html__('Unsupported content type.', 'beyondseo') );
		}
		$statusCode = wp_remote_retrieve_response_code( $response );
		if ( $statusCode >= 400 ) {
			$data    = is_array( $data ) ? $data : [];
			$error   = $data['error'] ?? __( 'Unknown error', 'beyondseo' );
			$message = $data['message'] ?? __( 'Unknown message', 'beyondseo' );
			/* translators: 1: error type, 2: error message */
			throw new Exception( sprintf( esc_html__( 'API error: %1$s - %2$s', 'beyondseo' ), esc_html( $error ), esc_html( $message ) ) );
		}
	}

	/**
	 * Validate the URL and method type.
	 * @return void
	 * @throws Exception
	 */
	public function validateUrlAndMethod(): void {
		if ( ! $this->url ) {
			throw new InvalidUrlException();
		}
		if ( ! $this->methodType ) {
            /* translators: %s: HTTP method type */
			throw new UnsupportedHttpMethodException( sprintf(esc_html__('Unsupported HTTP method: %s', 'beyondseo'), esc_html($this->methodType)) );
		}
	}

    /**
     * Update the object keys with a prefix.
     *
     * @param object|array $object The object to update.
     * @param bool $addPrefix
     * @param string $key The key to update.
     */
    public function updateObjectKeys( mixed &$object, bool $addPrefix = true, string $key = 'objectType' ): void {
        $prefix = "RankingCoach\\";
        if ( is_array( $object ) || is_object( $object ) ) {
            foreach ( $object as &$value ) {
                $this->updateObjectKeys( $value, $addPrefix, $key );
            }
        } else {
            return;
        }

        if ( is_array( $object ) && isset( $object[ $key ] ) ) {
            if ( $addPrefix && !str_starts_with( $object[ $key ], $prefix ) ) {
                $object[ $key ] = $prefix . $object[ $key ];
            }
            if ( !$addPrefix && str_starts_with( $object[ $key ], $prefix ) ) {
                $object[ $key ] = substr( $object[ $key ], strlen( $prefix ) );
            }
        }

        if ( is_object( $object ) && property_exists( $object, $key ) ) {
            if ( $addPrefix && !str_starts_with( $object->{$key}, $prefix ) ) {
                $object->{$key} = $prefix . $object->{$key};
            }
            if ( !$addPrefix && str_starts_with( $object->{$key}, $prefix ) ) {
                $object->{$key} = substr( $object->{$key}, strlen( $prefix ) );
            }
        }
    }

    /**
     * Prepare security headers for the API client.
     * Now uses the consolidated logic from CoreHelper.
     *
     * @param string|null $accessToken
     * @param array $userPayload
     * @return void
     */
	public function prepareSecurityHeaders(?string $accessToken = null, array $userPayload = []): void {
		CoreHelper::setSecurityHeaders($this, $accessToken, $userPayload);
	}

}
