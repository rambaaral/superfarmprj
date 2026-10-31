<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GetRedirectResponseDto
 * Response DTO for getting a single redirect
 */
class GetRedirectResponseDto
{
    /**
     * @var int Unique identifier for the redirect
     */
    public int $id;
    
    /**
     * @var string Source URI to redirect from
     */
    public string $source_uri;
    
    /**
     * @var string Destination URL to redirect to
     */
    public string $destination_url;
    
    /**
     * @var int HTTP redirect code (301 or 302)
     */
    public int $redirect_code;
    
    /**
     * @var int Whether the redirect is active (0 or 1)
     */
    public int $active;
    
    /**
     * @var string Date when the redirect was created
     */
    public string $created_at;
    
    /**
     * @var string|null Date when the redirect was last updated
     */
    public ?string $updated_at = null;
}
