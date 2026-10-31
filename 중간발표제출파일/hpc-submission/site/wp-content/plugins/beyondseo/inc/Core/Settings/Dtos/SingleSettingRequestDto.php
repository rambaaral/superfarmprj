<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Settings\Dtos;

if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Class SingleSettingRequestDto
 * 
 * Data Transfer Object for single setting update requests
 *
 * @property mixed $value The updated key value
 */
class SingleSettingRequestDto
{
    /**
     * The updated key value
     * 
     * @var mixed
     */
    public mixed $value = null;
}