<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Api\Content\ContentApiManager;
use RankingCoach\Inc\Core\Api\RC\RCApiManager;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\AutoSetup\Onboarding\AutoSetupOnboarding;
use RankingCoach\Inc\Core\AutoSetup\Onboarding\Collectors\CollectorManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Helpers\RequirementHelper;
use RankingCoach\Inc\Core\Review\ReviewState;
use RankingCoach\Inc\Core\Seo\Services\SeoOptimiserService;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class OnboardingController
{
    use RcApiTrait;
    use RcLoggerTrait;

    public function getRequirements(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $rows = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_SETUP)
                ->select(['*'])
                ->get();

            $rows = is_array($rows) ? array_map(fn($r) => is_object($r) ? (array) $r : $r, $rows) : [];

            foreach ($rows as &$row) {
                $alias = $row['entityAlias'] ?? '';
                $setupRequirement = $row['setupRequirement'] ?? '';
                if (($alias === 'categories' || $setupRequirement === 'businessCategories') && !empty($row['value'])) {
                    $row['value'] = $this->mapCategoryIdsToNames($row['value']);
                }
            }
            unset($row);

            return new WP_REST_Response(['requirements' => ['elements' => $rows]], 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::getRequirements: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function addRequirement(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $body = $request->get_json_params() ?: [];

            $payload = (isset($body['requirement']) && is_array($body['requirement']))
                ? $body['requirement']
                : $body;

            $setupRequirement = sanitize_text_field($payload['setupRequirement'] ?? '');
            $entityAlias      = sanitize_text_field($payload['entityAlias'] ?? '');
            $value            = $payload['value'] ?? '';

            if ($setupRequirement === '') {
                return $this->generateErrorResponse(null, __('Missing setupRequirement.', 'beyondseo'), 400);
            }

            RequirementHelper::updateRequirement($setupRequirement, $value);

            $existing = DatabaseManager::getInstance()->table(DatabaseTablesManager::DATABASE_SETUP)
                ->select(['id'])
                ->where('setupRequirement', $setupRequirement)
                ->first();
            $id = $existing ? (int) ($existing->id ?? $existing['id'] ?? 0) : 0;

            return new WP_REST_Response(['id' => $id], 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::addRequirement: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function updateRequirement(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $requirementId = (int) $request->get_param('requirementId');
            $body = $request->get_json_params() ?: [];

            $existing = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_SETUP)
                ->select(['*'])
                ->where('id', $requirementId)
                ->first();
            $existing = is_object($existing) ? (array) $existing : ($existing ?: []);

            $setupRequirement = sanitize_text_field($body['setupRequirement'] ?? $existing['setupRequirement'] ?? '');

            if (isset($body['value'])) {
                RequirementHelper::updateRequirement($setupRequirement, $body['value']);
            }

            return new WP_REST_Response(['id' => $requirementId], 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::updateRequirement: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function getCategories(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $search = sanitize_text_field($request->get_param('search') ?? '');

            $locale = WordpressHelpers::current_language_code_helper();
            $translatedCategories = \beyondseo_get_translated_categories($locale, 'id');

            if (!empty($search)) {
                $rows = array_filter($translatedCategories, static function ($category) use ($search) {
                    return isset($category['name']) && stripos($category['name'], $search) !== false;
                });
            } else {
                $rows = $translatedCategories;
            }

            $formattedRows = [];
            foreach ($rows as $category) {
                $formattedRows[] = [
                    'id'         => $category['id'] ?? null,
                    'categoryId' => $category['id'] ?? null,
                    'name'       => $category['name'] ?? '',
                    'externalId' => $category['externalId'] ?? '',
                ];
            }

            return new WP_REST_Response(['categories' => ['elements' => $formattedRows]], 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::getCategories: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function getStep(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $stepId = sanitize_text_field($request->get_param('stepId') ?? '');

            $step = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
                ->select(['*'])
                ->where('step', $stepId)
                ->first();

            $step = is_object($step) ? (array) $step : ($step ?: null);

            return new WP_REST_Response(['step' => $step], 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::getStep: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $requirementsData = $this->getFormattedRequirementsData();

            if (!empty($requirementsData['categories']) && is_array($requirementsData['categories'])) {
                $requirementsData['categories'] = $this->mapCategoriesNamesToIds($requirementsData['categories']);
            }

            // Raised as a catchable exception (not the #[NoReturn] error page) so a
            // remote failure surfaces as a structured REST error the frontend can show.
            $this->withRemoteErrorsAsExceptions(
                fn() => RCApiManager::getInstance()->submitOnboarding($requirementsData)
            );

            try {
                $this->withRemoteErrorsAsExceptions(
                    fn() => UserApiManager::getInstance(bearerToken: true)->fetchAndInsertAccountData()
                );
            } catch (Throwable $e) {
                $this->log('Post-onboarding account data sync failed: ' . $e->getMessage(), 'ERROR');
            }

            $now = time();
            update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP, true);
            update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, $now);
            update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC, true);
            update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, $now);
            update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_COMPLETED, true);

            $store = new OptionStore();
            $store->updateFlowState(function ($flowState) {
                $flowState->registered = true;
                $flowState->emailVerified = true;
                $flowState->activated = true;
                $flowState->onboarded = true;
                return $flowState;
            });
            OptionStore::disableFlowGuard();

            try {
                (new ReviewState())->recordOnboardingCompletion();
            } catch (Throwable $e) {
            }

            try {
                $posts = get_posts(['post_type' => ['post', 'page'], 'numberposts' => -1, 'fields' => 'ids']);
                foreach ($posts as $postId) {
                    SeoOptimiserService::getInstance()->analyzeFullOptimiser((int) $postId, [], false);
                }
            } catch (Throwable $e) {
            }

            $setupData = [
                'isPluginOnboarded'      => true,
                'lastPluginUpdate'       => $now,
                'isApplicationOnboarded' => true,
                'lastApplicationUpdate'  => $now,
                'account'                => null,
            ];

            return new WP_REST_Response(['setupData' => $setupData], 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::submit: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function scan(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $posts = get_posts(['post_type' => ['post', 'page'], 'numberposts' => -1, 'fields' => 'ids']);

            $scanCompleted = count($posts) > 0;
            foreach ($posts as $postId) {
                SeoOptimiserService::getInstance()->analyzeFullOptimiser((int) $postId, [], false, false, $analysisCompleted);
                $scanCompleted = $analysisCompleted && $scanCompleted;
            }

            SeoOptimiserService::getInstance()->calculateAndSaveAverageScore(
                10000,
                $scanCompleted ? count($posts) : 0
            );

            $review = ['completed' => false, 'milestone' => false];
            try {
                $review = apply_filters('rankingcoach_review_analysis_result', $review, 'site', 0);
            } catch (Throwable $e) {
            }
            return new WP_REST_Response(['scanned' => count($posts), 'review' => $review], 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::scan: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    /**
     * Run a remote RC API call so that HTTP error responses are raised as catchable
     * exceptions instead of rendering a fatal error page and exiting the request.
     *
     * By default the HTTP layer hands 4xx/5xx responses to the global exception handler,
     * which — outside of AJAX/Postman contexts — renders an error page and terminates the
     * request (ExceptionHandler::error() is #[NoReturn]). That makes it impossible for a
     * controller to recover. Enabling the `rankingcoach_http_api_response_throw_exception`
     * filter for the duration of the call flips that behaviour to `throw`, so onboarding
     * can catch the failure and degrade gracefully instead of hard-failing.
     *
     * @param callable $fn
     * @return mixed
     */
    private function withRemoteErrorsAsExceptions(callable $fn): mixed
    {
        $throwFilter = static fn() => true;
        add_filter('rankingcoach_http_api_response_throw_exception', $throwFilter);
        try {
            return $fn();
        } finally {
            remove_filter('rankingcoach_http_api_response_throw_exception', $throwFilter);
        }
    }

    private function getFormattedRequirementsData(): array
    {
        // The remote onboarding DTO (PostWPOnboardingRequestDto) is strict: every scalar
        // property it declares must be PRESENT in the request body, otherwise it throws
        // "Property X is missing" and the whole onboarding call (generateSteps / submit)
        // fails — which blocks onboarding entirely, not just auto onboarding.
        //
        // The setup rows are seeded with a NULL value (see the CreateAllTables migration),
        // and NULL values used to be dropped from the payload below. So at the start of
        // onboarding scalar properties such as "name" and "description" were missing and
        // the request was rejected. Seed every known scalar requirement with an empty
        // string up front so it is ALWAYS sent (blank rather than missing); real values
        // and the complex array/object requirements are layered on top below.
        $requirementsData = [
            'emailaddress'        => '',
            'websiteurl'          => '',
            'name'                => '',
            'description'         => '',
            'address'             => '',
            'servicearea'         => '',
            'specificdescription' => '',
            // Complex (array/object) requirements must also be present for the strict
            // DTO. Default them to empty arrays; real values overwrite them below.
            'keywords'            => [],
            'categories'          => [],
            'geoaddress'          => [],
        ];

        $requirements = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP)
            ->select(['*'])
            ->get();

        if (is_array($requirements)) {
            foreach ($requirements as $row) {
                $row = is_object($row) ? (array) $row : $row;
                if (empty($row['entityAlias'])) {
                    continue;
                }
                $alias = $row['entityAlias'];
                $setupRequirement = $row['setupRequirement'] ?? '';
                $value = $row['value'] ?? null;

                $isComplex = in_array($alias, ['keywords', 'categories', 'geoaddress'], true)
                    || in_array($setupRequirement, ['businessKeywords', 'businessCategories', 'businessGeoAddress'], true);

                if ($isComplex) {
                    // Complex (array/object) requirements are only sent when they actually
                    // carry data — an empty scalar would be the wrong shape for them.
                    if ($value === null || $value === '') {
                        continue;
                    }
                    try {
                        $requirementsData[$alias] = json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?? $value;
                    } catch (Throwable $e) {
                        $requirementsData[$alias] = $value;
                    }
                } else {
                    // Scalar: never send NULL (the DTO rejects a missing/null prop) — send
                    // the stored value or an empty string.
                    $requirementsData[$alias] = $value ?? '';
                }
            }
        }

        if (!empty($requirementsData['keywords']) && is_array($requirementsData['keywords'])) {
            $requirementsData['keywords'] = $this->normalizeKeywordsForPayload($requirementsData['keywords']);
        }

        if (empty($requirementsData['websiteurl'])) {
            $siteUrl = sanitize_url(get_option('siteurl'));
            if (wp_get_environment_type() !== 'production' && str_contains($siteUrl, 'local') !== false) {
                $siteUrl = RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST ?? $siteUrl;
            }
            $requirementsData['websiteurl'] = $siteUrl;
        }

        return $requirementsData;
    }

    /**
     * Dedupe and cap a keyword name list before it leaves the plugin.
     *
     * Keywords are identified case- and whitespace-insensitively, mirroring the alias
     * the remote side derives from the name, and the first occurrence wins so the
     * user's ordering is preserved.
     *
     * @param array $keywords Keyword names (tolerates {name: …} shapes)
     * @return string[]
     */
    private function normalizeKeywordsForPayload(array $keywords): array
    {
        $maxAllowed = (int) get_option(BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS, 0);
        $unique = [];

        foreach ($keywords as $keyword) {
            if (is_array($keyword) || is_object($keyword)) {
                $keyword = ((array) $keyword)['name'] ?? '';
            }

            $name = trim((string) preg_replace('/\s+/u', ' ', (string) $keyword));
            if ($name === '') {
                continue;
            }

            $identity = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            if (!isset($unique[$identity])) {
                $unique[$identity] = $name;
            }
        }

        $normalized = array_values($unique);

        if ($maxAllowed > 0 && count($normalized) > $maxAllowed) {
            $this->log(sprintf(
                'Onboarding payload holds %d keywords but the subscription allows %d; sending the first %d.',
                count($normalized),
                $maxAllowed,
                $maxAllowed
            ), 'WARNING');
            $normalized = array_slice($normalized, 0, $maxAllowed);
        }

        return $normalized;
    }

    public function generateSteps(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $collectorManager = new CollectorManager();
            $collectorManager->run();

            $body = $request->get_json_params() ?: [];

            $requirementsData = $this->getFormattedRequirementsData();

            $params = array_merge($requirementsData, $body);

            // Remote step generation returns AI-tailored steps, but it is a best-effort
            // enhancement layered on top of the locally seeded default steps. If it fails
            // for any reason (e.g. the remote DDD service throws and returns a non-JSON
            // "Unhandled exception" body), onboarding must still proceed with the local
            // default steps rather than hard-failing. Errors are raised as catchable
            // exceptions (see withRemoteErrorsAsExceptions) and swallowed here.
            try {
                $result = $this->withRemoteErrorsAsExceptions(
                    fn() => RCApiManager::getInstance()->generateSteps($params)
                );

                $responseArray = isset($result['content']) ? json_decode(json_encode($result['content']), true) : [];

                if (!empty($responseArray['steps'])) {
                    foreach ($responseArray['steps'] as $step) {
                        DatabaseManager::getInstance()->insertOrUpdate(
                            DatabaseTablesManager::DATABASE_SETUP_STEPS,
                            [
                                'step'     => sanitize_text_field($step['step'] ?? ''),
                                'priority' => (int) ($step['priority'] ?? 0),
                                'active'   => (int) ($step['active'] ?? 1),
                            ],
                            [
                                'priority' => (int) ($step['priority'] ?? 0),
                                'active'   => (int) ($step['active'] ?? 1),
                            ]
                        );
                    }
                }
            } catch (Throwable $e) {
                $this->log('Remote step generation failed; falling back to locally seeded default steps: ' . $e->getMessage(), 'WARNING');
            }

            $formattedSteps = $this->getFormattedSteps();

            $response = [
                'steps'                     => [
                    'elements' => $formattedSteps,
                ],
                'prefillAddressRequirement' => false,
            ];

            return new WP_REST_Response($response, 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::generateSteps: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    /**
     * Fetch, format, and translate steps natively
     *
     * @return array
     */
    private function getFormattedSteps(): array
    {
        $requirementsRows = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP)
            ->select(['setupRequirement', 'value'])
            ->get();

        $satisfiedRequirements = [];
        if (is_array($requirementsRows)) {
            foreach ($requirementsRows as $reqRow) {
                $reqRow = (array) $reqRow;
                $reqName = $reqRow['setupRequirement'] ?? '';
                $reqValue = $reqRow['value'] ?? null;
                if ($reqValue !== null && $reqValue !== '') {
                    $decoded = json_decode($reqValue, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (!empty($decoded)) {
                            $satisfiedRequirements[$reqName] = $decoded;
                        }
                    } else {
                        $satisfiedRequirements[$reqName] = $reqValue;
                    }
                }
            }
        }

        $questionsRows = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS)
            ->select(['*'])
            ->get();

        $questionsByStep = [];
        if (is_array($questionsRows)) {
            foreach ($questionsRows as $qRow) {
                $qRow = (array) $qRow;
                $sId = (int) ($qRow['stepId'] ?? 0);
                if (!isset($questionsByStep[$sId])) {
                    $questionsByStep[$sId] = [];
                }
                $questionsByStep[$sId][] = $qRow;
            }
        }

        $completionsRows = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS)
            ->select(['*'])
            ->get();

        $completionsByStep = [];
        if (is_array($completionsRows)) {
            foreach ($completionsRows as $cRow) {
                $cRow = (array) $cRow;
                $sId = (int) ($cRow['stepId'] ?? 0);
                if (!isset($completionsByStep[$sId])) {
                    $completionsByStep[$sId] = [];
                }
                $completionsByStep[$sId][] = [
                    'id' => (int) ($cRow['id'] ?? 0),
                    'stepId' => $sId,
                    'collectorId' => isset($cRow['collectorId']) ? (int) $cRow['collectorId'] : null,
                    'questionId' => isset($cRow['questionId']) ? (int) $cRow['questionId'] : null,
                    'answer' => $cRow['answer'] ?? '',
                    'data' => $cRow['data'] ?? null,
                    'timeOfCompletion' => isset($cRow['timeOfCompletion']) ? (int) $cRow['timeOfCompletion'] : null,
                    'isCompleted' => (bool) ($cRow['isCompleted'] ?? false),
                ];
            }
        }

        $questionTranslations = [
            1  => __("Let's get started.", 'beyondseo'),
            2  => __('First, could you tell me what your website or project is about?', 'beyondseo'),
            3  => __('Awesome!', 'beyondseo'),
            4  => __('Do you already have a name for your website, project, or business?', 'beyondseo'),
            5  => __('Wonderful!', 'beyondseo'),
            6  => __('Could you describe in more detail what you plan to do with your website? For example, will you offer products or services, share blog articles, or something else?', 'beyondseo'),
            7  => __('Just tasty! Thanks for sharing!', 'beyondseo'),
            8  => __('Is your project or business tied to a specific location? Do you serve customers locally, or operate in multiple areas?', 'beyondseo'),
            9  => __('I see.', 'beyondseo'),
            10 => __("Where do you primarily want to focus your reach? Is there a particular city or region you'd like to target, or do you want to go nationwide?", 'beyondseo'),
            11 => __('Thanks for providing that!', 'beyondseo'),
            12 => __("Lastly, is there anything else you'd like to highlight about your project or business, something that makes it unique or special?", 'beyondseo')
        ];

        $dbSteps = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
            ->select(['*'])
            ->orderBy('priority', 'ASC')
            ->get();

        $formattedSteps = [];
        if (is_array($dbSteps)) {
            foreach ($dbSteps as $stepRow) {
                $stepRow = (array) $stepRow;
                $stepIdInt = (int) $stepRow['id'];

                $stepRequirementsStr = $stepRow['requirements'] ?? '';
                $stepRequirements = array_filter(array_map('trim', explode(',', $stepRequirementsStr)));

                $allSatisfied = true;
                if (empty($stepRequirements)) {
                    $allSatisfied = false;
                } else {
                    foreach ($stepRequirements as $req) {
                        if (!isset($satisfiedRequirements[$req])) {
                            $allSatisfied = false;
                            break;
                        }
                    }
                }

                $isStepCompleted = ((int) ($stepRow['completed'] ?? 0) === 1) || $allSatisfied;

                if (!$stepRow['completed'] && $isStepCompleted) {
                    DatabaseManager::getInstance()->update(
                        DatabaseTablesManager::DATABASE_SETUP_STEPS,
                        ['completed' => 1],
                        ['id' => $stepIdInt]
                    );
                }

                $stepQuestions = [];
                if (isset($questionsByStep[$stepIdInt])) {
                    foreach ($questionsByStep[$stepIdInt] as $q) {
                        $qId = (int) ($q['id'] ?? 0);
                        $translatedQuestion = isset($questionTranslations[$qId]) ? $questionTranslations[$qId] : ($q['question'] ?? '');
                        $stepQuestions[] = [
                            'id' => $qId,
                            'parentId' => isset($q['parentId']) ? (int) $q['parentId'] : null,
                            'stepId' => (int) ($q['stepId'] ?? 0),
                            'question' => $translatedQuestion,
                            'sequence' => (int) ($q['sequence'] ?? 1),
                            'aiContext' => $q['aiContext'] ?? null,
                            'isAiGenerated' => (bool) ($q['isAiGenerated'] ?? false),
                        ];
                    }
                }

                usort($stepQuestions, function($a, $b) {
                    return $a['sequence'] <=> $b['sequence'];
                });

                $currentQuestionObj = null;
                if (!empty($stepQuestions)) {
                    $currentQuestionObj = $stepQuestions[count($stepQuestions) - 1];
                }

                $formattedSteps[] = [
                    'id' => $stepIdInt,
                    'step' => $stepRow['step'] ?? '',
                    'requirements' => $stepRow['requirements'] ?? '',
                    'priority' => (int) ($stepRow['priority'] ?? 0),
                    'isFinalStep' => (bool) ($stepRow['isFinalStep'] ?? false),
                    'active' => (bool) ($stepRow['active'] ?? true),
                    'completed' => $isStepCompleted,
                    'userSaveCount' => (int) ($stepRow['userSaveCount'] ?? 0),
                    'questions' => [
                        'elements' => $stepQuestions,
                    ],
                    'currentQuestion' => $currentQuestionObj,
                    'completions' => [
                        'elements' => isset($completionsByStep[$stepIdInt]) ? $completionsByStep[$stepIdInt] : [],
                    ],
                ];
            }
        }

        return $formattedSteps;
    }

    private function hasExistingSetupData(): bool
    {
        $requirements = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP)
            ->select(['setupRequirement', 'value'])
            ->get();

        if (!is_array($requirements)) {
            return false;
        }

        foreach ($requirements as $row) {
            $row = is_object($row) ? (array) $row : $row;
            $reqName = $row['setupRequirement'] ?? '';
            $value = $row['value'] ?? null;

            if ($value === null || $value === '' || $value === '[]' || $value === '{}') {
                continue;
            }

            if (in_array($reqName, ['businessKeywords', 'businessCategories'], true)) {
                $decoded = json_decode((string)$value, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function extractAuto(WP_REST_Request $request): WP_REST_Response
    {
        try {
            if ($this->hasExistingSetupData()) {
                return new WP_REST_Response(['alreadyConfigured' => true], 200);
            }

            $countryCode = get_option(BaseConstants::OPTION_RANKINGCOACH_REGISTER_COUNTRY_CODE);
            if (empty($countryCode)) {
                $defaultCountry = WordpressHelpers::getDefaultCountry();
                $countryCode = key($defaultCountry);
            }

            $onboarding = new AutoSetupOnboarding();
            $content = $onboarding->getOnboardingContent(true);

            $params = [
                'countryCode' => $countryCode,
                'content'     => $content,
            ];

            $result = $this->withRemoteErrorsAsExceptions(
                fn() => RCApiManager::getInstance()->extractAuto($params)
            );

            $responseArray = isset($result['content']) ? json_decode(json_encode($result['content']), true) : [];

            $extractedValues = $responseArray['extractedValues'] ?? null;
            if (is_array($extractedValues)) {
                $prefilledAddress = (bool)($responseArray['prefillCountryRelevantAddress'] ?? false);

                $requirements = [];

                if (!empty($extractedValues['businessDescription'])) {
                    $requirements['businessDescription'] = (string)$extractedValues['businessDescription'];
                }
                if (!empty($extractedValues['businessName'])) {
                    $requirements['businessName'] = (string)$extractedValues['businessName'];
                }
                if (!empty($extractedValues['businessKeywords']) && is_array($extractedValues['businessKeywords'])) {
                    $requirements['businessKeywords'] = $extractedValues['businessKeywords'];
                }
                if (!empty($extractedValues['businessCategories']) && is_array($extractedValues['businessCategories'])) {
                    $requirements['businessCategories'] = $extractedValues['businessCategories'];
                }
                if (!empty($extractedValues['businessAddress'])) {
                    $requirements['businessAddress'] = (string)$extractedValues['businessAddress'];
                    if ($prefilledAddress) {
                        update_option(BaseConstants::OPTION_PREFILLED_ADDRESS, $requirements['businessAddress']);
                    }
                }
                if (!empty($extractedValues['businessGeoAddress'])) {
                    $businessGeoAddress = (string)$extractedValues['businessGeoAddress'];
                    try {
                        $decodedAddress = json_decode($businessGeoAddress, true, 512, JSON_THROW_ON_ERROR);
                        if (is_array($decodedAddress)) {
                            $decodedAddress['prefilledAddress'] = $prefilledAddress;
                            $businessGeoAddress = json_encode($decodedAddress, JSON_THROW_ON_ERROR);
                        }
                    } catch (Throwable $e) {
                        $businessGeoAddress = '';
                    }
                    if (!empty($businessGeoAddress)) {
                        $requirements['businessGeoAddress'] = $businessGeoAddress;
                    }
                }
                if (isset($extractedValues['businessServiceArea']) && $extractedValues['businessServiceArea'] !== '' && $extractedValues['businessServiceArea'] !== null) {
                    $requirements['businessServiceArea'] = $extractedValues['businessServiceArea'];
                }

                if (!empty($requirements)) {
                    RequirementHelper::updateRequirements($requirements);
                }
            }

            return new WP_REST_Response($result, 200);
        } catch (Throwable $e) {
            $this->log('Auto onboarding extraction failed; falling back to normal onboarding: ' . $e->getMessage(), 'WARNING');
            return new WP_REST_Response(['autoOnboardingFailed' => true], 200);
        }
    }

    public function submitStep(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $body = $request->get_json_params() ?: [];
            $completionData = $body['completion'] ?? $body;

            $result = RCApiManager::getInstance()->submitStepAnswer($completionData);

            $stepId = null;
            if (!empty($completionData['stepId'])) {
                $stepId = sanitize_text_field((string)$completionData['stepId']);

                $stepRow = DatabaseManager::getInstance()
                    ->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
                    ->select(['*'])
                    ->where('id', (int) $stepId)
                    ->first();

                if (!$stepRow) {
                    $stepRow = DatabaseManager::getInstance()
                        ->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
                        ->select(['*'])
                        ->where('step', $stepId)
                        ->first();
                }

                if ($stepRow) {
                    $stepRow = (array) $stepRow;
                    $actualStepId = (int) $stepRow['id'];

                    $existingCompletion = DatabaseManager::getInstance()
                        ->table(DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS)
                        ->select(['id'])
                        ->where('stepId', $actualStepId)
                        ->where('questionId', isset($completionData['questionId']) ? (int) $completionData['questionId'] : null)
                        ->first();

                    $sanitizedAnswer = sanitize_text_field((string)($completionData['answer'] ?? ''));

                    if ($existingCompletion) {
                        DatabaseManager::getInstance()->update(
                            DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS,
                            [
                                'answer' => $sanitizedAnswer,
                                'isCompleted' => 1,
                                'timeOfCompletion' => time(),
                            ],
                            ['id' => (int) $existingCompletion->id]
                        );
                    } else {
                        DatabaseManager::getInstance()->insert(
                            DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS,
                            [
                                'stepId' => $actualStepId,
                                'questionId' => isset($completionData['questionId']) ? (int) $completionData['questionId'] : null,
                                'answer' => $sanitizedAnswer,
                                'isCompleted' => 1,
                                'timeOfCompletion' => time(),
                            ]
                        );
                    }
                }
            }

            $currentStep = null;
            $nextStep = null;
            $allStepsCompleted = true;

            $formattedSteps = $this->getFormattedSteps();
            foreach ($formattedSteps as $fStep) {
                if (!$fStep['completed']) {
                    $allStepsCompleted = false;
                }
            }

            if ($stepId !== null) {
                foreach ($formattedSteps as $fStep) {
                    if ($fStep['id'] === (int) $stepId || $fStep['step'] === $stepId) {
                        $currentStep = $fStep;
                        break;
                    }
                }

                if ($currentStep !== null) {
                    foreach ($formattedSteps as $fStep) {
                        if ($fStep['completed']) {
                            continue;
                        }
                        if ($fStep['priority'] > $currentStep['priority']) {
                            $nextStep = $fStep;
                            break;
                        }
                    }
                }
            }

            $response = [
                'completion' => $completionData,
                'step' => $currentStep,
                'nextStep' => $nextStep,
                'allStepsCompleted' => $allStepsCompleted,
                /* translators: %s: JSON-encoded boolean API response, either true or false. */
                'evaluationSucceeded' => sprintf(__('The API call (JSON response) is valid: %s', 'beyondseo'), json_encode(true)),
                /* translators: %s: JSON-encoded boolean API response, either true or false. */
                'failedAPICallFromResult' => sprintf(__('The API call (text/html or error property on JSON): %s', 'beyondseo'), json_encode(false)),
            ];

            return new WP_REST_Response($response, 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::submitStep: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function locationSuggestions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $body = $request->get_json_params() ?: [];

            $params = [
                'address' => sanitize_text_field((string) ($body['address'] ?? '')),
                'country' => sanitize_text_field((string) ($body['country'] ?? '')),
                'city'    => sanitize_text_field((string) ($body['city'] ?? '')),
                'zip'     => sanitize_text_field((string) ($body['zip'] ?? '')),
            ];

            $result = RCApiManager::getInstance()->getLocationSuggestions($params);

            return new WP_REST_Response($result, 200);
        } catch (Throwable $e) {
            $this->log('Error in OnboardingController::locationSuggestions: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    /**
     * Map category IDs stored in the database to localized names, while preserving string names.
     *
     * @param string $value JSON encoded array of category IDs or names
     * @return string JSON encoded array of category names
     */
    private function mapCategoryIdsToNames(string $value): string
    {
        try {
            $items = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($items) || empty($items)) {
                return $value;
            }

            $locale = WordpressHelpers::current_language_code_helper();
            $translatedCategories = \beyondseo_get_translated_categories($locale, 'id');

            $names = [];
            foreach ($items as $item) {
                if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                    $key = (int) $item;
                    if (!empty($translatedCategories) && isset($translatedCategories[$key]['name'])) {
                        $names[] = $translatedCategories[$key]['name'];
                    } else {
                        $names[] = (string) $item;
                    }
                } elseif (is_string($item) && trim($item) !== '') {
                    $names[] = trim($item);
                } elseif (is_array($item) && !empty($item['name']) && is_string($item['name'])) {
                    $names[] = trim($item['name']);
                } elseif (is_object($item) && !empty($item->name) && is_string($item->name)) {
                    $names[] = trim($item->name);
                }
            }

            return !empty($names) ? json_encode(array_values($names), JSON_THROW_ON_ERROR) : $value;
        } catch (Throwable $e) {
            return $value;
        }
    }

    /**
     * Map category names to their numeric category IDs.
     *
     * @param array $names Array of category names (or already numeric IDs)
     * @return array Array of category IDs. Names that couldn't be resolved are dropped.
     */
    private function mapCategoriesNamesToIds(array $names): array
    {
        try {
            $locale = WordpressHelpers::current_language_code_helper();
            $translatedCategories = \beyondseo_get_translated_categories($locale, 'name');

            $ids = [];
            foreach ($names as $item) {
                if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                    $ids[] = (int) $item;
                    continue;
                }

                if (!is_string($item) || trim($item) === '') {
                    continue;
                }

                $key = ucfirst(strtolower(trim($item)));
                if (!empty($translatedCategories) && isset($translatedCategories[$key]['id'])) {
                    $ids[] = (int) $translatedCategories[$key]['id'];
                }
            }

            return array_values(array_unique($ids));
        } catch (Throwable $e) {
            $this->log('Error mapping category names to IDs: ' . $e->getMessage(), 'ERROR');
            return $names;
        }
    }
}
