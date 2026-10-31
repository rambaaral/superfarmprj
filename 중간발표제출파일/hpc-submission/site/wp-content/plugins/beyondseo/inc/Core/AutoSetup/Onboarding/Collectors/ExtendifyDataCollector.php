<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\AutoSetup\Onboarding\Collectors;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ExtendifyDataCollector extends AbstractCollector
{
    public string $collector = 'Extendify';

    public const WP_OPTIONS_KEY = 'extendify_user_selections';

    public function __construct(?int $id = null, array $settings = [])
    {
        parent::__construct($id, array_merge($settings, get_option(self::WP_OPTIONS_KEY, [])));
    }

    public function businessName(): ?string
    {
        return $this->getSetting('state.siteInformation.title', null);
    }

    public function businessDescription(): ?string
    {
        return $this->getSetting('state.businessInformation.description', null);
    }
}
