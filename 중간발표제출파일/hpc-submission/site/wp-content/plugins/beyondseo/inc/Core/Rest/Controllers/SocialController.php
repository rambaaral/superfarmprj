<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\SocialMediaHelper;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Storage\MetaTagsStorage;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use RankingCoach\Inc\Modules\ModuleManager;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class SocialController
{
    use RcApiTrait;
    use RcLoggerTrait;

    private const TAG_TYPE_SOCIAL_TITLE = 'social_title';
    private const TAG_TYPE_SOCIAL_DESCRIPTION = 'social_description';

    private function upsertTagRow(int $postId, string $type, array $data): void
    {
        (new MetaTagsStorage())->save($postId, $type, $data['template'] ?? '');
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');

            /** @var MetaTags $metaTagsModule */
            $metaTagsModule = ModuleManager::instance()->get_module(MetaTags::MODULE_NAME);
            $response = $metaTagsModule->getMetaTagsData($postId);

            return new WP_REST_Response($response, 200);
        } catch (Throwable $e) {
            $this->log('Error in SocialController::get: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');
            $body = $request->get_json_params() ?: [];

            if (isset($body['socialTitle'])) {
                $rawTemplate = $body['socialTitle']['template'] ?? ($body['socialTitle']['content'] ?? '');
                $socialTitleTemplate = WordpressHelpers::sanitizeTemplateInput($rawTemplate, false);

                $this->upsertTagRow($postId, self::TAG_TYPE_SOCIAL_TITLE, [
                    'template' => $socialTitleTemplate,
                ]);
            }

            if (isset($body['socialDescription'])) {
                $rawTemplate = $body['socialDescription']['template'] ?? ($body['socialDescription']['content'] ?? '');
                $socialDescTemplate = WordpressHelpers::sanitizeTemplateInput($rawTemplate, true);

                $this->upsertTagRow($postId, self::TAG_TYPE_SOCIAL_DESCRIPTION, [
                    'template' => $socialDescTemplate,
                ]);
            }

            if (isset($body['selectedImageSource'])) {
                SocialMediaHelper::saveSelectedSocialImageSource(
                    $postId,
                    sanitize_text_field($body['selectedImageSource'])
                );
            }

            /** @var MetaTags $metaTagsModule */
            $metaTagsModule = ModuleManager::instance()->get_module(MetaTags::MODULE_NAME);
            return new WP_REST_Response($metaTagsModule->getMetaTagsData($postId), 200);
        } catch (Throwable $e) {
            $this->log('Error in SocialController::save: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function imageSources(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');
            $sources = SocialMediaHelper::getSocialImageSources($postId);

            return new WP_REST_Response(['imageSources' => $sources], 200);
        } catch (Throwable $e) {
            $this->log('Error in SocialController::imageSources: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }
}
