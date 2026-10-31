<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\AutoSetup\Onboarding\Collectors;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class RankingCoachDataCollector extends AbstractCollector
{
    public string $collector = 'RankingCoach';
}
