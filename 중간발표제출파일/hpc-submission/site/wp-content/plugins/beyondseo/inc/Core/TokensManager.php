<?php

declare (strict_types=1);
namespace RankingCoach\Inc\Core;

if (!defined('ABSPATH')) {
    exit;
}
use Exception;
use RankingCoach\Inc\Exceptions\CommunicationOptInDisabledException;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\Tokens\TokensApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Traits\SingletonTrait;
use RankingCoach\Inc\Exceptions\InvalidTokenException;
use RankingCoach\Inc\Exceptions\MissingTokenException;
use ReflectionException;
/**
 * Class TokensManager
 */
class TokensManager
{
    use SingletonTrait;
    use RcLoggerTrait;

    protected static ?self $instance = null;
	public const ACCESS_TOKEN = BaseConstants::OPTION_ACCESS_TOKEN;
	public const REFRESH_TOKEN = BaseConstants::OPTION_REFRESH_TOKEN;

    /**
     * TokensManager singleton
     * @return TokensManager
     */
    public static function instance(): TokensManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

	/**
	 * TokensManager constructor.
	 *
	 * @param TokensApiManager $tokensApiClient
	 */
	private TokensApiManager $tokensApiClient;

	/**
	 * Update the access and refresh tokens in the database.
	 * @param mixed $accessToken
	 * @param mixed $refreshToken
	 * @return bool
	 */
	public static function updateTokens( mixed $accessToken, mixed $refreshToken ): bool {

		return
			update_option( self::ACCESS_TOKEN, $accessToken ) &&
			update_option( self::REFRESH_TOKEN, $refreshToken );
	}

    /**
     * Delete the access and refresh tokens from the database.
     * @return bool
     */
    public function deleteTokens(): bool {
        return
            delete_option( self::ACCESS_TOKEN ) &&
            delete_option( self::REFRESH_TOKEN );
    }

    public function disconnectProject(): bool
    {
        $options = [
            BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID,
            BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID,
        ];
        $success = true;

        foreach ($options as $option) {
            try {
                delete_option($option);
            } catch (\Throwable $e) {
                $success = false;
            }
        }

        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        foreach ($options as $option) {
            wp_cache_delete($option, 'options');
            if (get_option($option, null) !== null) {
                $success = false;
            }
        }

        return $success;
    }

	/**
	 * Validate the accessToken hash.
	 * @param string|null $token
	 * @param bool $returnExpiration
	 * @return bool|int
	 */
	public static function validateToken( ?string $token = null,  bool $returnExpiration = false ): bool|int {
		list( , $expireAt ) = TokensManager::getJwtParts( $token );

		// Get the current time
		$currentTime = time();

		// Check if the token is expired
		if ( ! $expireAt || ( $expireAt > 0 && $expireAt < $currentTime ) ) {
			return false; // JWT has expired
		}

		if($returnExpiration) {
			return $expireAt;
		}

		return true;
	}

	/**
	 * Check JWT parts if the access token and account ID are set.
	 *
	 * @param string|null $jwt
	 *
	 * @return array
	 */
	public static function getJwtParts( ?string $jwt = null ): array {
		if ( ! $jwt ) {
			return [ null, null ];
		}

		// Split the token into parts
		$parts = explode( '.', $jwt );

		// Check if it has exactly three parts
		if ( count( $parts ) !== 3 ) {
			return [ null, null ];
		}

		// Decode each part
		list( $header, $payload, ) = $parts;

		// Validate Base64-URL encoding for header and payload
		if ( ! TokensManager::isBase64Url( $header ) || ! TokensManager::isBase64Url( $payload ) ) {
			return [ null, null ];
		}

		// Decode the header and payload to verify their JSON structure
		$decodedHeader = json_decode(TokensManager::base64UrlDecode($header), true);
		$decodedPayload = json_decode(TokensManager::base64UrlDecode($payload), true);

		if (!is_array($decodedHeader) || !is_array($decodedPayload)) {
			return [ null, null ];
		}

		// Optionally check required fields in the header or payload
		if (!isset($decodedHeader['alg']) || !isset($decodedHeader['typ'])) {
			return [ null, null ];
		}

		// Check if the 'exp' claim exists
		if ( ! isset( $decodedPayload['expiresAt'] ) || ! isset( $decodedPayload['accountId'] ) ) {
			return [ null, null ];
		}

		return [ $decodedPayload['accountId'], $decodedPayload['expiresAt'] ];
	}

	/**
	 * @param $string
	 *
	 * @return false|int
	 */
	public static function isBase64Url( $string ): bool|int {
		// Check if the string is valid Base64-URL (no padding '=' characters)
		return preg_match( '/^[A-Za-z0-9_\-]+$/', $string );
	}

	/**
	 * Decode a Base64-URL string.
	 *
	 * @param string $string The string to decode.
	 *
	 * @return string The decoded string.
	 */
	public static function base64UrlDecode( string $string): string {
		// Decode Base64-URL by replacing characters and padding if necessary
		$remainder = strlen($string) % 4;
		if ($remainder) {
			$string .= str_repeat('=', 4 - $remainder);
		}
		return base64_decode(strtr($string, '-_', '+/'));
	}

	/**
	 * Read the access token from the database and check if it is valid.
	 */
	private function isAccessTokenValid(): bool {

		$token = get_option(self::ACCESS_TOKEN);
		if (!$token) {
			return false;
		}

		return TokensManager::validateToken($token) === true;
	}

	/**
	 * Read the refresh token from the database and check if it is valid.
	 */
	private function isRefreshTokenValid(): bool {
		$token = get_option(self::REFRESH_TOKEN);
		if (!$token) {
			return false;
		}

		return TokensManager::validateToken($token) === true;
	}

	/**
	 * Get the access token from the database.
	 */
	public function getStoredAccessToken(): string {
		return get_option(self::ACCESS_TOKEN, '');
	}

	/**
	 * Get the refresh token from the database.
	 */
	public function getStoredRefreshToken(): string {
		return get_option(self::REFRESH_TOKEN, '');
	}

    /**
     * Check if the access token exists in the database and it is valid.
     * If not, generate a new access token.
     * @param string|null $context
     * @return string
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws CommunicationOptInDisabledException
     */
    public function getAccessToken(?string $context = null): string
    {
        if ($this->isAccessTokenValid()) {
            return $this->getStoredAccessToken();
        }
        return $this->generateAccessToken();
    }
    /**
     * Generate a new access token using the refresh token.
     * @return string
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Exception
     * @throws CommunicationOptInDisabledException
     */
    private function generateAccessToken(): string
    {
        $refreshToken = $this->checkRefreshToken(static::class);
        if(!empty($refreshToken)) {
            $this->generateAndSaveAccessToken($refreshToken);
        }
        return $this->getStoredAccessToken();
    }
    /**
     * Check if the refresh token exists in the database and it is valid.
     * If not, generate a new refresh token.
     * @param string|null $context
     * @return string
     * @throws Exception
     */
    public function checkRefreshToken(?string $context = null): string
    {
        $refreshToken = $this->getStoredRefreshToken();
        if (empty($refreshToken)) {
            \beyondseo_rclh('The token is missing.', 'ERROR');
            return '';
        }
        if (!$this->isRefreshTokenValid()) {
            \beyondseo_rclh('The refresh token is invalid or expired', 'ERROR');
            return '';
        }
        return $refreshToken;
    }
    /**
     * @param string $refreshToken
     *
     * @return string
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function generateAndSaveAccessToken(string $refreshToken): string
    {
        return TokensApiManager::getInstance()->generateToken($refreshToken);
    }
    /**
     * Check if the refresh token is expired and generate a new one.
     * @return float
     */
    public function calculateRefreshTokenRemainingDays(): float
    {
        $refreshToken = $this->getStoredRefreshToken();
        $expiration = self::validateToken($refreshToken, true) ?? 0;
        $currentTime = time();
        $nextCheckTime = $currentTime + 1800;
        // Add 1/2 hour to the current time
        if (!$expiration || $expiration < $currentTime) {
            return -1;
        }
        $cron_report = ['rc_refresh_token_validation_status' => ['days_validity' => round(($expiration - $currentTime) / (24 * 60 * 60)), 'last_check_time' => gmdate('Y-m-d H:i:s', $currentTime), 'next_check_time' => gmdate('Y-m-d H:i:s', $nextCheckTime)]];
        /** @var \CronJobManager $cronManager */
        $cronManager = CronJobManager::instance();
        $cronManager->saveRefreshTokenRemainingDays($cron_report);
        return round(($currentTime - $expiration) / (24 * 60 * 60)) ?? 0;
    }

    /**
     * Check the status of the iframe URL.
     * @param string $iframeUrl
     * @return int|null
     */
    public function checkIframeUrlStatus(string $iframeUrl): ?int {
        $response = wp_remote_get($iframeUrl, [
            'redirection' => 0,
            'timeout'     => 10,
        ]);

        return is_wp_error($response)
            ? null
            : wp_remote_retrieve_response_code($response);
    }

    /**
     * Reset all onboarding, activation, and other constants related to a successful activation.
     *
     * @return void
     */
    public function resetActivationData(): void {
        // 1. Delete Tokens
        $this->deleteTokens();

        // 2. Delete Activation Code and Account info
        delete_option( BaseConstants::OPTION_ACTIVATION_CODE );
        delete_option( BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID );
        delete_option( BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID );
        delete_option( BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID );

        // 3. Delete Subscription Data
        delete_option( BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION );
        delete_option( BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY );
        delete_option( BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS );

        // 4. Delete Onboarding status
        delete_option( BaseConstants::OPTION_ACCOUNT_ONBOARDING_COMPLETED );
        delete_option( BaseConstants::OPTION_ONBOARDING_COLLECT_DATE );
        delete_option( BaseConstants::OPTION_ONBOARDING_FINISH_DATE );
        delete_option( BaseConstants::OPTION_ONBOARDING_STEPS );
        delete_option( BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC );
        delete_option( BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE );
        delete_option( BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP );
        delete_option( BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE );
        delete_option( BaseConstants::OPTION_RANKINGCOACH_ONBOARDING_URL );

        // 5. Delete Settings/Setup
        delete_option( BaseConstants::OPTION_IS_RESELLER_ACCOUNT );
        delete_option( BaseConstants::OPTION_LOCATION_SETUP_SETTINGS );
        delete_option( BaseConstants::OPTION_ACCOUNT_SETUP_SETTINGS );

        // 6. Drop keywords
        $this->resetKeywordsData();

        // 7. Reset Flow State
        $store = new OptionStore();
        $store->resetFlowStateOnly();
    }

    /**
     * Clear the locally cached keywords and the keyword onboarding requirement.
     *
     * @return void
     */
    private function resetKeywordsData(): void {
        try {
            $dbManager = DatabaseManager::getInstance();

            if ($dbManager->tableExists(DatabaseTablesManager::DATABASE_APP_KEYWORDS)) {
                $dbManager->truncate(DatabaseTablesManager::DATABASE_APP_KEYWORDS);
            }

            // Back to the seeded state: an empty value is skipped when the onboarding
            // payload is assembled, so nothing stale is submitted.
            if ($dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP)) {
                $dbManager->update(
                    DatabaseTablesManager::DATABASE_SETUP,
                    ['value' => ''],
                    ['setupRequirement' => 'businessKeywords']
                );
            }
        } catch (\Throwable $e) {
            // Resetting activation must not fail because of the local keyword cache.
            $this->log('Failed to reset local keywords data: ' . $e->getMessage(), 'WARNING');
        }
    }
}
