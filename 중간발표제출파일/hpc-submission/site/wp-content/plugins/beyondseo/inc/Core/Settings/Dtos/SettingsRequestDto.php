<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Settings\Dtos;

if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Class SettingsRequestDto
 *
 * Data Transfer Object for settings update requests
 *
 * @property array[] $settings Settings data to update
 */
class SettingsRequestDto
{
    /**
     * Settings data to update
     * 
     * @var array[] $settings
     */
    public array $settings = [];
}