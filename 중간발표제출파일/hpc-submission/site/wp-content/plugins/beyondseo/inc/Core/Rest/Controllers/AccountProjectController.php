<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\CommunicationOptInDisabledException;
use RankingCoach\Inc\Exceptions\HttpApiException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class AccountProjectController
{
    private const MUTATION_LOCK = 'rankingcoach_account_project_deletion_lock';

    private const CONNECTION_OPTIONS = [
        BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID,
        BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID,
        BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID,
        BaseConstants::OPTION_ACTIVATION_CODE,
        BaseConstants::OPTION_ACCESS_TOKEN,
        BaseConstants::OPTION_REFRESH_TOKEN,
        BaseConstants::OPTION_REGISTRATION_EMAIL_ADDRESS,
        'bseo_flow_state',
    ];

    public static function isValidLocationId(mixed $value): bool
    {
        return self::positiveIdentifier($value) !== null;
    }

    private static function positiveIdentifier(mixed $value): ?int
    {
        if (!is_int($value) && (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value))) {
            return null;
        }

        $identifier = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $identifier === false ? null : $identifier;
    }

    private function refreshConnectionOptions(): void
    {
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        foreach (self::CONNECTION_OPTIONS as $option) {
            wp_cache_delete($option, 'options');
        }
    }

    private function getProjectState(): array
    {
        $this->refreshConnectionOptions();
        $accountId = self::positiveIdentifier(get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID));
        $refreshToken = get_option(BaseConstants::OPTION_REFRESH_TOKEN, '');
        $accountAuthenticated = $accountId !== null && is_string($refreshToken) && $refreshToken !== '';
        $projectId = self::positiveIdentifier(get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID));
        $locationId = self::positiveIdentifier(get_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID));
        $connected = $accountAuthenticated && $projectId !== null && $locationId !== null;
        $activationCode = get_option(BaseConstants::OPTION_ACTIVATION_CODE, '');
        $email = get_option(BaseConstants::OPTION_REGISTRATION_EMAIL_ADDRESS, '');
        $connectionMethod = OptionStore::retrieveFlowState()->meta['connectionMethod'] ?? null;
        if (WordpressHelpers::isPartnerIntegration() || (new OptionStore())->getChannel() === 'ionos') {
            $connectionMethod = 'activation';
        } elseif (!in_array($connectionMethod, ['email', 'activation'], true)) {
            $connectionMethod = is_string($activationCode) && $activationCode !== '' ? 'activation' : 'email';
        }

        $maskedActivationCode = null;
        if ($accountAuthenticated && $connectionMethod === 'activation' && is_string($activationCode) && $activationCode !== '') {
            $maskedActivationCode = strlen($activationCode) > 4 ? '********' . substr($activationCode, -4) : '********';
        }

        return [
            'connected' => $connected,
            'accountAuthenticated' => $accountAuthenticated,
            'accountId' => $accountAuthenticated ? $accountId : null,
            'connectionMethod' => $accountAuthenticated ? $connectionMethod : null,
            'email' => $accountAuthenticated && $connectionMethod === 'email' && is_string($email) && is_email($email) ? $email : null,
            'maskedActivationCode' => $maskedActivationCode,
            'projectId' => $connected ? $projectId : null,
            'locationId' => $connected ? $locationId : null,
            'website' => home_url(),
        ];
    }

    private function getConnectionIdentity(): array
    {
        $this->refreshConnectionOptions();

        return [
            'accountId' => self::positiveIdentifier(get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID)),
            'projectId' => self::positiveIdentifier(get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID)),
            'locationId' => self::positiveIdentifier(get_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID)),
            'activationCode' => get_option(BaseConstants::OPTION_ACTIVATION_CODE, null),
        ];
    }

    private function connectionIsCurrent(array $identity): bool
    {
        return $this->getConnectionIdentity() === $identity && WordpressHelpers::isActivationCompleted();
    }

    private function changedConnectionError(): WP_Error
    {
        return new WP_Error(
            'account_project_changed',
            __('The account or project connection has changed. Refresh Account before trying again.', 'beyondseo'),
            ['status' => 409]
        );
    }

    private function cleanupError(): WP_Error
    {
        return new WP_Error(
            'account_project_cleanup_failed',
            __('Project deletion was accepted, but the local project identifiers could not be fully removed. Your account and local data have been retained. Refresh Account and contact support before creating another project.', 'beyondseo'),
            ['status' => 500, 'project' => $this->getProjectState()]
        );
    }

    private function acquireMutationLock(): string|WP_Error
    {
        global $wpdb;
        $lockOwner = wp_generate_uuid4();
        $lockAcquired = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
            self::MUTATION_LOCK,
            $lockOwner,
            'no'
        ));
        if ($lockAcquired === false) {
            return new WP_Error(
                'account_project_lock_failed',
                __('Unable to safely start the project operation. Please try again or contact support.', 'beyondseo'),
                ['status' => 500]
            );
        }
        if ($lockAcquired !== 1) {
            return new WP_Error(
                'account_project_operation_in_progress',
                __('A project operation is already in progress. Wait for it to finish and refresh Account. If this persists, contact support.', 'beyondseo'),
                ['status' => 409]
            );
        }

        return $lockOwner;
    }

    private function releaseMutationLock(string $lockOwner): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->options, [
            'option_name' => self::MUTATION_LOCK,
            'option_value' => $lockOwner,
        ], ['%s', '%s']);
        wp_cache_delete(self::MUTATION_LOCK, 'options');
        wp_cache_delete('notoptions', 'options');
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $lockOwner = $this->acquireMutationLock();
        if (is_wp_error($lockOwner)) {
            return $lockOwner;
        }

        $savingProject = false;
        try {
            $identity = $this->getConnectionIdentity();
            $project = $this->getProjectState();
            if (!$project['accountAuthenticated']) {
                return new WP_Error(
                    'account_project_not_authenticated',
                    __('Connect your account before creating a project.', 'beyondseo'),
                    ['status' => 409]
                );
            }
            if ($project['connected']) {
                return new WP_REST_Response(['project' => $project], 200);
            }

            $connectionIsCurrent = fn(): bool => $this->getConnectionIdentity() === $identity
                && $this->getProjectState()['accountAuthenticated'];
            $api = new UserApiManager();
            $content = $api->createRemoteProject($connectionIsCurrent);
            if (!$connectionIsCurrent()) {
                return $this->changedConnectionError();
            }

            $accountId = is_object($content) ? self::positiveIdentifier($content->accountId ?? null) : null;
            $projectId = is_object($content) ? self::positiveIdentifier($content->projectId ?? null) : null;
            $locationId = is_object($content) ? self::positiveIdentifier($content->locationId ?? null) : null;
            if ($accountId !== $identity['accountId'] || $projectId === null || $locationId === null) {
                return new WP_Error(
                    'account_project_invalid_response',
                    __('The service did not return a valid project for your account. Refresh Account and try again or contact support.', 'beyondseo'),
                    ['status' => 502]
                );
            }
            if (($identity['projectId'] !== null && $identity['projectId'] !== $projectId)
                || ($identity['locationId'] !== null && $identity['locationId'] !== $locationId)) {
                return $this->changedConnectionError();
            }

            $savingProject = true;
            update_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, $projectId);
            update_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID, $locationId);
            $savedIdentity = $this->getConnectionIdentity();
            if ($savedIdentity['accountId'] !== $identity['accountId']
                || $savedIdentity['activationCode'] !== $identity['activationCode']
                || $savedIdentity['projectId'] !== $projectId || $savedIdentity['locationId'] !== $locationId) {
                return $this->creationSaveError();
            }

            $project = $this->getProjectState();
            if (!$project['connected']) {
                return $this->creationSaveError();
            }

            return new WP_REST_Response(['project' => $project], 200);
        } catch (CommunicationOptInDisabledException $e) {
            return new WP_Error(
                'account_project_communication_disabled',
                __('Enable communication with rankingCoach in Settings before creating a project.', 'beyondseo'),
                ['status' => 403]
            );
        } catch (HttpApiException $e) {
            if ($e->getCode() === 409) {
                return $this->changedConnectionError();
            }
            if (in_array($e->getCode(), [401, 403], true)) {
                return new WP_Error(
                    'account_project_authentication_failed',
                    __('The service could not authenticate project creation. Your account data has been retained. Contact support to restore access before retrying.', 'beyondseo'),
                    ['status' => 502]
                );
            }

            return $this->creationUpstreamError();
        } catch (Throwable $e) {
            return $savingProject ? $this->creationSaveError() : $this->creationUpstreamError();
        } finally {
            $this->releaseMutationLock($lockOwner);
        }
    }

    private function creationSaveError(): WP_Error
    {
        return new WP_Error(
            'account_project_save_failed',
            __('The service returned a project, but its local connection could not be fully saved. Refresh Account and try again or contact support. Your account and local data have been retained.', 'beyondseo'),
            ['status' => 500, 'project' => $this->getProjectState()]
        );
    }

    private function creationUpstreamError(): WP_Error
    {
        return new WP_Error(
            'account_project_creation_failed',
            __('Unable to confirm project creation. Your account and local data have been retained. Refresh Account and try again or contact support.', 'beyondseo'),
            ['status' => 502]
        );
    }

    public function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->getProjectState(), 200);
        } catch (Throwable $e) {
            return new WP_Error(
                'account_project_read_failed',
                __('Unable to load the local project connection. Please try again.', 'beyondseo'),
                ['status' => 500]
            );
        }
    }

    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $locationId = self::positiveIdentifier($request->get_url_params()['locationId'] ?? null);
        if ($locationId === null) {
            return new WP_Error('account_project_invalid_id', __('The project location ID is invalid.', 'beyondseo'), ['status' => 400]);
        }

        $lockOwner = $this->acquireMutationLock();
        if (is_wp_error($lockOwner)) {
            return $lockOwner;
        }

        $outcome = null;
        try {
            $identity = $this->getConnectionIdentity();
            if ($identity['accountId'] === null || $identity['projectId'] === null
                || $identity['locationId'] !== $locationId || !WordpressHelpers::isActivationCompleted()) {
                return $this->changedConnectionError();
            }

            $api = new UserApiManager();
            $outcome = $api->deleteRemoteProject($locationId, fn(): bool => $this->connectionIsCurrent($identity));
            if (is_wp_error($outcome)) {
                return $outcome;
            }

            if (!$this->connectionIsCurrent($identity)) {
                return $this->changedConnectionError();
            }

            if (!TokensManager::instance()->disconnectProject()) {
                return $this->cleanupError();
            }

            $project = $this->getProjectState();
            if ($project['connected']) {
                return $this->cleanupError();
            }

            return new WP_REST_Response([
                'deletionResult' => ['outcome' => $outcome],
                'project' => $project,
            ], 200);
        } catch (CommunicationOptInDisabledException $e) {
            return new WP_Error(
                'account_project_communication_disabled',
                __('Enable communication with rankingCoach in Settings before deleting the project. Your local connection has not been removed.', 'beyondseo'),
                ['status' => 403]
            );
        } catch (HttpApiException $e) {
            if ($e->getCode() === 409) {
                return $this->changedConnectionError();
            }
            if (in_array($e->getCode(), [401, 403], true)) {
                return new WP_Error(
                    'account_project_authentication_failed',
                    __('The service could not authenticate project deletion. Your local connection has not been removed. Contact support to restore access before retrying.', 'beyondseo'),
                    ['status' => 502]
                );
            }

            return new WP_Error(
                'account_project_upstream_failed',
                __('The service could not confirm project deletion. Your local connection has not been removed. Please try again or contact support.', 'beyondseo'),
                ['status' => 502]
            );
        } catch (Throwable $e) {
            if (is_string($outcome)) {
                return $this->cleanupError();
            }

            return new WP_Error(
                'account_project_deletion_failed',
                __('Unable to confirm project deletion. Your local connection has not been removed. Please try again or contact support.', 'beyondseo'),
                ['status' => 502]
            );
        } finally {
            $this->releaseMutationLock($lockOwner);
        }
    }
}
