<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ScanLinksResponseDto
 * Response DTO for scanning links for broken status
 * @property int $scanned_count
 * @property int $broken_count
 * @property int $active_count
 */
class ScanLinksResponseDto
{
    /**
     * @var int $scanned_count Number of links scanned
     */
    public int $scanned_count = 0;

    /**
     * @var int $broken_count Number of broken links found
     */
    public int $broken_count = 0;

    /**
     * @var int $active_count Number of active links found
     */
    public int $active_count = 0;
}