<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class AdvancedSettingsController
{
    use RcApiTrait;
    use RcLoggerTrait;

    private const FIELD_NOINDEX_FOR_PAGE = 'noindexForPage';
    private const FIELD_EXCLUDE_SITEMAP_FOR_PAGE = 'excludeSitemapForPage';
    private const FIELD_CANONICAL_URL = 'canonicalUrl';
    private const FIELD_DISABLE_AUTO_LINKS = 'disableAutoLinks';
    private const FIELD_VIEWPORT_FOR_PAGE = 'viewportForPage';

    private const FIELD_META_KEYS = [
        self::FIELD_NOINDEX_FOR_PAGE => MetaTags::META_NOINDEX_FOR_PAGE,
        self::FIELD_EXCLUDE_SITEMAP_FOR_PAGE => MetaTags::META_EXCLUDE_SITEMAP_FOR_PAGE,
        self::FIELD_CANONICAL_URL => MetaTags::META_CANONICAL_URL,
        self::FIELD_DISABLE_AUTO_LINKS => MetaTags::META_DISABLE_AUTO_LINKS,
        self::FIELD_VIEWPORT_FOR_PAGE => MetaTags::META_VIEWPORT_FOR_PAGE,
    ];

    private const BOOLEAN_FIELDS = [
        self::FIELD_NOINDEX_FOR_PAGE,
        self::FIELD_EXCLUDE_SITEMAP_FOR_PAGE,
        self::FIELD_DISABLE_AUTO_LINKS,
        self::FIELD_VIEWPORT_FOR_PAGE,
    ];

    private function getAdvancedSettingsData(int $postId): array
    {
        $data = [];

        foreach (self::BOOLEAN_FIELDS as $field) {
            $data[$field] = (bool) get_post_meta($postId, self::FIELD_META_KEYS[$field], true);
        }

        $canonicalUrl = get_post_meta($postId, self::FIELD_META_KEYS[self::FIELD_CANONICAL_URL], true);
        $data[self::FIELD_CANONICAL_URL] = ($canonicalUrl === '' || $canonicalUrl === false) ? null : (string) $canonicalUrl;

        return $data;
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');

            return new WP_REST_Response($this->getAdvancedSettingsData($postId), 200);
        } catch (Throwable $e) {
            $this->log('Error in AdvancedSettingsController::get: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');
            $body = $request->get_json_params() ?: [];

            foreach (self::BOOLEAN_FIELDS as $field) {
                $value = array_key_exists($field, $body) ? $body[$field] : false;
                update_post_meta($postId, self::FIELD_META_KEYS[$field], (bool) ($value ?? false));
            }

            if (array_key_exists(self::FIELD_CANONICAL_URL, $body)) {
                $canonicalUrl = $body[self::FIELD_CANONICAL_URL];
                $canonicalUrl = ($canonicalUrl === null || $canonicalUrl === '') ? '' : esc_url_raw((string) $canonicalUrl);
                update_post_meta($postId, self::FIELD_META_KEYS[self::FIELD_CANONICAL_URL], $canonicalUrl);
            }

            return new WP_REST_Response($this->getAdvancedSettingsData($postId), 200);
        } catch (Throwable $e) {
            $this->log('Error in AdvancedSettingsController::save: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }
}
