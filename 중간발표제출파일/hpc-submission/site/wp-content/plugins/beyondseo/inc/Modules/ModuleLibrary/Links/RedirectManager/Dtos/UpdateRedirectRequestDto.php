<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class UpdateRedirectRequestDto
 * Request DTO for updating an existing redirect
 */
class UpdateRedirectRequestDto
{
    /**
     * @var int Unique identifier for the redirect
     */
    public int $id;
    
    /**
     * @var string|null Source URI to redirect from
     */
    public ?string $source_uri = null;
    
    /**
     * @var string|null Destination URL to redirect to
     */
    public ?string $destination_url = null;
    
    /**
     * @var int|null HTTP redirect code (301 or 302)
     */
    public ?int $redirect_code = null;
    
    /**
     * @var int|null Whether the redirect is active (0 or 1)
     */
    public ?int $active = null;
}
