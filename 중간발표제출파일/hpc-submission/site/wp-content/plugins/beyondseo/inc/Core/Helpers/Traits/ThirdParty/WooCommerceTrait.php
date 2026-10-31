<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits\ThirdParty;

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

/**
 * Trait WooCommerceTrait
 *
 * @package RankingCoach\Inc\Core\Helpers\Traits\ThirdParty
 */
trait WooCommerceTrait
{
    /**
     * Verifies if WooCommerce plugin is currently loaded and active.
     *
     * @since 1.0.0
     *
     * @return bool Returns true when WooCommerce is available.
     */
    public function isWooCommerceLoaded(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Determines if the specified page is a WooCommerce special page type.
     *
     * @since 1.0.0
     *
     * @param  int    $pageId The page identifier to check.
     * @return string         Returns the page type name or empty string if not a WooCommerce page.
     */
    public function getWooCommercePageType( int $pageId = 0 ): string {
        $pageId = $pageId ?: get_the_ID();
        $wooCommerceSpecialPages = $this->retrieveWooCommercePageIds();

        $foundPageType = array_search( $pageId, $wooCommerceSpecialPages, true );

        return $foundPageType !== false ? $foundPageType : '';
    }

    /**
     * Retrieves all configured WooCommerce special page identifiers.
     *
     * @since 1.0.0
     *
     * @return array Returns associative array of page types and their IDs.
     */
    public function retrieveWooCommercePageIds(): array {
        if ( ! $this->isWooCommerceLoaded() ) {
            return [];
        }

        return [
            'cart'      => (int) get_option( 'woocommerce_cart_page_id' ),
            'checkout'  => (int) get_option( 'woocommerce_checkout_page_id' ),
            'myAccount' => (int) get_option( 'woocommerce_myaccount_page_id' ),
            'terms'     => (int) get_option( 'woocommerce_terms_page_id' ),
        ];
    }

    /**
     * Evaluates whether current page is a WooCommerce page that should exclude schema markup.
     *
     * @since 1.0.0
     *
     * @param  int  $pageId The page identifier to evaluate.
     * @return bool         Returns true if page should not display schema settings.
     */
    public function shouldExcludeWooCommercePageSchema( int $pageId = 0 ): bool {
        $pageType = $this->getWooCommercePageType( $pageId );

        if ( empty( $pageType ) ) {
            return false;
        }

        $excludedPageTypes = [ 'cart', 'checkout', 'myAccount' ];

        return in_array( $pageType, $excludedPageTypes, true );
    }

    /**
     * Validates if the current or specified page is the WooCommerce shop page.
     *
     * @since 1.0.0
     *
     * @param  int  $pageId Optional page ID to validate against.
     * @return bool         Returns true if this is the shop page.
     */
    public function validateWooCommerceShopPage( int $pageId = 0 ): bool {
        if ( ! $this->isWooCommerceLoaded() ) {
            return false;
        }

        if ( ! is_admin() && ! aioseo()->helpers->isAjaxCronRestRequest() && function_exists( 'is_shop' ) ) {
            return is_shop();
        }

        // phpcs:disable HM.Security.ValidatedSanitizedInput, HM.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Recommended
        if ( ! $pageId && ! empty( $_GET['post'] ) ) {
            $pageId = (int) sanitize_text_field( wp_unslash( $_GET['post'] ) );
        }
        // phpcs:enable

        return $pageId && wc_get_page_id( 'shop' ) === $pageId;
    }

    /**
     * Confirms if the current or specified page is the WooCommerce cart page.
     *
     * @since 1.0.0
     *
     * @param  int  $pageId Optional page ID to confirm against.
     * @return bool         Returns true if this is the cart page.
     */
    public function confirmWooCommerceCartPage( int $pageId = 0 ): bool {
        if ( ! $this->isWooCommerceLoaded() ) {
            return false;
        }

        if ( ! is_admin() && ! aioseo()->helpers->isAjaxCronRestRequest() && function_exists( 'is_cart' ) ) {
            return is_cart();
        }

        // phpcs:disable HM.Security.ValidatedSanitizedInput, HM.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Recommended
        if ( ! $pageId && ! empty( $_GET['post'] ) ) {
            $pageId = (int) sanitize_text_field( wp_unslash( $_GET['post'] ) );
        }
        // phpcs:enable

        return $pageId && wc_get_page_id( 'cart' ) === $pageId;
    }

    /**
     * Identifies if the current or specified page is the WooCommerce checkout page.
     *
     * @since 1.0.0
     *
     * @param  int  $pageId Optional page ID to identify against.
     * @return bool         Returns true if this is the checkout page.
     */
    public function identifyWooCommerceCheckoutPage( int $pageId = 0 ): bool {
        if ( ! $this->isWooCommerceLoaded() ) {
            return false;
        }

        if ( ! is_admin() && ! aioseo()->helpers->isAjaxCronRestRequest() && function_exists( 'is_checkout' ) ) {
            return is_checkout();
        }

        // phpcs:disable HM.Security.ValidatedSanitizedInput, HM.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Recommended
        if ( ! $pageId && ! empty( $_GET['post'] ) ) {
            $pageId = (int) sanitize_text_field( wp_unslash( $_GET['post'] ) );
        }
        // phpcs:enable

        return $pageId && wc_get_page_id( 'checkout' ) === $pageId;
    }

    /**
     * Detects if the current or specified page is the WooCommerce customer account page.
     *
     * @since 1.0.0
     *
     * @param  int  $pageId Optional page ID to detect against.
     * @return bool         Returns true if this is the account page.
     */
    public function detectWooCommerceAccountPage( int $pageId = 0 ): bool {
        if ( ! $this->isWooCommerceLoaded() ) {
            return false;
        }

        if ( ! is_admin() && ! aioseo()->helpers->isAjaxCronRestRequest() && function_exists( 'is_account_page' ) ) {
            return is_account_page();
        }

        // phpcs:disable HM.Security.ValidatedSanitizedInput, HM.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Recommended
        if ( ! $pageId && ! empty( $_GET['post'] ) ) {
            $pageId = (int) sanitize_text_field( wp_unslash( $_GET['post'] ) );
        }
        // phpcs:enable

        return $pageId && wc_get_page_id( 'myaccount' ) === $pageId;
    }

    /**
     * Determines if the current page displays a WooCommerce product.
     *
     * @since 1.0.0
     *
     * @return bool Returns true if viewing a product page.
     */
    public function isViewingWooCommerceProduct(): bool {
        if ( ! $this->isWooCommerceLoaded() || ! function_exists( 'is_product' ) ) {
            return false;
        }

        return is_product();
    }

    /**
     * Evaluates if the current page is a WooCommerce product taxonomy archive.
     *
     * @since 1.0.0
     *
     * @return bool Returns true if viewing a product taxonomy page.
     */
    public function isViewingWooCommerceProductTaxonomy(): bool {
        if ( ! $this->isWooCommerceLoaded() || ! function_exists( 'is_product_taxonomy' ) ) {
            return false;
        }

        return is_product_taxonomy();
    }

    /**
     * Verifies if WooCommerce Follow Up Emails extension is active.
     *
     * @since 1.0.0
     *
     * @return bool Returns true when the extension is loaded.
     */
    public function isWooCommerceFollowUpEmailsLoaded(): bool {
        return defined( 'FUE_VERSION' ) || ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-follow-up-emails/woocommerce-follow-up-emails.php' ) );
    }

    /**
     * Checks if the provided taxonomy represents a WooCommerce product attribute.
     *
     * @since 1.0.0
     *
     * @param  mixed $taxonomyData The taxonomy object, array, or string to examine.
     * @return bool                Returns true if this is a product attribute taxonomy.
     */
    public function isWooCommerceProductAttributeTaxonomy( $taxonomyData ): bool {
        $taxonomyName = '';

        if ( is_object( $taxonomyData ) && isset( $taxonomyData->name ) ) {
            $taxonomyName = $taxonomyData->name;
        } elseif ( is_array( $taxonomyData ) && isset( $taxonomyData['name'] ) ) {
            $taxonomyName = $taxonomyData['name'];
        } elseif ( is_string( $taxonomyData ) ) {
            $taxonomyName = $taxonomyData;
        }

        return ! empty( $taxonomyName ) && str_starts_with( $taxonomyName, 'pa_' );
    }
}