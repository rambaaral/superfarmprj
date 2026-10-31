<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Admin\Pages\ActivationPage;
use RankingCoach\Inc\Core\Admin\Pages\GeneralSettingsPage;
use RankingCoach\Inc\Core\Admin\Pages\IframePage;
use RankingCoach\Inc\Core\Admin\Pages\OnboardingPage;
use RankingCoach\Inc\Core\Admin\Pages\RegistrationPage;
use RankingCoach\Inc\Core\Admin\Pages\SetupWizardPage;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\CircuitBreaker\FunctionalityBlocker;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\Admin\Pages\FeedbackPage;
use RankingCoach\Inc\Core\Admin\Pages\UpsellPage;
use RankingCoach\Inc\Core\DashboardWidgetManager;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\ExternalLinks;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\ToolbarManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonTrait;
use Throwable;

// Define the base URL for the admin management pages
define('RANKINGCOACH_ADMIN_MANAGER_URL', RANKINGCOACH_PLUGIN_ADMIN_URL);


/**
 * Class AdminManager
 * @method AdminManager getInstance(): static
 */
class AdminManager
{

    use SingletonTrait;

    /**
     * The main page of the plugin.
     *
     * @var IframePage|null
     */
    private ?IframePage $main_page;

    /**
     * The activation page of the plugin.
     *
     * @var ActivationPage|null
     */
    private ?ActivationPage $activation_page;

    /**
     * The registration page of the plugin.
     *
     * @var RegistrationPage|null
     */
    private ?RegistrationPage $registration_page;

    /**
     * The onboarding page of the plugin.
     *
     * @var OnboardingPage|null
     */
    private ?OnboardingPage $onboarding_page;

    /**
     * The general settings page of the plugin.
     *
     * @var GeneralSettingsPage|null
     */
    private ?GeneralSettingsPage $general_settings_page;

    /**
     * The dashboard page of the plugin.
     *
     * @var UpsellPage|null
     */
    private ?UpsellPage $upsell_page;

    /**
     * The feedback page handler of the plugin.
     *
     * @var FeedbackPage|null
     */
    private ?FeedbackPage $feedback_page;

    /**
     * The setup wizard page of the plugin.
     *
     * @var SetupWizardPage|null
     */
    private ?SetupWizardPage $setup_wizard_page;

    /**
     * The name of the main admin page.
     *
     * @var string
     */
    public const PAGE_MAIN              = 'rankingcoach-main';
    public const PAGE_SETTINGS          = 'rankingcoach-settings';
    public const PAGE_ACTIVATION        = 'rankingcoach-activation';
    public const PAGE_REGISTRATION      = 'rankingcoach-registration';
    public const PAGE_ONBOARDING        = 'rankingcoach-onboarding';
    public const PAGE_GENERAL_SETTINGS  = 'rankingcoach-generalSettings';
    public const PAGE_SUPPORT           = 'rankingcoach-support';
    public const PAGE_UPSELL            = 'rankingcoach-connect';
    public const PAGE_SETUP_WIZARD      = 'rankingcoach-setupWizard';

    /**
     * AdminManager constructor.
     */
    public function __construct()
    {

        add_action('current_screen', static function ($screen) {
            // Ensure the screen object is available and valid
            if (!is_object($screen) || !isset($screen->id)) {
                return;
            }

            // Only apply to our supported post types
            if (
                $screen->base === 'post' &&
                in_array($screen->post_type, ALLOWED_RANKINGCOACH_CUSTOM_TYPES, true)
            ) {
                // Avoid Elementor or other non-standard editors if needed
                $action = WordpressHelpers::sanitize_input('GET', 'action');

                if ($action === 'elementor') {
                    return;
                }

                $post_id = WordpressHelpers::sanitize_input(
                    'GET',
                    'post',
                    filters: [FILTER_SANITIZE_NUMBER_INT],
                    validate: FILTER_VALIDATE_INT,
                    return: 'int'
                );

                // Determine if it's Add New or Edit
                if ($screen->action === 'add') {
                    // It's the Add New screen for post/page
                    ReactApp::get([
                        'edit', 'float', 'add_new'
                    ], $post_id);
                } elseif($post_id > 0) {
                    // It's the Edit screen
                    ReactApp::get([
                        'edit', 'float'
                    ], $post_id);
                }
            }
        });

        // Initialize toolbar and dashboard widget managers
        ToolbarManager::getInstance()->init();
        DashboardWidgetManager::getInstance()->init();

        $this->main_page = IframePage::getInstance()->setManager($this);
        $this->activation_page = ActivationPage::getInstance()->setManager($this);
        $this->registration_page = RegistrationPage::getInstance()->setManager($this);
        $this->onboarding_page = OnboardingPage::getInstance()->setManager($this);
        $this->general_settings_page = GeneralSettingsPage::getInstance()->setManager($this);
        $this->upsell_page = UpsellPage::getInstance()->setManager($this);
        $this->feedback_page = FeedbackPage::getInstance()->setManager($this);
        $this->setup_wizard_page = SetupWizardPage::getInstance()->setManager($this);
    }

    /**
     * Initialize the admin manager.
     */
    public function init(): void
    {
        // Add a div mounted on DOM, support for edit page/post
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        // Add a div mounted in DOM, support for a floating component. In footer admin
        add_action('admin_footer', [$this, 'footer_block']);
        // Create admin pages
        add_action('admin_menu', [$this, 'create_admin_pages']);
        // Add admin-specific hooks here
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts'], 0);
        // Add plugin action links
        add_filter('plugin_action_links_' . RANKINGCOACH_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        // Add plugin row meta (five-star review link)
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
        // Show a "not connected" notice on the Plugins page until onboarding is completed
        (new NotConnectedNotice())->init();
        // One-shot redirect to the setup wizard after activation (after the circuit breaker check at priority 5)
        add_action('admin_init', [$this, 'maybe_redirect_to_setup_wizard'], 10);
    }

    /**
     * Redirects once to the setup wizard after an interactive activation.
     *
     * @return void
     */
    public function maybe_redirect_to_setup_wizard(): void
    {
        if (!get_transient(BaseConstants::TRANSIENT_SETUP_WIZARD_REDIRECT)) {
            return;
        }
        // Not an interactive admin page request (ajax, network admin, other role, output already
        // started on the lazy activation path): keep the transient for the next request.
        if (wp_doing_ajax() || is_network_admin() || headers_sent() || !current_user_can('manage_options')) {
            return;
        }
        delete_transient(BaseConstants::TRANSIENT_SETUP_WIZARD_REDIRECT);

        if (
            WordpressHelpers::sanitize_input('GET', 'activate-multi')
            || FunctionalityBlocker::instance()->getCircuitBreaker()->should_block_functionality()
            || CoreHelper::isOnboarded()
        ) {
            return;
        }

        $wizard = (array) SettingsManager::instance()->get_option('setup_wizard', []);
        if (!empty($wizard['completed']) || !empty($wizard['dismissed'])) {
            return;
        }

        wp_safe_redirect(self::getPageUrl(self::PAGE_SETUP_WIZARD));
        exit;
    }

    /**
     * Returns the URL of the admin page.
     *
     * @param $page
     * @param string $additional
     * @return string
     */
    public static function getPageUrl($page, string $additional = ''): string
    {
        return admin_url( 'admin.php?page=' . $page . $additional );
    }

    /**
     * Enqueue admin scripts.
     * @throws Exception
     */
    public function enqueue_admin_scripts(string $hook_suffix): void
    {
        $is_plugin_page = in_array($hook_suffix, ALLOWED_RANKINGCOACH_PAGES, true);
        $is_edit_list   = ($hook_suffix === 'edit.php');

        // Plugin-page and edit-list assets only
        if ($is_plugin_page || $is_edit_list) {
            wp_enqueue_style('rankingcoach-admin-style', RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/admin-style.css', [], RANKINGCOACH_VERSION);

            // REST data needed by React app
            wp_register_script(
                'rankingcoach-admin-rest-data',
                '',
                [],
                RANKINGCOACH_VERSION,
                true
            );
            wp_enqueue_script('rankingcoach-admin-rest-data');
            wp_add_inline_script(
                'rankingcoach-admin-rest-data',
                'window.rankingCoachRestData = ' . wp_json_encode([
                    'nonce'             => wp_create_nonce('wp_rest'),
                    'restUrl'           => esc_url_raw(rest_url(RANKINGCOACH_REST_API_BASE . '/')),
                    'ajaxUrl'           => admin_url('admin-ajax.php'),
                    'pluginUrl'         => RANKINGCOACH_PLUGIN_URL,
                    'pluginVersion'     => RANKINGCOACH_VERSION,
                    'wordpressVersion'  => get_bloginfo('version'),
                    'phpVersion'        => phpversion(),
                ]) . ';',
                'before'
            );
        }

        // Plugin-page scripts: float container mover + handle for inline script attachments
        if ($is_plugin_page) {
            wp_register_script('rankingcoach-admin-script', '', [], RANKINGCOACH_VERSION, true);
            wp_enqueue_script('rankingcoach-admin-script');
            wp_add_inline_script('rankingcoach-admin-script', 'document.addEventListener("DOMContentLoaded", function() {
                var target = document.getElementById("wpcontent");
                var element = document.getElementById("seo-optimiser-rankingcoach-react");

                if (target && element) {
                    target.appendChild(element);
                }
            });');
        }

        // Enqueue only on some plugin pages, not all
        if (in_array($hook_suffix, ALLOWED_RANKINGCOACH_PAGES, true)) {
            // scripts
            $time = time();
            $nonce = CoreHelper::rc_custom_nonce(CoreHelper::RC_NONCE_ACTION_NAME, $time);
            wp_enqueue_script('rankingcoach-general-admin-script', RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/admin-script.js', ['jquery'], RANKINGCOACH_VERSION, true);
            wp_localize_script('rankingcoach-general-admin-script', 'RankingCoachGeneralData', [
                'site_url'          => get_site_url(),
                'api_base_url'      => get_rest_url(null, '/' . RANKINGCOACH_REST_API_BASE . '/'),
                'ajax_url'          => admin_url('admin-ajax.php'),
                'admin_url'         => admin_url('admin.php'),
                'nonce_ts'          => $time,
                'nonce'             => $nonce,
                'i18n'              => [
                    'showMore'      => __('Show more', 'beyondseo'),
                    'showLess'      => __('Show less', 'beyondseo'),
                ]
            ]);
        }

        // Enqueue the main React bundle using in page/post lists, inline score widget
        $this->enqueue_vite_asset(
            $hook_suffix,
            'src/main.tsx', // entryKey in manifest.json
            'rc-main-react'
        );
    }

    /**
     * Stylesheets of a manifest entry and of every chunk it (transitively) imports, dependencies
     * first and de-duplicated, the way Vite itself links them.
     *
     * Vite attributes a stylesheet to the chunk that owns the module importing it. Components
     * main.tsx shares with the App.tsx entry (for example the SEO title chip editor used by the
     * list quick editor) therefore end up in the App chunk's CSS, which the entry's own `css`
     * list does not mention.
     *
     * @param array $manifest Decoded Vite manifest.
     * @param string $entryKey Manifest key of the entry or chunk.
     * @param array $visited Chunks already collected (guards against import cycles).
     * @return string[] Relative CSS paths as listed in the manifest.
     */
    private function collectChunkCss(array $manifest, string $entryKey, array &$visited = []): array
    {
        if (isset($visited[$entryKey]) || !isset($manifest[$entryKey])) {
            return [];
        }
        $visited[$entryKey] = true;

        $css = [];
        foreach ($manifest[$entryKey]['imports'] ?? [] as $import) {
            $css = array_merge($css, $this->collectChunkCss($manifest, (string) $import, $visited));
        }
        $css = array_merge($css, $manifest[$entryKey]['css'] ?? []);

        return array_values(array_unique($css));
    }

    /**
     * This function is used to mount react components to mounting points.
     *
     * @param string $hookSuffix The hook name where the script should be enqueued.
     * @param string $entryKey The key corresponding to the entry point in the manifest file.
     * @param string $scriptHandle The handle for the script being registered.
     * @param string $manifestRelativePath Optional. Relative path to the manifest file. Default is 'react/dist/manifest.json'.
     */
    public function enqueue_vite_asset(string $hookSuffix, string $entryKey, string $scriptHandle, string $manifestRelativePath = 'react/dist/manifest.json'): void
    {
        if ( $hookSuffix !== 'edit.php') {
            return;
        }

        $manifest_path = RANKINGCOACH_PLUGIN_DIR . $manifestRelativePath;
        if (!file_exists($manifest_path)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (!isset($manifest[$entryKey])) {
            return;
        }

        $bundle = $manifest[$entryKey];
        $jsFile = $bundle['file'] ?? null;
        $cssFiles = $this->collectChunkCss($manifest, $entryKey);

        if ($jsFile) {
            wp_enqueue_script(
                $scriptHandle,
                plugin_dir_url(RANKINGCOACH_FILE) . dirname($manifestRelativePath) . '/' . $jsFile,
                [],
                RANKINGCOACH_VERSION,
                true
            );

            add_filter('script_loader_tag', function ($tag, $handle) use ($scriptHandle) {
                if ($handle === $scriptHandle) {
                    return str_replace(' src', ' type="module" src', $tag);
                }
                return $tag;
            }, 10, 2);
        }

        foreach ($cssFiles as $cssFile) {
            wp_enqueue_style(
                $scriptHandle . '-style-' . md5($cssFile),
                plugin_dir_url(RANKINGCOACH_FILE) . dirname($manifestRelativePath) . '/' . $cssFile,
                [],
                RANKINGCOACH_VERSION
            );
        }
    }

    /**
     * Create InPost Block
     * registers the meta-box within the WordPress infrastructure
     *
     * @return void
     */
    public function add_meta_boxes(): void
    {
        // Get the current screen object.
        $screen = get_current_screen();

        // Check if we're in the admin area, editing a post or page, and not creating a new one.
        if ($screen && $screen->base === 'post') {
            add_meta_box(
                'rankingcoach-seo-analysis',
                RANKINGCOACH_BRAND_NAME,
                [$this, 'edit_block_callback'],
                ALLOWED_RANKINGCOACH_CUSTOM_TYPES,
                'normal',
                'high'
            );
        }
    }

    /**
     * InPost Block Callback
     * The HTML echoed by the InPost Block
     *
     * @return void
     */
    public function edit_block_callback(): void
    {
        echo '<div id="edit-rankingcoach-react" class="beyondseo"></div>';
    }

    /**
     * Outputs a custom footer block container for React.
     *
     * @return void
     */
    public function footer_block(): void
    {
        // Only output the React mount point on pages where React components were requested
        if (empty(ReactApp::$loadComponents)) {
            return;
        }
        echo '<div id="seo-optimiser-rankingcoach-react" class="beyondseo"></div>';
    }

    /**
     * Outputs a custom page block container for React.
     *
     * @return void
     */
    public function page_block(): void
    {
        echo '<div id="page-rankingcoach-react"></div>';
    }

    /**
     * Creates admin pages for the plugin.
     *
     * All pages use the `load-{$page_hook}` pattern for access control.
     * This pattern hooks into WordPress's `load-{$page_hook}` action which fires BEFORE
     * any output is sent. This allows wp_safe_redirect() to work without output buffering!
     *
     * The hook name is returned by add_menu_page()/add_submenu_page()
     * (e.g., 'toplevel_page_rankingcoach-main', 'admin_page_rankingcoach-activation').
     *
     * @return void
     * @throws HttpApiException
     * @throws Throwable
     */
    public function create_admin_pages(): void
    {
        // ===================================================================================
        // MAIN PAGE: IframePage
        // ===================================================================================
        // add_menu_page() returns the hook suffix (e.g., 'toplevel_page_rankingcoach-main').
        // We hook into load-{$hook_suffix} which fires BEFORE any output is sent,
        // allowing us to perform access control and redirects without output buffering.
        $mainPageHook = add_menu_page(
            RANKINGCOACH_BRAND_NAME,
            RANKINGCOACH_BRAND_NAME,
            'manage_options',
            'rankingcoach-main',
            function () {
                // Access control was already handled in the load-{$page_hook} action before headers were sent.
                $this->main_page->page_content();
            },
            RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/icons/rC-logo-wp.svg',
            99
        );

        // Hook into load-{$page_hook} to handle access control BEFORE headers are sent.
        add_action("load-{$mainPageHook}", function () {
            $this->main_page->handleAccessControl();
        });

        // ===================================================================================
        // GENERAL SETTINGS PAGE
        // ===================================================================================
        $generalSettingsHook = add_submenu_page(
            'rankingcoach-main',
            __('Settings', 'beyondseo'),
            __('Settings', 'beyondseo'),
            'manage_options',
            'rankingcoach-generalSettings',
            function () {
                $this->general_settings_page->page_content();
            }
        );

        add_action("load-{$generalSettingsHook}", function () {
            $this->general_settings_page->handleAccessControl();
        });

        // ===================================================================================
        // ACTIVATION PAGE (Hidden from menu)
        // ===================================================================================
        $activationHook = add_submenu_page(
            '-',
            __('Activation', 'beyondseo'),
            __('Activation', 'beyondseo'),
            'manage_options',
            'rankingcoach-activation',
            function () {
                $this->activation_page->page_content();
            }
        );

        add_action("load-{$activationHook}", function () {
            $this->activation_page->handleAccessControl();
        });

        // ===================================================================================
        // REGISTRATION PAGE (Hidden from menu)
        // ===================================================================================
        $registrationHook = add_submenu_page(
            '-',
            __('Registration', 'beyondseo'),
            __('Registration', 'beyondseo'),
            'manage_options',
            'rankingcoach-registration',
            function () {
                $this->registration_page->page_content();
            }
        );

        add_action("load-{$registrationHook}", function () {
            $this->registration_page->handleAccessControl();
        });

        // ===================================================================================
        // ONBOARDING PAGE (Hidden from menu)
        // ===================================================================================
        $onboardingHook = add_submenu_page(
            '-',
            __('Onboarding', 'beyondseo'),
            __('Onboarding', 'beyondseo'),
            'manage_options',
            'rankingcoach-onboarding',
            function () {
                $this->onboarding_page->page_content();
            }
        );

        add_action("load-{$onboardingHook}", function () {
            $this->onboarding_page->handleAccessControl();
        });

        // ===================================================================================
        // SETUP WIZARD PAGE (Hidden from menu)
        // ===================================================================================
        $setupWizardHook = add_submenu_page(
            '-',
            __('Setup wizard', 'beyondseo'),
            __('Setup wizard', 'beyondseo'),
            'manage_options',
            self::PAGE_SETUP_WIZARD,
            function () {
                $this->setup_wizard_page->page_content();
            }
        );

        add_action("load-{$setupWizardHook}", function () {
            $this->setup_wizard_page->handleAccessControl();
        });

        // ===================================================================================
        // UPGRADE PAGE
        // ===================================================================================
        $upgradeHook = null;
        if(!CoreHelper::isOnboarded()){
            $upgradeHook = add_submenu_page(
                'rankingcoach-main',
                __('Connect', 'beyondseo'),
                __('Connect', 'beyondseo'),
                'manage_options',
                'rankingcoach-connect',
                function () {
                    $this->upsell_page->page_content();
                }
            );
        } else {
            if (CoreHelper::hasUpgradePlans()) {
                $upgradeHook = add_submenu_page(
                    'rankingcoach-main',
                    __('Upgrade', 'beyondseo'),
                    __('Upgrade', 'beyondseo'),
                    'manage_options',
                    'rankingcoach-connect',
                    function () {
                        $this->upsell_page->page_content();
                    }
                );
            }
        }

        if ($upgradeHook) {
            add_action("load-{$upgradeHook}", function () {
                $this->upsell_page->handleAccessControl();
            });
        }

        $supportHook = add_submenu_page(
            'rankingcoach-main',
            __('Support', 'beyondseo'),
            __('Support', 'beyondseo'),
            'manage_options',
            self::PAGE_SUPPORT,
            function () {
                echo '<div id="support-rankingcoach-page" class="wrap beyondseo"></div>';
            }
        );

        add_action("load-{$supportHook}", function () {
            ReactApp::get(['support']);
        });
    }

    /**
     * Redirects to the specified admin page.
     *
     * @param string $pageName - The name of the page to redirect to.
     * @param string|null $queries - Optional queries to append to the URL.
     * @return void
     */
    public function redirectPage(string $pageName, ?string $queries = null): void
    {
        if (isset($this->main_page) && property_exists($this->main_page, 'name') && $this->main_page->page_name() === $pageName) {
            $this->main_page->redirect($queries);
        }
        if (isset($this->onboarding_page) && property_exists($this->onboarding_page, 'name') && $this->onboarding_page->page_name() === $pageName) {
            $this->onboarding_page->redirect($queries);
        }
        if (isset($this->general_settings_page) && property_exists($this->general_settings_page, 'name') && $this->general_settings_page->page_name() === $pageName) {
            $this->general_settings_page->redirect($queries);
        }
        if (isset($this->activation_page) && property_exists($this->activation_page, 'name') && $this->activation_page->page_name() === $pageName) {
            $this->activation_page->redirect($queries);
        }
        if (isset($this->registration_page) && property_exists($this->registration_page, 'name') && $this->registration_page->page_name() === $pageName) {
            $this->registration_page->redirect($queries);
        }
        if (isset($this->upsell_page) && property_exists($this->upsell_page, 'name') && $this->upsell_page->page_name() === $pageName) {
            $this->upsell_page->redirect($queries);
        }
        if (isset($this->feedback_page) && property_exists($this->feedback_page, 'name') && $this->feedback_page->page_name() === $pageName) {
            $this->feedback_page->redirect($queries);
        }
        if (isset($this->setup_wizard_page) && property_exists($this->setup_wizard_page, 'name') && $this->setup_wizard_page->page_name() === $pageName) {
            $this->setup_wizard_page->redirect($queries);
        }
    }

    /**
     * Add action links to the plugin page.
     *
     * @param array $links
     * @return array
     */
    public function plugin_action_links(array $links): array
    {
        $new_links = [];

        $new_links['documentation'] = sprintf(
            '<a href="%1$s" target="_blank">%2$s</a>',
            ExternalLinks::getDocumentationUrl(),
            esc_html__('Documentation', 'beyondseo')
        );

        $new_links['support'] = sprintf(
            '<a href="%1$s" target="_blank">%2$s</a>',
            ExternalLinks::getSupportUrl(),
            esc_html__('Support', 'beyondseo')
        );

        $new_links['setup_wizard'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url(self::getPageUrl(self::PAGE_SETUP_WIZARD)),
            esc_html__('Setup wizard', 'beyondseo')
        );

        if (!CoreHelper::isOnboarded()) {
            $new_links['upgrade_link'] = sprintf(
                '<a style="font-weight: 900;" href="%1$s" class="rankingcoach-upgrade-link">%2$s</a>',
                esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL)),
                __( 'Connect', 'beyondseo')
            );
        }

        if (CoreHelper::hasUpgradePlans()) {
            $new_links['upgrade_link'] = sprintf(
                '<a style="font-weight: 900;" href="%1$s" class="rankingcoach-upgrade-link">%2$s</a>',
                esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL)),
                __( 'Upgrade', 'beyondseo')
            );
        }

        return array_merge($new_links, $links);
    }

    /**
     * Add a five-star review link to the plugin's row meta on the Plugins page.
     *
     * @param array $plugin_meta
     * @param string $plugin_file
     * @return array
     */
    public function plugin_row_meta(array $plugin_meta, string $plugin_file): array
    {
        if ($plugin_file !== RANKINGCOACH_PLUGIN_BASENAME) {
            return $plugin_meta;
        }

        $label = sprintf(
            /* translators: %s: plugin brand name */
            __('Rate %s on WordPress.org', 'beyondseo'),
            RANKINGCOACH_BRAND_NAME
        );

        $stars = str_repeat(
            '<span class="dashicons dashicons-star-filled" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;" aria-hidden="true"></span>',
            5
        );

        $plugin_meta[] = sprintf(
            '<a href="%1$s" target="_blank" rel="noopener noreferrer" class="rankingcoach-rate-us" style="color: #ffb900; text-decoration: none;" aria-label="%2$s" title="%2$s">%3$s</a>',
            ExternalLinks::getReviewUrl(),
            esc_attr($label),
            $stars
        );

        return $plugin_meta;
    }
}
