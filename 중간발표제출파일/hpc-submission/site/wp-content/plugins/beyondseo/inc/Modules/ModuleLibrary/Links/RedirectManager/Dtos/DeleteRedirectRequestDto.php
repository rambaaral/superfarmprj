<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DeleteRedirectRequestDto
 * Request DTO for deleting a redirect
 */
class DeleteRedirectRequestDto
{
    /**
     * @var int Unique identifier for the redirect
     */
    public int $id;
}
