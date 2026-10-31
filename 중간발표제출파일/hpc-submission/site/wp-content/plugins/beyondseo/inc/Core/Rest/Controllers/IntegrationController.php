<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class IntegrationController
{
    use RcApiTrait;
    use RcLoggerTrait;

    public function status(WP_REST_Request $request): WP_REST_Response
    {
        try {
            return new WP_REST_Response(['ok' => true], 200);
        } catch (Throwable $e) {
            $this->log('Error in IntegrationController::status: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }
}
