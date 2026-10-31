<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;

/**
 * Class ExternalLinks
 *
 * Single resolver for every outbound link the plugin renders: support, documentation,
 * WordPress.org review, legal pages and the customer-reviews page. The URLs themselves are
 * declared once in BaseConstants; this class only decides which variant applies to the
 * current install and returns it ready for its context:
 *  - `$raw = false` (default) for HTML output, escaped with esc_url();
 *  - `$raw = true` for wp_localize_script() / redirects, escaped with esc_url_raw().
 *
 * Support is partner-aware. Installs that came through a partner channel with its own
 * support desk (BaseConstants::PARTNER_SUPPORT_URLS - currently IONOS, with a dedicated
 * German desk) link to that desk; every other install, including partner-provisioned ones
 * whose partner cannot be identified, falls back to rankingCoach (direct-channel) support.
 *
 * Use this class wherever a link is rendered (plugins list, admin menu, toolbar, React
 * data, notices) instead of reading the constants directly, so that a partner or URL
 * change is a single edit in BaseConstants.
 */
final class ExternalLinks
{
    /**
     * Query parameter carrying the installed plugin version on the rankingCoach support URL,
     * so the support team sees which release the customer is contacting them from.
     */
    public const SUPPORT_PLUGIN_VERSION_PARAM = 'plugin_version';

    /**
     * Query parameter carrying the install's channel on the rankingCoach support URL
     * (see getSupportChannel()), so support knows which partner/flow the customer came from.
     */
    public const SUPPORT_CHANNEL_PARAM = 'channel';

    /**
     * Customer-support URL for the current install: the partner desk when the install
     * belongs to a partner listed in BaseConstants::PARTNER_SUPPORT_URLS, rankingCoach
     * support (with the plugin version, channel and UTM parameters) otherwise.
     *
     * @param bool $raw True for non-display contexts (wp_localize_script, redirects).
     * @return string
     */
    public static function getSupportUrl(bool $raw = false): string
    {
        $partner = self::getSupportPartner();
        if ($partner !== null) {
            $partnerUrl = self::getPartnerSupportUrl($partner);
            if ($partnerUrl !== null) {
                return self::escape($partnerUrl, $raw);
            }
        }

        $params  = [];
        $version = CoreHelper::getPluginVersion();
        if ($version !== '') {
            $params[self::SUPPORT_PLUGIN_VERSION_PARAM] = $version;
        }
        $params[self::SUPPORT_CHANNEL_PARAM] = self::getSupportChannel();

        $url = add_query_arg($params, BaseConstants::URL_SUPPORT);

        return CoreHelper::buildUtmUrl($url, utm_content: 'support', raw: $raw);
    }

    /**
     * Channel identifier reported to rankingCoach support, resolved in priority order:
     *  1. the partner name a partner installer stored in
     *     BaseConstants::OPTION_PARTNER_INTEGRATION_NAME (e.g. "ionos") - most explicit;
     *  2. the channel detected and stored by the ChannelFlow (OptionStore, e.g. "ionos",
     *     "extendify") when it is a real partner channel;
     *  3. a live, side-effect-free detection through ChannelResolver::detectChannel() for
     *     installs whose channel has not been stored yet;
     *  4. BaseConstants::SUPPORT_CHANNEL_DEFAULT ("dc", direct channel) otherwise.
     *
     * Values are normalised to a lowercase slug ([a-z0-9_-]) so they are safe in a URL.
     *
     * @return string
     */
    public static function getSupportChannel(): string
    {
        $partnerName = self::normalizeChannel(get_option(BaseConstants::OPTION_PARTNER_INTEGRATION_NAME));
        if ($partnerName !== null) {
            return $partnerName;
        }

        $store  = new OptionStore();
        $stored = self::normalizeChannel($store->getChannel());
        if ($stored !== null && $stored !== BaseConstants::CHANNEL_DIRECT) {
            return $stored;
        }

        // detectChannel() only reads options; unlike resolve() it does not persist anything.
        [$detected] = (new ChannelResolver($store))->detectChannel();
        $detected   = self::normalizeChannel($detected);
        if ($detected !== null && $detected !== BaseConstants::CHANNEL_DIRECT) {
            return $detected;
        }

        return BaseConstants::SUPPORT_CHANNEL_DEFAULT;
    }

    /**
     * Plugin documentation URL (rankingCoach, with UTM parameters).
     *
     * @param bool $raw True for non-display contexts.
     * @return string
     */
    public static function getDocumentationUrl(bool $raw = false): string
    {
        return CoreHelper::buildUtmUrl(BaseConstants::URL_DOCUMENTATION, utm_content: 'wordpress', raw: $raw);
    }

    /**
     * "Rate us" URL on WordPress.org.
     *
     * @param bool $raw True for non-display contexts.
     * @return string
     */
    public static function getReviewUrl(bool $raw = false): string
    {
        return self::escape(BaseConstants::URL_REVIEW, $raw);
    }

    /**
     * rankingCoach privacy policy URL.
     *
     * @param bool $raw True for non-display contexts.
     * @return string
     */
    public static function getPrivacyPolicyUrl(bool $raw = false): string
    {
        return self::escape(BaseConstants::URL_PRIVACY_POLICY, $raw);
    }

    /**
     * rankingCoach terms and conditions URL.
     *
     * @param bool $raw True for non-display contexts.
     * @return string
     */
    public static function getTermsUrl(bool $raw = false): string
    {
        return self::escape(BaseConstants::URL_TERMS_AND_CONDITIONS, $raw);
    }

    /**
     * rankingCoach customer reviews page (REVIEWS.io).
     *
     * @param bool $raw True for non-display contexts.
     * @return string
     */
    public static function getCustomerReviewsUrl(bool $raw = false): string
    {
        return self::escape(BaseConstants::URL_CUSTOMER_REVIEWS, $raw);
    }

    /**
     * Partner channel whose support desk serves this install (a key of
     * BaseConstants::PARTNER_SUPPORT_URLS, e.g. "ionos"), or null for rankingCoach.
     *
     * The stored channel wins. As a safety net the live IONOS brand marker is honoured too
     * (same rule as ChannelResolver / UpsellPage), so an IONOS install links to IONOS support
     * even before its channel has been detected and stored. The partner-integration flag
     * (WordpressHelpers::isPartnerIntegration()) is a plain boolean that cannot identify the
     * partner by itself, hence it plays no role here: such installs use the channel, and
     * fall back to rankingCoach support when the channel is not a listed partner.
     *
     * @return string|null
     */
    public static function getSupportPartner(): ?string
    {
        $channel = strtolower(trim((string) (new OptionStore())->getChannel()));
        if (isset(BaseConstants::PARTNER_SUPPORT_URLS[$channel])) {
            return $channel;
        }

        $brand = get_option('ionos_group_brand');
        if (is_scalar($brand) && strtolower(trim((string) $brand)) === BaseConstants::CHANNEL_IONOS) {
            return BaseConstants::CHANNEL_IONOS;
        }

        return null;
    }

    /**
     * Links exposed to the React app through the localized `rankingCoachReactData` object
     * (raw form, see Assets::front()). The React side reads them via helpers/external-links.ts.
     *
     * @return array<string, string|null>
     */
    public static function getReactData(): array
    {
        return [
            'supportUrl'         => self::getSupportUrl(true),
            'supportPartner'     => self::getSupportPartner(),
            'supportChannel'     => self::getSupportChannel(),
            'documentationUrl'   => self::getDocumentationUrl(true),
            'reviewUrl'          => self::getReviewUrl(true),
            'privacyPolicyUrl'   => self::getPrivacyPolicyUrl(true),
            'termsUrl'           => self::getTermsUrl(true),
            'customerReviewsUrl' => self::getCustomerReviewsUrl(true),
        ];
    }

    /**
     * Support desk of the given partner for the current market, or null when the partner
     * has no desk configured at all.
     *
     * @param string $partner Key of BaseConstants::PARTNER_SUPPORT_URLS.
     * @return string|null Unescaped URL.
     */
    private static function getPartnerSupportUrl(string $partner): ?string
    {
        $urls = BaseConstants::PARTNER_SUPPORT_URLS[$partner] ?? null;
        if (!is_array($urls) || $urls === []) {
            return null;
        }

        $market = self::getPartnerMarket($partner);
        if ($market !== null && isset($urls[$market])) {
            return $urls[$market];
        }

        return $urls[BaseConstants::PARTNER_SUPPORT_DEFAULT_MARKET] ?? null;
    }

    /**
     * Market code used to pick the partner desk: the partner's own market marker when it
     * exposes one (IONOS: wp_option `ionos_market`, e.g. "de-DE" => "DE"), otherwise the
     * language of the effective WordPress locale (de_DE => "DE").
     *
     * @param string $partner Key of BaseConstants::PARTNER_SUPPORT_URLS.
     * @return string|null
     */
    private static function getPartnerMarket(string $partner): ?string
    {
        if ($partner === BaseConstants::CHANNEL_IONOS) {
            $market = self::normalizeMarket(get_option('ionos_market'));
            if ($market !== null) {
                return $market;
            }
        }

        return self::normalizeMarket(WordpressHelpers::get_effective_wp_locale());
    }

    /**
     * Uppercase first token of a market/locale string ("de-DE", "de_de", "de" => "DE"),
     * or null when missing/invalid. Mirrors ChannelResolver::getNormalizedIonosMarket().
     *
     * @param mixed $value
     * @return string|null
     */
    private static function normalizeMarket(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $first = preg_split('/[-_]/', $raw)[0] ?? $raw;
        $token = preg_replace('/[^A-Z0-9]/', '', strtoupper($first)) ?: '';

        return $token !== '' ? $token : null;
    }

    /**
     * Lowercase URL-safe channel slug ("IONOS " => "ionos", "Direct Channel" => "direct-channel"),
     * or null when the value is missing, not scalar, or has no usable characters.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function normalizeChannel(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $slug = strtolower(trim((string) $value));
        $slug = preg_replace('/\s+/', '-', $slug) ?? '';
        $slug = preg_replace('/[^a-z0-9_-]/', '', $slug) ?? '';
        $slug = trim($slug, '-_');

        return $slug !== '' ? $slug : null;
    }

    /**
     * Escape a URL for its output context.
     *
     * @param string $url
     * @param bool $raw True for non-display contexts.
     * @return string
     */
    private static function escape(string $url, bool $raw): string
    {
        return $raw ? esc_url_raw($url) : esc_url($url);
    }
}
