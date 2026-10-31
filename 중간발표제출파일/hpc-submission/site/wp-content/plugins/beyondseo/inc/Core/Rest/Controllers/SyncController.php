<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Api\Content\ContentApiManager;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class SyncController
{
    use RcApiTrait;
    use RcLoggerTrait;

    public function keywords(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $result = ContentApiManager::handleKeywordsSynchronization();
            return new WP_REST_Response(['keywords' => $result], 200);
        } catch (Throwable $e) {
            $this->log('Error in SyncController::keywords: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }
}
