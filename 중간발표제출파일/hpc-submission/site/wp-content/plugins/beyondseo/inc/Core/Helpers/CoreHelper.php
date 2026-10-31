<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcTaxonomyTrait;
use RankingCoach\Inc\Core\PluginSettings;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use ReflectionObject;
use Throwable;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class CoreHelper
 */
class CoreHelper {

	use RcTaxonomyTrait;
	use RcApiTrait;

	public const RC_NONCE_ACTION_NAME = 'rc_sec_nonce_admin_action';
	public const RC_NONCE_SALT = '7b9f9a0728a0c9185fb055a2df7bdba92951a9dbd1268dffb5782d866648c2ad';

    /**
     * List of option prefixes that should be managed (e.g. deleted on deactivation)
     */
    public const OPTION_PREFIXES = [
        'rankingcoach_',
        'rc_',
        'bseo_',
        'beyondseo_'
    ];

    /**
     * List of specific option keys that should NEVER be deleted even if they match a prefix
     */
    public const EXCLUDED_OPTION_KEYS = [
        BaseConstants::OPTION_PARTNER_INTEGRATION
    ];

    public const FALLBACK_WP_SUBSCRIPTION = 'dc_wp_free_int';
    public const RC_FREE_SUBSCRIPTIONS = [
        'seo_wp_free',      // IONOS free
        'radar_wp_test',    // DC test free
        'dc_wp_free_eu',    // DC free EU
        'dc_wp_free_us',    // DC free US
        'dc_wp_free_int',   // DC free International
        'ventraip_wp_free', // VentraIP free
    ];

    /**
     * The full set of "momentum" upsell packets. A customer may own any subset
     * of these at once (they are additive, not tiered), so the upsell/Upgrade
     * page must stay available until every one is owned.
     *
     * Keep in sync with the packets listed in views/upsell-ionos-page.php.
     */
    public const MOMENTUM_UPSELL_PACKETS = [
        'momentum_search',      // AI Search Manager (visibility)
        'momentum_reputation',  // AI Reputation Manager
        'momentum_marketing',   // AI Marketing Manager (social)
    ];


    /**
     * @return bool|string true or false
     */
    public static function isUpVersion($returnNameIfTrue = false): bool|string
    {
        // Check if the options table for a specific subscription on OPTION_RANKINGCOACH_SUBSCRIPTION
        $subscription = get_option( BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION );

        if ( empty( $subscription ) ) {
            return false;
        }
        
        // Handle different subscription data formats
        $planName = null;
        
        if ( is_array( $subscription ) ) {
            // Extract plan_name from array structure
            $planName = $subscription['plan_name'] ?? $subscription['plan'] ?? null;
        } elseif ( is_string( $subscription ) ) {
            // Check if it's JSON encoded
            $decoded = json_decode( $subscription, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $planName = $decoded['plan_name'] ?? $decoded['plan'] ?? null;
            } else {
                // Direct plan name as string
                $planName = $subscription;
            }
        }
        
        if ( empty( $planName ) ) {
            return false;
        }
        
        // Normalize plan name for comparison
        $planName = strtolower( trim( $planName ) );

        $freePlans = self::RC_FREE_SUBSCRIPTIONS;

        $isNotFree = !in_array($planName, $freePlans, true);

        if ($returnNameIfTrue && $isNotFree) {
            return $planName;
        }

        return $isNotFree;
    }

    /**
     * Get subscription information from WordPress options
     *
     * Retrieves and normalizes subscription data stored in the WordPress options table.
     * Handles multiple data formats including JSON strings, plain strings, and arrays.
     *
     * @return array|null The subscription data as an array, or null if not found
     */
    public static function getSubscriptionFromOptions(): ?array
    {
        $subscription = get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION);

        if (!empty($subscription)) {
            // If the subscription is stored as JSON string, decode it
            if (is_string($subscription)) {
                if (self::is_valid_json($subscription)) {
                    $subscription = json_decode($subscription, true);
                } else {
                    // If it's a string but not JSON, create a simple array with the value
                    $subscription = ['plan_name' => $subscription];
                }
            }

            // Ensure we have an array to return
            if (!is_array($subscription)) {
                // This case should ideally not be hit if the above logic is sound
                // but as a safeguard, wrap non-array data.
                $subscription = ['raw_data' => $subscription];
            }

            return $subscription;
        }

        return null;
    }

    /**
     * @return int The plan level (0-3), defaults to 0 if no subscription or unrecognized plan
     */
    public static function getPlanLvl(): int
    {
        $currentLevel = 0;

        $subscription = self::getSubscriptionFromOptions();
        if ($subscription && !empty($subscription['plan_name'])) {
            $currentPlanName = strtolower(trim($subscription['plan_name']));

            $currentLevel = match ($currentPlanName) {
                'seo_wp_free', 'ventraip_wp_free' => 0,
                'seo_wp_standard', 'seo_ai_small' => 1,
                'seo_ai_medium', 'seo_ai_medium2025', 'seo_wp_advanced', 'seo_wp_advanced2025' => 2,
                'seo_ai_social', 'seo_ai_large', 'seo_wp_pro', 'seo_wp_social', 'annual_360', 'monthly_360', '360_wp_test', 'monthly_360_eu', 'annual_360_eu', 'monthly_360_int', 'annual_360_int', 'monthly_360_us', 'annual_360_us' => 3,
                default => 0,
            };
        }
        return $currentLevel;
    }

    /**
     * @return bool true or false
     */
    public static function isPlanLvl3(): bool
    {
        $lever = self::getPlanLvl();
        return $lever === 3;
    }

    /**
     * Current subscription plan level, used by the channel-specific upsell views.
     *
     * Alias of {@see self::getPlanLvl()} (0=Free, 1=Standard, 2=Advanced, 3=Social),
     * kept so upsell templates can read the level under an explicit name.
     *
     * @return int
     */
    public static function getCurrentPlanLevel(): int
    {
        return self::getPlanLvl();
    }

    /**
     * Get the list of active subscription names for the current customer.
     *
     * Reads the subscription history from OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY,
     * iterates over every element and returns only those with status "ACTIVE".
     * A customer may hold several active subscriptions at the same time (e.g.
     * momentum_search, momentum_reputation, momentum_marketing).
     *
     * @return string[] Lower-cased, trimmed list of active subscription names.
     */
    public static function getActiveSubscriptions(): array
    {
        $history = get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY, null);

        if (is_string($history)) {
            $decoded = json_decode($history, true);
            $history = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : null;
        }

        if (!is_array($history) || empty($history['elements']) || !is_array($history['elements'])) {
            return [];
        }

        $active = array_filter(
            $history['elements'],
            static fn($element) => isset($element['status']) && strtoupper($element['status']) === 'ACTIVE'
        );

        return array_values(array_filter(array_map(
            static fn($element) => isset($element['subscriptionName'])
                ? strtolower(trim((string) $element['subscriptionName']))
                : '',
            $active
        )));
    }

    /**
     * Whether the customer owns a specific subscription packet.
     *
     * @param string $packet Packet identifier (e.g. "momentum_search").
     * @return bool
     */
    public static function hasActiveSubscription(string $packet): bool
    {
        if ($packet === '') {
            return false;
        }
        return in_array(strtolower(trim($packet)), self::getActiveSubscriptions(), true);
    }

    /**
     * Whether the customer already owns every "momentum" upsell packet.
     *
     * Used to decide when the Upgrade submenu should disappear: it stays visible
     * while at least one packet is still available to buy, and is only removed
     * once all of {@see self::MOMENTUM_UPSELL_PACKETS} are owned.
     *
     * @return bool
     */
    public static function ownsAllMomentumPackets(): bool
    {
        $active = self::getActiveSubscriptions();
        foreach (self::MOMENTUM_UPSELL_PACKETS as $packet) {
            if (!in_array($packet, $active, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether the customer is on the "momentum" product line, i.e. owns at least
     * one momentum packet.
     *
     * @return bool
     */
    public static function isOnMomentumPlan(): bool
    {
        return (bool) array_intersect(self::MOMENTUM_UPSELL_PACKETS, self::getActiveSubscriptions());
    }

    /**
     * Whether the customer still has something to upgrade to. This is the single
     * gate used across the plugin (Upgrade submenu, toolbar, upsell notification,
     * admin action link, iframe plan level), so all channel/product rules live here.
     *
     * - Momentum (multi-packet) customers: packets are additive, so keep the page
     *   available until EVERY packet is owned, and hide it once all are owned.
     *   Channel-independent and independent of the backend upgradable-plans list
     *   (which is unreliable for momentum: empty for IONOS, and empty when one
     *   packet is flagged as the "highest price" subscription).
     * - IONOS without momentum packets: free/Radar customers can still buy momentum
     *   packets, so the page shows for them; legacy paid IONOS plans (e.g.
     *   seo_wp_advanced) have nothing to buy here, so it stays hidden.
     * - Legacy / direct customers: unchanged — the backend dictates availability.
     *
     * @return bool true if upgrade plans are available, false otherwise
     */
    public static function hasUpgradePlans(): bool
    {
        if ( self::isOnMomentumPlan() ) {
            return ! self::ownsAllMomentumPackets();
        }

        if ( self::isIonos() ) {
            // Free/Radar IONOS customers can still purchase momentum packets;
            // legacy paid plans have no further self-service upgrade.
            return self::getCurrentPlanLevel() === 0;
        }

        $upgradePlans = get_option( BaseConstants::OPTION_RANKINGCOACH_UPGRADE_PLANS, null );

        return ! is_null( $upgradePlans ) && is_array( $upgradePlans );
    }

    public static function isIonos(): bool
    {
        return get_option('bseo_channel') === 'ionos';
    }

    public static function isOnboarded(): bool
    {
        return (bool) get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_COMPLETED, false)
            || WordpressHelpers::isOnboardingCompleted();
    }

    /**
     * Build the `pluginInformation` payload exposed on the `rankingCoachReactData`
     * window object.
     *
     * This is the single, Symfony-free source of truth for the plugin information the
     * React app (metabox, settings and connect/upsell areas) reads from the window.
     * Every value comes from WordPress options / `inc` helpers — it never touches the
     * app/src (DDD) layer. The shape mirrors the fields the React components actually
     * consume from the (deprecated) pluginInformation REST response.
     *
     * @return array
     */
    public static function getPluginInformation(): array
    {
        $settings = SettingsManager::instance();

        // Boolean plugin settings the React "General settings" screen reads. Defaults
        // mirror the read sites elsewhere in the plugin (e.g. PostEventsManager,
        // CoreHelper::manageHeartbeatService) for the rare case the option is unset.
        $reactSettingKeys = [
            'allow_seo_optimiser_on_saved_posts'  => false,
            'allow_sync_keywords_to_rankingcoach' => false,
            'enable_wp_cron_service'              => false,
            'enable_broken_link_checker_job'      => false,
            'beyondseo_comm_opt_in'               => false,
            'disable_wp_heartbeat_service'        => true,
            'open_rc_dashboard_in_new_tab'        => false,
        ];
        $pluginSettings = [];
        foreach ($reactSettingKeys as $key => $default) {
            $pluginSettings[$key] = (bool) $settings->get_option($key, $default);
        }

        $allowedCountries = $settings->get_option('allowed_countries', []);

        // Map the stored subscription code (e.g. `seo_wp_free`, `annual_360`) to the
        // display name the React app expects for `rcSubscriptionName` (e.g. "Free",
        // "360"). Mirrors the mapping the pluginInformation REST endpoint uses.
        $subscription = self::getSubscriptionFromOptions();
        $subscriptionCode = is_array($subscription)
            ? ($subscription['plan_name'] ?? $subscription['plan'] ?? '')
            : (string) $subscription;
        $subscriptionCode = strtolower(trim((string) $subscriptionCode));
        $rcSubscriptionName = match ($subscriptionCode) {
            'seo_ai_small', 'seo_wp_standard' => 'Standard',
            'seo_ai_medium', 'seo_ai_medium2025', 'seo_wp_advanced', 'seo_wp_advanced2025' => 'Advanced',
            'seo_ai_large', 'seo_wp_pro' => 'Pro',
            'seo_ai_social', 'seo_wp_social' => 'Social',
            'annual_360', 'monthly_360', '360_wp_test', '360_wp_test_annual',
            'monthly_360_eu', 'annual_360_eu', 'monthly_360_int', 'annual_360_int',
            'monthly_360_us', 'annual_360_us' => '360',
            default => 'Free',
        };

        return [
            'pluginData' => [
                'version'  => defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : null,
                // Cast to object so it serialises as a JSON object (not an array) for the
                // React `Record<SettingsKeys, boolean>` consumers.
                'settings' => (object) $pluginSettings,
                'website'  => [
                    'settings' => [
                        'homeUrl'          => get_home_url(),
                        'adminEmail'       => get_bloginfo('admin_email'),
                        'searchEngineVisibility' => (bool) get_option('blog_public', 1),
                        'allowedCountries' => (object) ((array) $allowedCountries),
                    ],
                ],
                'setupData' => [
                    'isPluginOnboarded'      => WordpressHelpers::isInternalOnboardingCompleted(),
                    'isApplicationOnboarded' => WordpressHelpers::isExternalOnboardingCompleted(),
                ],
                'setupWizard' => (object) $settings->get_option('setup_wizard', PluginSettings::instance()->getDefault('setup_wizard')),
            ],
            'rcSubscriptionName'  => $rcSubscriptionName,
            'rcRemainingKeywords' => (int) get_option(BaseConstants::OPTION_SYNC_KEYWORDS_REMAINS_KEYWORDS, 99999),
            'rcAccountId'         => get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, null),
            'rcProjectId'         => get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, null),
            'userData'            => null,
        ];
    }

    /**
     * Installed plugin version: the RANKINGCOACH_VERSION constant (read from the plugin
     * header at bootstrap), falling back to the stored option when the constant is not
     * defined yet. Empty string when neither is available.
     *
     * @return string
     */
    public static function getPluginVersion(): string
    {
        $version = defined('RANKINGCOACH_VERSION')
            ? RANKINGCOACH_VERSION
            : get_option(BaseConstants::OPTION_PLUGIN_VERSION, '');

        return is_scalar($version) ? trim((string) $version) : '';
    }

    public static function buildUtmUrl(
        string $url,
        string $utm_source = 'wordpress_plugin',
        ?string $utm_content = null,
        string $utm_medium = 'plugin',
        string $utm_campaign = 'beyondseo',
        bool $raw = false
    ): string {
        $params = [
            'utm_source'   => $utm_source,
            'utm_medium'   => $utm_medium,
            'utm_campaign' => $utm_campaign,
        ];

        if ( $utm_content !== null ) {
            $params['utm_content'] = $utm_content;
        }

        // $raw for non-display contexts (e.g. wp_localize_script) where esc_url()'s
        // entity-encoded ampersands would corrupt the query string.
        return $raw
            ? esc_url_raw( add_query_arg( $params, $url ) )
            : esc_url( add_query_arg( $params, $url ) );
    }

	/**
	 * Determines if the current environment is a local development environment.
	 *
	 * @return bool True if the environment is recognized as localhost, false otherwise.
	 */
	public static function is_localhost(): bool {
		// List of common local IP addresses and hostnames.
		$local_addresses = [
			'127.0.0.1',    // IPv4 loopback address.
			'::1',          // IPv6 loopback address.
			'localhost',    // Localhost hostname.
		];

		// Get the remote address or default to an empty string if undefined.
		$remote_addr = self::get_remote_addr();

		// Check if the remote address matches any local address or hostname.
		return in_array( $remote_addr, $local_addresses, true );
	}

	/**
	 * Retrieves the IP address from which the user is accessing the current page.
	 *
	 * Attempts to get the IP address using multiple methods for compatibility across various server configurations.
	 *
	 * @return string The validated IP address, or an empty string if unavailable.
	 */
	public static function get_remote_addr(): string {
		// Attempt to retrieve a valid IP address from the server variables.
		$ip = WordpressHelpers::sanitize_input( 'SERVER', 'REMOTE_ADDR' );

		// Fallback to environment variables if INPUT_SERVER is unavailable on this host.
		if ( ! $ip ) {
			$ip = WordpressHelpers::sanitize_input( 'ENV', 'REMOTE_ADDR' );
		}

        if ( ! $ip ) {
            $remote_addr = WordpressHelpers::sanitize_input('SERVER', 'REMOTE_ADDR', validate: FILTER_VALIDATE_IP);

            // Final validation as IP
            if ( $remote_addr ) {
                $ip = filter_var( $remote_addr, FILTER_VALIDATE_IP );
            }
        }
		// Return the IP address if found, or an empty string if unavailable.
		return $ip ?: '';
	}

	/**
	 * Extracts attributes from a given HTML element string.
	 *
	 * @param string $elem The HTML element as a string.
	 *
	 * @return array An associative array
	 * where the keys are attribute names and the values are attribute values or null if the attribute has no value.
	 */
	public static function extract_html_attributes( string $elem ): array {
		$regex = '#([^\s=]+)\s*=\s*(\'[^<\']*\'|"[^<"]*")#';
		preg_match_all( $regex, $elem, $attributes, PREG_SET_ORDER );

		$new       = [];
		$remaining = $elem;
		foreach ( $attributes as $attribute ) {
			$val                  = substr( $attribute[2], 1, - 1 );
			$new[ $attribute[1] ] = $val;
			$remaining            = str_replace( $attribute[0], '', $remaining );
		}

		// Chop off the tag name.
		/** @noinspection RegExpSimplifiable */
		$remaining = preg_replace( '/<[^\s]+/', '', $remaining, 1 );
		// Check for empty attributes without values.
		/** @noinspection RegExpRedundantClassElement */
		$regex = '/([^<][\w\d:_-]+)[\s>]/i';
		preg_match_all( $regex, $remaining, $attributes, PREG_SET_ORDER );
		foreach ( $attributes as $attribute ) {
			$new[ trim( $attribute[1] ) ] = null;
		}

		return $new;
	}

	/**
	 * Generate an HTML attribute string for an array.
	 *
	 * @param array $attributes Contains a key/value pair to generate a string.
	 * @param string $prefix If you want to append a prefix before every key.
	 *
	 * @return bool|string
	 */
	public static function html_attributes_to_string( array $attributes = [], string $prefix = '' ): bool|string {

		// Early Bail!
		if ( empty( $attributes ) ) {
			return false;
		}

		$out = '';
		foreach ( $attributes as $key => $value ) {
			if ( true === $value || false === $value ) {
				$value = $value ? 'true' : 'false';
			}

			$out .= ' ' . esc_html( $prefix . $key );
			if ( null === $value ) {
				continue;
			}
			$out .= sprintf( '="%s"', esc_attr( $value ) );
		}

		return $out;
	}

	/**
	 * Check if the string contains the given value.
	 *
	 * @param string $needle The substring to search for.
	 * @param string $haystack The string to search.
	 *
	 * @return bool
	 */
	public static function string_contains( string $needle, string $haystack ): bool {
		return self::string_is_non_empty( $needle ) && str_contains( $haystack, $needle );
	}

	/**
	 * Validates whether the passed variable is a non-empty string.
	 *
	 * @param mixed $variable The variable to validate.
	 *
	 * @return bool Whether the passed value is a non-empty string.
	 */
	public static function string_is_non_empty( mixed $variable ): bool {
		return is_string( $variable ) && '' !== $variable;
	}

	/**
	 * Check if the string starts with the given value.
	 *
	 * @param string $action
	 * @param int|string|null $time
	 *
	 * @return string
	 */
	public static function rc_custom_nonce( string $action, int|string|null $time = null ): string {
		$key = self::RC_NONCE_SALT;
		if ( defined( 'NONCE_SALT' ) ) {
			$key = NONCE_SALT;
		}

		// Include time in creating a nonce algorithm
		if ( ! $time ) {
			$time = time();
		}
		$action = $action . '_' . $time;

		// Generate and return an SHA-256 hash combining the action and the salt.
		return hash( 'sha256', $action . $key );
	}

	/**
	 * Pretty print data for debugging.
	 *
	 * @param mixed $data
	 * @param int $indent
	 * @param int $recursionLevel
	 * @param bool $htmlType
	 * @param bool $builtIn
	 *
	 * @return string|null
	 */
	public static function pre_print(
		mixed $data,
		int $indent = 0,
		int $recursionLevel = 0,
		bool $htmlType = false,
		bool $builtIn = false
	): ?string {
		if($builtIn) {
			echo '<pre>' . esc_html( var_export( $data, true ) ) . '</pre>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			return null;
		}
		$newLine = $htmlType ? '<br>' : "\n";
		$maxRecursion = 10;
		$indentStr = str_repeat('    ', $indent);
		$output = '';

		switch (true) {
			case is_null($data):
				return $indentStr . "null$newLine";

			case is_bool($data):
				return $indentStr . ($data ? 'true' : 'false' ) . "$newLine";

			case is_float($data):
			case is_int($data):
				return $indentStr . $data . "$newLine";

			case is_string($data):
				if (strlen($data) > 100) {
					$data = substr($data, 0, 97) . '...';
				}
				return $indentStr . '"' . htmlspecialchars($data) . "\"$newLine";

			case is_array($data):
				if ($recursionLevel >= $maxRecursion) {
					return $indentStr . "*MAX RECURSION*$newLine";
				}

				if (empty($data)) {
					return $indentStr . "[]$newLine";
				}

				$output .= $indentStr . "[$newLine";
				foreach ($data as $key => $value) {
					$output .= $indentStr . '    ' . $key . ' => ';
					$output .= ltrim(CoreHelper::pre_print($value, $indent + 1, $recursionLevel + 1, $htmlType));
				}
				$output .= $indentStr . "]$newLine";
				return $output;

			case is_object($data):
				if ($recursionLevel >= $maxRecursion) {
					return $indentStr . "*MAX RECURSION*$newLine";
				}

				$className = get_class($data);
				$output .= $indentStr . $className . ' {' . $newLine;

				$reflection = new ReflectionObject($data);
				$properties = $reflection->getProperties();

				foreach ($properties as $property) {
                    $property->setAccessible(true);
					$propertyName = $property->getName();
					$propertyValue = $property->getValue($data);
					$output .= $indentStr . '    ' . $propertyName . ' => ';
					$output .= ltrim(self::pre_print($propertyValue, $indent + 1, $recursionLevel + 1, $htmlType));
				}
				$output .= $indentStr . '}' . $newLine;
				return $output;

			case is_resource($data):
				return $indentStr . 'Resource(' . get_resource_type($data) . ")$newLine";

			default:
				return $indentStr . gettype($data) . "$newLine";
		}
	}

	/**
	 * Generates a unique installation ID for the plugin.
	 * @return string
	 */
	public static function generate_installation_id(): string {
		return wp_generate_password( 48, false );
	}

	/**
	 * Sanitizes content to ensure it is compatible with JSON format.
	 *
	 * @param string $content The input content to sanitize.
	 * @return string The sanitized content.
	 * @throws Exception If the content cannot be encoded as JSON.
	 */
	public static function sanitize_content(string $content): string
	{
		if(!$content) {
			return '';
		}

		// Ensure the content is valid UTF-8
		$content = self::ensure_utf8($content);
		// Remove invalid Unicode characters
		$content = self::remove_invalid_unicode($content);
		// Remove unsupported JSON control characters
		return preg_replace('/[\x00-\x1F\x7F]/u', '', $content);
	}

	/**
	 * Removes invalid Unicode characters from a string.
	 *
	 * @param string $string The input string to process.
	 * @return string The string with invalid Unicode characters removed.
	 */
	public static function remove_invalid_unicode(string $string): string
	{
		return preg_replace('/[^\x{09}\x{0A}\x{0D}\x{20}-\x{7E}\x{A0}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $string);
	}

	/**
	 * Ensures a string is encoded in valid UTF-8.
	 *
	 * @param string $string The input string to process.
	 * @return string The UTF-8 encoded string.
	 */
	public static function ensure_utf8(string $string): string
	{
		return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
	}

	/**
	 * Escapes special JSON characters in a string.
	 *
	 * @param string $string The input string to escape.
	 * @return string The escaped string.
	 */
	public static function escape_json_string(string $string): string
	{
		return addslashes($string);
	}

	/**
	 * Validates if a string is JSON-compatible.
	 *
	 * @param string $string The string to validate.
	 * @return bool True if the string is JSON-compatible, false otherwise.
	 */
	public static function is_valid_json(string $string): bool
	{
		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;
	}

    /**
     * Retrieves the settings of the module.
     * @param $key
     * @return mixed The setting value.
     */
	public static function settings($key): mixed {
        return SettingsManager::instance()->get_option($key);
	}

	/**
	 * Sanitizes dollar signs in input text for regex operations.
	 *
	 * @param  string $text Input text requiring sanitization.
	 * @return string      Text with escaped dollar signs.
	 */
	public static function sanitize_regex_pattern( string $text ): string {
		static $processedStrings = [];
		$cacheKey = md5($text);

		if (array_key_exists($cacheKey, $processedStrings)) {
			$result = $processedStrings[$cacheKey];
		} else {
			$result = str_replace( '$', '\$', $text );
		}

		$processedStrings[ $text ] = $result;

		return $result;
	}

	/**
	 * Performs pattern-based text substitution with cached results.
	 *
	 * @param string $searchExp    Expression to find.
	 * @param string $replaceText  Text for substitution.
	 * @param string $content      Target content.
	 * @return string             Modified content.
	 */
	public static function preg_replace_cached( string $searchExp, string $replaceText, string $content ): string {
		if ( empty( $content ) ) {
			return $content;
		}

		$transformWithCache = function ( $exp, $new, $old ) {
			static $memoryCache = [];

			$cacheIdentifier = md5( $exp . $new . $old );

			if ( array_key_exists( $cacheIdentifier, $memoryCache ) ) {
				return $memoryCache[ $cacheIdentifier ];
			}

			$safeReplacement = self::sanitize_regex_pattern( $new );
			$memoryCache[ $cacheIdentifier ] = preg_replace( $exp, $safeReplacement, $old );

			return $memoryCache[ $cacheIdentifier ];
		};

        return $transformWithCache( $searchExp, $replaceText, $content );
	}

	/**
	 * Processes text by converting HTML character references to their corresponding characters.
	 *
	 * @param string $input Text containing potential HTML entities.
	 * @return string Text with HTML entities transformed to regular characters.
	 */
	public static function decode_html_entities( string $input ): string {
		static $processedResults = [];

		$cacheKey = md5($input);

		if (array_key_exists($cacheKey, $processedResults)) {
			return $processedResults[$cacheKey];
		}

		$intermediateText = function(string $text) {
			$pattern = '/&nbsp;/';
			$replacement = ' ';
			return self::preg_replace_cached($pattern, $replacement, $text);
		};

		$cleanedText = $intermediateText($input);

		$processedResults[$cacheKey] = html_entity_decode(
			$cleanedText,
			ENT_QUOTES
		);

		return $processedResults[$cacheKey];
	}

	/**
	 * Returns the string after it is encoded with htmlspecialchars().
	 *
	 * @param string $rawText
	 * @param string $charEncoding
	 *
	 * @return string         The encoded string.
	 */
	public static function encode_output_html( string $rawText, string $charEncoding = '' ): string {
		$sanitizedOutput = '';

		$validateInput = function($input) {
			return is_string($input) && strlen(trim($input)) > 0;
		};

		$determineEncoding = function($userEncoding) {
			if (!empty($userEncoding)) {
				return $userEncoding;
			}

			$defaultOptions = [
				'UTF-8',
				'ISO-8859-1',
				self::get_charset()
            ];

			foreach ($defaultOptions as $option) {
				if (!empty($option)) {
					return $option;
				}
			}

			return 'UTF-8';
		};

		if ($validateInput($rawText)) {
			$selectedEncoding = $determineEncoding($charEncoding);
			$htmlFlags = ENT_COMPAT | ENT_HTML401;
			$sanitizedOutput = htmlspecialchars(
				$rawText,
				$htmlFlags,
				$selectedEncoding,
				false
			);
		}

		return $sanitizedOutput;
	}

	/**
	 * Determines system encoding configuration
	 *
	 * @return string|null Encoding format
	 */
	public static function get_charset(): ?string {
		$encodingCache = function() {
			$storedValue = null;
			return function() use (&$storedValue) {
				if (is_null($storedValue)) {
					$configValue = get_option('blog_charset');
					$storedValue = empty($configValue) ? 'UTF-8' : $configValue;
				}
				return $storedValue;
			};
		};

		static $retrieveEncoding = null;

		if (!$retrieveEncoding) {
			$retrieveEncoding = $encodingCache();
		}

		return $retrieveEncoding();
	}

	/**
	 * Processes and validates input data for database storage.
	 *
	 * @param  mixed $rawInput Raw data for processing.
	 * @return mixed Processed and safe data for storage.
	 */
	public static function clean_data_for_storage( mixed $rawInput ): mixed {
		$processInner = function($content) use (&$processInner) {
			$dataType = gettype($content);

			if ($dataType === 'array') {
				$cleanList = [];
				foreach ($content as $item) {
					$cleanList[] = $processInner($item);
				}
				return $cleanList;
			}

			if ($dataType === 'string') {
				$steps = [
					fn($str) => trim($str),
					fn($str) => wp_check_invalid_utf8($str),
					fn($str) => wp_strip_all_tags($str),
					fn($str) => self::decode_html_entities($str),
					fn($str) => self::encode_output_html($str)
				];

				return array_reduce($steps, fn($carry, $fn) => $fn($carry), $content);
			}

			$typeConversions = [
				'boolean' => fn($val) => (bool) $val,
				'integer' => fn($val) => intval($val),
				'double'  => fn($val) => floatval($val)
			];

			return isset($typeConversions[$dataType])
				? $typeConversions[$dataType]($content)
				: false;
		};

		return $processInner($rawInput);
	}

	/**
	 * Prepares the title/description before returning it.
	 *
	 * @param string $value       The value.
	 *
	 * @return string                The sanitized value.
	 */
	public static function prepare_string( string $value ): string {

		$value = self::decode_html_entities( $value );
		$value = self::encode_output_html( $value );
		$value = wp_strip_all_tags( strip_shortcodes( $value ) );
		$value = self::decode_html_entities( $value );

		// Trim internal and external whitespace.
		$value = preg_replace( '/[\s]+/u', ' ', trim( $value ) );

		return apply_filters( 'beyondseo_localization', $value );
	}

	/**
	 * Determines current pagination position
	 *
	 * @return int Current position in pagination sequence
	 */
	public static function determine_page_number(): int {
		$calculatePosition = function($paramName) {
			$rawValue = get_query_var($paramName);
			return (is_numeric($rawValue) && $rawValue > 0) ? (int) $rawValue : 0;
		};

		$result = 0;
		$possibleParams = ['page', 'paged'];

		foreach ($possibleParams as $currentParam) {
			$position = $calculatePosition($currentParam);
			if ($position > $result) {
				$result = $position;
			}
		}

		return max(1, $result);
	}

	/**
	 * Converts input to JSON string with precision handling for floats.
	 * This approach ensures accurate decimal representation by recursively replacing floats
	 * with placeholders before encoding and then substituting them back as numbers in the final JSON string.
	 *
	 * @param mixed $content Content to encode.
	 * @param int $options Encoding options for wp_json_encode().
	 * @return string JSON formatted string.
	 */
	public static function convert_to_json_string(mixed $content, int $options = 0): string
	{
		// Step 1: Recursively replace float values with unique string placeholders.
		$contentWithPlaceholders = self::replace_floats_with_placeholders($content);

		// Step 2: Encode the data structure. The placeholders will be encoded as JSON strings.
		$jsonString = wp_json_encode($contentWithPlaceholders, $options);

		// Step 3: Use regex to find the placeholders (including quotes) and replace them
		// with the raw numeric value, effectively converting them back to JSON numbers.
		$finalJson = preg_replace(
			'/"##BEYONDSEO_FLOAT##(-?[\d.]+)##BEYONDSEO_FLOAT_END##"/',
			'\1',
			$jsonString
		);

		return $finalJson;
	}

	/**
	 * Recursively traverses data to replace float values with a string placeholder.
	 * This allows preserving float precision during JSON encoding without altering global settings.
	 *
	 * @param mixed $data The data to traverse.
	 * @return mixed Data with floats replaced by placeholders.
	 */
	private static function replace_floats_with_placeholders(mixed $data): mixed
	{
		if (is_float($data)) {
			// Format the float as a string with high precision.
			// 'F' specifier prevents scientific notation. 17 decimal places is standard for double precision.
			$floatAsString = sprintf('%.17F', $data);

			// Trim trailing zeros from the fractional part for a cleaner representation.
			// e.g., "1.2000..." becomes "1.2".
			$floatAsString = rtrim($floatAsString, '0');

			// Ensure that numbers like "1.0" don't become "1."
			if (str_ends_with($floatAsString, '.')) {
				$floatAsString .= '0';
			}

			// Return the formatted number wrapped in a unique placeholder.
			return '##BEYONDSEO_FLOAT##' . $floatAsString . '##BEYONDSEO_FLOAT_END##';
		}

		if (is_array($data)) {
			// If it's an array, recursively process each element.
			foreach ($data as $key => $value) {
				$data[$key] = self::replace_floats_with_placeholders($value);
			}
		} elseif (is_object($data)) {
			// If it's an object, recursively process each property.
			foreach ($data as $key => $value) {
				$data->{$key} = self::replace_floats_with_placeholders($value);
			}
		}

		return $data;
	}

	/**
     * Escapes input for use in a regular expression pattern.
     *
     * @param string $input         Input string.
     * @param string $boundaryChar Boundary character for escaping.
	 */
	public static function escape_pattern_regex( string $input, string $boundaryChar = '/'): string {
		static $patternCache = [];

		$processInputForPattern = function($text, $marker) {
			return preg_quote($text, $marker);
		};

		$inputKey = (string) $input;

		if (array_key_exists($inputKey, $patternCache)) {
			$processedPattern = $patternCache[$inputKey];
		} else {
			$processedPattern = $processInputForPattern($inputKey, $boundaryChar);
			$patternCache[$inputKey] = $processedPattern;
		}

		return $processedPattern;
	}

    /**
     * Sanitizes a user-related string, ensuring it is not an email.
     *
     * @param mixed $value The input value to sanitize.
     * @param string $fallback The fallback value if input is invalid.
     * @return string The sanitized user string or fallback.
     */
    public static function safe_user_string($value, $fallback = '') {
        $value = sanitize_text_field(trim((string)$value));
        return ($value && !is_email($value)) ? $value : $fallback;
    }

	/**
	 * Builds the flow guard context data for API requests.
	 *
	 * This method gathers channel detection information, flow state,
	 * and related metadata to provide context about the plugin's
	 * registration and activation flow.
	 *
	 * @return array Flow guard context data
     * @throws Throwable If required classes are missing or errors occur during processing.
	 */
	public static function buildFlowGuardContext(): array
	{
        try {
            $store = new OptionStore();
            $resolver = new ChannelResolver($store);
            [$channel, $proofs] = $resolver->detectChannel();
            $flowState = $store->getFlowState();

            return [
                'channel' => $channel ?: 'none',
                'detection_method' => (is_array($proofs) && !empty($proofs)) ?  implode(', ', $proofs) : 'none',
                'flow_state' => [
                    'registered' => $flowState->registered ?? false,
                    'email_verified' => $flowState->emailVerified ?? false,
                    'activated' => $flowState->activated ?? false,
                    'onboarded' => $flowState->onboarded ?? false,
                ]
            ];
        } catch (Throwable $e) {
            return [
                'channel' => 'none',
                'detection_method' => 'Classes missing',
                'flow_state' => [
                    'registered' => false,
                    'email_verified' => false,
                    'activated' => false,
                    'onboarded' => false,
                ],
                'error' => $e->getMessage(),
            ];
        }
	}

    /**
     * Generates common security payload for API requests.
     *
     * @param array $additionalData Optional additional data to merge with base payload
     * @return array Common payload structure for authentication and security
     * @throws Throwable
     */
	public static function generateCommonSecurityPayload(array $additionalData = []): array
	{
        // Remove changeHistory if exists to avoid throwing error on API side, due to changeHistory type mismatch
        if(isset($additionalData['geoaddress']['city']['changeHistory']))
            unset($additionalData['geoaddress']['city']['changeHistory']);
        if(isset($additionalData['geoaddress']['prefilledAddress']))
            unset($additionalData['geoaddress']['prefilledAddress']);

		$siteUrl = sanitize_url(get_option('siteurl'));
        if(wp_get_environment_type() !== 'production' && str_contains($siteUrl, 'local') !== false) {
            $siteUrl = RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST ?? $siteUrl;
        }

        $user_agent = WordpressHelpers::sanitize_input('SERVER', 'HTTP_USER_AGENT');
        $user_agent = $user_agent ?: 'RankingCoach-WP-Plugin';

        $basePayload = [
			// Site identification
			'siteUrl' => $siteUrl,
			'siteDomain' => wp_parse_url($siteUrl, PHP_URL_HOST),
			'siteTitle' => get_bloginfo('name'),

			// Environment context
			'environment' => wp_get_environment_type(),
			'locale' => WordpressHelpers::get_wp_locale(),
			'language' => WordpressHelpers::getDefaultLanguage(),
			'timezone' => wp_timezone_string(),
			
			// Installation context
			'installationId' => get_option(BaseConstants::OPTION_INSTALLATION_ID, ''),
			'installationDate' => get_option(BaseConstants::OPTION_INSTALLATION_DATE, ''),

			// flow-guard channel context
			'channelContext' => json_encode(self::buildFlowGuardContext()),

			// Version context
            'pluginBrandSlug' => RANKINGCOACH_BRAND_SLUG,
			'pluginVersion' => get_option(BaseConstants::OPTION_PLUGIN_VERSION, ''),
			'dbVersion' => get_option(BaseConstants::OPTION_DB_VERSION, ''),
			'apiVersion' => get_option(BaseConstants::OPTION_API_VERSION, ''),
			'wpVersion' => get_bloginfo('version'),
			'phpVersion' => PHP_VERSION,
			
			// Account context
			'accountId' => get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, null),
			'projectId' => get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, null),
			'locationId' => get_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID, null),

			// Request metadata
			'timestamp' => time(),
			'requestId' => wp_generate_uuid4(),
			'userAgent' => $user_agent,
			'clientIp' => self::get_remote_addr(),

            // Reseller info
            'resellerAccount' => (bool)get_option(BaseConstants::OPTION_IS_RESELLER_ACCOUNT),
            'locationSetupSettings' => !empty(get_option(BaseConstants::OPTION_LOCATION_SETUP_SETTINGS, null)) ? get_option(BaseConstants::OPTION_LOCATION_SETUP_SETTINGS) : null,
            'pageKeywordsData' => base64_encode(serialize(get_option(BaseConstants::OPTION_USE_PLUGIN_PAGE_KEYWORDS_DATA, []))),
		];

        $store = new OptionStore();
        $channel = $store->getChannel();

        if ($channel) {
            $basePayload['channel'] = $channel;
        }

        return array_merge($basePayload, $additionalData);
	}

	/**
	 * Generates minimal security payload for lightweight API requests.
	 * 
	 * @param array $additionalData Optional additional data to merge with base payload
	 * @return array Minimal payload structure for basic authentication
	 */
	public static function generateMinimalSecurityPayload(array $additionalData = []): array
	{
		$basePayload = [
			'siteUrl' => sanitize_url(get_option('siteurl')),
			'adminEmail' => sanitize_email(get_option('admin_email')),
			'environment' => wp_get_environment_type(),
			'installationId' => get_option(BaseConstants::OPTION_INSTALLATION_ID, ''),
			'timestamp' => time(),
		];

		return array_merge($basePayload, $additionalData);
	}

	/**
	 * Generates enhanced security headers for API requests with HMAC-SHA256 signature.
	 *
	 * Implements 7 security layers:
	 * 1. Environment Fingerprint - Hash from WordPress environment
	 * 2. Unique Nonce - random_bytes(16) + microtime for one-time use
	 * 3. Key Derivation - accessToken → dateKey → regionKey → signingKey
	 * 4. Canonical Request - Deterministic format of all signed data
	 * 5. Content Hash - SHA256 of payload JSON
	 * 6. Timestamp - Unix timestamp for time-based validation
	 * 7. HMAC-SHA256 Signature - Final cryptographic signature
	 *
	 * @param string|null $accessToken The access token for authentication
	 * @param array $userPayload The payload data to include in signature
	 * @param string $httpMethod The HTTP method (GET, POST, etc.)
	 * @param string $endpoint The API endpoint being called
	 * @return array Security headers array
	 */
	public static function generateSecurityHeaders(?string $accessToken = null, array $userPayload = [], string $httpMethod = 'POST', string $endpoint = ''): array
	{
		// Return empty headers if no access token provided
		if (empty($accessToken)) {
			return [];
		}

		// ─────────────────────────────────────────────────────────────────────
		// Sub-function: Generate environment fingerprint
		// Fingerprint: Creates a unique identifier using WordPress security salts
		// (designed for hashing entropy) and public site data for installation verification.
		// ─────────────────────────────────────────────────────────────────────
		$generateFingerprint = function(): string {
			$components = [];

			// WordPress security salts (designed for adding entropy to hashing)
			// These are unique per installation and don't interfere with core WP functions
			if (defined('AUTH_SALT')) {
				$components[] = AUTH_SALT;
			}
			if (defined('SECURE_AUTH_SALT')) {
				$components[] = SECURE_AUTH_SALT;
			}

			// Public WordPress data
			$components[] = get_site_url();
			$components[] = get_bloginfo('version');
			$components[] = get_locale();

			// Plugin's own installation ID
			$components[] = get_option(BaseConstants::OPTION_INSTALLATION_ID, '');

			// Hash to create fingerprint
			return hash('sha256', implode('|', array_filter($components)));
		};

		// ─────────────────────────────────────────────────────────────────────
		// Sub-function: Generate secure nonce
		// Creates a unique one-time use value using random bytes and microtime
		// ─────────────────────────────────────────────────────────────────────
		$generateNonce = function(): string {
			try {
				$randomBytes = random_bytes(16);
				$microtime = microtime(true);
				return bin2hex($randomBytes) . '-' . dechex((int)($microtime * 1000000));
			} catch (Exception $e) {
				// Fallback if random_bytes fails
				return hash('sha256', uniqid('', true) . wp_rand() . microtime(true));
			}
		};

		// ─────────────────────────────────────────────────────────────────────
		// Sub-function: Derive signing key (date-based key derivation)
		// Implements: accessToken → dateKey → regionKey → signingKey
		// Never sends the raw token, only derived keys
		// ─────────────────────────────────────────────────────────────────────
		$deriveSigningKey = function(string $token, string $dateStamp, string $region = 'global'): string {
			// Step 1: Create date key from access token
			$dateKey = hash_hmac('sha256', $dateStamp, 'RC1' . $token, true);

			// Step 2: Create region key from date key
			$regionKey = hash_hmac('sha256', $region, $dateKey, true);

			// Step 3: Create signing key from region key
            return hash_hmac('sha256', 'rc_request', $regionKey, true);
		};

		// ─────────────────────────────────────────────────────────────────────
		// Sub-function: Build canonical request
		// Creates a deterministic string format of all signed data
		// ─────────────────────────────────────────────────────────────────────
		$buildCanonicalRequest = function(array $parts): string {
			$lines = [];

			// Sort parts by key for deterministic ordering
			ksort($parts);

			foreach ($parts as $key => $value) {
				// Normalize value
				if (is_array($value)) {
					$value = json_encode($value, JSON_UNESCAPED_SLASHES);
				}
				$lines[] = strtolower($key) . ':' . trim((string)$value);
			}

			return implode("\n", $lines);
		};

		// ─────────────────────────────────────────────────────────────────────
		// Sub-function: Generate key ID from access token and installation ID
		// Creates a non-reversible identifier for the key being used
		// ─────────────────────────────────────────────────────────────────────
		$generateKeyId = function(string $token, string $installationId): string {
			return hash('sha256', $token . '|' . $installationId);
		};

		// ─────────────────────────────────────────────────────────────────────
		// Main logic: Gather all required information
		// ─────────────────────────────────────────────────────────────────────

		// Get site and plugin information
		$siteUrl = sanitize_url(get_option('siteurl'));
		$siteDomain = self::normalizeDomain($siteUrl);
		$installationId = trim(get_option(BaseConstants::OPTION_INSTALLATION_ID, ''));
		$pluginVersion = trim(get_option(BaseConstants::OPTION_PLUGIN_VERSION, ''));
		$dbVersion = trim(get_option(BaseConstants::OPTION_DB_VERSION, ''));
		$apiVersion = trim(get_option(BaseConstants::OPTION_API_VERSION, ''));

		// Generate timestamp and date components
		$timestamp = time();
		$dateStamp = gmdate('Ymd', $timestamp);
		$dateTime = gmdate('Ymd\THis\Z', $timestamp);

		// Generate security components using sub-functions
		$fingerprint = $generateFingerprint();
		$nonce = $generateNonce();
		$keyId = $generateKeyId($accessToken, $installationId);

		// Compute content hash (SHA256 of payload JSON)
		$payloadJson = json_encode($userPayload, JSON_UNESCAPED_SLASHES);
		$contentHash = hash('sha256', $payloadJson);

		// Define headers that will be signed
		$signedHeaders = [
			'x-rc-timestamp',
			'x-rc-nonce',
			'x-rc-installation-id',
			'x-rc-site-domain',
			'x-rc-fingerprint',
			'x-rc-content-hash',
		];
		$signedHeadersList = implode(';', $signedHeaders);

		// Build canonical request parts
		$canonicalParts = [
			'method' => strtoupper($httpMethod),
			'endpoint' => $endpoint,
			'x-rc-timestamp' => $timestamp,
			'x-rc-nonce' => $nonce,
			'x-rc-installation-id' => $installationId,
			'x-rc-site-domain' => $siteDomain,
			'x-rc-fingerprint' => $fingerprint,
			'x-rc-content-hash' => $contentHash,
		];

		// Build canonical request string
		$canonicalRequest = $buildCanonicalRequest($canonicalParts);
		$canonicalRequestHash = hash('sha256', $canonicalRequest);

		// Build string to sign
		$stringToSign = implode("\n", [
			'RC1-HMAC-SHA256',
			$dateTime,
			$dateStamp . '/global/rc/rc_request',
			$canonicalRequestHash,
		]);

		// Derive signing key and compute signature
		$signingKey = $deriveSigningKey($accessToken, $dateStamp, 'global');
		$signature = hash_hmac('sha256', $stringToSign, $signingKey);

		// ─────────────────────────────────────────────────────────────────────
		// Return complete security headers
		// ─────────────────────────────────────────────────────────────────────
		return [
			// Version 2.0 security headers
			'X-RC-Auth-Version' => '2.0',
			'X-RC-Key-Id' => $keyId,
			'X-RC-Timestamp' => (string)$timestamp,
			'X-RC-Nonce' => $nonce,
			'X-RC-Installation-Id' => $installationId,
			'X-RC-Site-Domain' => $siteDomain,
			'X-RC-Fingerprint' => $fingerprint,
			'X-RC-Content-Hash' => $contentHash,
			'X-RC-Signature' => $signature,
			'X-RC-Signed-Headers' => $signedHeadersList,

			// Version info headers (backward compatibility)
			'X-RC-Plugin-Version' => $pluginVersion,
			'X-RC-Api-Version' => $apiVersion,
			'X-RC-Db-Version' => $dbVersion,
            'X-RC-Origin' => 'wordpress',
		];
	}

	/**
	 * Sets security headers for HTTP API clients.
	 * This method provides a unified interface for setting security headers
	 * that can be used by both HttpApiClient and RC-style classes.
	 * 
	 * @param object $client The client object (HttpApiClient or RC class)
	 * @param string|null $accessToken The access token
	 * @param array $userPayload The payload for signature generation
	 * @return void
	 */
	public static function setSecurityHeaders(object $client, ?string $accessToken = null, array $userPayload = []): void
	{
		$headers = self::generateSecurityHeaders($accessToken, $userPayload);
		
		if (empty($headers)) {
			return;
		}

		// Handle HttpApiClient instances
		if (method_exists($client, 'setSecurityHeaders')) {
			$client->setSecurityHeaders($headers);
			return;
		}

		// Handle RC-style classes that might have a different method
		if (method_exists($client, 'setHeaders')) {
			$client->setHeaders($headers);
			return;
		}

		// For RC classes, we might need to set headers differently
		// This can be extended based on the specific RC class implementation
		if (property_exists($client, 'headers')) {
			$client->headers = array_merge($client->headers ?? [], $headers);
		}
	}

	/**
	 * Manages WordPress heartbeat service based on plugin settings.
	 * Disables or enables the heartbeat admin-ajax service according to user preference.
	 * 
	 * @return void
	 */
	public static function manageHeartbeatService(): void
	{
		$settings = SettingsManager::instance();
		$disableHeartbeat = (bool) $settings->get_option('disable_wp_heartbeat_service', true);
		
		if ($disableHeartbeat) {
			self::disableHeartbeatService();
		} else {
			self::enableHeartbeatService();
		}
	}

	/**
	 * Disables WordPress heartbeat service.
	 * 
	 * @return void
	 */
	private static function disableHeartbeatService(): void
	{
		// Disable heartbeat filters
		add_filter('heartbeat_send', '__return_false');
		add_filter('heartbeat_tick', '__return_false');
		
		// Deregister heartbeat script
		add_action('init', function() {
			wp_deregister_script('heartbeat');
		}, 1);
	}

	/**
	 * Enables WordPress heartbeat service by removing disable filters.
	 * 
	 * @return void
	 */
	private static function enableHeartbeatService(): void
	{
		// Remove disable filters if they exist
		remove_filter('heartbeat_send', '__return_false');
		remove_filter('heartbeat_tick', '__return_false');
		
		// Re-register heartbeat script if needed
		add_action('init', function() {
			if (!wp_script_is('heartbeat', 'registered')) {
				wp_enqueue_script('heartbeat');
			}
		}, 1);
	}


    /**
     * Generates a secure token for API requests.
     * This method creates a secure token using random bytes and hashes it with a timestamp.
     * The token can be stored in the database or session as needed.
     *
     * @return string The generated secure token.
     * @throws Throwable
     */
    public static function generateSecureToken():string
    {
        $token = bin2hex(random_bytes(32)); // Generate a secure random token
        $timestamp = time(); // Current timestamp

        // Create a hash of the token with the timestamp for added security
        return hash('sha256', $token . $timestamp);
    }

    /**
     * Stores a secure token with a specified time-to-live (TTL) in the WordPress options table, using transient.
     * This method updates the secure token and its expiration time in the options table.
     *
     * @param int $ttl The time-to-live in seconds (default is 300 seconds).
     * @return void
     * @throws Throwable
     */
    public static function secureTokenWithTTL(int $ttl = 300): void
    {
        $token = self::generateSecureToken();
        // Store the token in the options table with a transient
        set_transient(BaseConstants::OPTION_SECURE_TOKEN, $token, $ttl);
    }

    /**
     * Retrieves all options that start with a specific string.
     * This method filters the WordPress options table to return only those options
     * whose keys begin with the specified string.
     *
     * @param string $string The prefix string to filter options by.
     * @return array An associative array of options that match the prefix.
     */
    public static function getOptions(string $string, array $allowedOptions): array
    {
        // Return all options which start with provided specific string
        // e.g. 'rankingCoach_' will return all options which start with 'rankingCoach_%'

        $options = [];
        $allOptions = wp_load_alloptions();
        foreach ($allOptions as $key => $value) {
            // Check the elements from the allowed options list
            // Concatenate $string with each allowed option item and if the result is different from the key, skip it
            $removedStringFromKey = str_replace($string, '', $key);
            if (!in_array($removedStringFromKey, $allowedOptions)) {
                // If the key is in the allowed options, we can skip it
                continue;
            }

            if (str_starts_with($key, $string)) {
                $options[$key] = $value;
            }
        }
        return $options;
    }

    /**
     * Retrieves the URL of the favicon for the site.
     * This method should be implemented to return the correct favicon URL.
     *
     * @return string|null The URL of the favicon, or null if not available.
     */
    public static function getFaviconUrl(): ?string
    {
        // This method should return the URL of the favicon for the site.
        // The implementation can vary based on how the favicon is stored or configured.
        $faviconUrl = get_site_icon_url();

        if ($faviconUrl) {
            return $faviconUrl;
        }

        // Fallback to a default favicon if none is set
        return RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/icons/favicon.ico';
    }

    /**
     * Normalizes a domain name by ensuring it has a scheme and extracting the host.
     *
     * @param string $domain The input domain name.
     * @return string The normalized domain name.
     */
    public static function normalizeDomain(string $domain): string {
        // If no scheme is present, prepend https://
        if (!preg_match('~^https?://~i', $domain)) {
            $domain = 'https://' . $domain;
        }

        return rtrim(wp_parse_url($domain, PHP_URL_HOST) ?? $domain, '/');
    }

    /**
     * Retrieves the list of option prefixes that should be managed.
     *
     * @return array Array of option prefixes.
     */
    public static function getOptionPrefixes(): array
    {
        return self::OPTION_PREFIXES;
    }

    /**
     * Retrieves the list of specific option keys that should be excluded from deletion.
     *
     * @return array Array of excluded option keys.
     */
    public static function getExcludedOptionKeys(): array
    {
        return self::EXCLUDED_OPTION_KEYS;
    }

    public static function filterExcludedOptions(array $options): array
    {
        $excluded = self::getExcludedOptionKeys();
        return array_filter($options, static function ($option) use ($excluded) {
            return !in_array($option, $excluded, true);
        });
    }

    /**
     * Get count of public published posts and pages for allowed custom types.
     *
     * Counts all published content from post types defined in ALLOWED_RANKINGCOACH_CUSTOM_TYPES.
     * This includes 'post', 'page', and any other custom post types like 'tribe_events'.
     *
     * @return int Total count of public posts and pages
     */
    public static function getPublicPostsAndPagesCount(): int {
        $allowedTypes = ALLOWED_RANKINGCOACH_CUSTOM_TYPES; // ['post', 'page', 'tribe_events']

        $count = 0;
        foreach ($allowedTypes as $postType) {
            $counts = wp_count_posts($postType);
            if (isset($counts->publish)) {
                $count += (int) $counts->publish;
            }
        }

        return $count;
    }
}
