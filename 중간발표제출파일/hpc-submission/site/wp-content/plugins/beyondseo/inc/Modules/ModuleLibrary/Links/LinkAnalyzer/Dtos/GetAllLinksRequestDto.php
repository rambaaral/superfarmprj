<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GetAllLinksRequestDto
 * Request DTO for getting all links with pagination
 * @property int $limit
 * @property int $offset
 */
class GetAllLinksRequestDto
{
    /**
     * @var int $limit Maximum number of links to return
     */
    public int $limit = 100;

    /**
     * @var int $offset Offset for pagination
     */
    public int $offset = 0;
}