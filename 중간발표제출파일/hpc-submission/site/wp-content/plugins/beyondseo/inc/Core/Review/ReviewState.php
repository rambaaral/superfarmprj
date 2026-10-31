<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Review;

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class ReviewState
{
    public const DASHBOARD_SCORE_THRESHOLD = 70;
    public const EDITOR_SCORE_THRESHOLD = 75;
    public const MAX_DISPLAY_COUNT = 3;
    public const WAIT_SECONDS = 7 * 24 * 60 * 60;
    public const CUSTOMER_NOT_STARTED = 'not_started';
    public const CUSTOMER_STARTED = 'started';
    public const CUSTOMER_UNRESOLVED = 'unresolved';

    private array $reviewBaselines = [];
    private array $reviewResults = [];

    public function registerAnalysisHooks(): void
    {
        add_action('rankingcoach_seo_analysis_started', [$this, 'observeAnalysis'], 10, 1);
        add_action('rankingcoach_seo_analysis_completed', [$this, 'recordAnalysis'], 10, 1);
        add_filter('rankingcoach_review_analysis_result', [$this, 'getAnalysisResult'], 10, 3);
    }

    public function observeAnalysis(array $analysis): void
    {
        try {
            $scope = $analysis['scope'] ?? '';
            $postId = (int) ($analysis['postId'] ?? 0);
            $key = $scope === 'site' ? 'site' : 'post:' . $postId;
            $this->reviewResults[$key] = ['completed' => false, 'milestone' => false];
            $this->reviewBaselines[$key] = $scope === 'site'
                ? $this->observeSiteBaseline()
                : $this->observePostBaseline($postId, $analysis['previousScore'] ?? null);
        } catch (Throwable $e) {
        }
    }

    public function recordAnalysis(array $analysis): void
    {
        try {
            $scope = $analysis['scope'] ?? '';
            $postId = (int) ($analysis['postId'] ?? 0);
            $key = $scope === 'site' ? 'site' : 'post:' . $postId;
            $interactive = ($analysis['interactive'] ?? false) === true;
            $milestone = false;
            if ($this->reviewBaselines[$key] ?? false) {
                $milestone = $scope === 'site'
                    ? $this->recordSiteScore($analysis['metrics'], $interactive)
                    : $this->recordPostAnalysis($postId, $analysis['score'], $interactive);
            } elseif ($interactive) {
                $this->recordMeaningfulUse();
            }
            $this->reviewResults[$key] = [
                'completed' => $interactive,
                'milestone' => $milestone,
            ];
            if ($scope === 'post') {
                $this->reviewResults[$key]['postId'] = $postId;
                $this->reviewResults[$key]['score'] = $analysis['score'] * 100;
            }
        } catch (Throwable $e) {
        }
    }

    public function getAnalysisResult(array $default, string $scope, int $postId = 0): array
    {
        $key = $scope === 'site' ? 'site' : 'post:' . $postId;
        return array_merge($default, $this->reviewResults[$key] ?? []);
    }

    public function observeActivePlugin(): void
    {
        try {
            if ($this->isPluginActive()) {
                $this->recordPluginObservation($this->isNewInstallation());
            }
        } catch (Throwable $e) {
        }
    }

    public function isNewInstallation(): bool
    {
        try {
            $flowState = OptionStore::retrieveFlowState();
            return get_option(BaseConstants::OPTION_INSTALLATION_DATE, false) === false
                && get_option(BaseConstants::OPTION_INSTALLATION_ID, false) === false
                && get_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_DONE, false) === false
                && get_option(BaseConstants::OPTION_PLUGIN_VERSION, false) === false
                && get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, false) === false
                && get_option(BaseConstants::OPTION_REFRESH_TOKEN, false) === false
                && get_option(BaseConstants::OPTION_ACTIVATION_CODE, false) === false
                && get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_COMPLETED, false) === false
                && !$flowState->registered && !$flowState->activated
                && !$flowState->emailVerified && !$flowState->onboarded
                && $flowState->meta === [];
        } catch (Throwable $e) {
            return false;
        }
    }

    public function recordPluginObservation(bool $newInstallation = false): bool
    {
        try {
            $recorded = $this->recordFirstTimestamp(BaseConstants::OPTION_REVIEW_PLUGIN_OBSERVED_AT);
            if (get_option(BaseConstants::OPTION_REVIEW_CUSTOMER_STATE, false) === false) {
                $customerState = $newInstallation ? self::CUSTOMER_NOT_STARTED : self::CUSTOMER_UNRESOLVED;
                $recorded = update_option(BaseConstants::OPTION_REVIEW_CUSTOMER_STATE, $customerState, false)
                    && $recorded;
            }
            $this->recordOnboardingCompletion();
            return $recorded;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getCustomerState(): string
    {
        $storedState = get_option(BaseConstants::OPTION_REVIEW_CUSTOMER_STATE, false);
        if ($storedState === self::CUSTOMER_STARTED) {
            return self::CUSTOMER_STARTED;
        }

        return $storedState === self::CUSTOMER_NOT_STARTED
            ? self::CUSTOMER_NOT_STARTED
            : self::CUSTOMER_UNRESOLVED;
    }

    public function recordCustomerStarted(): bool
    {
        try {
            if (get_option(BaseConstants::OPTION_REVIEW_CUSTOMER_STATE, false) !== self::CUSTOMER_STARTED
                && !update_option(BaseConstants::OPTION_REVIEW_CUSTOMER_STATE, self::CUSTOMER_STARTED, false)) {
                return false;
            }
            $this->recordOnboardingCompletion();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function recordOnboardingCompletion(): bool
    {
        try {
            if (!$this->isCurrentOnboardingCompleted()) {
                return false;
            }
            return $this->recordFirstTimestamp(BaseConstants::OPTION_REVIEW_ONBOARDING_COMPLETED_AT);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function recordMeaningfulUse(): bool
    {
        try {
            return $this->recordFirstTimestamp(BaseConstants::OPTION_REVIEW_MEANINGFUL_USE_AT);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function observePostBaseline(int $postId, ?float $rawScore): bool
    {
        try {
            if ($postId <= 0) {
                return false;
            }
            $milestones = get_option(BaseConstants::OPTION_REVIEW_POST_MILESTONES, []);
            if (!is_array($milestones)) {
                return false;
            }
            if (array_key_exists($postId, $milestones)) {
                return is_bool($milestones[$postId]);
            }
            $breakdown = json_decode((string) get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_SCORE_BREAKDOWN, true), true);
            $scores = [$rawScore];
            if (is_array($breakdown) && isset($breakdown['score']) && is_numeric($breakdown['score'])) {
                $scores[] = (float) $breakdown['score'];
            }
            $validScores = array_filter($scores, static fn($score) => $score !== null
                && is_finite($score) && $score >= 0 && $score <= 1);
            if ($validScores !== []) {
                $qualified = max($validScores) > self::EDITOR_SCORE_THRESHOLD / 100;
            } else {
                $legacyScore = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, true);
                $qualified = $legacyScore !== ''
                    && (!is_numeric($legacyScore) || (float) $legacyScore >= self::EDITOR_SCORE_THRESHOLD);
            }
            $milestones[$postId] = $qualified;
            return update_option(BaseConstants::OPTION_REVIEW_POST_MILESTONES, $milestones, false);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function recordPostAnalysis(int $postId, float $rawScore, bool $interactive): bool
    {
        try {
            if ($postId <= 0 || !is_finite($rawScore) || $rawScore < 0 || $rawScore > 1) {
                return false;
            }
            if ($interactive) {
                $this->recordMeaningfulUse();
            }
            if ($rawScore <= self::EDITOR_SCORE_THRESHOLD / 100) {
                return false;
            }
            $milestones = get_option(BaseConstants::OPTION_REVIEW_POST_MILESTONES, []);
            if (!is_array($milestones) || !array_key_exists($postId, $milestones) || $milestones[$postId] !== false) {
                return false;
            }
            $milestones[$postId] = true;
            if (!update_option(BaseConstants::OPTION_REVIEW_POST_MILESTONES, $milestones, false)) {
                return false;
            }
            return $interactive && $this->isEligible();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function observeSiteBaseline(): bool
    {
        try {
            $previous = get_option(BaseConstants::OPTION_REVIEW_SITE_GREEN, false);
            if ($previous !== false) {
                return $previous === 'green' || $previous === 'not_green';
            }
            $metrics = [
                'average_score' => get_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE, false),
                'count' => get_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT, false),
                'min_score' => get_option(BaseConstants::OPTION_ANALYSIS_SCORE_MIN, false),
                'max_score' => get_option(BaseConstants::OPTION_ANALYSIS_SCORE_MAX, false),
            ];
            if ($this->isValidSiteScore($metrics)) {
                $green = (float) $metrics['average_score'] > self::DASHBOARD_SCORE_THRESHOLD && (float) $metrics['max_score'] > 0;
            } elseif (array_filter($metrics, static fn($value) => $value !== false) === []) {
                $green = false;
            } else {
                return false;
            }
            return update_option(BaseConstants::OPTION_REVIEW_SITE_GREEN, $green ? 'green' : 'not_green', false);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isValidSiteScore(array $metrics): bool
    {
        foreach (['average_score', 'count', 'min_score', 'max_score'] as $key) {
            if (!isset($metrics[$key]) || !is_numeric($metrics[$key]) || !is_finite((float) $metrics[$key])) {
                return false;
            }
        }
        return (float) $metrics['count'] > 0
            && (float) $metrics['count'] === (float) (int) $metrics['count']
            && (float) $metrics['min_score'] >= 0
            && (float) $metrics['max_score'] <= 100
            && (float) $metrics['min_score'] <= (float) $metrics['average_score']
            && (float) $metrics['average_score'] <= (float) $metrics['max_score'];
    }

    public function recordSiteScore(array $metrics, bool $interactive): bool
    {
        try {
            if (!$this->isValidSiteScore($metrics)) {
                return false;
            }
            $previous = get_option(BaseConstants::OPTION_REVIEW_SITE_GREEN, false);
            $state = (float) $metrics['average_score'] > self::DASHBOARD_SCORE_THRESHOLD && (float) $metrics['max_score'] > 0
                ? 'green' : 'not_green';
            if ($previous !== $state && !update_option(BaseConstants::OPTION_REVIEW_SITE_GREEN, $state, false)) {
                return false;
            }
            if ($interactive) {
                $this->recordMeaningfulUse();
            }
            return $interactive && $previous === 'not_green' && $state === 'green' && $this->isEligible();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isEligible(): bool
    {
        try {
            if (!$this->isPluginActive() || $this->isSuppressed()) {
                return false;
            }

            $now = time();
            $observedAt = $this->getTimestamp(BaseConstants::OPTION_REVIEW_PLUGIN_OBSERVED_AT);
            $meaningfulUseAt = $this->getTimestamp(BaseConstants::OPTION_REVIEW_MEANINGFUL_USE_AT);
            if ($observedAt === 0 || $now - $observedAt < self::WAIT_SECONDS
                || $meaningfulUseAt === 0 || $meaningfulUseAt > $now) {
                return false;
            }

            $customerState = $this->getCustomerState();
            if ($customerState === self::CUSTOMER_NOT_STARTED) {
                return true;
            }
            if ($customerState !== self::CUSTOMER_STARTED || !$this->isCurrentOnboardingCompleted()) {
                return false;
            }

            $completedAt = $this->getTimestamp(BaseConstants::OPTION_REVIEW_ONBOARDING_COMPLETED_AT);
            return $completedAt > 0 && $now - $completedAt >= self::WAIT_SECONDS;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function isPluginActive(): bool
    {
        $pluginBasename = plugin_basename(RANKINGCOACH_FILE);
        if (in_array($pluginBasename, (array) get_option('active_plugins', []), true)) {
            return true;
        }
        if (is_multisite()) {
            $activeSitewidePlugins = (array) get_site_option('active_sitewide_plugins', []);
            if (isset($activeSitewidePlugins[$pluginBasename])) {
                return true;
            }
        }

        return defined('WPMU_PLUGIN_DIR')
            && str_starts_with(wp_normalize_path(RANKINGCOACH_FILE), wp_normalize_path(WPMU_PLUGIN_DIR) . '/');
    }

    private function isCurrentOnboardingCompleted(): bool
    {
        if (!WordpressHelpers::isOnboardingCompleted(true, false)) {
            return false;
        }

        $now = time();
        $internalAt = $this->getTimestamp(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE);
        $externalAt = $this->getTimestamp(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE);
        return $internalAt > 0 && $internalAt <= $now && $externalAt > 0 && $externalAt <= $now;
    }

    private function getTimestamp(string $option): int
    {
        $value = get_option($option, 0);
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return 0;
        }
        return max(0, (int) $value);
    }

    private function recordFirstTimestamp(string $option): bool
    {
        if (get_option($option, false) !== false) {
            return $this->getTimestamp($option) > 0;
        }
        return update_option($option, time(), false);
    }

    public function getDisplayCount(): int
    {
        return max(0, (int) get_option(BaseConstants::OPTION_REVIEW_DISPLAY_COUNT, 0));
    }

    public function getCooldownUntil(): int
    {
        return max(0, (int) get_option(BaseConstants::OPTION_REVIEW_COOLDOWN_UNTIL, 0));
    }

    public function isPermanentlyStopped(): bool
    {
        return (bool) get_option(BaseConstants::OPTION_REVIEW_PERMANENT_STOP, false);
    }

    public function isSuppressed(): bool
    {
        return $this->isPermanentlyStopped()
            || $this->getDisplayCount() >= self::MAX_DISPLAY_COUNT
            || $this->getCooldownUntil() > time();
    }

    public function incrementDisplayCount(): bool
    {
        return update_option(BaseConstants::OPTION_REVIEW_DISPLAY_COUNT, $this->getDisplayCount() + 1, false);
    }

    public function extendCooldown(int $until): bool
    {
        $current = $this->getCooldownUntil();
        if ($until <= $current) {
            return true;
        }

        return update_option(BaseConstants::OPTION_REVIEW_COOLDOWN_UNTIL, $until, false);
    }

    public function stopPermanently(): bool
    {
        if ($this->isPermanentlyStopped()) {
            return true;
        }

        return update_option(BaseConstants::OPTION_REVIEW_PERMANENT_STOP, true, false);
    }
}
