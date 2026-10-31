<?php /** @noinspection PhpFunctionCyclomaticComplexityInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Plugin;
use Exception;
use JsonException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Builders\Elementor\ElementorContentRenderer;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use RankingCoach\Inc\Modules\ModuleManager;
use Throwable;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Screen;
use WP_Term;
use WPBMap;

/**
 * Class WordpressHelpers
 */
class WordpressHelpers {

    /**
     * Get the current language in 2-letter ISO format (e.g., 'en', 'fr').
     *
     * Supports native WordPress, WPML, Polylang, TranslatePress, or manual URL parsing fallback.
     *
     * @return string Two-letter language code (e.g., 'en').
     */
    public static function current_language_code_helper(?string $localeArg = null): string
    {
        /**
         * Priority order for determining current language:
         */
        if ($localeArg) {
            return substr($localeArg, 0, 2);
        }

        // 1. WPML support
        if (function_exists('icl_object_id') || function_exists('wpml_current_language')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WPML core filter
            $lang = apply_filters('wpml_current_language', null);
            if ($lang) return substr($lang, 0, 2);
        }

        // 2. Polylang support
        if (function_exists('pll_current_language')) {
            $lang = pll_current_language();
            if ($lang) return substr($lang, 0, 2);
        }

        // 3. TranslatePress support
        if (function_exists('trp_get_current_language')) {
            $lang = trp_get_current_language();
            if ($lang) return substr($lang, 0, 2);
        }

        // 4. WordPress default locale
        $locale = get_locale(); // e.g., en_US
        if ($locale) {
            return substr($locale, 0, 2);
        }

        // 5. Fallback: Try to detect from URL
        $uri = filter_input(
            INPUT_SERVER,
            'REQUEST_URI',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        $uri = $uri ? wp_unslash( $uri ) : '';

        $segments = explode('/', trim($uri, '/'));
        $maybe_lang = $segments[0] ?? '';

        // Optional: Define your supported languages here
        // $supported_langs = ['en', 'fr', 'de', 'ro', 'es', 'it'];

        //if (in_array($maybe_lang, $supported_langs)) {
        //    return $maybe_lang;
        //}

        // 6. Final fallback
        return 'en'; // Default to English
    }

    /**
     * Get WordPress locale with proper fallback handling.
     *
     * @return string WordPress locale (e.g., 'en_US', 'de_DE').
     */
    public static function get_wp_locale(): string {
        $locale = self::get_effective_wp_locale();
        
        // Validate language code format
        if (!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/i', $locale)) {
            return 'en_US';
        }
        
        // Check installed WordPress translations first
        if (function_exists('wp_get_installed_translations')) {
            $translations = wp_get_installed_translations('core');
            $normalized_lang = str_replace('-', '_', $locale);
            
            foreach (array_keys($translations['default'] ?? []) as $installed) {
                if (stripos($installed, $normalized_lang . '_') === 0 || $installed === $normalized_lang) {
                    return $installed;
                }
            }
        }
        
        // Common language mappings - Expanded to cover all allowed countries in $allowed_countries
        $locale_map = [

            // English
            'en_us' => 'en_US', // USA
            'en_gb' => 'en_GB', // United Kingdom
            'en_ie' => 'en_IE', // Ireland
            'en_au' => 'en_AU', // Australia
            'en_ca' => 'en_CA', // Canada (EN)
            'en_za' => 'en_ZA', // South Africa

            // French
            'fr_fr' => 'fr_FR', // France
            'fr_ca' => 'fr_CA', // Canada (FR)
            'fr_be' => 'fr_BE', // Belgique (FR)

            // German
            'de_de' => 'de_DE', // Deutschland
            'de_at' => 'de_AT', // Österreich
            'de_ch' => 'de_CH', // Schweiz

            // Dutch
            'nl_nl' => 'nl_NL', // Nederland
            'nl_be' => 'nl_BE', // België (NL)

            // Spanish
            'es_es' => 'es_ES', // España
            'es_mx' => 'es_MX', // México
            'es_ar' => 'es_AR', // Argentina
            'es_cl' => 'es_CL', // Chile

            // Portuguese
            'pt_pt' => 'pt_PT', // Portugal
            'pt_br' => 'pt_BR', // Brasil

            // Italian
            'it_it' => 'it_IT', // Italia

            // Polish
            'pl_pl' => 'pl_PL', // Polska

            // Romanian
            'ro_ro' => 'ro_RO', // Romania

            // Czech
            'cs_cz' => 'cs_CZ', // Česko

            // Slovak
            'sk_sk' => 'sk_SK', // Slovensko
        ];
        
        $normalized_key = strtolower($locale);
        
        if (isset($locale_map[$normalized_key])) {
            return $locale_map[$normalized_key];
        }
        
        // Fallback: rebuild lang_REGION from the normalized key (fi -> fi_FI, ca_es -> ca_ES)
        $parts = explode('_', $normalized_key);
        $lang = $parts[0];
        $region = $parts[1] ?? $lang;
        return $lang . '_' . strtoupper($region);
    }

    /**
     * Get the effective locale of the WordPress site.
     *
     * This function determines the current locale using WordPress functions,
     * ensuring a valid locale string is returned.
     *
     * @return string The effective locale (e.g., 'en_US').
     */
    public static function get_effective_wp_locale(): string {
        $locale = determine_locale();

        if (!$locale || !is_string($locale)) {
            $locale = get_locale();
        }

        // some times get_locale() can return just "en", so we need to handle that
        if ($locale && is_string($locale)) {
            // Normalize separators like "en-US" -> "en_US"
            $locale = str_replace('-', '_', $locale);

            $segments = explode('_', $locale);
            $lang = strtolower($segments[0]);
            $region = $segments[1] ?? '';

            if (preg_match('/^[a-zA-Z]{2}$/', $region)) {
                // Ensure proper casing and drop variant suffixes (de_DE_formal -> de_DE)
                $locale = $lang . '_' . strtoupper($region);
            } elseif (strlen($lang) === 2) {
                // Map common 2-letter languages to sensible default regions
                $defaultRegions = [
                    'en' => 'US',
                    'fr' => 'FR',
                    'de' => 'DE',
                    'es' => 'ES',
                    'it' => 'IT',
                    'pt' => 'PT',
                    'nl' => 'NL',
                    'pl' => 'PL',
                    'ro' => 'RO',
                    'sv' => 'SE',
                    'no' => 'NO',
                    'da' => 'DK',
                    'fi' => 'FI',
                    'cs' => 'CZ',
                    'sk' => 'SK',
                    'hu' => 'HU',
                    'tr' => 'TR',
                    'el' => 'GR',
                    'he' => 'IL',
                    'ar' => 'SA',
                    'ru' => 'RU',
                    'bg' => 'BG',
                    'hr' => 'HR',
                    'sl' => 'SI',
                    'lt' => 'LT',
                    'lv' => 'LV',
                    'et' => 'EE',
                    'ja' => 'JP',
                    'zh' => 'CN',
                    'ko' => 'KR',
                    'vi' => 'VN',
                    'id' => 'ID',
                    'th' => 'TH',
                    'ms' => 'MY',
                    'ca' => 'ES',
                ];
                $region = $defaultRegions[$lang] ?? strtoupper($lang);
                $locale = $lang . '_' . $region;
            } else {
                // Fallback: normalize to lowercase if unexpected format
                $locale = strtolower($locale);
            }
        }

        return $locale ?: 'en_US';
    }

    /**
     * Check if a post is built with Elementor and render its content if it is.
     *
     * @param int $post_id The ID of the post to check and render.
     *
     * @return string The rendered content if the post is built with Elementor, otherwise an empty string.
     */
	public static function render_elementor_content( int $post_id): string {
        if (
            ! class_exists('\Elementor\Plugin') ||
            ! did_action('elementor/loaded')
        ) {
            return '';
        }

        if(! ElementorContentRenderer::is_built_with_elementor($post_id)) {
            return '';
        }

        return ElementorContentRenderer::render($post_id, false) ?? '';
    }

	/**
	 * Render content for a post, ensuring WPBakery shortcodes are processed if WPBakery is active.
	 *
	 * @param WP_Post $post The post object to render content for.
	 *
	 * @return string The rendered content.
	 */
	public static function render_wpbakery_content( WP_Post $post): string {

		// Check if WPBakery is active
		if (class_exists('WPBMap') && method_exists('WPBMap', 'addAllMappedShortcodes')) {
			// WPBakery is active: map all shortcodes
			WPBMap::addAllMappedShortcodes();
		}

		// Get the raw content
		$raw_content = $post->post_content;

		// Apply 'the_content' filter (which includes do_shortcode)
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$content = apply_filters('the_content', $raw_content);

		// If WPBakery is active, ensure shortcodes are processed
		if (class_exists('WPBMap')) {
			// Check if the content contains WPBakery shortcodes
			if ( str_contains( $raw_content, '[vc_' ) ) {
				// Force shortcode processing if WPBakery shortcodes are detected
				$content = do_shortcode($content);
			}
		}

		return $content;
	}

	/**
	 * Get a list of available WordPress variables that can be used in various contexts.
	 *
	 * @param array $context The context in which the variables are being used.
	 *
	 * @return array
	 */
    public static function get_available_WPVariables( array $context = []): array {
        // Safe context initialization - only get post if we're in a proper WordPress context
        if (empty($context)) {
            $post = null;
            
            // Only attempt to get post if WordPress is fully loaded and we're in the right context
            if (function_exists('get_post') && did_action('wp_loaded')) {
                $post = get_post();
            }
            
            $context = [
                'post' => $post,
            ];
        }

        // Helper function to safely check WordPress conditional functions
        $safe_conditional_check = function($function_name, $default = false) {
            // Only call conditional functions if WordPress query is ready
            if (!function_exists($function_name) || !did_action('wp') || is_admin()) {
                return $default;
            }
            
            try {
                return call_user_func($function_name);
            } catch (Exception $e) {
                return $default;
            }
        };

        // Helper function to safely get user functions
        $safe_user_function = function($function_name, $default = '') {
            if (!function_exists($function_name)) {
                return $default;
            }
            
            try {
                return call_user_func($function_name);
            } catch (Exception $e) {
                return $default;
            }
        };

        // Helper to safely get current user data
        $get_current_user_data = function($property, $default = '') use ($safe_user_function) {
            if (!$safe_user_function('is_user_logged_in', false)) {
                return $default;
            }
            
            try {
                $user = wp_get_current_user();
                return $user && isset($user->$property) ? $user->$property : $default;
            } catch (Exception $e) {
                return $default;
            }
        };

        $request_uri = self::sanitize_input('SERVER', 'REQUEST_URI');
        $host        = self::sanitize_input('SERVER', 'HTTP_HOST');
        $user_agent  = self::sanitize_input('SERVER', 'HTTP_USER_AGENT');
        $user_ip     = self::sanitize_input(
            'SERVER',
            'REMOTE_ADDR',
            filters: [FILTER_SANITIZE_FULL_SPECIAL_CHARS],
            validate: FILTER_VALIDATE_IP
        );



        return [
            // Post/Page Specific
            [
                'key' => 'post_title',
                'description' => 'Post / Page title.',
                'value' => isset($context['post']) && $context['post'] ? self::get_editable_post_title($context['post']) : '',
            ],
            [
                'key' => 'post_slug',
                'description' => 'Post / Page slug',
                'value' => isset($context['post']) && $context['post'] ? $context['post']->post_name : '',
            ],
            [
                'key' => 'post_excerpt',
                'description' => 'Post excerpt',
                'value' => isset($context['post']) && $context['post'] ? get_the_excerpt($context['post']) : '',
            ],
            [
                'key' => 'post_id',
                'description' => 'Post / Page ID',
                'value' => isset($context['post']) && $context['post'] ? $context['post']->ID : '',
            ],
            [
                'key' => 'post_date',
                'description' => 'Post / Page publish date',
                'value' => isset($context['post']) && $context['post'] ? get_the_date('', $context['post']) : '',
            ],
            [
                'key' => 'post_modified',
                'description' => 'Last modified date',
                'value' => isset($context['post']) && $context['post'] ? get_the_modified_date('', $context['post']) : '',
            ],
            [
                'key' => 'post_content',
                'description' => 'Post content',
                'value' => isset($context['post']) && $context['post'] ? wp_strip_all_tags($context['post']->post_content) : '',
            ],
            [
                'key' => 'post_type',
                'description' => 'Post type',
                'value' => isset($context['post']) && $context['post'] ? get_post_type($context['post']) : '',
            ],
            [
                'key' => 'post_status',
                'description' => 'Post status',
                'value' => isset($context['post']) && $context['post'] ? $context['post']->post_status : '',
            ],
            [
                'key' => 'post_url',
                'description' => 'Post URL',
                'value' => isset($context['post']) && $context['post'] ? get_permalink($context['post']) : '',
            ],
            [
                'key' => 'post_comment_count',
                'description' => 'Number of comments',
                'value' => isset($context['post']) && $context['post'] ? $context['post']->comment_count : '',
            ],
            [
                'key' => 'post_parent_id',
                'description' => 'Parent post ID',
                'value' => isset($context['post']) && $context['post'] ? $context['post']->post_parent : '',
            ],
            [
                'key' => 'post_parent_title',
                'description' => 'Parent post title',
                'value' => isset($context['post']) && $context['post'] && $context['post']->post_parent ? get_the_title($context['post']->post_parent) : '',
            ],
            [
                'key' => 'post_menu_order',
                'description' => 'Post menu order',
                'value' => isset($context['post']) && $context['post'] ? $context['post']->menu_order : '',
            ],

            // Author Specific
            [
                'key' => 'author_name',
                'description' => 'Post / Page author',
                'value' => isset($context['post']) && $context['post'] ? get_the_author_meta('display_name', $context['post']->post_author) : '',
            ],
            [
                'key' => 'author_url',
                'description' => 'Author`s archive URL',
                'value' => isset($context['post']) && $context['post'] ? get_author_posts_url($context['post']->post_author) : '',
            ],
            [
                'key' => 'author_email',
                'description' => 'Author email',
                'value' => isset($context['post']) && $context['post'] ? get_the_author_meta('user_email', $context['post']->post_author) : '',
            ],
            [
                'key' => 'author_first_name',
                'description' => 'Author first name',
                'value' => isset($context['post']) && $context['post'] ? get_the_author_meta('first_name', $context['post']->post_author) : '',
            ],
            [
                'key' => 'author_last_name',
                'description' => 'Author last name',
                'value' => isset($context['post']) && $context['post'] ? get_the_author_meta('last_name', $context['post']->post_author) : '',
            ],
            [
                'key' => 'author_bio',
                'description' => 'Author biography',
                'value' => isset($context['post']) && $context['post'] ? get_the_author_meta('description', $context['post']->post_author) : '',
            ],
            [
                'key' => 'author_nickname',
                'description' => 'Author nickname',
                'value' => isset($context['post']) && $context['post'] ? get_the_author_meta('nickname', $context['post']->post_author) : '',
            ],

            // Site Specific
            [
                'key' => 'site_title',
                'description' => 'Site title',
                'value' => get_bloginfo('name'),
            ],
            [
                'key' => 'site_tagline',
                'description' => 'Site tagline',
                'value' => get_bloginfo('description'),
            ],
            [
                'key' => 'site_url',
                'description' => 'Site URL',
                'value' => get_home_url(),
            ],
            [
                'key' => 'admin_email',
                'description' => 'Admin email',
                'value' => get_bloginfo('admin_email'),
            ],
            [
                'key' => 'wp_version',
                'description' => 'WordPress version',
                'value' => get_bloginfo('version'),
            ],
            [
                'key' => 'site_language',
                'description' => 'Site language',
                'value' => get_bloginfo('language'),
            ],
            [
                'key' => 'site_charset',
                'description' => 'Site charset',
                'value' => get_bloginfo('charset'),
            ],
            [
                'key' => 'template_directory',
                'description' => 'Template directory URL',
                'value' => get_template_directory_uri(),
            ],
            [
                'key' => 'stylesheet_directory',
                'description' => 'Stylesheet directory URL',
                'value' => get_stylesheet_directory_uri(),
            ],

            // Taxonomy Specific
            [
                'key' => 'category',
                'description' => 'Post first category',
                'value' => isset($context['post']) && $context['post'] ? (get_the_category($context['post']->ID)[0]->name ?? '') : '',
            ],
            [
                'key' => 'categories',
                'description' => 'Post categories list',
                'value' => isset($context['post']) && $context['post'] ? implode(', ', wp_list_pluck(get_the_category($context['post']->ID), 'name')) : '',
            ],
            [
                'key' => 'category_ids',
                'description' => 'Post category IDs',
                'value' => isset($context['post']) && $context['post'] ? implode(', ', wp_list_pluck(get_the_category($context['post']->ID), 'term_id')) : '',
            ],
            [
                'key' => 'tag',
                'description' => 'Post first tag',
                'value' => isset($context['post']) && $context['post'] ? (get_the_tags($context['post']->ID)[0]->name ?? '') : '',
            ],
            [
                'key' => 'tags',
                'description' => 'Post tags list',
                'value' => isset($context['post']) && $context['post'] ? implode(', ', wp_list_pluck(get_the_tags($context['post']->ID) ?: [], 'name')) : '',
            ],
            [
                'key' => 'tag_ids',
                'description' => 'Post tag IDs',
                'value' => isset($context['post']) && $context['post'] ? implode(', ', wp_list_pluck(get_the_tags($context['post']->ID) ?: [], 'term_id')) : '',
            ],

            // Date and Time
            [
                'key' => 'current_date',
                'description' => 'Current date',
                'value' => date_i18n(get_option('date_format')),
            ],
            [
                'key' => 'current_time',
                'description' => 'Current time',
                'value' => date_i18n(get_option('time_format')),
            ],
            [
                'key' => 'current_datetime',
                'description' => 'Current date and time',
                'value' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            ],
            [
                'key' => 'year',
                'description' => 'Current year',
                'value' => date_i18n('Y'),
            ],
            [
                'key' => 'month',
                'description' => 'Current month',
                'value' => date_i18n('F'),
            ],
            [
                'key' => 'month_number',
                'description' => 'Current month number',
                'value' => date_i18n('m'),
            ],
            [
                'key' => 'day',
                'description' => 'Current day',
                'value' => date_i18n('j'),
            ],
            [
                'key' => 'day_of_week',
                'description' => 'Current day of week',
                'value' => date_i18n('l'),
            ],
            [
                'key' => 'week_number',
                'description' => 'Current week number',
                'value' => date_i18n('W'),
            ],

            // Archive Specific - Safe conditional checks
            [
                'key' => 'archive_title',
                'description' => 'Archive title',
                'value' => $safe_conditional_check('is_archive') ? get_the_archive_title() : '',
            ],
            [
                'key' => 'archive_description',
                'description' => 'Archive description',
                'value' => $safe_conditional_check('is_archive') ? get_the_archive_description() : '',
            ],
            [
                'key' => 'search_term',
                'description' => 'Site term searched',
                'value' => $safe_conditional_check('is_search') ? get_search_query() : '',
            ],

            // User Specific (Current User) - Safe user checks
            [
                'key' => 'current_user_id',
                'description' => 'Current user ID',
                'value' => $safe_user_function('get_current_user_id', 0),
            ],
            [
                'key' => 'current_user_name',
                'description' => 'Current user display name',
                'value' => $get_current_user_data('display_name'),
            ],
            [
                'key' => 'current_user_login',
                'description' => 'Current user login',
                'value' => $get_current_user_data('user_login'),
            ],
            [
                'key' => 'current_user_email',
                'description' => 'Current user email',
                'value' => $get_current_user_data('user_email'),
            ],

            // Page Template
            [
                'key' => 'page_template',
                'description' => 'Page template filename',
                'value' => isset($context['post']) && $context['post'] ? get_page_template_slug($context['post']) : '',
            ],

            // Comments
            [
                'key' => 'comments_number',
                'description' => 'Number of comments',
                'value' => isset($context['post']) && $context['post'] ? get_comments_number($context['post']) : '',
            ],

            // Featured Image
            [
                'key' => 'featured_image_id',
                'description' => 'Featured image ID',
                'value' => isset($context['post']) && $context['post'] ? get_post_thumbnail_id($context['post']) : '',
            ],
            [
                'key' => 'featured_image_url',
                'description' => 'Featured image URL',
                'value' => isset($context['post']) && $context['post'] ? get_the_post_thumbnail_url($context['post'], 'full') : '',
            ],
            [
                'key' => 'featured_image_alt',
                'description' => 'Featured image alt text',
                'value' => isset($context['post']) && $context['post'] ? get_post_meta(get_post_thumbnail_id($context['post']), '_wp_attachment_image_alt', true) : '',
            ],

            // Request/Server Info
            [
                'key' => 'request_uri',
                'description' => 'Current request URI',
                'value' => $request_uri,
            ],
            [
                'key' => 'current_url',
                'description' => 'Current page URL',
                'value' => ( is_ssl() ? 'https://' : 'http://' ) . $host . $request_uri,
            ],
            [
                'key' => 'user_agent',
                'description' => 'User agent string',
                'value' => $user_agent,
            ],
            [
                'key' => 'user_ip',
                'description' => 'User IP address',
                'value' => $user_ip,
            ],

            // Conditional Tags Results - Safe conditional checks
            [
                'key' => 'is_home',
                'description' => 'Is home page',
                'value' => $safe_conditional_check('is_home') ? 'true' : 'false',
            ],
            [
                'key' => 'is_front_page',
                'description' => 'Is front page',
                'value' => $safe_conditional_check('is_front_page') ? 'true' : 'false',
            ],
            [
                'key' => 'is_single',
                'description' => 'Is single post',
                'value' => $safe_conditional_check('is_single') ? 'true' : 'false',
            ],
            [
                'key' => 'is_page',
                'description' => 'Is page',
                'value' => $safe_conditional_check('is_page') ? 'true' : 'false',
            ],
            [
                'key' => 'is_archive',
                'description' => 'Is archive',
                'value' => $safe_conditional_check('is_archive') ? 'true' : 'false',
            ],
            [
                'key' => 'is_search',
                'description' => 'Is search',
                'value' => $safe_conditional_check('is_search') ? 'true' : 'false',
            ],
            [
                'key' => 'is_404',
                'description' => 'Is 404 page',
                'value' => $safe_conditional_check('is_404') ? 'true' : 'false',
            ],
            [
                'key' => 'is_admin',
                'description' => 'Is admin area',
                'value' => $safe_conditional_check('is_admin') ? 'true' : 'false',
            ],
            [
                'key' => 'is_user_logged_in',
                'description' => 'Is user logged in',
                'value' => $safe_user_function('is_user_logged_in') ? 'true' : 'false',
            ],

            // Separator
            [
                'key' => 'separator',
                'description' => 'Separator',
                'value' => isset($context['post']) && $context['post'] ? get_post_meta($context['post']->ID, MetaTags::META_TITLE_SEPARATOR, true) ?: '-' : '-',
            ],

            // Custom Fields
            [
                'key' => 'custom_field',
                'description' => 'Custom field value',
                'value' => isset($context['post']) && $context['post'] ? get_post_meta($context['post']->ID, 'custom_field_key', true) : '',
            ],
        ];
    }

    /**
     * Resolve a template (either a JSON-structured array or a legacy string template)
     * containing text, variables, and separators into a final, human-readable string.
     *
     * Supports legacy strings containing `{% variable:<key> %}` and `{% separator:<key> %}`
     * as well as new JSON format: `[{"type": "text", "content": "..."}, {"type": "variable", "key": "..."}, {"type": "separator", "key": "..."}]`.
     *
     * @param int    $postId   The post ID to resolve variables against.
     * @param string $template The raw template (JSON string or legacy string).
     *
     * @return string The resolved string.
     */
    public static function resolveTemplate(int $postId, string $template): string {
        if (empty($postId) || empty($template)) {
            return '';
        }

        $post = get_post($postId);
        $variables = self::get_available_WPVariables(['post' => $post]);

        $is_json = false;
        $decoded = null;
        $trimmed_template = trim($template);

        if (str_starts_with($trimmed_template, '[') && str_ends_with($trimmed_template, ']')) {
            try {
                $decoded = json_decode($template, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $is_json = true;
                }
            } catch (Exception $e) {
                $is_json = false;
            }
        }

        if ($is_json) {
            $resolved_parts = [];

            foreach ($decoded as $element) {
                if (!is_array($element) || !isset($element['type'])) {
                    continue;
                }

                $type = $element['type'];

                if ($type === 'text') {
                    $resolved_parts[] = $element['content'] ?? '';
                } elseif ($type === 'separator') {
                    $key = $element['key'] ?? '';
                    if ($key !== '') {
                        $resolved_parts[] = self::getSeparatorChar($key);
                    }
                } elseif ($type === 'variable') {
                    $key = $element['key'] ?? '';
                    if ($key !== '') {
                        $found = false;
                        foreach ($variables as $var) {
                            if ($var['key'] === $key) {
                                $resolved_parts[] = html_entity_decode((string) $var['value']);
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $resolved_parts[] = '';
                        }
                    }
                }
            }

            $resolved = implode('', $resolved_parts);
        } else {
            $resolved = preg_replace_callback(
                '/{% (variable|separator):([a-zA-Z0-9_-]+) %}/',
                function ($matches) use ($variables) {
                    $type = $matches[1];
                    $key = $matches[2];

                    if ($type === 'separator') {
                        return self::getSeparatorChar($key);
                    }

                    if ($type === 'variable') {
                        foreach ($variables as $var) {
                            if ($var['key'] === $key) {
                                return html_entity_decode((string) $var['value']);
                            }
                        }
                    }

                    return '';
                },
                $template
            );
        }

        return trim(preg_replace('/\s+/', ' ', $resolved));
    }

    /**
     * Map a separator key (as used in the React `SeparatorOptions` list) to its character.
     *
     * @param string $key The separator key.
     *
     * @return string The separator character, or '-' as a fallback.
     */
    private static function getSeparatorChar(string $key): string {
        $separators = [
            'angle_double' => '»',
            'angle_single' => '›',
            'asterisk' => '*',
            'backslash' => '\\',
            'bullet' => '•',
            'colon' => ':',
            'dash' => '-',
            'dot' => '·',
            'ellipsis' => '…',
            'em_dash' => '—',
            'en_dash' => '–',
            'equals' => '=',
            'pipe' => '|',
            'plus' => '+',
            'slash' => '/',
            'tilde' => '~',
        ];

        return $separators[$key] ?? '-';
    }

    public static function parseLegacyTemplateToArray(string $template): array
    {
        $trimmed = trim($template);
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            try {
                $decoded = json_decode($template, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (Throwable $e) {
            }
        }

        if ($template === '') {
            return [];
        }

        $pattern = '/({% (variable|separator):([a-zA-Z0-9_-]+) %})/';
        $parts = preg_split($pattern, $template, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = [];
        $i = 0;
        while ($i < count($parts)) {
            $text = $parts[$i];
            if ($text !== '') {
                $result[] = [
                    'type'    => 'text',
                    'content' => $text,
                ];
            }
            if (isset($parts[$i + 1])) {
                $type = $parts[$i + 2];
                $key = $parts[$i + 3];
                $result[] = [
                    'type' => $type,
                    'key'  => $key,
                ];
                $i += 4;
            } else {
                $i++;
            }
        }

        return $result;
    }

    /**
     * Sanitize structured template input (JSON array or legacy string).
     *
     * @param mixed $template   The template to sanitize.
     * @param bool  $isTextArea Whether the content is from a textarea (preserves newlines).
     *
     * @return string Sanitized JSON string or plain text.
     */
    public static function sanitizeTemplateInput($template, bool $isTextArea = false): string
    {
        if (is_array($template)) {
            $sanitized = [];
            foreach ($template as $element) {
                if (!is_array($element) || !isset($element['type'])) {
                    continue;
                }
                $type = sanitize_text_field($element['type']);
                $item = ['type' => $type];
                if ($type === 'text') {
                    $content = (string) ($element['content'] ?? '');
                    $item['content'] = self::sanitizeTemplateTextContent($content, $isTextArea);
                } elseif ($type === 'separator' || $type === 'variable') {
                    $item['key'] = sanitize_text_field($element['key'] ?? '');
                }
                $sanitized[] = $item;
            }
            return wp_json_encode($sanitized);
        }

        if (is_string($template)) {
            $trimmed = trim($template);
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                try {
                    $decoded = json_decode($template, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return self::sanitizeTemplateInput($decoded, $isTextArea);
                    }
                } catch (Throwable $e) {
                }
            }
            return $isTextArea ? sanitize_textarea_field($template) : sanitize_text_field($template);
        }

        return '';
    }

    /**
     * Sanitize the content of a structured-template text element while
     * preserving user-authored whitespace. Spacing around variables and
     * separators lives in dedicated text nodes (e.g. `{"type":"text",
     * "content":"  "}`), where every space is significant — but
     * sanitize_text_field() trims edges and collapses space runs to one.
     *
     * Mirrors _sanitize_text_fields() (invalid UTF-8, tag stripping,
     * percent-encoded octet removal, newline/tab normalization for
     * single-line fields) without the space collapsing and trimming.
     *
     * @param string $content    The content to sanitize.
     * @param bool   $isTextArea Whether to preserve newlines and tabs.
     *
     * @return string Sanitized content.
     */
    public static function sanitizeTemplateTextContent(string $content, bool $isTextArea): string
    {
        $filtered = (string) wp_check_invalid_utf8($content);

        if (str_contains($filtered, '<')) {
            $filtered = wp_pre_kses_less_than($filtered);
            $filtered = wp_strip_all_tags($filtered, false);
            $filtered = str_replace("<\n", "&lt;\n", $filtered);
        }

        if (!$isTextArea) {
            $filtered = preg_replace('/[\r\n\t]/', ' ', $filtered);
        }

        while (preg_match('/%[a-f0-9]{2}/i', $filtered, $match)) {
            $filtered = str_replace($match[0], '', $filtered);
        }

        return $filtered;
    }


	/**
	     * Determine the current screen in WordPress admin.
	     *
	     * @return WP_Screen|false The current screen object, or false if conditions are not met.
	     */
	public static function determine_screen(): false|WP_Screen {
		// Confirm that the current environment is admin and the required function exists.
		if (is_admin() && function_exists('get_current_screen')) {
			$screen = get_current_screen();
			// get_current_screen() can return null in AJAX context, so ensure we return false
			return $screen ?: false;
		}

		// Return false if conditions are not met.
		return false;
	}

	/**
	 * Validates if the current admin screen matches the specified identifier.
	 *
	 * This method checks if the current WordPress admin screen matches a given identifier.
	 * It supports both exact matching and prefix-based matching of screen base names.
	 *
	 * @param string $targetScreen    The screen identifier to check against.
	 * @param string $matchingMode    The comparison mode ('prefix' for prefix matching, empty for exact matching).
	 *
	 * @return bool Returns true if:
	 *                 - In exact matching mode (empty $matchingMode): the screen base exactly matches $targetScreen
	 *                 - In prefix mode: the screen base starts with $targetScreen
	 *                 Returns false if:
	 *                 - Current screen cannot be determined
	 *                 - Screen base property is not set
	 *                 - No match is found
	 */
	public static function validate_admin_screen( string $targetScreen, string $matchingMode = ''): bool {
		$currentScreen = self::determine_screen();

		if (empty($currentScreen) || !isset($currentScreen->base)) {
			return false;
		}

		if ($matchingMode === 'beginWith') {
			return stripos($currentScreen->base, $targetScreen) === 0;
		}

		return $currentScreen->base === $targetScreen;
	}

	/**
     * Retrieves the appropriate WordPress post object based on various context conditions.
     *
     * The function is context-aware and handles special cases like static front pages
     * and separate blog pages as configured in WordPress settings.
	 *
	 * @param int|WP_Post|null $input The post identifier. Can be:
	 *                                    - Post ID (integer)
	 *                                    - WP_Post object
	 *
	 * @return int|null Returns:
	 *                      - WP_Post object if a post is found
	 *                      - null if no appropriate post could be determined
	 */
	public static function extract_post_id( int|WP_Post|null $input): int|null {
		return ($input instanceof WP_Post) ? $input->ID : $input;
	}

	/**
     * Retrieves the appropriate WordPress post object based on various context conditions.
     *
     * The function is context-aware and handles special cases like static front pages
     * and separate blog pages as configured in WordPress settings.
	 *
	 */
	public static function get_special_page_by_type(): array|WP_Post|null {
		if (is_front_page()) {
			return get_post((int) get_option('page_on_front'));
		}

		if (is_home()) {
			return get_post((int) get_option('page_for_posts'));
		}

		return null;
	}

	/**
     * Retrieves the appropriate WordPress post object based on various context conditions.
     *
     * The function is context-aware and handles special cases like static front pages
     * and separate blog pages as configured in WordPress settings.
	 *
	 */
	public static function handle_special_pages(): array|WP_Post|null {
		if (!is_front_page() && !is_home()) {
			return null;
		}

		$frontPageType = get_option('show_on_front');
		if ($frontPageType !== 'page') {
			return null;
		}

		return self::get_special_page_by_type();
	}

	/**
	 * Retrieves the appropriate WordPress post object based on various context conditions.
	 *
	 * The function is context-aware and handles special cases like static front pages
	 * and separate blog pages as configured in WordPress settings.
	 *
	 * @param int|WP_Post|null $identifier The post identifier. Can be:
	 *                                      - Post ID (integer)
	 *                                      - WP_Post object
	 *                                      - false for automatic detection
	 *
	 * @return array|WP_Post|null Returns:
	 *                      - WP_Post object if a post is found
	 *                      - null if no appropriate post could be determined
	 */
	public static function retrieve_post( int|WP_Post|null $identifier = null, bool $returnAsArray = false ): array|WP_Post|null {
		$postId = self::extract_post_id($identifier);

		if ( is_front_page() || is_home() ) {
			return self::handle_special_pages();
		}

		$filteredPostId = apply_filters( 'rankingcoach_wp_post/post_id', $postId );
		if ( !self::validate_admin_screen('post') && !$filteredPostId && !is_singular()) {
			return null;
		}

        $output = OBJECT;
        if($returnAsArray) {
            $output = ARRAY_A;
        }
		return get_post($filteredPostId, $output);
	}

	/**
     * Retrieves the post type title for a given post type.
     */
	public static function determine_post_type_name($typeObject, $defaultText) {
		// Check for valid post type object first
		if ($typeObject && !empty($typeObject->labels->name)) {
			return $typeObject->labels->name;
		}

		// Try singular name if regular name isn't available
		if ($typeObject && !empty($typeObject->labels->singular_name)) {
			return $typeObject->labels->singular_name;
		}

		// Last - use default text
		return $defaultText;
	}

	/**
	 * Retrieves the post type description for a given post type.
	 */
	public static function determine_post_type_description($typeObject, $defaultText): string {
		// Ensure a valid post type object is provided
		if ($typeObject && isset($typeObject->description) && !empty($typeObject->description)) {
			return CoreHelper::prepare_string(trim($typeObject->description));
		}

		// Fallback to the name label if the description is not available
		if ($typeObject && !empty($typeObject->labels->name)) {
			return CoreHelper::prepare_string(trim($typeObject->labels->name));
		}

		// Fallback to the singular name if neither description nor name is available
		if ($typeObject && !empty($typeObject->labels->singular_name)) {
			return CoreHelper::prepare_string(trim($typeObject->labels->singular_name));
		}

		// Use the default text if all else fails
		return CoreHelper::prepare_string(trim($defaultText));
	}

	/**
	 * Retrieve the default title for the post type.
	 *
	 * @param  string $typeSlug The post type.
	 * @param  string $defaultText The default text.
	 *
	 * @return string           The title.
	 *
	 */
	public static function retrieve_post_type_title( string $typeSlug, string $defaultText = '' ): string {
		// Static cache to store resolved labels
		static $labelCache = [];

		// Return cached value if available
		if (isset($labelCache[$typeSlug])) {
			return $labelCache[$typeSlug];
		}

		// Attempt to get post type object
		$typeObject = get_post_type_object($typeSlug);

		// Resolve label using multiple fallback options
		$resolvedLabel = self::determine_post_type_name($typeObject, $defaultText);

		// Cache the result
		$labelCache[$typeSlug] = $resolvedLabel;

		return $resolvedLabel;
	}

    /**
     * Retrieves the title of a given post with fallback options.
     *
     * @param int|WP_Post|null $targetPost The post object or ID. Null defaults to the current global post.
     * @param bool $useDefault Whether to return a default title if no custom title is found.
     * @return string The resolved post title.
     * @throws Exception
     */
	public static function retrieve_post_title( int|WP_Post|null $targetPost, bool $useDefault = false ): string {
		// Fetch the post object if only the ID is provided, or it's null.
		$resolvedPost = $targetPost && is_object($targetPost)
			? $targetPost
			: self::retrieve_post($targetPost);

		// Return an empty string if the provided object is not a valid WP_Post instance.
		if (!$resolvedPost instanceof WP_Post) {
			return '';
		}

		// Static cache to avoid redundant processing for the same post.
		static $postTitleCache = [];
		if (isset($postTitleCache[$resolvedPost->ID])) {
			return $postTitleCache[$resolvedPost->ID];
		}

		// TODO title from SEO meta-data
		// ================================
		// extract SEO meta-data here and put it into $title based on customer custom formatting
        // Initialize title variable.
        $computedTitle = ModuleManager::instance()->metaTags()->getMetaTitle($resolvedPost->ID);

		// Use the blog's name if this is the static front page.
		if (empty($computedTitle)
		    && 'page' === get_option('show_on_front')
		    && (int) get_option('page_on_front') === $resolvedPost->ID) {

			$computedTitle = CoreHelper::decode_html_entities(get_bloginfo('name'));
		}

		// Use the WordPress default title format if no custom title is found.
		if (empty($computedTitle)) {
			$defaultTitle = get_the_title($resolvedPost->ID) . ' - ' . get_bloginfo('name');
			$computedTitle = CoreHelper::decode_html_entities($defaultTitle);
		}

		// Fallback to post type title resolution if the title is still empty.
		if (empty($computedTitle)) {
			$computedTitle = CoreHelper::prepare_string(
				self::retrieve_post_type_title($resolvedPost->post_type)
			);
		}

		// Cache the computed title for the post ID to avoid duplicate calculations.
		$postTitleCache[$resolvedPost->ID] = $computedTitle;

		return $postTitleCache[$resolvedPost->ID];
	}

	/**
	 * Generates the title for the homepage.
	 *
	 * @return string The homepage title, resolved based on settings and WPML translation (if applicable).
	 */
	public static function retrieve_home_page_title(): string {
		// Handle the case where a static front page is set.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$homePageTitle = self::retrieve_post_title( (int) get_option( 'page_on_front' ) );
			return $homePageTitle ?: CoreHelper::decode_html_entities( get_bloginfo( 'name' ) );
		}

		// Default to the site name for non-static front pages.
		$homePageTitle = get_bloginfo( 'name' );

		// Handle WPML translation if the WPML plugin is active.
		if ( class_exists( 'SitePress' ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $homePageTitle = apply_filters( 'wpml_translate_single_string', $homePageTitle);
		}

		// Ensure the title is properly formatted and decoded.
		$homePageTitle = CoreHelper::prepare_string( $homePageTitle );
		return CoreHelper::decode_html_entities( $homePageTitle );
	}

	/**
	 * Checks whether we're viewing the blog archive page configured in Settings.
	 *
	 * @param int|WP_Post|null $target Optional. Specific post to evaluate.
	 *
	 * @return bool|null Returns true for blog archive page, false if not, null if inconclusive
	 */
	public static function is_blog_archive_page( int|WP_Post|null $target = null): ?bool {
		// Cache by post ID to support multiple calls with different targets
		static $cache = [];

		// Generate cache key
		$cacheKey = $target instanceof WP_Post ? $target->ID : (int)$target;

		// Return cached result if exists
		if (isset($cache[$cacheKey])) {
			return $cache[$cacheKey];
		}

		try {
			// Get configured blog page ID from options
			$blogPageId = (int) get_option('page_for_posts');

			// Get post data
			$post = self::retrieve_post($target);

			if (!$post && $target !== null) {
				return null; // Return null if post retrieval failed
			}

			// Evaluate if current view matches blog archive criteria
			$result = (
				($blogPageId !== 0 && is_home()) ||
				( $post instanceof WP_Post && $post->ID === $blogPageId)
			);

			$cache[$cacheKey] = $result;
			return $result;
		} catch ( Exception ) {
			return null; // Handle unexpected errors gracefully
		}
	}

	/**
	 * Validates if current view represents the front page set in Reading Settings.
	 *
	 * @param null|int|WP_Post $target Optional. Post object/ID to check against
	 *
	 * @return bool|null True for front page, false otherwise, null if undetermined
	 */
	public static function is_front_page( null|int|WP_Post $target = null): ?bool {
		// Cache by post ID
		static $cache = [];

		// Generate cache key
		$cacheKey = $target instanceof WP_Post ? $target->ID : (int)$target;

		if (isset($cache[$cacheKey])) {
			return $cache[$cacheKey];
		}

		try {
			$post = self::retrieve_post($target);

			if (!$post && $target !== null) {
				return null;
			}

			$result = (
				get_option('show_on_front') === 'page' &&
				$post instanceof WP_Post &&
				$post->ID === (int) get_option('page_on_front')
			);

			$cache[$cacheKey] = $result;
			return $result;
		} catch ( Exception) {
			return null;
		}
	}


	/**
	 * Determines if current page is configured as a static page in WordPress settings.
	 *
	 * @param int|WP_Post|null $target Optional. Post to check
	 *
	 * @return bool True if page is either static front page or blog archive
	 */
	public static function is_static_page( int|WP_Post|null $target = null): bool {
		$isFront = self::is_front_page($target);
		$isBlog = self::is_blog_archive_page($target);
		return ($isFront === true || $isBlog === true);
	}

	/**
	 * Checks whether the current page is the dynamic homepage.
	 *
	 * @return bool Whether the current page is the dynamic homepage.
	 */
	public static function is_dynamic_home_page(): bool {
		return is_front_page() && is_home();
	}

	/**
	 * Returns the term title.
	 *
	 * @param WP_Term $term    The term object.
	 * @param bool $default Whether we want the default value, not the post one.
	 *
	 * @return string            The term title.
	 */
	public static function retrieve_term_title( WP_Term $term, bool $default = false ): string {

        static $terms = [];
		if ( isset( $terms[ $term->term_id ] ) ) {
			return $terms[ $term->term_id ];
		}

		$newTitle = CoreHelper::sanitize_regex_pattern( $term->name );
		$title    = CoreHelper::prepare_string( $newTitle, $term->term_id, $default );

		$terms[ $term->term_id ] = $title;

		return $terms[ $term->term_id ];
	}

	/**
	 * Retrieve the default title for the archive template.
	 *
	 * @param string $postType The custom post type.
	 *
	 * @return string           The title.
	 */
	public static function retrieve_archive_title( string $postType ): string {
		static $archiveTitle = [];
		if ( isset( $archiveTitle[ $postType ] ) ) {
			return $archiveTitle[ $postType ];
		}

		// Get the post type object to retrieve its labels.
		$postTypeObject = get_post_type_object( $postType );
		$title = '';

		if ( $postTypeObject ) {
			// Check if the post type has an archive and use its label.
			$title = $postTypeObject->has_archive
				? ($postTypeObject->labels?->archive_title ?? $postTypeObject->labels->archives)
				: $postTypeObject->labels->name;
		}

		// Cache the title for future use.
		$archiveTitle[ $postType ] = empty( $title ) ? '' : $title;

		return $archiveTitle[ $postType ];
	}


    /**
     * Gets the title for a given post.
     * @throws Exception
     */
	public static function retrieve_title($post = null, $default = false): string {
		// Front page handling
		if (is_home()) {
			$blogName = self::retrieve_home_page_title();
			return wp_strip_all_tags(trim($blogName));
		}

		// Single content pages (posts, pages, custom types)
		if ($post instanceof WP_Post || is_singular() || is_page()) {
			return self::retrieve_post_title( $post, $default );
		}

		// Taxonomy archives
		if ( is_category() || is_tax() || is_tag() ) {
			$taxonomyObject = $post ?: get_queried_object();
			return self::retrieve_term_title( $taxonomyObject, $default );
		}

		// Author archives
		if (is_author()) {
			$authorName = get_the_author();
			return sprintf(
			/* translators: %s: author name */
				esc_html__('Written by %s', 'beyondseo'),
				wp_strip_all_tags(trim($authorName))
			);
		}

		// Date-based archives
		if (is_date()) {
			$archiveDate = get_the_date();
			return sprintf(
			/* translators: %s: date */
				esc_html__('Content from %s', 'beyondseo'),
				wp_strip_all_tags(trim($archiveDate))
			);
		}

		// Search results
		if (is_search()) {
			$queryTerm = get_search_query();
			return sprintf(
			/* translators: %s: search term */
				esc_html__('Found results for: %s', 'beyondseo'),
				wp_strip_all_tags(trim($queryTerm))
			);
		}

		// Tag archives (specific handling)
		if (is_tag()) {
			$tagTitle = single_tag_title('', false);
			return sprintf(
			/* translators: %s: tag name */
				esc_html__('Tagged: %s', 'beyondseo'),
				wp_strip_all_tags(trim($tagTitle))
			);
		}

		if ( is_post_type_archive() ) {
			$postType = get_queried_object();
			if ($postType instanceof WP_Post_Type) {
				return CoreHelper::prepare_string( self::retrieve_archive_title( $postType->name ) );
			}
		}

		return '';
	}

	/**
	 * Retrieve the description.
	 */
	public static function retrieve_description($post = null, $default = false): string {
		// Front page handling
		if (is_home()) {
			$blogDescription = self::retrieve_home_page_description();
			return wp_strip_all_tags(trim($blogDescription));
		}

		// Single content pages (posts, pages, custom types)
		if ($post instanceof WP_Post || is_singular() || WordpressHelpers::is_static_page()) {
			$description = self::retrieve_post_description($post, $default);
			if ($description) {
				return $description;
			}

			if (is_attachment()) {
				$post = empty($post) ? self::retrieve_post() : $post;
				$caption = wp_get_attachment_caption($post->ID);

				return $caption ? CoreHelper::prepare_string(trim($caption)) : CoreHelper::prepare_string(trim($post->post_content));
			}
		}

		// Taxonomy archives
		if (is_category() || is_tax() || is_tag()) {
			$taxonomyObject = $post ?: get_queried_object();
			return self::retrieve_term_description($taxonomyObject);
		}

		// Search results
		if (is_search()) {
			$queryTerm = get_search_query();
			return sprintf(
			/* translators: %s: search term */
				esc_html__('Search results for: %s', 'beyondseo'),
				wp_strip_all_tags(trim($queryTerm))
			);
		}

		// Post type archives
		if (is_post_type_archive()) {
			$postType = get_queried_object();
			if ($postType instanceof WP_Post_Type) {
				return self::retrieve_archive_description($postType->name);
			}
		}

		return '';
	}

	/**
     * Retrieve the description for the homepage.
     */
	public static function retrieve_home_page_description(): string {
		// Handle the case where a static front page is set.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$homePageDescription = self::retrieve_post_description( (int) get_option( 'page_on_front' ) );
			return $homePageDescription ?: CoreHelper::decode_html_entities( get_bloginfo( 'description' ) );
		}

		// Default to the site name for non-static front pages.
		$homePageDescription = get_bloginfo( 'description' );

		// Handle WPML translation if the WPML plugin is active.
		if ( class_exists( 'SitePress' ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$homePageDescription = apply_filters( 'wpml_translate_single_string', $homePageDescription);
		}

		// Ensure the title is properly formatted and decoded.
		$homePageDescription = CoreHelper::prepare_string( $homePageDescription );
		return CoreHelper::decode_html_entities( $homePageDescription );
	}

	/**
     * Retrieve the description for a given post.
     */
	public static function retrieve_post_description($targetPost = null, $useDefault = false): string {
		// Fetch the post object if only the ID is provided, or it's null.
		$resolvedPost = $targetPost && is_object($targetPost)
			? $targetPost
			: self::retrieve_post($targetPost);

		// Return an empty string if the provided object is not a valid WP_Post instance.
		if (!$resolvedPost instanceof WP_Post) {
			return '';
		}

		// Static cache to avoid redundant processing for the same post.
		static $postDescriptionCache = [];
		if (isset($postDescriptionCache[$resolvedPost->ID])) {
			return $postDescriptionCache[$resolvedPost->ID];
		}

		// Initialize description variable.
		$computedDescription = '';

		// TODO description from SEO meta-data
		// ================================
		// extract SEO meta-data here and put it into $computedDescription based on customer custom formatting

		// Use the blog's name if this is the static front page.
		if (empty($computedDescription)
		    && 'page' === get_option('show_on_front')
		    && (int) get_option('page_on_front') === $resolvedPost->ID) {

			$computedDescription = CoreHelper::decode_html_entities(get_bloginfo('description'));
		}

		//retrieve_description_from_content
		if (empty($computedDescription)) {
			$computedDescription = self::retrieve_description_from_content($resolvedPost);
			$computedDescription = CoreHelper::decode_html_entities($computedDescription);
		}

		// Use the WordPress default title format if no custom title is found.
		if (empty($computedDescription)) {
			$computedDescription = get_bloginfo('description');
			$computedDescription = CoreHelper::decode_html_entities($computedDescription);
		}

		// Fallback to post type title resolution if the title is still empty.
		if (empty($computedDescription)) {
			$computedDescription = CoreHelper::prepare_string(
				self::retrieve_post_type_description($resolvedPost->post_type)
			);
		}

		// Cache the computed description for the post ID to avoid duplicate calculations.
		$postDescriptionCache[$resolvedPost->ID] = $computedDescription;

		return $postDescriptionCache[$resolvedPost->ID];
	}

	public static function retrieve_post_type_description( string $typeSlug, string $defaultText = '' ): string {
		// Static cache to store resolved labels
		static $labelCache = [];

		// Return cached value if available
		if (isset($labelCache[$typeSlug])) {
			return $labelCache[$typeSlug];
		}

		// Attempt to get post type object
		$typeObject = get_post_type_object($typeSlug);

		// Resolve label using multiple fallback options
		$resolvedLabel = self::determine_post_type_description($typeObject, $defaultText);

		// Cache the result
		$labelCache[$typeSlug] = $resolvedLabel;

		return $resolvedLabel;
	}

	/**
     * Retrieve the description for a given term.
     */
	public static function retrieve_term_description(int|WP_Term|null $term = null): string {
		return CoreHelper::prepare_string(term_description( $term ));
	}

	/**
     * Retrieve the description for a given archive page.
     */
	public static function retrieve_archive_description($postType = null): string {
		return '';
	}

	/**
	 * Returns the description based on the post content.
	 *
	 * @param WP_Post|int $post The post (optional).
	 * @return string             The description.
	 */
	public static function retrieve_description_from_content( $post = null ): string {
		$post = $post instanceof WP_Post ? $post : self::retrieve_post( $post );

		// Null safety check - return empty string if post retrieval failed
		if (!$post instanceof WP_Post) {
			return '';
		}

		static $content = [];
		if ( isset( $content[ $post->ID ] ) ) {
			return $content[ $post->ID ];
		}

		$content[ $post->ID ] = '';
		if ( ! empty( $post->post_password ) ) {
			return $content[ $post->ID ];
		}

		// Raw content only: rendering blocks/shortcodes outside The Loop breaks page builders (Divi static CSS cache).
		$postContent = (string) $post->post_content;

		// Strip images, captions and WP oembed wrappers (e.g. YouTube URLs) from the post content.
		$postContent          = preg_replace( '/(<figure.*?\/figure>|<img.*?\/>|<div.*?class="wp-block-embed__wrapper".*?>.*?<\/div>)/s', '', $postContent );
		$postContent          = str_replace( ']]>', ']]&gt;', (string) $postContent );
		$postContent          = self::extract_text_from_content( (string) $postContent );
		$content[ $post->ID ] = wp_trim_words( $postContent, 55, '' );

		return $content[ $post->ID ];
	}

	/**
	 * Plain text of post content without rendering it (safe outside The Loop).
	 *
	 * @param string $content Raw post content.
	 *
	 * @return string
	 */
	public static function extract_text_from_content( string $content ): string {
		if ( has_blocks( $content ) ) {
			$content = self::extract_text_from_blocks( parse_blocks( $content ) );
		}

		// Remove shortcode tags, registered or not, but keep the text they wrap.
		$content = (string) preg_replace( '/\[\/?[\w-]+(?:\s[^\]]*)?\]/', ' ', $content );

		return trim( wp_strip_all_tags( $content, true ) );
	}

	/**
	 * @param array $blocks Output of parse_blocks().
	 *
	 * @return string
	 */
	private static function extract_text_from_blocks( array $blocks ): string {
		$text = '';
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$text .= self::extract_text_from_block_attrs( $block['attrs'] );
			}
			if ( ! empty( $block['innerHTML'] ) ) {
				$text .= ' ' . $block['innerHTML'];
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$text .= ' ' . self::extract_text_from_blocks( $block['innerBlocks'] );
			}
		}

		return $text;
	}

	/**
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private static function extract_text_from_block_attrs( array $attrs ): string {
		$text = '';
		foreach ( $attrs as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( 'innerContent' === $key ) {
				$desktop = $value['desktop']['value'] ?? null;
				// Skip empty values and bare URLs (audio/video sources, links).
				if ( is_string( $desktop ) && '' !== trim( $desktop ) && ! preg_match( '~^\s*[a-z][a-z0-9+.-]*://\S+\s*$~i', $desktop ) ) {
					$text .= ' ' . $desktop;
				}
				continue;
			}
			$text .= self::extract_text_from_block_attrs( $value );
		}

		return $text;
	}

	/**
	 *
	 * @param $post
	 *
	 * @return string
	 */
	public static function retrieve_post_content( $post = null ): string {
		$post = $post instanceof WP_Post ? $post : self::retrieve_post( $post );

		// Null safety check - return empty string if post retrieval failed
		if (!$post instanceof WP_Post) {
			return '';
		}

		static $content = [];
		if ( isset( $content[ $post->ID ] ) ) {
			return $content[ $post->ID ];
		}

		// We need to process the content for page builders.
		$postContent = $post->post_content;

		$postContent = is_string( $postContent ) ? $postContent : '';

		$content[ $post->ID ] = self::process_the_post_content( $postContent );

		return $content[ $post->ID ];
	}

    /**
     * @throws Exception
     */
    public static function process_the_post_content($postContent = null ): string {
		global $wp_query, $post;

		// Clone for backup
		$originalQuery = unserialize( serialize( $wp_query ) );
		$originalPost  = is_a( $post, 'WP_Post' ) ? unserialize( serialize( $post ) ) : null;

		// The order of the function calls below is intentional and should NOT change.
		$postContent = do_blocks( $postContent );
		$postContent = wpautop( $postContent );
		$postContent = self::do_shortcodes( $postContent );

		// Restore
		if ($originalQuery instanceof WP_Query) {
			// Loop over all properties and replace the ones that have changed.
			// We want to avoid replacing the entire object because it can cause issues with other plugins.
			foreach ( $originalQuery as $key => $value ) {
				if ( $value !== $wp_query->{$key} ) { // phpcs:ignore Squiz.NamingConventions.ValidVariableName
					$wp_query->{$key} = $value; // phpcs:ignore Squiz.NamingConventions.ValidVariableName
				}
			}
		}

		if ($originalPost instanceof WP_Post) {
			foreach ( $originalPost as $key => $value ) {
				if ( $value !== $post->{$key} ) {
					$post->{$key} = $value;
				}
			}
		}

		return $postContent;
	}

	/**
	 * @throws Exception
	 */
	public static function do_shortcodes( $content = null, $override = false, $postId = 0 ): string {
		if ( ! $override && is_admin() ) {
			return $content;
		}

        /** @var SettingsManager $optionManager */
        $options = SettingsManager::instance()->get_options();

		if ( ! wp_doing_cron() && ! wp_doing_ajax() ) {
			if ( ! $override && ! $options['run_shortcodes'] ) {
				return self::retrieve_allowed_shortcodes( $content, $postId );
			}
		}

		return self::do_shortcodes_helper( $content, [], $postId );
	}

	/**
	 * @param $content
	 * @param $postId
	 * @param array $allowedTags
	 *
	 * @return mixed
	 */
	public static function retrieve_allowed_shortcodes($content, $postId = null, array $allowedTags = [] ): mixed
    {

		$getShortcodeTags = static function($content) use (&$getShortcodeTags) {
			$tags    = [];
			$pattern = '\\[(\\[?)([^\s]*)(?![\\w-])([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)(\\]?)';
			if ( preg_match_all( "#$pattern#s", (string) $content, $matches ) && array_key_exists( 2, $matches ) ) {
				$tags = array_unique( $matches[2] );
			}

			if ( ! count( $tags ) ) {
				return $tags;
			}

			// Extract nested shortcodes.
			foreach ( $matches[5] as $innerContent ) {
				$tags = array_merge( $tags, $getShortcodeTags( $innerContent ) );
			}

			return $tags;
		};

		$tags = $getShortcodeTags( $content );
		if ( ! count( $tags ) ) {
			return $content;
		}

		$tagsToRemove = array_diff( $tags, $allowedTags );

        return self::do_shortcodes_helper( $content, $tagsToRemove, $postId );
	}

	/**
	 * Returns the content with only the allowed shortcodes and wildcards replaced.
	 *
	 * @param  string $content      The content.
	 * @param  array  $tagsToRemove The shortcode tags to remove (optional).
	 * @param  int    $postId       The post ID (optional).
	 * @return string               The content with shortcodes replaced.
	 */
	public static function do_shortcodes_helper( $content, $tagsToRemove = [], $postId = 0 ) {
		global $shortcode_tags;
		$conflictingShortcodes = [
			'WooCommerce Login'                => 'woocommerce_my_account',
			'WooCommerce Checkout'             => 'woocommerce_checkout',
			'WooCommerce Order Tracking'       => 'woocommerce_order_tracking',
			'WooCommerce Cart'                 => 'woocommerce_cart',
			'WooCommerce Registration'         => 'wwp_registration_form',
			'WISDM Group Registration'         => 'wdm_group_users',
			'WISDM Quiz Reporting'             => 'wdm_quiz_statistics_details',
			'WISDM Course Review'              => 'rrf_course_review',
			'Simple Membership Login'          => 'swpm_login_form',
			'Simple Membership Mini Login'     => 'swpm_mini_login',
			'Simple Membership Payment Button' => 'swpm_payment_button',
			'Simple Membership Thank You Page' => 'swpm_thank_you_page_registration',
			'Simple Membership Registration'   => 'swpm_registration_form',
			'Simple Membership Profile'        => 'swpm_profile_form',
			'Simple Membership Reset'          => 'swpm_reset_form',
			'Simple Membership Update Level'   => 'swpm_update_level_to',
			'Simple Membership Member Info'    => 'swpm_show_member_info',
			'Revslider'                        => 'rev_slider'
		];

		$tagsToRemove = [];
		foreach ( $conflictingShortcodes as $shortcode ) {
			$shortcodeTag = str_replace( [ '[', ']' ], '', $shortcode );
			if ( array_key_exists( $shortcodeTag, $shortcode_tags ) ) {
				$tagsToRemove[ $shortcodeTag ] = $shortcode_tags[ $shortcodeTag ];
			}
		}

		// Remove all conflicting shortcodes before parsing the content.
		foreach ( $tagsToRemove as $shortcodeTag => $shortcodeCallback ) {
			remove_shortcode( $shortcodeTag );
		}

		if ( $postId ) {
			global $post;
			$post = get_post( $postId );
			if ( is_a( $post, 'WP_Post' ) ) {
				// Add the current post to the loop so that shortcodes can use it if needed.
				setup_postdata( $post );
			}
		}

		$content = do_shortcode( $content );

		if ( $postId ) {
			wp_reset_postdata();
		}

		// Add back shortcodes as remove_shortcode() disables them site-wide.
		foreach ( $tagsToRemove as $shortcodeTag => $shortcodeCallback ) {
			add_shortcode( $shortcodeTag, $shortcodeCallback );
		}

		return $content;
	}

	/**
	 * Checks if current request is administrative
	 */
	public static function is_admin_request(): bool {
		return is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)
        //    || (defined('WP_CLI') && WP_CLI)
        ;
	}

	/**
	 * Determines the character encoding for the site
	 */
	public static function determine_encoding(): string {
		return get_option('blog_charset') ?: 'UTF-8';
	}

    /**
     * Checks if the account activation process has been completed.
     * @return bool
     */
    public static function isActivationCompleted(): bool {

        // Check if the activation process has been completed
        $accountId = (bool)get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, false);
        $projectId = (bool)get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, false);
        $locationId = (bool)get_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID, false);
        $refCode = (bool)get_option(BaseConstants::OPTION_REFRESH_TOKEN, false);

        return $accountId && $projectId && $locationId && $refCode;
    }

    /**
     * Whether this install was provisioned through a partner integration.
     *
     * Partner-provisioned installs must use the activation-code flow exclusively;
     * the email registration flow is hidden and its endpoints are refused.
     * Only explicit truthy representations (true, 1, '1', 'true', 'on', 'yes')
     * enable it — anything else, including a missing option, stays false.
     *
     * @return bool
     */
    public static function isPartnerIntegration(): bool {
        return filter_var(
            get_option( BaseConstants::OPTION_PARTNER_INTEGRATION, false ),
            FILTER_VALIDATE_BOOLEAN
        );
    }

	/**
	 * Checks if the onboarding process has been completed successfully.
	 * 
	 * This method validates both internal WordPress onboarding and external 
	 * RankingCoach application onboarding completion status. It ensures that
	 * both onboarding processes have been marked as completed with valid timestamps.
	 * 
	 * The method is designed to be used throughout the plugin to determine if
	 * onboarding-dependent features should be activated, such as:
	 * - SEO analysis hooks registration
	 * - Content management features
	 * - API integrations
	 * - Admin interface components
	 * 
	 * @param bool $requireBoth Whether both internal and external onboarding must be completed (default: true)
	 * @param bool $useCache Whether to use static caching for performance optimization (default: true)
	 * @return bool True if onboarding is completed according to the specified criteria, false otherwise
	 * 
	 * @since 1.0.0
	 */
	public static function isOnboardingCompleted(bool $requireBoth = true, bool $useCache = false): bool {
		// Static cache to avoid redundant database queries
		static $cache = [];
		
		// Generate cache key based on parameters
		$cacheKey = $requireBoth ? 'both' : 'either';
		
		// Return cached result if available and caching is enabled
		if ($useCache && isset($cache[$cacheKey])) {
			return $cache[$cacheKey];
		}
		
		// Check internal WordPress onboarding completion
		$internalOnboardingCompleted = self::isInternalOnboardingCompleted();
		
		// Check external RankingCoach application onboarding completion
		$externalOnboardingCompleted = self::isExternalOnboardingCompleted();
		
		// Determine result based on requirements
		$result = $requireBoth 
			? ($internalOnboardingCompleted && $externalOnboardingCompleted)
			: ($internalOnboardingCompleted || $externalOnboardingCompleted);
		
		// Cache the result if caching is enabled
		if ($useCache) {
			$cache[$cacheKey] = $result;
		}
		
		return $result;
	}
	
	/**
	 * Checks if the internal WordPress onboarding has been completed.
	 * 
	 * This validates that the WordPress-specific onboarding process has been
	 * completed and has a valid timestamp indicating when it was finished.
	 * 
	 * @return bool True if internal onboarding is completed with valid timestamp, false otherwise
	 * 
	 * @since 1.0.0
	 */
	public static function isInternalOnboardingCompleted(): bool {
		$onboardingFlag = (bool)get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP, false);
		$onboardingTimestamp = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null);
		
		return $onboardingFlag === true && !empty($onboardingTimestamp) && is_numeric($onboardingTimestamp);
	}
	
	/**
	 * Checks if the external RankingCoach application onboarding has been completed.
	 * 
	 * This validates that the RankingCoach application-specific onboarding process
	 * has been completed and has a valid timestamp indicating when it was finished.
	 * 
	 * @return bool True if external onboarding is completed with valid timestamp, false otherwise
	 * 
	 * @since 1.0.0
	 */
	public static function isExternalOnboardingCompleted(): bool {
		$onboardingFlag = (bool)get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC, false);
		$onboardingTimestamp = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, null);
		
		return $onboardingFlag === true && !empty($onboardingTimestamp) && is_numeric($onboardingTimestamp);
	}
	
	/**
	 * Gets the onboarding completion timestamps.
	 * 
	 * Returns an array containing the completion timestamps for both internal
	 * and external onboarding processes. Useful for debugging, analytics, or
	 * determining the order of completion.
	 * 
	 * @return array{
	 *     internal: int|null,
	 *     external: int|null,
	 *     internal_completed: bool,
	 *     external_completed: bool,
	 *     both_completed: bool
	 * } Array containing completion timestamps and status flags
	 * 
	 * @since 1.0.0
	 */
	public static function getOnboardingCompletionData(): array {
		$internalTimestamp = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null);
		$externalTimestamp = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, null);
		
		$internalCompleted = self::isInternalOnboardingCompleted();
		$externalCompleted = self::isExternalOnboardingCompleted();
		
		return [
			'internal' => is_numeric($internalTimestamp) ? (int)$internalTimestamp : null,
			'external' => is_numeric($externalTimestamp) ? (int)$externalTimestamp : null,
			'internal_completed' => $internalCompleted,
			'external_completed' => $externalCompleted,
			'both_completed' => $internalCompleted && $externalCompleted,
		];
	}
	
	/**
	 * Clears the onboarding completion cache.
	 * 
	 * This method should be called when onboarding status changes to ensure
	 * that subsequent calls to isOnboardingCompleted() reflect the current state.
	 * 
	 * @return void
	 * 
	 * @since 1.0.0
	 */
	public static function clearOnboardingCache(): void {
		// Clear the static cache by resetting the static variable
		// This is done by calling the method with useCache=false to reset the cache
		static $cache = [];
		$cache = [];
	}

	/**
	 * Returns the current post ID.
	 *
	 * @return int|null The post ID.
	 */
	public static function get_post_id(): ?int {
		$post = self::retrieve_post();

		return is_object( $post ) && property_exists( $post, 'ID' ) ? $post->ID : null;
	}

	/**
	 * Get the canonical URL for a post.
	 * This is a duplicate of wp_get_canonical_url() with a fix for issue #6372 where
	 * posts with paginated comment pages return the wrong canonical URL due to how WordPress sets the cpage var.
	 * We can remove this once trac ticket 60806 is resolved.
	 *
	 * @param int|WP_Post|null $post The post object or ID.
	 *
	 * @return string|false            The post's canonical URL, or false if the post is not published.
	 */
	public static function retrieve_canonical_url( int|WP_Post|null $post = null ): false|string {
		$post = self::retrieve_post( $post );

		if ( ! $post ) {
			return false;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		$canonical_url = get_permalink( $post );

		// If a canonical is being generated for the current page, make sure it has pagination if needed.
		if ( get_queried_object_id() === $post->ID ) {
			$page = get_query_var( 'page', 0 );
			if ( $page >= 2 ) {
				if ( ! get_option( 'permalink_structure' ) ) {
					$canonical_url = add_query_arg( 'page', $page, $canonical_url );
				} else {
					$canonical_url = trailingslashit( $canonical_url ) . user_trailingslashit( $page, 'single_paged ' );
				}
			}

			$cpage = get_query_var( 'cpage', null );
			$cpage = isset( $cpage ) ? (int) $cpage : false;
			if ( $cpage ) {
				$canonical_url = get_comments_pagenum_link( $cpage );
			}
		}

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( 'get_canonical_url', $canonical_url, $post );
	}

	/**
	 * @param bool $canonical
	 *
	 * @return string
	 */
	public static function retrieve_url( bool $canonical = false): string {
		global $wp, $wp_rewrite;

		$url = '';
		if ( is_singular() ) {
			$objectId = self::get_post_id();

			if ( $canonical ) {
				$url = self::retrieve_canonical_url( $objectId );
			}

			if ( ! $url ) {
				// Therefore, we must fall back to the permalink if the post isn't published, e.g. draft post or attachment (inherit).
				$url = get_permalink( $objectId );
			}
		}

		if ( $url ) {
			return $url;
		}

		// Permalink url without the query string.
		$url = user_trailingslashit( home_url( $wp->request ) );

		// If permalinks are not being used we need to append the query string to the home url.
		if ( ! $wp_rewrite->using_permalinks() ) {
			$url = home_url( ! empty( $wp->query_string ) ? '?' . $wp->query_string : '' );
		}

		return $url;
	}

	/**
	 * @return array|string
	 */
	public static function current_language_code_BCP47(): array|string {
		return str_replace( '_', '-', determine_locale() );
	}


	/**
	 * Determines if the current context is within the WordPress admin post editing screen.
	 *
	 * @return bool True if the current context is the edit or new post screen for allowed post types, false otherwise.
	 */
	public static function is_edit_post_context(): bool {
		if (!is_admin() || !function_exists('get_current_screen')) {
			return false;
		}

		// Get the current screen object.
		$current_screen = get_current_screen();
		if (!$current_screen || $current_screen->base !== 'post') {
			return false;
		}

		// Optionally restrict to specific post types if needed.
		$allowed_post_types = ['post', 'page'];
		return in_array($current_screen->post_type, $allowed_post_types, true);
	}

	/**
	 * Determines if the current context is within the WordPress admin post creation screen.
	 *
	 * This method checks if the current screen is the "Add New Post" screen in the WordPress admin area.
	 * It can be used to conditionally load scripts or styles specific to the post creation context.
	 *
	 * WordPress does not give post-new.php its own screen base: WP_Screen maps it to base `post`
	 * with action `add` (post.php is base `post` with an empty action), so this is a narrower
	 * check than is_edit_post_context(), which is true on both screens.
	 *
	 * @return bool True if the current context is the "Add New Post" screen, false otherwise.
	 */
	public static function is_add_post_context(): bool {
		if (!is_admin() || !function_exists('get_current_screen')) {
			return false;
		}

		// Get the current screen object.
		$current_screen = get_current_screen();
		if (!$current_screen || $current_screen->base !== 'post' || $current_screen->action !== 'add') {
			return false;
		}

		// Optionally restrict to specific post types if needed.
		$allowed_post_types = ['post', 'page'];
		return in_array($current_screen->post_type, $allowed_post_types, true);
	}

	/**
	 * Post title as the editor presents it.
	 *
	 * WordPress inserts every "Add New" post as an `auto-draft` row titled "Auto Draft"
	 * (get_default_post_to_edit()) and blanks that placeholder again before rendering the
	 * editor. Anything that reads the title from the database for a post the user has not
	 * saved yet (SEO title fallback, the `post_title` template variable, the analysis) must
	 * do the same, otherwise "Auto Draft" leaks into meta tags and previews.
	 *
	 * @param int|WP_Post|null $post Post ID or object; defaults to the global post.
	 * @return string The title, or an empty string for a post that has never been saved.
	 */
	public static function get_editable_post_title( int|WP_Post|null $post = null ): string {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		if ( 'auto-draft' === $post->post_status ) {
			return '';
		}

		return get_the_title( $post );
	}

    /**
     * Check if the current context is within Elementor.
     *
     * @return bool True if in Elementor context, false otherwise.
     */
    public static function is_elementor_exist(): bool
    {
        if (class_exists('\Elementor\Plugin') && Plugin::$instance) {
            return true;
        }

        return false;
    }

    /**
     * @throws JsonException
     */
    public static function get_http_scheme(): string {
        if (self::lb_ssl()) {
            return 'https';
        }

        return 'http';
    }

    /**
     * Checks if the website is behind a load balancer or proxy.
     * @return bool
     * @throws JsonException
     */

    private static function lb_ssl() {
        // 1. Native WordPress detection first
        if (function_exists('is_ssl') && is_ssl()) {
            return true;
        }

        // 2. Cloudflare visitor scheme header
        $httpCfVisitor = self::sanitize_input('SERVER', 'HTTP_CF_VISITOR');
        if (!empty($httpCfVisitor)) {
            $cfo = json_decode($httpCfVisitor, false, 512, JSON_THROW_ON_ERROR);
            if (isset($cfo->scheme) && $cfo->scheme === 'https') {
                return true;
            }
        }

        // 3. Generic proxy SSL header
        $httpXForwardedProto = self::sanitize_input('SERVER', 'HTTP_X_FORWARDED_PROTO');
        return !empty($httpXForwardedProto) && $httpXForwardedProto === 'https';
    }

    /**
     * Generate a hash of the post content and relevant SEO data
     *
     * @param int|null $postId Optional. The ID of the post to generate the hash for. If not provided, the current post will be used.
     */
    public static function generateContentHash(int|null $postId = null): string
    {
        $post = self::retrieve_post($postId);
        if ( ! $post instanceof WP_Post ) {
            return '';
        }

        $postId = $post->ID;

        // Generate a hash based on the post content and metadata.
        // Combine all relevant content that could affect SEO score
        $contentToHash = [
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'modified' => $post->post_modified,
            // Include meta fields that might affect SEO
            'meta_title' => get_post_meta($postId, MetaTags::META_SEO_TITLE, true),
            'meta_description' => get_post_meta($postId, MetaTags::META_SEO_DESCRIPTION, true),
            'focus_keyword' => get_post_meta($postId, BaseConstants::META_KEY_SEO_KEYWORDS, true),
        ];

        return hash(BaseConstants::OPTION_ANALYSIS_HASH_ALGORITHM, serialize($contentToHash));
    }

    /**
     * Check if post content has changed since last analysis
     *
     * @param int $postId The ID of the post to check
     * @return bool True if content has changed, false otherwise
     */
    public static function hasContentChanged(int $postId): bool
    {
        // Generate content hash for future comparisons
        $currentHash = self::generateContentHash($postId);
        $savedHash = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_CONTENT_HASH, true);

        if (empty($savedHash)) {
            return true; // First analysis, consider as changed
        }

        return $currentHash !== $savedHash;
    }

    /**
     * Retrieves the RankingCoach project ID from WordPress options.
     *
     * @return int The project ID, or null if not set.
     */
    public static function getProjectId(): int
    {
        return (int)get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, null);
    }

    public static function sanitize_input(
        string $source,
        string|array|null $key = null,
        array $filters = [FILTER_SANITIZE_FULL_SPECIAL_CHARS],
        ?int $validate = null,
        string $return = 'string',
        mixed $default = ''
    ): mixed {

        $source = strtoupper($source);

        // Pick source array
        $raw = match ($source) {
            //phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'GET'     => $_GET,
            //phpcs:ignore WordPress.Security.NonceVerification.Missing
            'POST'    => $_POST,
            //phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'REQUEST' => $_REQUEST,
            'SERVER'  => $_SERVER,
            'ENV'     => $_ENV,
            default   => [],
        };

        $raw = wp_unslash($raw);

        /**
         * CASE 1 — FULL ARRAY SANITIZATION
         * sanitize_input('GET')
         */
        if ($key === null) {
            return self::sanitize_recursive(
                $raw,
                $filters,
                $validate,
                $return
            );
        }

        /**
         * CASE 2 — MULTIPLE KEYS
         * sanitize_input('GET', ['id','action'])
         */
        if (is_array($key)) {
            $clean = [];
            foreach ($key as $singleKey) {
                $clean[$singleKey] = self::sanitize_input(
                    $source,
                    $singleKey,
                    $filters,
                    $validate,
                    $return,
                    $default
                );
            }
            return $clean;
        }

        /**
         * CASE 3 — SINGLE KEY
         * sanitize_input('GET', 'id')
         */
        if (!array_key_exists($key, $raw)) {
            return self::cast($default, $return);
        }

        $value = $raw[$key];

        // If input is array → recursive
        if (is_array($value)) {
            return self::sanitize_recursive(
                $value,
                $filters,
                $validate,
                $return
            );
        }

        // Scalar → normal sanitization
        return self::sanitize_value(
            $value,
            $filters,
            $validate,
            $return
        );
    }

    /**
     * Recursive sanitizer for arrays + nested arrays.
     */
    public static function sanitize_recursive(
        mixed $value,
        array $filters,
        ?int $validate = null,
        string $return = 'string'
    ): mixed {

        if (is_array($value)) {
            return array_map(static function ($v) use ($filters, $return, $validate) {
                return self::sanitize_recursive(
                    $v,
                    $filters,
                    $validate,
                    $return
                );
            }, $value);
        }

        return self::sanitize_value(
            $value,
            $filters,
            $validate,
            $return
        );
    }

    /**
     * Core scalar sanitizer — used everywhere.
     */
    private static function sanitize_value(
        mixed $value,
        array $filters,
        ?int $validate,
        string $return
    ): mixed {

        $value = (string) $value;

        // Apply sanitize filters
        foreach ($filters as $filter) {
            $filtered = filter_var($value, $filter);
            if ($filtered !== false && $filtered !== null) {
                $value = $filtered;
            }
        }

        // Optional validation
        if ($validate !== null) {
            $validated = filter_var($value, $validate);
            if ($validated !== false && $validated !== null) {
                $value = $validated;
            }
        }

        return self::cast($value, $return);
    }


    /**
     * Type casting.
     */
    private static function cast(mixed $value, string $type = 'string'): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($type)) {
            'int'   => (int) $value,
            'float' => (float) $value,
            'bool'  => (bool) $value,
            'string' => (string) $value,
            default => $value
        };
    }

    /**
     * Check if a given URL is a localhost URL.
     *
     * @param string $url The URL to check.
     * @return bool True if the URL is a localhost URL, false otherwise.
     */
    public static function isLocalhostUrl(string $url): bool
    {
        $localhostPatterns = [
            '/^http:\/\/localhost(\/|$)/i',
            '/^https:\/\/localhost(\/|$)/i',
            '/^http:\/\/127\.0\.0\.1(\/|$)/i',
            '/^http:\/\/::1(\/|$)/i',
        ];

        foreach ($localhostPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a given URL uses an IP address as the host.
     *
     * @param string $url The URL to check.
     * @return bool True if the URL uses an IP address as the host, false otherwise.
     */
    public static function isIpAddressUrl(string $url): bool
    {
        $parsedUrl = wp_parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['host'])) {
            return false;
        }

        $host = $parsedUrl['host'];
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    // ========================================
    // Multilingual Support (WPML & Polylang)
    // ========================================

    /**
     * Check if WPML is active and configured.
     *
     * @return bool True if WPML is active and properly configured.
     */
    public static function isWpmlActive(): bool
    {
        // Check for WPML core functions
        if (!function_exists('icl_object_id') && !defined('ICL_SITEPRESS_VERSION')) {
            return false;
        }

        // Check if WPML is properly configured with at least 2 languages
        global $sitepress;
        if (!$sitepress || !method_exists($sitepress, 'get_active_languages')) {
            return false;
        }

        $activeLanguages = $sitepress->get_active_languages();
        return is_array($activeLanguages) && count($activeLanguages) >= 1;
    }

    /**
     * Check if Polylang is active and configured.
     *
     * @return bool True if Polylang is active and properly configured.
     */
    public static function isPolylangActive(): bool
    {
        // Check for Polylang core functions
        if (!function_exists('pll_languages_list') || !function_exists('pll_current_language')) {
            return false;
        }

        // Check if Polylang has at least 1 language configured
        $languages = pll_languages_list();
        return is_array($languages) && count($languages) >= 1;
    }

    /**
     * Get all language translations for a given post/page.
     * Returns array of ['lang_code' => 'url'] for WPML, Polylang, or empty array if not available.
     *
     * @param int $postId The post ID to get translations for.
     * @return array<string, string> Array mapping language codes to their translated URLs.
     */
    public static function getTranslatedUrls(int $postId): array
    {
        $translations = [];

        // Try WPML first
        if (self::isWpmlActive()) {
            $translations = self::getWpmlTranslatedUrls($postId);
            if (!empty($translations)) {
                return $translations;
            }
        }

        // Try Polylang
        if (self::isPolylangActive()) {
            $translations = self::getPolylangTranslatedUrls($postId);
            if (!empty($translations)) {
                return $translations;
            }
        }

        // Fallback: return current URL for current language
        $currentLang = self::getCurrentLanguage();
        $currentUrl = get_permalink($postId);

        if ($currentUrl) {
            $translations[$currentLang] = $currentUrl;
        }

        return $translations;
    }

    /**
     * Get WPML translated URLs for a post.
     *
     * @param int $postId The post ID.
     * @return array<string, string> Array mapping language codes to URLs.
     */
    private static function getWpmlTranslatedUrls(int $postId): array
    {
        $translations = [];

        if (!function_exists('icl_get_languages')) {
            return $translations;
        }

        // Get post type
        $postType = get_post_type($postId);
        if (!$postType) {
            return $translations;
        }

        // Get all languages from WPML
        global $sitepress;
        if (!$sitepress || !method_exists($sitepress, 'get_active_languages')) {
            return $translations;
        }

        $activeLanguages = $sitepress->get_active_languages();

        foreach ($activeLanguages as $langCode => $langData) {
            // Get the translated post ID using WPML's translation API
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $translatedPostId = apply_filters('wpml_object_id', $postId, $postType, false, $langCode);

            if ($translatedPostId) {
                $translatedUrl = get_permalink($translatedPostId);
                if ($translatedUrl) {
                    $translations[$langCode] = $translatedUrl;
                }
            }
        }

        return $translations;
    }

    /**
     * Get Polylang translated URLs for a post.
     *
     * @param int $postId The post ID.
     * @return array<string, string> Array mapping language codes to URLs.
     */
    private static function getPolylangTranslatedUrls(int $postId): array
    {
        $translations = [];

        if (!function_exists('pll_languages_list') || !function_exists('pll_get_post')) {
            return $translations;
        }

        // Get all languages from Polylang
        $languages = pll_languages_list(['fields' => 'slug']);

        if (!is_array($languages)) {
            return $translations;
        }

        foreach ($languages as $langCode) {
            // Get the translated post ID for this language
            $translatedPostId = pll_get_post($postId, $langCode);

            if ($translatedPostId) {
                $translatedUrl = get_permalink($translatedPostId);
                if ($translatedUrl) {
                    $translations[$langCode] = $translatedUrl;
                }
            }
        }

        return $translations;
    }

    /**
     * Get the default/primary language code.
     *
     * @return string The default language code (e.g., 'en', 'de').
     */
    public static function getDefaultLanguage(): string
    {
        // Try WPML
        if (self::isWpmlActive()) {
            global $sitepress;
            if ($sitepress && method_exists($sitepress, 'get_default_language')) {
                $defaultLang = $sitepress->get_default_language();
                if ($defaultLang) {
                    return $defaultLang;
                }
            }
        }

        // Try Polylang
        if (self::isPolylangActive() && function_exists('pll_default_language')) {
            $defaultLang = pll_default_language();
            if ($defaultLang) {
                return $defaultLang;
            }
        }

        // Fallback to WordPress locale
        return self::current_language_code_helper();
    }

    /**
     * Get current content language.
     *
     * @return string The current language code (e.g., 'en', 'de').
     */
    public static function getCurrentLanguage(): string
    {
        // Try WPML
        if (self::isWpmlActive()) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $currentLang = apply_filters('wpml_current_language', null);
            if ($currentLang) {
                return $currentLang;
            }
        }

        // Try Polylang
        if (self::isPolylangActive() && function_exists('pll_current_language')) {
            $currentLang = pll_current_language();
            if ($currentLang) {
                return $currentLang;
            }
        }

        // Fallback to WordPress locale
        return self::current_language_code_helper();
    }

    // ========================================
    // Pagination Support
    // ========================================

    /**
     * Get pagination links for current archive/search/blog page.
     * Returns ['prev' => 'url or null', 'next' => 'url or null'].
     *
     * Works for: blog index, category archives, tag archives, author archives,
     * search results, date archives, and paginated single posts (<!--nextpage-->).
     *
     * @return array{prev: string|null, next: string|null} Array with prev and next URLs.
     */
    public static function getPaginationLinks(): array
    {
        global $wp_query, $paged, $page;

        $result = [
            'prev' => null,
            'next' => null,
        ];

        // Handle paginated single posts (using <!--nextpage-->)
        if (is_singular()) {
            return self::getSinglePostPaginationLinks();
        }

        // Handle archive pages, search, blog index, etc.
        if (!isset($wp_query) || !$wp_query instanceof \WP_Query) {
            return $result;
        }

        $maxPages = (int) $wp_query->max_num_pages;
        $currentPage = max(1, (int) ($paged ?: get_query_var('paged', 1)));

        // No pagination needed if only 1 page
        if ($maxPages <= 1) {
            return $result;
        }

        // Previous page link
        if ($currentPage > 1) {
            $prevPageNum = $currentPage - 1;
            $result['prev'] = self::getPagedArchiveUrl($prevPageNum);
        }

        // Next page link
        if ($currentPage < $maxPages) {
            $nextPageNum = $currentPage + 1;
            $result['next'] = self::getPagedArchiveUrl($nextPageNum);
        }

        return $result;
    }

    /**
     * Get pagination links for paginated single posts (using <!--nextpage-->).
     *
     * @return array{prev: string|null, next: string|null} Array with prev and next URLs.
     */
    private static function getSinglePostPaginationLinks(): array
    {
        global $page, $numpages, $post;

        $result = [
            'prev' => null,
            'next' => null,
        ];

        if (!$post || !isset($numpages)) {
            return $result;
        }

        $currentPage = max(1, (int) $page);
        $totalPages = (int) $numpages;

        // No pagination needed if only 1 page
        if ($totalPages <= 1) {
            return $result;
        }

        $permalink = get_permalink($post->ID);
        if (!$permalink) {
            return $result;
        }

        // Previous page link
        if ($currentPage > 1) {
            $prevPageNum = $currentPage - 1;
            $result['prev'] = self::getPagedSingleUrl($permalink, $prevPageNum, $post->ID);
        }

        // Next page link
        if ($currentPage < $totalPages) {
            $nextPageNum = $currentPage + 1;
            $result['next'] = self::getPagedSingleUrl($permalink, $nextPageNum, $post->ID);
        }

        return $result;
    }

    /**
     * Build paged URL for archive pages.
     *
     * @param int $pageNum The page number.
     * @return string|null The paged URL or null on failure.
     */
    private static function getPagedArchiveUrl(int $pageNum): ?string
    {
        if ($pageNum < 1) {
            return null;
        }

        // For page 1, return the base archive URL without pagination
        if ($pageNum === 1) {
            // Get the current archive base URL
            if (is_home()) {
                return get_permalink(get_option('page_for_posts')) ?: home_url('/');
            }
            if (is_front_page()) {
                return home_url('/');
            }
            if (is_category()) {
                return get_category_link(get_queried_object_id());
            }
            if (is_tag()) {
                return get_tag_link(get_queried_object_id());
            }
            if (is_author()) {
                return get_author_posts_url(get_queried_object_id());
            }
            if (is_search()) {
                return get_search_link(get_search_query());
            }
            if (is_post_type_archive()) {
                return get_post_type_archive_link(get_query_var('post_type'));
            }
            if (is_date()) {
                if (is_year()) {
                    return get_year_link(get_query_var('year'));
                }
                if (is_month()) {
                    return get_month_link(get_query_var('year'), get_query_var('monthnum'));
                }
                if (is_day()) {
                    return get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
                }
            }

            // Fallback
            return home_url('/');
        }

        // Use WordPress's get_pagenum_link for pages > 1
        return get_pagenum_link($pageNum);
    }

    /**
     * Build paged URL for single posts with <!--nextpage-->.
     *
     * @param string $permalink The base permalink.
     * @param int $pageNum The page number.
     * @param int $postId The post ID.
     * @return string|null The paged URL or null on failure.
     */
    private static function getPagedSingleUrl(string $permalink, int $pageNum, int $postId): ?string
    {
        if ($pageNum < 1) {
            return null;
        }

        // For page 1, return the base permalink
        if ($pageNum === 1) {
            return $permalink;
        }

        // Use WordPress's _wp_link_page logic
        global $wp_rewrite;

        if (!$wp_rewrite->using_permalinks() || !get_option('permalink_structure')) {
            // Using plain permalinks
            return add_query_arg('page', $pageNum, $permalink);
        }

        // Using pretty permalinks
        $permalink = trailingslashit($permalink);
        return user_trailingslashit($permalink . $pageNum, 'single_paged');
    }

    // ========================================
    // Image Dimensions Support
    // ========================================

    /**
     * Get image dimensions from URL or attachment ID.
     * Returns ['width' => int, 'height' => int] or empty array if unavailable.
     *
     * @param string|int $imageUrlOrId Image URL or attachment ID.
     * @return array{width?: int, height?: int} Array with width and height, or empty array.
     */
    public static function getImageDimensions($imageUrlOrId): array
    {
        // If it's a numeric ID, treat as attachment ID
        if (is_numeric($imageUrlOrId)) {
            return self::getImageDimensionsFromAttachmentId((int) $imageUrlOrId);
        }

        // It's a URL - try to find the attachment ID first
        if (is_string($imageUrlOrId) && !empty($imageUrlOrId)) {
            return self::getImageDimensionsFromUrl($imageUrlOrId);
        }

        return [];
    }

    /**
     * Get image dimensions from attachment ID.
     *
     * @param int $attachmentId The attachment ID.
     * @return array{width?: int, height?: int} Array with width and height.
     */
    private static function getImageDimensionsFromAttachmentId(int $attachmentId): array
    {
        if ($attachmentId <= 0) {
            return [];
        }

        // Try to get from attachment metadata
        $metadata = wp_get_attachment_metadata($attachmentId);
        if ($metadata && isset($metadata['width'], $metadata['height'])) {
            return [
                'width' => (int) $metadata['width'],
                'height' => (int) $metadata['height'],
            ];
        }

        // Fallback: try wp_get_attachment_image_src
        $imageData = wp_get_attachment_image_src($attachmentId, 'full');
        if ($imageData && isset($imageData[1], $imageData[2])) {
            return [
                'width' => (int) $imageData[1],
                'height' => (int) $imageData[2],
            ];
        }

        return [];
    }

    /**
     * Get image dimensions from URL.
     *
     * @param string $imageUrl The image URL.
     * @return array{width?: int, height?: int} Array with width and height.
     */
    private static function getImageDimensionsFromUrl(string $imageUrl): array
    {
        if (empty($imageUrl)) {
            return [];
        }

        // First, try to find the attachment ID from URL
        $attachmentId = attachment_url_to_postid($imageUrl);
        if ($attachmentId > 0) {
            $dimensions = self::getImageDimensionsFromAttachmentId($attachmentId);
            if (!empty($dimensions)) {
                return $dimensions;
            }
        }

        // Try getting image size from local file
        $localPath = self::urlToLocalPath($imageUrl);
        if ($localPath && file_exists($localPath)) {
            $imageSize = @getimagesize($localPath);
            if ($imageSize && isset($imageSize[0], $imageSize[1])) {
                return [
                    'width' => (int) $imageSize[0],
                    'height' => (int) $imageSize[1],
                ];
            }
        }

        // For external URLs or if local path doesn't work,
        // we could fetch headers or download the image, but that's expensive.
        // Return empty for performance reasons - only works with local images.
        return [];
    }

    /**
     * Convert a URL to a local file path if the URL is on the same server.
     *
     * @param string $url The URL to convert.
     * @return string|null The local path or null if not local.
     */
    private static function urlToLocalPath(string $url): ?string
    {
        // Get the upload directory info
        $uploadDir = wp_upload_dir();
        $siteUrl = site_url();
        $homeUrl = home_url();

        // Check if URL starts with site URL or home URL
        $baseUrls = array_filter([$siteUrl, $homeUrl, $uploadDir['baseurl']]);

        foreach ($baseUrls as $baseUrl) {
            if (strpos($url, $baseUrl) === 0) {
                // URL is local, convert to path
                $relativePath = str_replace($baseUrl, '', $url);

                // Determine the base path
                if ($baseUrl === $uploadDir['baseurl']) {
                    $localPath = $uploadDir['basedir'] . $relativePath;
                } else {
                    $localPath = ABSPATH . ltrim($relativePath, '/');
                }

                // Normalize path separators
                $localPath = str_replace('/', DIRECTORY_SEPARATOR, $localPath);

                return $localPath;
            }
        }

        return null;
    }

    // ========================================
    // Meta Description Helper
    // ========================================

    /**
     * Get meta description with fallback logic.
     *
     * Returns the best available description from: custom meta description, excerpt,
     * or auto-generated from content. This helper provides a unified way to get
     * descriptions for both SEO meta tags and social media tags.
     *
     * @param int|null $postId The post ID (uses current post if null)
     * @param string|null $customDescription Custom description if already available
     * @param int $maxLength Maximum character length for the description (default 160)
     * @return string The description or empty string if none available
     *
     * @since 1.0.0
     */
    public static function getMetaDescription(?int $postId = null, ?string $customDescription = null, int $maxLength = 160): string
    {
        // Return custom description if provided and not empty
        if (!empty($customDescription)) {
            $description = self::sanitizeMetaDescription($customDescription);
            return self::truncateDescription($description, $maxLength);
        }

        // Get the post object
        $post = self::retrieve_post($postId);

        // Return empty string if no valid post
        if (!$post instanceof WP_Post) {
            return '';
        }

        // Static cache to avoid redundant processing for the same post
        static $descriptionCache = [];
        $cacheKey = $post->ID . '_' . $maxLength;

        if (isset($descriptionCache[$cacheKey])) {
            return $descriptionCache[$cacheKey];
        }

        $description = '';

        // Try to get description from retrieve_description (handles various contexts)
        $description = self::retrieve_description($post);

        // Fallback 1: If still empty, try extracting from post excerpt
        if (empty($description) && !empty($post->post_excerpt)) {
            $description = wp_strip_all_tags(strip_shortcodes($post->post_excerpt));
        }

        // Fallback 2: If still empty, try extracting from post content
        if (empty($description) && !empty($post->post_content)) {
            $description = self::extractDescriptionFromContent($post->post_content);
        }

        // Fallback 3: If still empty, use post title as last resort
        if (empty($description) && !empty($post->post_title)) {
            $description = $post->post_title;
        }

        // Sanitize and truncate
        $description = self::sanitizeMetaDescription($description);
        $description = self::truncateDescription($description, $maxLength);

        // Cache the result
        $descriptionCache[$cacheKey] = $description;

        return $description;
    }

    /**
     * Extract a description from post content.
     *
     * Strips HTML, shortcodes, and extracts clean text content suitable for meta descriptions.
     *
     * @param string $content The post content to extract from
     * @return string The extracted description
     *
     * @since 1.0.0
     */
    private static function extractDescriptionFromContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Strip images, captions and WP oembed wrappers
        $content = preg_replace(
            '/(<figure.*?\/figure>|<img.*?\/>|<div.*?class="wp-block-embed__wrapper".*?>.*?<\/div>)/s',
            '',
            $content
        );

        // Convert special characters
        $content = str_replace(']]>', ']]&gt;', (string) $content);

        // Strip all HTML tags and shortcodes
        $content = trim(wp_strip_all_tags(strip_shortcodes((string) $content)));

        // Use WordPress's word trimmer for initial cleanup (55 words)
        $content = wp_trim_words((string) $content, 55, '');

        return $content;
    }

    /**
     * Sanitize a meta description string.
     *
     * Removes extra whitespace, HTML entities, and normalizes the string.
     *
     * @param string $description The description to sanitize
     * @return string The sanitized description
     *
     * @since 1.0.0
     */
    private static function sanitizeMetaDescription(string $description): string
    {
        if (empty($description)) {
            return '';
        }

        // Ensure string type and trim
        $description = is_string($description) ? trim($description) : '';

        // Strip any remaining HTML tags
        $description = wp_strip_all_tags($description);

        // Decode HTML entities
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace (replace multiple spaces/newlines with single space)
        $description = preg_replace('/\s+/', ' ', $description);

        // Final trim
        $description = trim($description);

        return $description;
    }

    /**
     * Truncate a description to a maximum length with proper word boundary handling.
     *
     * Ensures descriptions are truncated at word boundaries and adds ellipsis if needed.
     *
     * @param string $description The description to truncate
     * @param int $maxLength Maximum character length (default 160)
     * @return string The truncated description
     *
     * @since 1.0.0
     */
    private static function truncateDescription(string $description, int $maxLength = 160): string
    {
        if (empty($description)) {
            return '';
        }

        // No truncation needed if within limit
        if (mb_strlen($description) <= $maxLength) {
            return $description;
        }

        // Reserve space for ellipsis
        $truncateAt = $maxLength - 3;

        // Truncate at the specified length
        $truncated = mb_substr($description, 0, $truncateAt);

        // Find the last word boundary (space)
        $lastSpace = mb_strrpos($truncated, ' ');

        // If we found a space and it's not too close to the beginning, truncate there
        if ($lastSpace !== false && $lastSpace > ($truncateAt * 0.75)) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return trim($truncated) . '...';
    }

    /**
     * Retrieves the array of allowed countries from plugin settings.
     *
     * This method accesses the WPSettings entity via SettingsManager and returns
     * the $allowed_countries array, which maps ISO 3166-1 alpha-2 codes to country names.
     * Supports both default (hardcoded) and saved (customized) settings.
     *
     * @return array<string, string> Array of ['US' => 'United States', ...] or empty array if unavailable.
     */
    public static function getAllowedCountries(): array
    {
        static $cache = [];

        if (isset($cache['allowed_countries'])) {
            return $cache['allowed_countries'];
        }

        $settingsManager = SettingsManager::instance();
        $options = $settingsManager->get_options();

        $allowedCountries = $options['allowed_countries'] ?? [];

        // Ensure it's an array of string => string
        if (!is_array($allowedCountries) || empty($allowedCountries)) {
            $allowedCountries = []; // Or log an error if needed
        }

        $cache['allowed_countries'] = $allowedCountries;
        return $allowedCountries;
    }

    /**
     * Retrieves the default platform country code and name.
     *
     * Prioritizes:
     * 1. Registered country from options (BaseConstants::OPTION_RANKINGCOACH_REGISTER_COUNTRY_CODE).
     * 2. Country extracted from current WordPress locale (e.g., 'en_US' → 'US').
     * 3. Validates against allowed countries; defaults to US if invalid/unavailable.
     *
     * Returns a single array item like ['US' => 'United States'] for easy use in configs or displays.
     *
     * @return array<string, string> Single country pair, e.g., ['US' => 'United States'].
     */
    public static function getDefaultCountry(): array
    {
        static $cache = [];

        if (isset($cache['default_country'])) {
            return $cache['default_country'];
        }

        $allowedCountries = self::getAllowedCountries();
        $defaultCountry = ['US' => 'United States']; // Fallback

        // Step 1: Check registered country option
        $registeredCode = get_option(BaseConstants::OPTION_RANKINGCOACH_REGISTER_COUNTRY_CODE, '');
        if (!empty($registeredCode) && isset($allowedCountries[$registeredCode])) {
            $defaultCountry = [$registeredCode => $allowedCountries[$registeredCode]];
            $cache['default_country'] = $defaultCountry;
            return $defaultCountry;
        }

        // Step 2: Extract from WordPress locale
        $locale = self::get_wp_locale(); // e.g., 'en_US' (from core WP)
        if (!empty($locale) && str_contains($locale, '_')) {
            $parts = explode('_', $locale);
            if (isset($parts[1]) && strlen($parts[1]) === 2) {
                $localeCountryCode = strtoupper($parts[1]); // e.g., 'US'

                // Step 3: Validate against allowed
                if (isset($allowedCountries[$localeCountryCode])) {
                    $defaultCountry = [$localeCountryCode => $allowedCountries[$localeCountryCode]];
                }
            }
        }

        $cache['default_country'] = $defaultCountry;
        return $defaultCountry;
    }
}
