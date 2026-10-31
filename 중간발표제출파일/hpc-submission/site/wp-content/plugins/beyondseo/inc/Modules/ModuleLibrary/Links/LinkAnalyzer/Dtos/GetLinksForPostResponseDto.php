<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GetLinksForPostResponseDto
 * Response DTO for getting links for a specific post
 * @property array[] $inbound
 * @property array[] $outbound
 * @property array[] $external
 */
class GetLinksForPostResponseDto
{
    /**
     * @var array $inbound Inbound links to the post
     */
    public array $inbound = [];

    /**
     * @var array $outbound Outbound links from the post
     */
    public array $outbound = [];

    /**
     * @var array $external External links from the post
     */
    public array $external = [];
}
