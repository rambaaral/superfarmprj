<?php
namespace RankingCoach\Inc\Core\ChannelFlow\Flows;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\ChannelFlow\Channels\ChannelInterface;
use RankingCoach\Inc\Core\ChannelFlow\FlowState;

final class ExtendifyFlow implements ChannelInterface {
    private ?string $siteId;
    private ?string $partnerId;
    private array $userSelections;

    /**
     * Optional context for Extendify.
     * Backward compatible: all params optional; if omitted, they are loaded defensively from wp_options.
     */
    public function __construct(?string $siteId = null, ?string $partnerId = null, ?array $userSelections = null) {
        $this->siteId = $siteId !== null ? $this->normalizeScalar($siteId) : $this->loadSiteId();
        $this->partnerId = $partnerId !== null ? $this->normalizeScalar($partnerId) : $this->loadPartnerId();
        $this->userSelections = $userSelections !== null ? $userSelections : $this->loadUserSelections();
    }

    public function getNextStep(FlowState $state): array {
        // Channel resolution guarantees required IDs exist; keep logic silent and stable.
        if (!$state->registered || !$state->activated) {
            return ['step' => 'register', 'description' => 'Extendify data found. Register user and activate project.'];
        }
        if (!$state->onboarded) {
            return ['step' => 'onboarding', 'description' => 'Complete Extendify onboarding.'];
        }
        return ['step' => 'done', 'description' => 'All Extendify steps completed.'];
    }

    /**
     * Defensive loader for site ID from wp_options('extendify_site_id')
     */
    private function loadSiteId(): ?string {
        $value = get_option('extendify_site_id');
        if (is_string($value) || is_numeric($value)) {
            $v = $this->normalizeScalar((string)$value);
            return $v !== '' ? $v : null;
        }
        return null;
    }

    /**
     * Defensive loader for PartnerID:
     * - Prefer wp_options('extendify_partner_data_v2')['PartnerID']
     * - Fallback to EXTENDIFY_PARTNER_ID constant when defined
     */
    private function loadPartnerId(): ?string {
        $partnerId = null;

        $data = get_option('extendify_partner_data_v2');
        if (is_array($data)) {
            $partnerId = $data['PartnerID'] ?? null;
        } elseif (is_object($data)) {
            $arr = (array)$data;
            $partnerId = $arr['PartnerID'] ?? null;
        }

        if (is_string($partnerId) || is_numeric($partnerId)) {
            $partnerId = $this->normalizeScalar((string)$partnerId);
            if ($partnerId === '') {
                $partnerId = null;
            }
        } else {
            $partnerId = null;
        }

        if ($partnerId === null && defined('EXTENDIFY_PARTNER_ID')) {
            $const = constant('EXTENDIFY_PARTNER_ID');
            if (is_string($const) || is_numeric($const)) {
                $pid = $this->normalizeScalar((string)$const);
                if ($pid !== '') {
                    $partnerId = $pid;
                }
            }
        }

        return $partnerId;
    }

    /**
     * Optional user selections loader from wp_options('extendify_user_selections').
     * Returns [] when absent or invalid.
     */
    private function loadUserSelections(): array {
        $value = get_option('extendify_user_selections');
        if (is_array($value)) return $value;
        if (is_object($value)) return (array)$value;
        return [];
    }

    private function normalizeScalar(string $value): string {
        return trim($value);
    }
}