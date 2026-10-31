<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CreateRedirectRequestDto
 * Request DTO for creating a new redirect
 */
class CreateRedirectRequestDto
{
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
    public int $redirect_code = 301;
    
    /**
     * @var int Whether the redirect is active (0 or 1)
     */
    public int $active = 1;
}
