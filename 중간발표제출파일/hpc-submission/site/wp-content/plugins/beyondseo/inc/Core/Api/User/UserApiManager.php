<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api\User;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\UrlChangeDetector;
use RankingCoach\Inc\Exceptions\CommunicationOptInDisabledException;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use ReflectionException;
use Throwable;
use WP_Error;

/**
 * Class UserApiManager
 */
class UserApiManager extends HttpApiClient
{

    use RcLoggerTrait;

	/** @var UserApiManager[] $instances Array of instances keyed by bearerToken */
	protected static array $instances = [];

	/** @var int $userId The current user ID */
	protected int $userId;

    /**
     * Keyword scopes the plugin owns.
     *
     * checkAccount returns EVERY keyword_sites row of the location flattened into one
     * list, both SEO scope and Google-Ads scope. Ads-scope rows belong to the Ads
     * flows: they are a separate namespace, they are frequently the very same keyword
     * that is already tracked for SEO (which is what made every keyword show up twice
     * locally), and they carry dozens of machine-generated suggestions on top — pushing
     * the flat list past the subscription's keyword allowance. Only SEO-scope rows are
     * plugin keywords. A missing type is treated as SEO for backwards compatibility
     * with older API responses.
     *
     * @var string[]
     */
    private const SEO_KEYWORD_TYPES = ['seo'];

    private bool $projectMutationRequest = false;
    private ?\Closure $projectMutationConnectionCheck = null;

    public function deleteRemoteProject(int $locationId, \Closure $connectionIsCurrent): string|WP_Error
    {
        if ($locationId <= 0) {
            return new WP_Error('account_project_invalid_id', __('The project location ID is invalid.', 'beyondseo'), ['status' => 400]);
        }

        $previousOptInBypass = $this->bypassOptIn;
        $this->bypassOptIn = false;
        $this->projectMutationRequest = true;
        $this->projectMutationConnectionCheck = $connectionIsCurrent;

        try {
            $accessToken = $this->prepareProjectMutationAuthentication($connectionIsCurrent);
            $this->setUrl('projects/' . $locationId, 'baseUrl', false);
            $this->prepareSecurityHeaders($accessToken);
            $response = $this->delete();
            $content = $response['content'] ?? null;
            $outcome = is_object($content) && is_object($content->deletionResult ?? null)
                ? ($content->deletionResult->outcome ?? null)
                : null;

            if (!in_array($outcome, ['deleted', 'deferred', 'alreadyDeleted'], true)) {
                return new WP_Error(
                    'account_project_invalid_response',
                    __('The service did not confirm project deletion. Your local connection has not been removed. Please try again or contact support.', 'beyondseo'),
                    ['status' => 502]
                );
            }

            return $outcome;
        } finally {
            $this->projectMutationRequest = false;
            $this->projectMutationConnectionCheck = null;
            $this->bypassOptIn = $previousOptInBypass;
        }
    }

    public function createRemoteProject(\Closure $connectionIsCurrent): mixed
    {
        $previousOptInBypass = $this->bypassOptIn;
        $this->bypassOptIn = false;
        $this->projectMutationRequest = true;
        $this->projectMutationConnectionCheck = $connectionIsCurrent;

        try {
            $accessToken = $this->prepareProjectMutationAuthentication($connectionIsCurrent);
            $this->setUrl('checkAccount');
            $payload = CoreHelper::generateCommonSecurityPayload([
                'activationCode' => get_option(BaseConstants::OPTION_ACTIVATION_CODE, null),
                'verificationOnly' => false,
                'contextReferrer' => 'account-project-creation',
            ]);
            $this->prepareSecurityHeaders($accessToken, $payload);
            $response = $this->post($payload);

            return $response['content'] ?? null;
        } finally {
            $this->projectMutationRequest = false;
            $this->projectMutationConnectionCheck = null;
            $this->bypassOptIn = $previousOptInBypass;
        }
    }

    private function prepareProjectMutationAuthentication(\Closure $connectionIsCurrent): string
    {
        if (!SettingsManager::instance()->get_option('beyondseo_comm_opt_in', false)) {
            throw new CommunicationOptInDisabledException(__('Communication opt-in is disabled.', 'beyondseo'));
        }

        $tokensManager = TokensManager::instance();
        $accessToken = $tokensManager->getStoredAccessToken();
        $accountId = get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID);
        if (!TokensManager::validateToken($accessToken)) {
            $refreshToken = $tokensManager->getStoredRefreshToken();
            if (!TokensManager::validateToken($refreshToken)
                || (string) TokensManager::getJwtParts($refreshToken)[0] !== (string) $accountId) {
                throw new HttpApiException('Project operation requires valid credentials.', 401);
            }

            $this->setUrl('refresh', 'refreshUrl', false);
            $payload = CoreHelper::generateCommonSecurityPayload(['refreshToken' => $refreshToken]);
            $this->prepareSecurityHeaders($refreshToken, $payload);
            $response = $this->post($payload);
            $content = $response['content'] ?? null;
            if (!is_object($content)
                || !is_string($content->accessToken ?? null)
                || !is_string($content->refreshToken ?? null)
                || !TokensManager::validateToken($content->accessToken)
                || !TokensManager::validateToken($content->refreshToken)
                || (string) TokensManager::getJwtParts($content->accessToken)[0] !== (string) $accountId
                || (string) TokensManager::getJwtParts($content->refreshToken)[0] !== (string) $accountId) {
                throw new HttpApiException('Project operation token refresh failed.', 401);
            }

            if (!$connectionIsCurrent()) {
                throw new HttpApiException('The account or project changed.', 409);
            }

            update_option(TokensManager::ACCESS_TOKEN, $content->accessToken);
            update_option(TokensManager::REFRESH_TOKEN, $content->refreshToken);
            $accessToken = $content->accessToken;
        }

        if ((string) TokensManager::getJwtParts($accessToken)[0] !== (string) $accountId) {
            throw new HttpApiException('Project operation requires matching account credentials.', 401);
        }

        $this->setBearerToken($accessToken);
        return $accessToken;
    }

    protected function handleUnauthorized(HttpApiException $e): bool
    {
        if ($this->projectMutationRequest) {
            return false;
        }

        return parent::handleUnauthorized($e);
    }

    public function sendClientRequest(array $options = [], array $securityHeaders = []): array
    {
        if ($this->projectMutationConnectionCheck !== null && !($this->projectMutationConnectionCheck)()) {
            throw new HttpApiException('The connected project changed.', 409);
        }

        $response = parent::sendClientRequest($options, $securityHeaders);
        if ($this->projectMutationRequest) {
            $status = wp_remote_retrieve_response_code($response);
            if ($status < 200 || $status >= 300) {
                throw new HttpApiException('Project operation request failed.', in_array($status, [401, 403], true) ? $status : 502);
            }

            $contentType = wp_remote_retrieve_header($response, 'content-type');
            $content = json_decode(wp_remote_retrieve_body($response));
            if (!is_string($contentType) || !str_contains($contentType, 'application/json')
                || json_last_error() !== JSON_ERROR_NONE || !is_object($content)) {
                throw new HttpApiException('Project operation received an invalid response.', 502);
            }
        }

        return $response;
    }

	/**
	 * Get the singleton instance.
	 *
	 * @param array $defaultHeaders
	 * @param bool $bearerToken
	 *
	 * @return UserApiManager
	 * @throws HttpApiException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function getInstance( array $defaultHeaders = [], bool $bearerToken = false ): UserApiManager {
		$key = $bearerToken ? 'with_token' : 'without_token';
		if ( ! isset(self::$instances[$key]) ) {
			$accessToken = null;
			if($bearerToken) {
                /** @var TokensManager $tokensManager */
                $tokensManager = TokensManager::instance();
                $accessToken = $tokensManager->getStoredAccessToken();
                if (TokensManager::validateToken($accessToken) === false) {
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
	 * UserApiManager constructor.
	 *
	 * @param array $defaultHeaders
	 * @param string|null $accessToken
	 *
	 * @throws Exception
	 */
	public function __construct(
		array $defaultHeaders = [],
		?string $accessToken = null
	) {
		parent::__construct($defaultHeaders, $accessToken);
		$this->loadConfiguration();
	}

    /**
     * Prepare security headers for the API request.
     *
     * @param string $activationCode
     * @return mixed
     * @throws HttpApiException
     * @throws Exception
     */
    public function checkActivationCode(string $activationCode): mixed {
        $this->setUrl('activation/check', 'publicApi');

        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload([
            'activationCode' => $activationCode,
        ]);
        $this->prepareSecurityHeaders($activationCode, $payload);

        $response = $this->post($payload);

        if (empty($response['content'])) {
            return false;
        }

        return $response['content'];
    }

    /**
     * Asks rankingCoach to re-send the activation code for the given email + this site's URL.
     * No token exists yet, so no security headers are prepared (same as RegisterApiManager::register()).
     * Bypasses the communication opt-in gate: the user has not consented yet at this point (decision D2).
     *
     * @param string $email
     * @return mixed
     * @throws HttpApiException
     * @throws Throwable
     */
    public function recoverActivationCode(string $email): mixed {
        $this->bypassOptIn = true;
        $this->setUrl('activation/recover', 'publicApi');
        $payload = CoreHelper::generateCommonSecurityPayload(['email' => $email]); // carries siteUrl + siteDomain
        $response = $this->post($payload);
        return $response['content'] ?? false;
    }

    /**
     * Get the user account details from RankingCoach API.
     *
     * @param string|null $locale
     * @return mixed
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Throwable
     * @throws Exception
     */
    public function getCategoriesTranslated(?string $locale = null): mixed
    {
        // Always fetch the latest data from the API without using cache
        $this->setUrl('categories');

        $payload = CoreHelper::generateCommonSecurityPayload([
            'locale' => WordpressHelpers::get_wp_locale($locale),
            'language' => WordpressHelpers::current_language_code_helper($locale),
        ]);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        $response = $this->post($payload);

        $this->log_json([
            'operation_type' => 'api_config',
            'operation_status' => 'prepared',
            'context_entity' => 'categories',
            'context_type' => 'config_resolution',
            'metadata' => [
                'endpoint' => 'categories',
                'url_type' => 'baseUrl',
                'environment' => (RankingCoachPlugin::isProductionMode() ? 'liveEnv' : 'devEnv'),
                'api_base_domain' => $this->configuration['prefix'] ?? null,
                'base_url_template' => $this->configuration['baseUrl'] ?? null,
                'base_url_resolved' => (isset($this->configuration['baseUrl']) && isset($this->configuration['prefix'])) ? sprintf($this->configuration['baseUrl'], $this->configuration['prefix']) : null,
                'final_url' => $this->url ?? null,
                'debug_params' => (is_string($this->url) ? (str_contains($this->url, 'debug=1') && str_contains($this->url, 'noCache=1')) : null),
                'config_source' => RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Api/externalIntegrations.php',
                'request_headers_keys' => array_keys($this->defaultHeaders ?? []),
                'payload' => $payload,
                'response_content' => $response['content']
            ],
        ], 'categories_translated', !RankingCoachPlugin::isProductionMode());

        return $response['content']?->translatedCategories ?? [];
    }

    /**
     * @return mixed
     * @throws HttpApiException
     */
    public function updateWebsiteUrl(): bool
    {
        $this->setUrl('updateWebsiteUrl');
        
        // Sanitize and validate the URL before sending
        $sanitizedUrl = $this->sanitizeWebsiteUrl(home_url());
        if (!$sanitizedUrl) {
            throw new Exception('Invalid website URL detected, cannot update API');
        }
        
        // Update the website URL in the database
        $payload = CoreHelper::generateCommonSecurityPayload([
            'siteDomain' => $sanitizedUrl,
        ]);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        $response = $this->put($payload);
        if (empty($response['content'])) {
            $this->log('Failed to update website URL in the API', 'ERROR');
            return false;
        }
        return true;
    }

    /**
     * Sanitize and validate website URL before API transmission
     */
    private function sanitizeWebsiteUrl(string $url): ?string
    {
        // Parse URL components
        $parsed = wp_parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }

        // Validate scheme
        $scheme = $parsed['scheme'] ?? 'https';
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // Sanitize host
        $host = strtolower($parsed['host']);

        // Sanitize port
        $port = '';
        if (!empty($parsed['port'])) {
            $portNum = (int)$parsed['port'];
            if ($portNum > 0 && $portNum <= 65535) {
                // Only include non-standard ports
                if (($scheme === 'http' && $portNum !== 80) || ($scheme === 'https' && $portNum !== 443)) {
                    $port = ':' . $portNum;
                }
            }
        }

        // Sanitize path
        $path = !empty($parsed['path']) ? rtrim($parsed['path'], '/') : '';

        return $scheme . '://' . $host . $port . $path;
    }

    /**
     * Fetches account details from the external API and updates only subscription-related data.
     * 
     * @throws Throwable
     * @throws HttpApiException
     */
    public function fetchAndUpdateAccountDetails(): mixed
    {
        $this->setUrl('checkAccount');

        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload();
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        // If the account details were fetched successfully, update the data
        $response = $this->post($payload);

        // Return false if response is empty
        if (empty($response['content'])) {
            return false;
        }

        // Update only subscription-related data
        $result = $this->updateSubscriptionData($response['content']);

        if (is_wp_error($result)) {
            $this->log('Error while updating subscription data: ' . $result->get_error_message(), 'ERROR');
            return false;
        }

        return $response['content'];
    }

    /**
     * Updates only subscription-related data from the API response.
     *
     * @param mixed $jsonData
     * @return bool|WP_Error
     * @throws Exception
     */
    public function updateSubscriptionData(mixed $jsonData): bool|WP_Error
    {
        // Decode JSON data
        $data = json_decode(json_encode($jsonData), true);
        if (!$data) {
            return false;
        }

        try {
            $subscriptionExternal = $data['subscription'] ?? null;
            $subscriptionHistory = $data['subscriptionHistory'] ?? null;
            $maxKeywords = $data['maxAllowedKeywords'] ?? null;
            $upgradablePlans = $data['upgradablePlans'] ?? null;

//            if (!$subscriptionExternal) {
//                throw new Exception('Update subscription data failed. Missing required fields: subscription, subscriptionHistory or maxAllowedKeywords.');
//            }

            // Update only subscription-related options
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, $subscriptionExternal, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY, $subscriptionHistory, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS, $maxKeywords, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_UPGRADE_PLANS, $upgradablePlans, true);
            if(!empty($data['couponCode'] ?? null)) {
                update_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE, $data['couponCode'], null);
            }

            return true;

        } catch (Exception $e) {
            $this->log('Error while updating subscription data: ' . $e->getMessage(), 'ERROR');
            return new WP_Error('subscription_update_failed', $e->getMessage());
        }
    }

    /**
     * Fetches account data from the external API and inserts it directly into the database.
     *
     * Configures the API request using the external integration configuration, sets security headers,
     * sends a POST request with necessary data, and inserts the returned account details into the database.
     * If the API response is empty, the method returns false.
     *
     * @return mixed The account data if fetched successfully, or false if the response is empty.
     * @throws Exception
     * @throws Throwable
     */
    public function fetchAndInsertAccountData(bool $onlyVerification = false, string $context = ''): mixed
    {
        // Configure API request
        $this->setUrl('checkAccount');

        $params = [
            'activationCode' => get_option( BaseConstants::OPTION_ACTIVATION_CODE, null ),
            'verificationOnly' => $onlyVerification === true
        ];
        if($context) {
            $params['contextReferrer'] = $context;
        }
        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload($params);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        // Make API request
        $response = $this->post($payload);


        //$this->log(json_encode($response), 'DEBUG_RESPONSE');

        // Return false if response is empty
        if (empty($response['content'])) {
            return false;
        }
        
        // Insert data directly into the database
        $content = $response['content'];
        $result = $this->insertRCAccountData($content);

        if (is_wp_error($result)) {
            $this->log('Error while inserting Rankingcoach data: ' . $result->get_error_message(), 'ERROR');
            return false;
        }

        return $response['content'];
    }

    /**
     * Get the plugin information.
     *
     * @return mixed
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Exception
     * @throws CommunicationOptInDisabledException
     */
    public static function handleGmbCategories(): mixed
    {
        /** @var UserApiManager $instance This handles the API requests */
        $instance = UserApiManager::getInstance(bearerToken: true);
        $categories = $instance->getCategoriesTranslated();
        if($categories) {
            // Save the categories to the database

            // Check if the DatabaseSetupCategories table exists
            $tableExists = DatabaseManager::getInstance()->tableExists(DatabaseTablesManager::DATABASE_SETUP_CATEGORIES);
            if($tableExists) {
                DatabaseManager::getInstance()->tables()->saveCategoriesToDatabase($categories);
                // Update the last update timestamp and language
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_UPDATE, time());
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_LANGUAGE, WordpressHelpers::current_language_code_helper());
            }
        }
        return $categories;
    }


    /**
     * Insert the account details.
     *
     * @param mixed $jsonData
     * @return bool|WP_Error
     * @throws Throwable
     */
    public function insertRCAccountData(mixed $jsonData): bool|WP_Error
    {
        $dbManager = DatabaseManager::getInstance();

        // Decode JSON data
        $data = json_decode(json_encode($jsonData), true);
        if (!$data) {
            return false;
        }

        $dbManager->db()->beginTransaction();

        try {
            $accountId = $data['accountId'] ?? null;
            $projectId = $data['projectId'] ?? null;
            $locationId = $data['locationId'] ?? null;

            if(!$accountId || !$projectId || !$locationId) {
                throw new Exception('Save account data failed. Missing required fields: accountId, projectId, or locationId.');
            }

            // Update only account-related options
            update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $accountId, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, $projectId, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID, $locationId, true);
            $countryShortCode = $data['countryCode'] ?? null;
            if (!empty($countryShortCode)) {
                update_option(BaseConstants::OPTION_RANKINGCOACH_REGISTER_COUNTRY_CODE, strtolower($countryShortCode));
            }
            if(!empty($data['couponCode'] ?? null)) {
                update_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE, $data['couponCode'], null);
            }

            $subscriptionExternal = $data['subscription'] ?? '';
            if (empty($subscriptionExternal)) {
                // Unknown plan: treat it as a free, direct-channel plan — never as a partner (IONOS) one.
                $subscriptionExternal = CoreHelper::FALLBACK_WP_SUBSCRIPTION;
            }
            $subscriptionHistory = $data['subscriptionHistory'] ?? null;
            $maxKeywords = $data['maxAllowedKeywords'] ?? null;

            if (!$subscriptionExternal || !$subscriptionHistory || $maxKeywords === null) {
                throw new Exception('Update subscription data failed. Missing required fields: subscription, subscriptionHistory or maxAllowedKeywords.');
            }

            // Update only subscription-related options
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, $subscriptionExternal, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY, $subscriptionHistory, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS, $maxKeywords, true);
            update_option(BaseConstants::OPTION_RANKINGCOACH_UPGRADE_PLANS, $data['upgradablePlans'] ?? null, true);

            // Categories
            $categories = $data['categories']['elements'] ?? [];

            // Keywords
            $keywords = $data['keywords']['elements'] ?? [];
            $this->saveKeywords($keywords);
            
            // Everything is saved on the setup requirements table ( categories, keywords, location data )
            $this->updateSetupRequirements($data, $categories, $keywords);
            $dbManager->db()->commit();

            return true;

        } catch (Exception $e) {
            $dbManager->db()->rollback();
            $this->log('Error while inserting Rankingcoach data: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }


    /**
     * Insert the account details.
     *
     * @param array $categories
     * @param bool $fileStorage
     * @return bool|WP_Error
     */
    public function saveRCTranslatedCategories(array $categories = [], bool $fileStorage = false): bool|WP_Error
    {
        $dbManager = DatabaseManager::getInstance();

        if($fileStorage) {

            try {

                // Update the last update timestamp and language
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_UPDATE, time());
                update_option(BaseConstants::OPTION_CATEGORIES_LAST_LANGUAGE, WordpressHelpers::current_language_code_helper());

            } catch (Exception $e) {
                // do nothing
            }
        }

        $dbManager->db()->beginTransaction();
        try {
            $dbManager->tables()->saveCategoriesToDatabase($categories);

            $dbManager->db()->commit();
            // Update the last update timestamp and language
            update_option(BaseConstants::OPTION_CATEGORIES_LAST_UPDATE, time());
            update_option(BaseConstants::OPTION_CATEGORIES_LAST_LANGUAGE, WordpressHelpers::current_language_code_helper());

            return true;
        } catch (Exception $e) {
            $dbManager->db()->rollback();
            $this->log('Error while saving in database the translated categories: ' . $e->getMessage(), 'ERROR');
        }

        return false;
    }

    /**
     * @param array $data
     * @param array|null $categories
     * @param array|null $keywords
     * @return void
     * @throws Throwable
     */
    public function updateSetupRequirements(array $data, ?array $categories = null, ?array $keywords = null ): void
    {
        if(!empty($data['descriptionLong'])) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessDescription', $data['descriptionLong']);
        }
        if(!empty($data['companyName'])) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessName', $data['companyName']);
        }
        DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessServiceArea', 'y');
        // If the formattedAddress include just the country and that is Germany, we don't need to update the business address
        if(!empty($data['formattedAddress']) &&
            ($data['formattedAddress'] !== 'Germany' && $data['formattedAddress'] !== 'Deutschland')
        ) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessAddress', $data['formattedAddress']);
        }
        // If the geoaddress is not empty, we need to update the business geolocation
        if(!empty($data['geoaddress'])) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessGeoAddress', $data['geoaddress']);
        }
        // Categories (if any
        if(!empty($categories)) {
            DatabaseManager::getInstance()->tables()->updateSetupRequirements('businessCategories', array_map(static fn($cat) => (int) $cat['categoryId'], $categories));
        }

        if(!empty($keywords)) {
            // Normalize before storing: this row IS the onboarding payload, so ads-scope
            // rows, duplicates and anything beyond the subscription allowance must never
            // reach it. Writing the raw list here is what made onboarding submit ~30
            // names (21 distinct) against a limit of 20 and fail.
            $normalizedKeywords = $this->normalizeRcKeywords($keywords);
            if (!empty($normalizedKeywords)) {
                DatabaseManager::getInstance()->tables()->updateSetupRequirements(
                    'businessKeywords',
                    array_values(array_map(static fn(array $keyword) => (string) $keyword['name'], $normalizedKeywords))
                );
            }
        }

    }

    /**
     * Reduce a raw checkAccount keywords payload to the set the plugin owns.
     *
     * Applies, in order: SEO-scope filtering (see self::SEO_KEYWORD_TYPES), one entry
     * per alias, ordering by the priority the API assigned, and a cap at the
     * subscription's keyword allowance. Every keyword that leaves this plugin — local
     * table, onboarding payload, keyword sync — must go through here, otherwise the
     * remote side rejects the request with "maximum of N keywords".
     *
     * @param array $elements Raw `keywords.elements` from the API response
     * @return array<string, array<string, mixed>> Keyword rows keyed by alias
     */
    public function normalizeRcKeywords(array $elements): array
    {
        $maxAllowed = (int) get_option(BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS, 0);
        $byAlias = [];

        foreach ($elements as $element) {
            $element = is_object($element) ? (array) $element : $element;
            if (!is_array($element)) {
                continue;
            }

            if (!in_array(strtolower((string) ($element['type'] ?? 'seo')), self::SEO_KEYWORD_TYPES, true)) {
                continue;
            }

            $keyword = $element['keyword'] ?? null;
            $keyword = is_object($keyword) ? (array) $keyword : $keyword;
            if (!is_array($keyword)) {
                continue;
            }

            // The alias is the keyword's stable identity on both sides of the API.
            $alias = (string) ($keyword['alias'] ?? '');
            $name  = (string) ($keyword['name'] ?? '');
            if ($alias === '' || $name === '' || isset($byAlias[$alias])) {
                continue;
            }

            $byAlias[$alias] = [
                // externalId is the RC *keyword* id, not the id of the location-keyword
                // relation that wraps it. Keying on the wrapper id is what defeated the
                // duplicate check here and grew the table on every checkAccount call.
                'externalId' => ((int) ($keyword['id'] ?? 0)) ?: null,
                'name'       => $name,
                'alias'      => $alias,
                'hash'       => (string) ($keyword['hash'] ?? ''),
                'priority'   => (int) ($element['priority'] ?? PHP_INT_MAX),
            ];
        }

        uasort($byAlias, static fn(array $a, array $b) => $a['priority'] <=> $b['priority']);

        if ($maxAllowed > 0 && count($byAlias) > $maxAllowed) {
            $this->log(sprintf(
                'checkAccount returned %d SEO keywords but the subscription allows %d; keeping the %d highest-priority ones.',
                count($byAlias),
                $maxAllowed,
                $maxAllowed
            ), 'WARNING');
            $byAlias = array_slice($byAlias, 0, $maxAllowed, true);
        }

        return $byAlias;
    }

    /**
     * Mirror the account's SEO keywords into the local keywords table.
     *
     * RC is authoritative: aliases it reports are inserted or updated, aliases it no
     * longer reports are removed, and duplicate rows left behind by earlier plugin
     * versions are collapsed onto the newest row.
     *
     * @param array $keywords Raw `keywords.elements` from the API response
     * @return void
     * @throws Exception
     */
    public function saveKeywords(array $keywords): void
    {
        $dbManager = DatabaseManager::getInstance();

        if(empty($keywords)) {
            return;
        }

        $normalized = $this->normalizeRcKeywords($keywords);

        // An ads-only (or otherwise empty) payload is not an instruction to wipe the
        // local set — leave the table untouched rather than emptying the keyword picker.
        if (empty($normalized)) {
            return;
        }

        // Index the current rows by alias. Older versions of this method inserted a
        // fresh row on every call, so one alias can legitimately map to many rows here.
        $idsByAlias = [];
        $existingRows = $dbManager->db()
            ->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
            ->select(['id', 'alias'])
            ->get();

        foreach (($existingRows ?: []) as $existingRow) {
            $existingRow = is_object($existingRow) ? (array) $existingRow : $existingRow;
            $existingAlias = (string) ($existingRow['alias'] ?? '');
            if ($existingAlias === '') {
                continue;
            }
            $idsByAlias[$existingAlias][] = (int) ($existingRow['id'] ?? 0);
        }

        // Rows to drop: every alias RC no longer reports, plus the older copies of the
        // aliases it does report.
        $staleIds = [];
        foreach ($idsByAlias as $existingAlias => $ids) {
            rsort($ids);
            $staleIds = array_merge($staleIds, isset($normalized[$existingAlias]) ? array_slice($ids, 1) : $ids);
        }

        try {
            foreach ($normalized as $alias => $keywordData) {
                unset($keywordData['priority']); // ordering hint only, not a table column

                if (isset($idsByAlias[$alias])) {
                    $dbManager->db()
                        ->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
                        ->update()
                        ->set($keywordData)
                        ->where('alias', $alias)
                        ->get();
                    continue;
                }

                $dbManager->db()
                    ->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
                    ->insert()
                    ->set($keywordData)
                    ->get();
            }

            if (!empty($staleIds)) {
                $dbManager->db()
                    ->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
                    ->delete()
                    ->whereIn('id', array_values(array_unique($staleIds)))
                    ->get();
            }
        } catch (Exception $e) {
            throw new Exception(sprintf(
                /* translators: %1$s is the error message */
                esc_html__('Keyword insert failed. Error: %1$s', 'beyondseo'),
                esc_html($e->getMessage())
            ));
        }
    }

    /**
     * Fetch upselling URL from the external API for the selected plan.
     *
     * @param string $planSelected The selected plan for upselling
     * @return array|false Returns array with upsellUrl and status code, or false on failure
     * @throws Exception
     */
    public function fetchUpsellingUrl(string $planSelected): array|false
    {
        // Configure API request
        $this->setUrl('intention/upselling/');

        // Set security headers
        $payload = CoreHelper::generateCommonSecurityPayload([
            'planSelected' => $planSelected
        ]);


        //wp_die(json_encode(['token' => $this->getBearerToken(), 'payload' => $payload]));
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);

        // Make API request
        $response = $this->post($payload);

        // Return false if response is empty
        if (empty($response['content'])) {
            return false;
        }

        // Check if we have a successful response (200) and upsellUrl
        if (!empty($response['content']->upsellUrl)) {
            // Get existing upsell URLs object or create new one
            $existingUpsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
            if (!is_array($existingUpsellUrls)) {
                $existingUpsellUrls = [];
            }
            
            // Save the upsell URL for the specific plan
            $existingUpsellUrls[$planSelected] = $response['content'];
            update_option(BaseConstants::OPTION_UPSELL_URLS, $existingUpsellUrls);
            
            return [
                'upsellUrl' => $response['content']->upsellUrl
            ];
        }

        return false;
    }

    /**
     * Handle upselling process for the selected plan.
     *
     * @param string $planSelected The selected plan for upselling
     * @return array|false Returns upselling data or false on failure
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Exception
     * @throws CommunicationOptInDisabledException
     */
    public static function handleUpselling(string $planSelected): array|false
    {
        /** @var UserApiManager $instance This handles the API requests */
        $instance = UserApiManager::getInstance(bearerToken: true);
        $upsellingUrl = $instance->fetchUpsellingUrl($planSelected);
        /**
         * We should check next time customer if he upgraded or not
         */
        if ($upsellingUrl) {
            update_option(BaseConstants::OPTION_UPSELL_FORCE_CHECK, true, true);
        }
        return $upsellingUrl;
    }

    /**
     * Get stored upsell URL for a specific plan.
     *
     * @param string $planSelected The plan to get the upsell URL for
     * @return string|null Returns the upsell URL or null if not found
     */
    public static function getStoredUpsellUrl(string $planSelected): ?string
    {
        $upsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
        
        if (is_array($upsellUrls) && isset($upsellUrls[$planSelected])) {
            return $upsellUrls[$planSelected];
        }
        
        return null;
    }

    /**
     * Get all stored upsell URLs.
     *
     * @return array Returns array of all stored upsell URLs indexed by plan
     */
    public static function getAllStoredUpsellUrls(): array
    {
        $upsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
        
        return is_array($upsellUrls) ? $upsellUrls : [];
    }

    /**
     * Clear stored upsell URL for a specific plan.
     *
     * @param string $planSelected The plan to clear the upsell URL for
     * @return bool Returns true if cleared successfully
     */
    public static function clearStoredUpsellUrl(string $planSelected): bool
    {
        $upsellUrls = get_option(BaseConstants::OPTION_UPSELL_URLS, []);
        
        if (is_array($upsellUrls) && isset($upsellUrls[$planSelected])) {
            unset($upsellUrls[$planSelected]);
            return update_option(BaseConstants::OPTION_UPSELL_URLS, $upsellUrls);
        }
        
        return false;
    }

    /**
     * Fetch upsell magic link from the external API.
     *
     * @param string $paymentPeriod
     * @param string $countryCode
     * @param string $sessionId
     * @return array|false
     * @throws HttpApiException
     */
    public function fetchUpsellMagicLink(string $paymentPeriod, string $countryCode): array|false
    {
        $this->setUrl('intention/upselling-dc');

        $payload = CoreHelper::generateCommonSecurityPayload([
            'paymentPeriod' => $paymentPeriod,
            'countryShortCode'   => strtolower($countryCode),
        ]);

        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        $response = $this->post($payload);

        if (empty($response['content'])) {
            return false;
        }

        $content = $response['content'];

        // Recursively decode HTML entities for URLs to handle double encoding
        $voucherURL = $content->voucherURL ?? '';
        $voucherURL = html_entity_decode($voucherURL, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $registrationURL = $content->registrationURL ?? '';
        $registrationURL = html_entity_decode($registrationURL, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $couponCode = $content->couponCode ?? null;

        return [
            'voucherURL'      => $voucherURL,
            'registrationURL' => $registrationURL,
            'couponCode'      => $couponCode,
            's'               => $content->s ?? '',
            'meta'            => $content->meta ?? null,
        ];
    }

    /**
     * Get the client dashboard URL based on the current environment.
     *
     * @return string
     */
    public function getClientDashboardUrl(): string
    {
        $this->loadConfiguration();
        return sprintf('https://%s.rankingcoach.com', $this->configuration['prefix']);
    }
}
