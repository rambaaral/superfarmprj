<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\ChannelFlow\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\FlowManager;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\TokensManager;

/**
 * FlowGuardTrait
 *
 * Shared Flow evaluation helper for admin pages to avoid duplication.
 * New canonical location: RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait
 */
trait FlowGuardTrait
{
    /**
     * Evaluate the current channel flow and return a normalized result.
     *
     * @return array{channel?:string,next_step?:string,description?:string,meta?:mixed}
     */
    private function evaluateFlow(): array
    {
        $store    = new OptionStore();
        $resolver = new ChannelResolver($store);
        $flow     = new FlowManager($store, $resolver);

        return $flow->evaluate();
    }

    private function canResumeOnboarding(): bool
    {
        if (!WordpressHelpers::isActivationCompleted() || CoreHelper::isOnboarded()) {
            return false;
        }

        try {
            $accessToken = TokensManager::getInstance()->getAccessToken(static::class);
            return TokensManager::validateToken($accessToken) === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function redirectActivatedAccountToOnboarding(): void
    {
        if (WordpressHelpers::sanitize_input('GET', 'bypass_flow') || !$this->canResumeOnboarding()) {
            return;
        }

        wp_safe_redirect(AdminManager::getPageUrl(AdminManager::PAGE_ONBOARDING));
        exit;
    }
}