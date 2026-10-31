<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Api\Register;

use Exception;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Exceptions\HttpApiException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RegisterApiManager
 *
 * Handles secure public registration flow:
 *  - Challenge creation (anti-abuse OTT with short TTL)
 *  - Request email verification (no authenticated user yet)
 *
 * All endpoints are public-facing and protected via signed headers over payload.
 */
class RegisterApiManager extends HttpApiClient
{
    use RcLoggerTrait;

    /** @var self|null */
    protected static ?self $instance = null;

    /**
     * Singleton accessor (no bearer token required for public registration flow).
     *
     * @param array<string,string> $defaultHeaders
     * @return self
     */
    public static function getInstance(
        array $defaultHeaders = []
    ): self {
        if (!self::$instance) {
            self::$instance = new self($defaultHeaders);
        }
        return self::$instance;
    }

    /**
     * Poll verification status using pollToken.
     *
     * Expected API:
     *  POST /account/verificationStatus (register)
     *
     * @param string $pollToken
     * @param string $status
     * @return array<string,mixed>|false
     */
    public function pollEmailVerificationStatus(string $pollToken, string $status): array|false
    {
        $operation = 'registration_poll_verification';
        try {
            $payload = CoreHelper::generateCommonSecurityPayload([
                'pluginIdentifier' => defined('RANKINGCOACH_BRAND_SLUG') ? RANKINGCOACH_BRAND_SLUG : 'rankingcoach',
                'pollToken'        => $pollToken,
                'accountStatus'    => $status,
            ]);

            $this->setUrl('account/verificationStatus', 'register');

            $response = $this->post($payload);
            $content  = $response['content'] ?? null;
            $content = $content ? json_decode(json_encode($content), true) : null;

            if (!is_array($content) || empty($content)) {
                $this->log_json([
                    'operation_type'   => $operation,
                    'operation_status' => 'invalid_response',
                    'poll_token_mask'  => $this->maskToken($pollToken),
                ], 'registrationPoll');
                return false;
            }

            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => 'success',
                'poll_token_mask'  => $this->maskToken($pollToken),
                'metadata'         => [
                    'status'             => $content['status'] ?? null,
                ],
            ], 'registrationPoll');

            return $content;
        } catch (Exception $e) {
            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => 'error',
                'poll_token_mask'  => $this->maskToken($pollToken),
                'error_details'    => $this->formatExceptionContext($e),
            ], 'registrationPoll');

            if ($e instanceof HttpApiException) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * Initial account registration.
     *
     * Expected API:
     *  POST /account/register (register)
     *
     * @return array<string,mixed>|false
     */
    public function register(
        string $email,
        string $country,
        ?string $type = null
    ): array|false {
        $operation = 'initiate_registration';
        try {
            $challenge = $this->requestChallenge($email);
            $currentUser = wp_get_current_user();

            $firstName = $currentUser && $currentUser->exists()
                ? CoreHelper::safe_user_string($currentUser->first_name)
                    ?: CoreHelper::safe_user_string($currentUser->user_login)
                : 'unknown';
            $lastName = $currentUser && $currentUser->exists()
                ? CoreHelper::safe_user_string($currentUser->last_name)
                : '';
            if (empty($lastName)) {
                $lastName = $firstName;
            }

            $payload = $this->generateCommonSecurityPayload([
                'pluginIdentifier'   => defined('RANKINGCOACH_BRAND_SLUG') ? RANKINGCOACH_BRAND_SLUG : 'beyondseo',
                // User first & last name
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email'              => $email,
                'countryCode'        => strtoupper($country),
                'type'               => $type,
                'challengeHash'      => $challenge['challengeHash'],
                'challengeTimestamp' => $challenge['challengeTimestamp'],
                'installationId'     => get_option(BaseConstants::OPTION_INSTALLATION_ID, ''),
                'sandbox'            => wp_get_environment_type() !== 'production',
            ]);

            $this->setUrl('account/register', 'register');

            // Store the chose country code for later use
            update_option(BaseConstants::OPTION_RANKINGCOACH_REGISTER_COUNTRY_CODE, strtolower($country));
            // Save the action in options for future reference
            update_option(BaseConstants::OPTION_LAST_REGISTRATION_ATTEMPT, time());
            update_option(BaseConstants::OPTION_REGISTRATION_EMAIL_ADDRESS, $email);
            update_option(BaseConstants::OPTION_REGISTRATION_COUNTRY_SHORTCODE, $country);

            $response = $this->post($payload);
            $content  = $response['content'] ?? null;
            $content = $content ? json_decode(json_encode($content), true) : null;

            if (!is_array($content) || empty($content)) {
                $this->log_json([
                    'operation_type'   => $operation,
                    'operation_status' => 'invalid_response',
                ], 'registration');
                return false;
            }

            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => $content['status'] ?? 'unknown',
                'metadata'         => [
                    'has_user_id'    => isset($content['userId']),
                    'has_project_id' => isset($content['projectId']),
                ],
            ], 'registration');

            return $content;
        } catch (Exception $e) {
            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => 'error',
                'error_details'    => $this->formatExceptionContext($e),
            ], 'registration');

            if ($e instanceof HttpApiException) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * Finalize account registration.
     *
     * Expected API:
     *  POST /account/finalizeRegister
     *
     * @return array<string,mixed>|false
     */
    public function finalizeRegister(
        string $email,
        string $country,
        ?string $type = null,
        ?string $pollToken = null
    ): array|false {
        $operation = 'registration_complete';
        try {
            $challenge = $this->requestChallenge($email);

            $payload = CoreHelper::generateCommonSecurityPayload([
                'pluginIdentifier'   => defined('RANKINGCOACH_BRAND_SLUG') ? RANKINGCOACH_BRAND_SLUG : 'rankingcoach',
                'email'              => $email,
                'countryCode'        => strtoupper($country),
                'type'               => $type,
                'challengeHash'      => $challenge['challengeHash'],
                'challengeTimestamp' => $challenge['challengeTimestamp'],
                'installationId'     => get_option(BaseConstants::OPTION_INSTALLATION_ID, ''),
                'pollToken'          => $pollToken,
            ]);

            $this->setUrl('account/finalizeRegister', 'register');

            $response = $this->post($payload);
            $content  = $response['content'] ?? null;
            $content = $content ? json_decode(json_encode($content), true) : null;

            if (!is_array($content) || empty($content)) {
                $this->log_json([
                    'operation_type'   => $operation,
                    'operation_status' => 'invalid_response',
                ], 'finalizeRegistration');
                return false;
            }

            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => $content['status'] ?? 'unknown',
                'metadata'         => [
                    'has_user_id'    => isset($content['userId']),
                    'has_project_id' => isset($content['projectId']),
                ],
            ], 'finalizeRegistration');

            return $content;
        } catch (Exception $e) {
            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => 'error',
                'error_details'    => $this->formatExceptionContext($e),
            ], 'finalizeRegistration');

            if ($e instanceof HttpApiException) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * Internal: create a short-lived challenge via public API.
     *
     * Expected API:
     *  POST /account/challenge (register)
     *
     * Response keys (expected):
     *  - challengeHash (string)
     *  - challengeTimestamp (int)
     *  - ttl (int)
     *
     * @return array{challengeHash:string,challengeTimestamp:int,ttl:int}
     * @throws HttpApiException
     */
    public function requestChallenge($email): array
    {
        $operation = 'registration_challenge';

        try {
            $payload = CoreHelper::generateCommonSecurityPayload([
                'pluginIdentifier' => defined('RANKINGCOACH_BRAND_SLUG') ? RANKINGCOACH_BRAND_SLUG : 'rankingcoach',
                'email'            => $email,
                'installationId'   => (string) get_option(BaseConstants::OPTION_INSTALLATION_ID, ''),
            ]);

            $this->setUrl('account/challenge', 'register');

            $response = $this->post($payload);
            $content  = $response['content'] ?? null;
            if($content) {
                $content = json_decode(json_encode($content), true);
            }

            if (!is_array($content) || empty($content)) {
                $this->log_json([
                    'operation_type'   => $operation,
                    'operation_status' => 'invalid_response',
                    ], 'registration');
                throw new HttpApiException('Invalid challenge response from API');
            }

            $timestamp = (int)($content['challengeTimestamp'] ?? 0);
            $hash      = (string)($content['challengeHash'] ?? '');
            $ttl       = (int)($content['ttl'] ?? 60);

            if ($hash === '' || $timestamp === 0) {
                $this->log_json([
                    'operation_type'   => $operation,
                    'operation_status' => 'invalid_response',
                    'metadata'         => ['missing_fields' => ['challengeHash','challengeTimestamp']],
                ], 'registration');
                throw new HttpApiException('Missing fields in challenge response');
            }

            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => 'success',
                'metadata'         => ['ttl' => $ttl],
            ], 'registration');

            return [
                'challengeHash'      => $hash,
                'challengeTimestamp' => $timestamp,
                'ttl'                => $ttl,
            ];
        } catch (Exception $e) {
            $this->log_json([
                'operation_type'   => $operation,
                'operation_status' => 'error',
                'error_details'    => $this->formatExceptionContext($e),
            ], 'registration');

            if ($e instanceof HttpApiException) {
                throw $e;
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new HttpApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Mask email for logs (e***@d***).
     */
    protected function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email !== '' ? substr($email, 0, 1) . '***' : '';
        }
        [$local, $domain] = explode('@', $email, 2);
        $localMasked  = ($local !== '') ? substr($local, 0, 1) . '***' : '';
        $domainMasked = ($domain !== '') ? substr($domain, 0, 1) . '***' : '';
        return $localMasked . '@' . $domainMasked;
    }
}
