<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GetAllLinksResponseDto
 * Response DTO for getting all links with pagination
 * @property int $limit Maximum number of results returned
 * @property int $offset Offset for pagination
 * @property int $count Total count of links
 * @property array[] $data Array of link data
 */
class GetAllLinksResponseDto
{
    /**
     * @var int $limit Maximum number of results returned
     */
    public int $limit;
    
    /**
     * @var int $offset Offset for pagination
     */
    public int $offset;
    
    /**
     * @var int $count Total count of links
     */
    public int $count = 0;
    
    /**
     * @var array[] $data Array of link data
     */
    public array $data = [];
}
