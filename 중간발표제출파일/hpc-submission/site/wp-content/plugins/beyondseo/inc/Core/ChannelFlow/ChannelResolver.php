<?php
namespace RankingCoach\Inc\Core\ChannelFlow;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Base\BaseConstants;

/**
 * Resolves the channel through which the user came to the plugin.
 */
final class ChannelResolver {
    /**
     * ChannelResolver constructor.
     */
    public function __construct(private OptionStore $store) {}

    /**
     * Resolve channel, using cached value if available
     */
    public function resolve($useCache = false): string {
        $saved = $this->store->getChannel();
        if ($useCache && $saved) return $saved;


        [$channel, $proofs] = $this->detectChannel();

        if ($channel === 'direct' && $saved && $saved !== 'direct') {
            return $saved;
        }

        $this->store->setChannel($channel, $proofs);
        return $channel;
    }

    /**
     * Detect channel with environment-specific logic
     * Returns array: [channel string, array of proofs]
     */
    public function detectChannel(): array {

        // Development/staging/local: Use complete detection logic
        return $this->detectChannelComplete();
    }

    /**
     * Check if we're running in production environment
     */
    private function isProductionEnvironment(): bool {
        return wp_get_environment_type() === 'production';
    }

    /**
     * Force IONOS channel in production environment with fallback handling
     */
    private function forceIonosInProduction(): array {
        $proofs = [];
        // Try normal IONOS detection first
        if ($this->isIonosBrandActive('ionos')) {
            $this->store->updateFlowState(function (FlowState $flowState) {
                $flowState->registered = true;
                if (get_option(BaseConstants::OPTION_ACTIVATION_CODE, null)) {
                    $flowState->activated = true;
                }
                if (get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_COMPLETED, false)) {
                    $flowState->onboarded = true;
                }
                return $flowState;
            });

            $proofs[] = ['ionos_group_brand'];
            $market = $this->getNormalizedIonosMarket();
            if ($market !== null) {
                // record that market metadata was available (do not fail if missing)
                $proofs[] = 'ionos_market';
            }
        } else {
            // Fallback: record that we forced IONOS channel
            $proofs[] = 'forced_ionos_in_production';
        }
        return ['ionos', $proofs];
    }

    /**
     * Complete channel detection logic for development/staging/local environments
     */
    private function detectChannelComplete(): array {
        // IONOS: resolve ONLY when ionos_group_brand normalized equals "ionos"
        // - Normalize by trim + case-insensitive compare
        // - ionos_market is optional; normalize to uppercase first token if present
        if ($this->isIonosBrandActive('ionos')) {
            $proofs = ['ionos_group_brand'];
            $market = $this->getNormalizedIonosMarket();
            if ($market !== null) {
                $proofs[] = 'ionos_market';
            }
            return ['ionos', $proofs];
        }

        // EXTENDIFY: resolve ONLY when both siteID and PartnerID are available
        // - siteID from option "extendify_site_id"
        // - PartnerID from option "extendify_partner_data_v2" (field "PartnerID") or fallback constant EXTENDIFY_PARTNER_ID
        // - user selections are optional context
        $siteId    = $this->getExtendifySiteId();
        $partnerId = $this->getExtendifyPartnerId();

        if ($siteId !== null && $partnerId !== null) {
            $proofs = ['extendify_site_id'];

            // Include which sources were present for traceability (non-functional)
            $partnerData = get_option('extendify_partner_data_v2');
            if (is_array($partnerData) || is_object($partnerData)) {
                $proofs[] = 'extendify_partner_data_v2';
            }
            if (defined('EXTENDIFY_PARTNER_ID')) {
                $proofs[] = 'EXTENDIFY_PARTNER_ID';
            }
            if (!empty($this->getExtendifyUserSelections())) {
                $proofs[] = 'extendify_user_selections';
            }

            return ['extendify', $proofs];
        }

        $subscription = get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION);
        if (is_string($subscription)) {
            $decoded = json_decode($subscription, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $subscription = $decoded;
            }
        }
        if (is_array($subscription)) {
            $subscription = $subscription['plan_name'] ?? $subscription['plan'] ?? '';
        }
        if (is_string($subscription) && str_starts_with(strtolower(trim($subscription)), 'seo_wp_')) {
            return ['ionos', ['ionos_subscription']];
        }

        return ['direct', []];
    }
    /**
     * Check if ionos_group_brand is set to "ionos" (case-insensitive, trimmed).
     */
    private function isIonosBrandActive($normalizedValue = null): bool {
        $value = get_option('ionos_group_brand');
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
        } elseif (is_scalar($value)) {
            $normalized = strtolower(trim((string)$value));
        } else {
            return false;
        }
        return $normalized === $normalizedValue;
    }

    /**
     * Read and normalize IONOS market to uppercase first token.
     * Examples: "de", "De", "de-DE", "de_de" => "DE"
     * Returns null when missing/invalid; resolution must not fail because of it.
     */
    private function getNormalizedIonosMarket(): ?string {
        $value = get_option('ionos_market');
        if (!(is_string($value) || is_numeric($value))) {
            return null;
        }
        $raw = trim((string)$value);
        if ($raw === '') return null;

        $first = preg_split('/[-_]/', $raw)[0] ?? $raw;
        $token = strtoupper($first);
        // keep ASCII letters/digits only; ignore the rest
        $token = preg_replace('/[^A-Z0-9]/', '', $token) ?: '';

        return $token !== '' ? $token : null;
    }

    /**
     * Get Extendify site ID from wp_options (stringified, non-empty).
     */
    private function getExtendifySiteId(): ?string {
        $value = get_option('extendify_site_id');
        if (is_string($value) || is_numeric($value)) {
            $siteId = trim((string)$value);
            return $siteId !== '' ? $siteId : null;
        }
        return null;
    }

    /**
     * Get Extendify PartnerID with fallback to EXTENDIFY_PARTNER_ID constant.
     * - Prefer wp_options('extendify_partner_data_v2')['PartnerID']
     * - Fallback to constant EXTENDIFY_PARTNER_ID if defined
     */
    private function getExtendifyPartnerId(): ?string {
        $partnerId = null;

        $data = get_option('extendify_partner_data_v2');
        if (is_array($data)) {
            $partnerId = $data['PartnerID'] ?? null;
        } elseif (is_object($data)) {
            $arr = (array) $data;
            $partnerId = $arr['PartnerID'] ?? null;
        }

        if (is_string($partnerId) || is_numeric($partnerId)) {
            $partnerId = trim((string)$partnerId);
            if ($partnerId === '') {
                $partnerId = null;
            }
        } else {
            $partnerId = null;
        }

        if ($partnerId === null && defined('EXTENDIFY_PARTNER_ID')) {
            $const = constant('EXTENDIFY_PARTNER_ID');
            if (is_string($const) || is_numeric($const)) {
                $pid = trim((string)$const);
                if ($pid !== '') {
                    $partnerId = $pid;
                }
            }
        }

        return $partnerId;
    }

    /**
     * Load optional Extendify user selections as array; returns [] when absent.
     */
    private function getExtendifyUserSelections(): array {
        $value = get_option('extendify_user_selections');
        if (is_array($value)) return $value;
        if (is_object($value)) return (array) $value;
        return [];
    }
}
