<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Traits;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use JsonException;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use WP_Error;

/**
 * Trait ResponseErrorTrait
 * 
 * Provides methods for handling and formatting API and application errors.
 */
trait ResponseErrorTrait {
    use RcLoggerTrait;

    /**
     * Cached result of WordPress production check
     * @var bool|null
     */
    private ?bool $isWordPressProduction = null;

    /**
     * Initialize environment state
     */
    private function initResponseErrorTrait(): void
    {
        if ($this->isWordPressProduction === null) {
            $this->isWordPressProduction = wp_get_environment_type() === 'production' ||
                !defined('WP_DEBUG') || WP_DEBUG === false;
        }
    }

    /**
     * Process an exception and return a formatted error array or WP_Error
     *
     * @param Exception $e
     * @param string|null $customMessage
     * @return array
     */
    public function processException(Exception $e, ?string $customMessage = null): array
    {
        $this->initResponseErrorTrait();
        $this->log($e->getMessage() . ' --> ' . $e->getTraceAsString(), 'ERROR');

        // Build base response data
        $data = [
            'success' => false,
            'message' => $customMessage ?? $this->getFirstErrorMessage($e),
            'error' => true
        ];

        // Add detailed information only in non-production environments
        if (!$this->isWordPressProduction) {
            $data['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $data;
    }

    /**
     * Get the first error message from the exception
     *
     * @param Exception $e
     * @return string
     */
    private function getFirstErrorMessage(Exception $e): string
    {
        $message = $e->getMessage();
        
        if ($message === 'Invalid API') {
            return __('There was a problem with the API request. Please try again later.', 'beyondseo');
        }

        // Try to parse JSON error responses if message looks like JSON
        if (str_starts_with($message, '{') || str_starts_with($message, '[')) {
            try {
                $details = json_decode($message, false, 512, JSON_THROW_ON_ERROR);
                if (property_exists($details, 'error') && !empty($details->error)) {
                    return (string)$details->error;
                }
                if (property_exists($details, 'message') && !empty($details->message)) {
                    return (string)$details->message;
                }
            } catch (JsonException) {
                // Not valid JSON, fall back to raw message
            }
        }

        return $message ?: __('An unknown error occurred.', 'beyondseo');
    }
}
