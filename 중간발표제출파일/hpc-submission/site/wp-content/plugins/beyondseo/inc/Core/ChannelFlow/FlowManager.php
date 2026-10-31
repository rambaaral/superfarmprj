<?php
namespace RankingCoach\Inc\Core\ChannelFlow;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\ChannelFlow\Flows\{IonosFlow, ExtendifyFlow, DirectFlow};
use RankingCoach\Inc\Core\ChannelFlow\Channels\ChannelInterface;

final class FlowManager {
    public function __construct(private OptionStore $store, private ChannelResolver $resolver) {}

    public function evaluate(): array {
        $channel = $this->resolver->resolve();
        $state   = $this->store->getFlowState();

        $flow = $this->makeFlow($channel);
        $result = $flow->getNextStep($state);

        return [
            'channel' => $channel,
            'next_step' => $result['step'],
            'description' => $result['description'] ?? '',
            'meta' => $state->meta,
        ];
    }

    private function makeFlow(string $channel): ChannelInterface {
        return match ($channel) {
            // Pass optional, normalized market when available (backward-compatible)
            'ionos'     => new IonosFlow($this->getNormalizedIonosMarket()),
            // Pass siteID, PartnerID (required by resolver), and optional user selections (backward-compatible)
            'extendify' => new ExtendifyFlow(
                $this->getExtendifySiteId(),
                $this->getExtendifyPartnerId(),
                $this->getExtendifyUserSelections()
            ),
            default     => new DirectFlow(),
        };
    }

    // Helper methods replicate the defensive reads used in ChannelResolver for passing optional context

    private function getNormalizedIonosMarket(): ?string {
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

    private function getExtendifySiteId(): ?string {
        $value = get_option('extendify_site_id');
        if (is_string($value) || is_numeric($value)) {
            $siteId = trim((string)$value);
            return $siteId !== '' ? $siteId : null;
        }
        return null;
    }

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
            if ($partnerId === '') $partnerId = null;
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

    private function getExtendifyUserSelections(): array {
        $value = get_option('extendify_user_selections');
        if (is_array($value)) return $value;
        if (is_object($value)) return (array)$value;
        return [];
    }
}