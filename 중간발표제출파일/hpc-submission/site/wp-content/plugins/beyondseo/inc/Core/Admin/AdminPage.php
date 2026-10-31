<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin;

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminPage
 */
abstract class AdminPage {

    use RcLoggerTrait;

    public const PAGE_SLUG_PREFIX = 'rankingcoach-';
    public const PAGE_MAIN = 'main';
    public const PAGE_ONBOARDING = 'onboarding';
    public const PAGE_SETTINGS = 'generalSettings';
    public const PAGE_REGISTRATION = 'registration';
    public const PAGE_UPSELL = 'upsell';

    /**
     * AdminPage constructor
     */
    public function __construct() {
        // No global side effects -- WordPress footer is left intact per WP plugin guidelines
    }

	/**
	 * Handles the generation or processing of page content within the application.
     * @return void
	 */
	abstract public function page_content(): void;

    /**
     * Returns the name of the page.
     * @return string
     */
	abstract public function page_name(): string;

    /**
     * Returns the instance of the AdminManager.
     * @param AdminManager $adminManager
     * @return static
     */
    public function setManager(AdminManager $adminManager): static {
        static::$managerInstance = $adminManager;
        return static::getInstance();
    }

    /**
     * Redirects to the admin page.
     *
     * @param string|null $queries Optional query parameters to append (without leading &).
     * @return void This function never returns as it exits after redirect.
     */
    public function redirect(?string $queries = null): void
    {
        $page_name = static::page_name();
        if (empty($page_name)) {
            return;
        }

        // Build base URL
        $url = admin_url('admin.php?page=rankingcoach-' . $page_name);

        // Add query parameters if provided
        if (!empty($queries)) {
            // Sanitize and format the query string
            $queries = sanitize_text_field($queries);

            // Ensure query starts with & if it doesn't already
            if (!str_starts_with($queries, '&')) {
                $queries = '&' . $queries;
            }

            $url .= $queries;
        }

        // Perform redirect and check result
        // phpcs:ignore
        if (!wp_redirect($url)) {
            return;
        }

        exit;
    }
}