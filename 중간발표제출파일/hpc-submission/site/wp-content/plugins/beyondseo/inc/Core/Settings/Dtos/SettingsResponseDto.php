<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Settings\Dtos;

if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Class SettingsResponseDto
 * 
 * Data Transfer Object for settings API responses
 *
 * @property bool $success Indicates if the request was successful
 * @property string $message Response message
 * @property array[] $response Response data array
 * @property string[] $error Validation errors (if any)
 * @property array[] $meta Response metadata
 * @property array[] $request Request information
 */
class SettingsResponseDto
{
    /**
     * Success status
     *
     * @var bool
     */
    public bool $success = true;

    /**
     * Response message
     *
     * @var string
     */
    public string $message = '';

    /**
     * Response data array
     *
     * @var array
     */
    public array $response = [];

    /**
     * Validation errors (if any)
     *
     * @var array
     */
    public array $error = [];

    /**
     * Response metadata
     *
     * @var array
     */
    public array $meta = [];

    /**
     * Request information
     *
     * @var array
     */
    public array $request = [];
}