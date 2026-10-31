<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Api\Tokens;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\InvalidResponseException;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use ReflectionException;
use function beyondseo_rceh;

/**
 * Class TokensApiManager
 */
class TokensApiManager extends HttpApiClient
{

	/** @var TokensApiManager|null $instance Singleton instance */
	protected static ?TokensApiManager $instance = null;

    /** @var TokensApiManager[] $instances Array of instances keyed by bearerToken */
    protected static array $instances = [];

	public static bool $useCache = false;
	public static bool $devMode = true;

	/**
	 * Get the singleton instance.
	 *
	 * @param array $defaultHeaders
	 * @param bool $bearerToken
	 *
	 * @return TokensApiManager
	 * @throws HttpApiException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function getInstance( array $defaultHeaders = [], bool $bearerToken = false ): TokensApiManager {
        $key = $bearerToken ? 'with_token' : 'without_token';
        if ( ! isset(self::$instances[$key]) ) {
			$accessToken = null;
			if($bearerToken) {
				/** @var TokensManager $tokensManager */
                $tokensManager = TokensManager::instance();
				$accessToken = $tokensManager->getStoredAccessToken();
				if (TokensManager::validateToken($accessToken, false) === false) {
					$refreshToken = $tokensManager->getStoredRefreshToken();
					$tokensManager->generateAndSaveAccessToken($refreshToken);
					$accessToken = $tokensManager->getStoredAccessToken();
				}
			}
			self::$instances[$key] = new self($defaultHeaders, $accessToken);
		}

		return self::$instances[$key];
	}

	/**
	 * TokensApiManager constructor.
	 *
	 * @param array $defaultHeaders
	 * @param string|null $accessToken
	 */
	public function __construct(
		array $defaultHeaders = [],
		?string $accessToken = null
	) {

		parent::__construct($defaultHeaders, $accessToken);
	}

	/**
	 * Handle the response for token-related operations.
	 *
	 * @param array $response
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function handleResponse(array $response): bool
	{
		if(!empty($response->error)) {
			\beyondseo_rclh('Invalid API response during generate the refresh token. Error:' . $response->error . ' Context: ' . json_encode($response), 'ERROR');
			return false;
		}
		if (empty($response['content']) ?? false) {
			\beyondseo_rclh('Invalid API response during generate the refresh token. Context: ' . json_encode($response), 'ERROR');
			return false;
		}
		$response = $response['content'];

		if (empty($response->refreshToken) ?? false) {
			\beyondseo_rclh('Invalid API response during generate the refresh token. Context: ' . json_encode($response), 'ERROR');
			return false;
		}

		$refreshToken = $response->refreshToken;
		$accessToken = $response->accessToken;

		return TokensManager::updateTokens($accessToken, $refreshToken);
	}

    /**
     * Refresh the token by making an API call.
     *
     * @param string $refreshToken
     * @return string
     * @throws HttpApiException
     * @throws Exception
     */
	public function generateToken(string $refreshToken): string
	{
		// Set the URL for the refresh endpoint
		$this->setUrl('refresh', 'refreshUrl', !self::$useCache);

		// Generate payload using the modern approach with security metadata
		$payload = CoreHelper::generateCommonSecurityPayload([
			'refreshToken' => $refreshToken,
		]);

		// Prepare security headers using the refresh token for HMAC signature
		$this->prepareSecurityHeaders($refreshToken, $payload);

		// Make the POST request
		$response = $this->post($payload);
		$this->handleResponse($response);

		/** @var TokensManager $tokenManager */
		$tokenManager = TokensManager::instance();
		return $tokenManager->getStoredAccessToken();
	}
}
