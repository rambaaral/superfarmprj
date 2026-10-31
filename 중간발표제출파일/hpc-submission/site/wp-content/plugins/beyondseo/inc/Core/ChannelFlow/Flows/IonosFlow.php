<?php
namespace RankingCoach\Inc\Core\ChannelFlow\Flows;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\ChannelFlow\Channels\ChannelInterface;
use RankingCoach\Inc\Core\ChannelFlow\FlowState;

final class IonosFlow implements ChannelInterface {
    private ?string $market;

    /**
     * Optional market context for IONOS (e.g., "DE"). Backward compatible: can be omitted.
     * If not provided, it will be read from wp_options('ionos_market') and normalized.
     */
    public function __construct(?string $market = null) {
        $this->market = $market !== null ? $this->normalizeMarket($market) : $this->loadNormalizedMarket();
    }

    public function getNextStep(FlowState $state): array {
        if (!$state->registered) {
            return ['step' => 'register', 'description' => 'User needs to register.'];
        }
        if (!$state->onboarded) {
            return ['step' => 'onboarding', 'description' => 'Proceed to onboarding.'];
        }
        return ['step' => 'done', 'description' => 'All IONOS steps completed.'];
    }

    /**
     * Attempt to read and normalize ionos_market from wp_options.
     * Examples: "de", "De", "de-DE", "de_de" => "DE"
     * Returns null when missing/invalid.
     */
    private function loadNormalizedMarket(): ?string {
        $value = get_option('ionos_market');
        if (!(is_string($value) || is_numeric($value))) {
            return null;
        }
        $raw = trim((string)$value);
        if ($raw === '') return null;

        $first = preg_split('/[-_]/', $raw)[0] ?? $raw;
        $token = strtoupper($first);
        $token = preg_replace('/[^A-Z0-9]/', '', $token) ?: '';

        return $token !== '' ? $token : null;
    }

    /**
     * Normalize an incoming market hint to uppercase first token.
     */
    private function normalizeMarket(string $value): ?string {
        $raw = trim($value);
        if ($raw === '') return null;
        $first = preg_split('/[-_]/', $raw)[0] ?? $raw;
        $token = strtoupper($first);
        $token = preg_replace('/[^A-Z0-9]/', '', $token) ?: '';
        return $token !== '' ? $token : null;
    }
}