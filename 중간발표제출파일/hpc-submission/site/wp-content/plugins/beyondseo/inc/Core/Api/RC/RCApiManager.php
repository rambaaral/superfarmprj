<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Api\RC;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use RankingCoach\Inc\Core\AutoSetup\Onboarding\AutoSetupOnboarding;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Helpers\RequirementHelper;

class RCApiManager extends HttpApiClient
{
    protected static ?RCApiManager $instance = null;

    public static function getInstance(): RCApiManager
    {
        if (self::$instance === null) {
            $tokensManager = TokensManager::instance();
            $accessToken = $tokensManager->getStoredAccessToken();
            self::$instance = new self([], $accessToken);
        }
        return self::$instance;
    }

    public function __construct(array $defaultHeaders = [], ?string $accessToken = null)
    {
        parent::__construct($defaultHeaders, $accessToken);
        $this->loadConfiguration();
    }

    public function submitOnboarding(array $requirementsData): array
    {
        $this->setUrl('onboarding');
        $payload = CoreHelper::generateCommonSecurityPayload($requirementsData);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        return $this->post($payload, 240);
    }

    public function getLocationSuggestions(array $addressParams): array
    {
        $this->setUrl('location/suggestions');
        $payload = CoreHelper::generateCommonSecurityPayload($addressParams);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        return $this->post($payload);
    }

    public function generateSteps(array $params = []): array
    {
        $this->setUrl('onboarding');
        $payload = CoreHelper::generateCommonSecurityPayload($params);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        return $this->post($payload);
    }

    public function extractAuto(array $params = []): array
    {

        $this->setUrl('onboarding/extractFromText');
        $payload = CoreHelper::generateCommonSecurityPayload($params);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        return $this->post($payload);
    }

    public function submitStepAnswer(array $completionData): array
    {
        $this->setUrl('onboarding/answers/aiValidation');

        if (isset($completionData['objectType'])) {
            unset($completionData['objectType']);
        }

        $stepId = $completionData['stepId'] ?? null;
        $stepRow = null;
        if ($stepId !== null) {
            if (is_numeric($stepId)) {
                $stepRow = DatabaseManager::getInstance()
                    ->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
                    ->select(['*'])
                    ->where('id', (int) $stepId)
                    ->first();
            }
            if (!$stepRow) {
                $stepRow = DatabaseManager::getInstance()
                    ->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
                    ->select(['*'])
                    ->where('step', $stepId)
                    ->first();
            }
        }
        $stepRow = $stepRow ? (array) $stepRow : null;

        $questionId = $completionData['questionId'] ?? null;
        $questionRow = null;
        if ($questionId !== null) {
            $questionRow = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS)
                ->select(['*'])
                ->where('id', (int) $questionId)
                ->first();
        }
        $questionRow = $questionRow ? (array) $questionRow : null;

        $completionRow = null;
        if ($stepId !== null) {
            $completionQuery = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS)
                ->select(['data'])
                ->where('stepId', (int) ($stepRow['id'] ?? $stepId));
            
            if ($questionId !== null) {
                $completionQuery->where('questionId', (int) $questionId);
            } else {
                $completionQuery->whereNull('questionId');
            }
            
            $completionRow = $completionQuery->first();
        }
        $completionRow = $completionRow ? (array) $completionRow : null;
        $dbData = !empty($completionRow['data']) ? json_decode($completionRow['data'], true) : [];

        $currentStepContext = [
            'question'    => '',
            'answer'      => $completionData['answer'] ?? '',
            'requirement' => $stepRow ? ($stepRow['requirements'] ?? '') : '',
            'number'      => $stepRow ? ($stepRow['priority'] ?? '') : '',
        ];

        if ($questionRow) {
            $rawQuestionText = $questionRow['question'] ?? '';
            $qId = (int) ($questionRow['id'] ?? 0);
            $currentStepContext['question'] = $this->getTranslatedQuestion($qId, $rawQuestionText);
        }

        $previousSteps = [];
        if ($stepRow) {
            $currentStepPriority = (int) ($stepRow['priority'] ?? 0);
            $previousStepsRows = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
                ->select(['*'])
                ->where('priority', $currentStepPriority, '<')
                ->orderBy('priority', 'ASC')
                ->get() ?: [];

            foreach ($previousStepsRows as $pStepRow) {
                $pStepRow = (array) $pStepRow;
                $pStepId = (int) $pStepRow['id'];

                $pCompletions = DatabaseManager::getInstance()
                    ->table(DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS)
                    ->select(['*'])
                    ->where('stepId', $pStepId)
                    ->whereNull('collectorId')
                    ->get() ?: [];

                $completionsContext = [];
                foreach ($pCompletions as $pComp) {
                    $pComp = (array) $pComp;
                    $pQuestionId = isset($pComp['questionId']) ? (int) $pComp['questionId'] : null;
                    if ($pQuestionId) {
                        $pQuestionRow = DatabaseManager::getInstance()
                            ->table(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS)
                            ->select(['question'])
                            ->where('id', $pQuestionId)
                            ->first();
                        $pQuestionRow = $pQuestionRow ? (array) $pQuestionRow : null;

                        $rawQuestionText = $pQuestionRow['question'] ?? '';
                        $translatedQuestionText = $this->getTranslatedQuestion($pQuestionId, $rawQuestionText);

                        $completionsContext[] = [
                            'question'     => $translatedQuestionText,
                            'answer'       => $pComp['answer'] ?? '',
                            'requirements' => $pStepRow['requirements'] ?? '',
                        ];
                    }
                }

                if (!empty($completionsContext)) {
                    $previousSteps['stepID_' . $pStepId] = [
                        'completions' => $completionsContext,
                    ];
                }
            }
        }

        $requirementsRows = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP)
            ->select(['setupRequirement', 'value'])
            ->get() ?: [];

        $collectedRequirements = [];
        foreach ($requirementsRows as $reqRow) {
            $reqRow = (array) $reqRow;
            $reqName = $reqRow['setupRequirement'] ?? '';
            $reqValue = $reqRow['value'] ?? null;
            if ($reqValue !== null && $reqValue !== '') {
                if ($reqName === 'businessGeoAddress') {
                    continue;
                }

                if (is_string($reqValue) && (str_starts_with($reqValue, '[') || str_starts_with($reqValue, '{'))) {
                    $decoded = json_decode($reqValue, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $reqValue = $decoded;
                    }
                }

                $collectedRequirements[$reqName] = $reqValue;
            }
        }

        $completionData['language'] = WordpressHelpers::current_language_code_helper();
        $completionData['isEvaluated'] = $dbData['isEvaluated'] ?? false;
        $completionData['evaluationResult'] = $dbData['evaluationResult'] ?? false;
        $completionData['evaluationFeedback'] = $dbData['evaluationFeedback'] ?? '';
        $completionData['evaluationRawAIResult'] = $dbData['evaluationRawAIResult'] ?? '';
        $completionData['evaluationRawAIPrompt'] = $dbData['evaluationRawAIPrompt'] ?? '';
        $completionData['metadata'] = [
            'currentStep'           => $currentStepContext,
            'previousSteps'          => empty($previousSteps) ? [] : (object) $previousSteps,
            'collectedRequirements' => (object) $collectedRequirements,
        ];
        $completionData['postalAddress'] = $dbData['postalAddress'] ?? null;

        $onboarding = new AutoSetupOnboarding();
        $websiteGeneralDescription = $onboarding->getOnboardingContent(true) ?: '';

        $payloadData = [
            'dataForEvaluation'         => (object) $completionData,
            'websiteGeneralDescription' => $websiteGeneralDescription,
        ];

        $payload = CoreHelper::generateCommonSecurityPayload($payloadData);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        $response = $this->post($payload);

        $this->handleEvaluationResponse($response, $completionRow, (int) ($stepRow['id'] ?? 0), $questionId);

        return $response;
    }

    /**
     * Handle the response from AI evaluation and update local DB
     */
    private function handleEvaluationResponse(array $response, ?array $completionRow, int $stepId, ?int $questionId): void
    {
        $evaluatedData = $response['content']->evaluatedData ?? null;
        if (empty($evaluatedData) || empty($evaluatedData->evaluationRawAIResult)) {
            return;
        }

        $toArray = function ($data) use (&$toArray) {
            if (is_object($data)) {
                $data = get_object_vars($data);
            }
            if (is_array($data)) {
                return array_map($toArray, $data);
            }
            return $data;
        };

        $isEvaluated = true;
        $evaluationResult = (bool) ($evaluatedData->evaluationResult ?? false);
        $evaluationFeedback = $evaluatedData->evaluationFeedback ?? '';
        $evaluationRawAIResult = $evaluatedData->evaluationRawAIResult ?? '';
        $evaluationRawAIPrompt = $evaluatedData->evaluationRawAIPrompt ?? '';
        $metadata = $toArray($evaluatedData->metadata ?? []);
        $postalAddress = $evaluatedData->postalAddress ?? null;
        if (is_object($postalAddress)) {
            $postalAddress = $toArray($postalAddress);
        }

        $dataToSave = [
            'isEvaluated' => $isEvaluated,
            'evaluationResult' => $evaluationResult,
            'evaluationFeedback' => $evaluationFeedback,
            'evaluationRawAIResult' => $evaluationRawAIResult,
            'evaluationRawAIPrompt' => $evaluationRawAIPrompt,
            'metadata' => $metadata,
            'postalAddress' => $postalAddress,
        ];

        // 1. Update Completion Data
        if ($completionRow && !empty($completionRow['id'])) {
            $completionId = (int) $completionRow['id'];
            $updateData = [
                'data' => json_encode($dataToSave),
                'timeOfCompletion' => time(),
            ];
            if ($evaluationResult) {
                $updateData['isCompleted'] = 1;
            }
            DatabaseManager::getInstance()->update(
                DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS,
                $updateData,
                ['id' => $completionId]
            );
        }

        // 2. Process Extracted Requirements
        $currentStepMetadata = (array) ($metadata['currentStep'] ?? []);
        $requirementExtracted = (array) ($currentStepMetadata['requirementExtracted'] ?? []);
        if (!empty($requirementExtracted)) {
            if ($postalAddress) {
                $requirementExtracted['businessGeoAddress'] = $postalAddress;
            }
            RequirementHelper::updateRequirements($requirementExtracted);
        }

        // 3. Process Next Step Question
        $nextStepQuestion = $currentStepMetadata['nextStepQuestion'] ?? '';
        if (!empty($nextStepQuestion) && $stepId > 0) {
            $this->processNextStepQuestion((string)$nextStepQuestion, $stepId, $questionId);
        }

        // 4. Update Step Status if evaluation succeeded
        if ($evaluationResult && $stepId > 0) {
            DatabaseManager::getInstance()->update(
                DatabaseTablesManager::DATABASE_SETUP_STEPS,
                ['completed' => 1],
                ['id' => $stepId]
            );
        }
    }

    private function processNextStepQuestion(string $questionText, int $currentStepId, ?int $parentQuestionId): void
    {
        $db = DatabaseManager::getInstance();
        $nextStep = $db->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
            ->select(['id'])
            ->where('priority', function($query) use ($currentStepId) {
                $query->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
                    ->select(['priority'])
                    ->where('id', $currentStepId);
            }, '>')
            ->orderBy('priority', 'ASC')
            ->first();

        if ($nextStep) {
            $nextStepId = (int) $nextStep->id;
            
            $existingQuestions = $db->table(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS)
                ->select(['id'])
                ->where('stepId', $nextStepId)
                ->get() ?: [];

            $db->insert(
                DatabaseTablesManager::DATABASE_SETUP_QUESTIONS,
                [
                    'stepId' => $nextStepId,
                    'parentId' => $parentQuestionId,
                    'question' => $questionText,
                    'sequence' => count($existingQuestions) + 1,
                    'isAiGenerated' => 1,
                ]
            );
        }
    }

    private function getTranslatedQuestion(int $questionId, string $defaultQuestion): string
    {
        $translations = [
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

        return isset($translations[$questionId]) ? $translations[$questionId] : $defaultQuestion;
    }

    public function syncKeywords(array $keywords): array
    {
        $this->setUrl('sync/keywords');
        $payload = CoreHelper::generateCommonSecurityPayload($keywords);
        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
        return $this->post($payload);
    }
}
