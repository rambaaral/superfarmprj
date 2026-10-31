<?php
namespace RankingCoach\Inc\Core\ChannelFlow;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\DB\DatabaseManager;

/**
 * Class OptionStore
 * Handles storage and retrieval of channel detection and flow state options.
 */
final class OptionStore {
    private const KEY_CHANNEL = 'bseo_channel';
    private const KEY_CHANNEL_META = 'bseo_channel_meta';
    private const KEY_FLOW_STATE = 'bseo_flow_state';
    private const KEY_FLOW_GUARD_ACTIVE = 'rankingcoach_flow_guard_active';

    /**
     * Retrieve the detected channel from the database.
     */
    public function getChannel(): ?string {
        return get_option(self::KEY_CHANNEL) ?: 'direct';
    }

    /**
     * Retrieve the metadata associated with the detected channel.
     */
    public function getChannelMeta(): array {
        return get_option(self::KEY_CHANNEL_META, []);
    }

    /**
     * Store the detected channel and its proofs in the database.
     */
    public function setChannel(string $channel, array $proofs = []): void {
        update_option(self::KEY_CHANNEL, $channel, false);
        update_option(self::KEY_CHANNEL_META, [
            'channel' => $channel,
            'detected_at' => time(),
            'proofs' => $proofs,
        ], false);
    }

    /**
     * Retrieve the current flow state from the database.
     * Automatically restores state from environment if data is missing or empty.
     */
    public function getFlowState(): FlowState {
        $data = get_option(self::KEY_FLOW_STATE, false);

        // Check if data is missing (option not set) or empty/invalid
        if (!is_array($data) || empty($data)) {
            // Restore state from environment markers
            return $this->restoreFlowStateFromEnvironment();
        }

        // Return existing valid state
        return FlowState::fromArray($data);
    }

    /**
     * Save the given flow state to the database.
     */
    public function saveFlowState(FlowState $state): bool {
        update_option(self::KEY_FLOW_STATE, $state->toArray(), false);
        wp_cache_delete(self::KEY_FLOW_STATE, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');

        return get_option(self::KEY_FLOW_STATE, null) === $state->toArray();
    }

    /**
     * Safely update flow state using a callback to prevent race conditions.
     * The callback receives the current FRESH state and should return the modified state.
     *
     * @param callable $updater function(FlowState $state): FlowState
     * @return void
     */
    public function updateFlowState(callable $updater): void {
        // 1. Fetch fresh state directly from DB
        $currentState = $this->getFlowState();

        // 2. Apply updates via callback
        $newState = $updater($currentState);

        // 3. Save back to DB
        $this->saveFlowState($newState);
    }

    /**
     * Check if FlowGuard is active.
     * IMPORTANT: Once a channel is detected and stored, FlowGuard becomes MANDATORY
     * and cannot be disabled. This ensures business rules are always enforced.
     *
     * @return bool
     */
    public static function isFlowGuardActive(): bool {
        // Check if channel has been detected and stored
        $channel = get_option(self::KEY_CHANNEL);
        
        // Once channel is detected, FlowGuard is ALWAYS active (cannot be disabled)
        if (!empty($channel)) {
            return true;
        }
        
        // Before channel detection, use the stored flag (for initial setup)
        return (bool) get_option(self::KEY_FLOW_GUARD_ACTIVE, true);
    }

    /**
     * Check if the channel has been locked (detected and stored).
     * Once locked, FlowGuard cannot be disabled.
     *
     * @return bool
     */
    public static function isChannelLocked(): bool {
        $channel = get_option(self::KEY_CHANNEL);
        return !empty($channel);
    }

    /**
     * Enable FlowGuard globally.
     *
     * @return void
     */
    public static function enableFlowGuard(): void {
        update_option(self::KEY_FLOW_GUARD_ACTIVE, true);
    }

    /**
     * Disable FlowGuard.
     * NOTE: This only works before channel detection. After channel is detected,
     * FlowGuard is permanently active for security.
     *
     * @return void
     */
    public static function disableFlowGuard(): void {
        // Check if channel has been detected
        $channel = get_option(self::KEY_CHANNEL);
        
        if (!empty($channel)) {
            // Log warning - FlowGuard cannot be disabled after channel detection
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] WARNING: Attempted to disable FlowGuard after channel detection. Operation ignored for security.');
            }
            return;
        }
        
        update_option(self::KEY_FLOW_GUARD_ACTIVE, false);
    }

    /**
     * Restore flow state from environment markers (hard evidence).
     * Useful if state gets corrupted or desynchronized.
     */
    public function restoreFlowStateFromEnvironment(): FlowState {
        $state = new FlowState();

        // 1. Check Registration (Account ID existence)
        $accountId = get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID);
        if (!empty($accountId)) {
            $state->registered = true;
        }

        // 2. Check Email Verification (subscription name implies verified)
        $hasSubscriptionName = get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION);
        if ($hasSubscriptionName) {
            $state->emailVerified = true;
        }

        // 3. Check Onboarding (Onboarding Completed flag)
        $onboardingCompleted = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_COMPLETED);
        if ($onboardingCompleted) {
            $state->onboarded = true;
        }

        $this->saveFlowState($state);
        return $state;
    }

    /**
     * Retrieve stored channel metadata.
     */
    public static function retrieveChannel(): array {
        $meta = get_option(self::KEY_CHANNEL_META, []);
        return is_array($meta) ? $meta : [];
    }

    /**
     * Retrieve stored flow state.
     */
    public static function retrieveFlowState(): FlowState {
        $data = get_option(self::KEY_FLOW_STATE, []);
        return FlowState::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Reset FlowGuard state completely including IONOS/EXTENDIFY simulation rollback
     * This performs a full cleanup of all channel detection and flow state data
     */
    public function resetFlowGuardState(): bool {
        // 1. Delete IONOS detection options
        delete_option('ionos_group_brand');
        delete_option('ionos_market');
        
        // 2. Delete EXTENDIFY detection options
        delete_option('extendify_site_id');
        delete_option('extendify_partner_data_v2');
        delete_option('extendify_user_selections');
        
        // 3. Delete FlowGuard state and channel cache
        $this->removeFlowState();
        
        // 4. Cleanup transients
        $this->cleanupTransients();
        
        // 5. Flush cache
        wp_cache_flush();
        
        return true;
    }

    /**
     * Reset only flow state, keeping channel detection
     */
    public function resetFlowStateOnly(): void {
        delete_option(self::KEY_FLOW_STATE);
        $this->cleanupTransients();
        wp_cache_flush();
    }

    /**
     * Remove flow state and channel data
     */
    public function removeFlowState(): void {
        delete_option(self::KEY_CHANNEL);
        delete_option(self::KEY_CHANNEL_META);
        delete_option(self::KEY_FLOW_STATE);
    }

    /**
     * Cleanup registration transients from wp_options
     */
    private function cleanupTransients(): void {
        DatabaseManager::getInstance()
            ->table('options')
            ->delete()
            ->whereOr(function($group) {
                $group->whereRaw("option_name LIKE '_transient_rc_reg_poll_%'");
                $group->whereRaw("option_name LIKE '_transient_timeout_rc_reg_poll_%'");
            })
            ->get();
    }
}
