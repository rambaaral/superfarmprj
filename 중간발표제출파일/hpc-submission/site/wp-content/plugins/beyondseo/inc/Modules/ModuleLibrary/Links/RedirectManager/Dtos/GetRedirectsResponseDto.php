<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GetRedirectsResponseDto
 * Response DTO for getting all redirects with pagination
 * @property array[] $redirects
 */
class GetRedirectsResponseDto
{
    /**
     * @var array[] $redirects List of redirects
     */
    public array $redirects = [];

}
