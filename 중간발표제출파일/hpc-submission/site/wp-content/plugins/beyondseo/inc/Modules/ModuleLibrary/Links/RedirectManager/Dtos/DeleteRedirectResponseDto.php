<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DeleteRedirectResponseDto
 * Response DTO for deleting a redirect
 */
class DeleteRedirectResponseDto
{
    /**
     * @var int Unique identifier for the deleted redirect
     */
    public int $id;
}
