<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Storage\MetaTagsStorage;
use RankingCoach\Inc\Modules\ModuleManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class MetaTagsController
{
    use RcApiTrait;
    use RcLoggerTrait;

    private const TAG_TYPE_TITLE       = 'title';
    private const TAG_TYPE_DESCRIPTION = 'description';
    private const TAG_TYPE_KEYWORDS    = 'keywords';
    private const TAG_TYPE_SOCIAL_TITLE = 'social_title';
    private const TAG_TYPE_SOCIAL_DESCRIPTION = 'social_description';
    private const REMEMBERED_KEYWORDS_LIMIT = 500;

    private function upsertTagRow(int $postId, string $type, array $data, ?array $expectedRows = null): void
    {
        (new MetaTagsStorage())->save($postId, $type, $data['template'] ?? '', $expectedRows);
    }

    /**
     * @throws \JsonException
     */
    private function getKeywordsContent(array $rows): array
    {
        if ($rows === []) {
            return ['primaryKeyword' => '', 'additionalKeywords' => []];
        }

        if (count($rows) !== 1 || !isset($rows[0]['template']) || !is_string($rows[0]['template'])) {
            throw new \InvalidArgumentException('invalid_keyword_rows');
        }

        return MetaTagsStorage::decodeKeywordsTemplate($rows[0]['template']);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');

            /** @var MetaTags $metaTagsModule */
            $metaTagsModule = ModuleManager::instance()->get_module(MetaTags::MODULE_NAME);
            $response = $metaTagsModule->getMetaTagsData($postId);

            return new WP_REST_Response($response, 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::get: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');
            $body   = $request->get_json_params() ?: [];

            // Title
            if (isset($body['title'])) {
                $rawTemplate = $body['title']['template'] ?? ($body['title']['content'] ?? '');
                $titleTemplate = WordpressHelpers::sanitizeTemplateInput($rawTemplate, false);

                $this->upsertTagRow($postId, self::TAG_TYPE_TITLE, [
                    'template' => $titleTemplate,
                ]);
            }

            // Description
            if (isset($body['description'])) {
                $rawTemplate = $body['description']['template'] ?? ($body['description']['content'] ?? '');
                $descTemplate = WordpressHelpers::sanitizeTemplateInput($rawTemplate, true);

                $this->upsertTagRow($postId, self::TAG_TYPE_DESCRIPTION, [
                    'template' => $descTemplate,
                ]);
            }

            // Social Title
            if (isset($body['socialTitle'])) {
                $rawTemplate = $body['socialTitle']['template'] ?? ($body['socialTitle']['content'] ?? '');
                $socialTitleTemplate = WordpressHelpers::sanitizeTemplateInput($rawTemplate, false);

                $this->upsertTagRow($postId, self::TAG_TYPE_SOCIAL_TITLE, [
                    'template' => $socialTitleTemplate,
                ]);
            }

            // Social Description
            if (isset($body['socialDescription'])) {
                $rawTemplate = $body['socialDescription']['template'] ?? ($body['socialDescription']['content'] ?? '');
                $socialDescTemplate = WordpressHelpers::sanitizeTemplateInput($rawTemplate, true);

                $this->upsertTagRow($postId, self::TAG_TYPE_SOCIAL_DESCRIPTION, [
                    'template' => $socialDescTemplate,
                ]);
            }

            // Keywords
            if (isset($body['keywords'])) {
                $primaryKeyword     = sanitize_text_field($body['keywords']['primaryKeyword'] ?? '');
                $additionalKeywords = array_map(
                    'sanitize_text_field',
                    (array) ($body['keywords']['additionalKeywords'] ?? [])
                );
                $this->upsertTagRow($postId, self::TAG_TYPE_KEYWORDS, [
                    'template' => wp_json_encode([
                        'primaryKeyword'     => $primaryKeyword,
                        'additionalKeywords' => $additionalKeywords,
                    ]),
                ]);
                $this->rememberKeywords(array_merge([$primaryKeyword], $additionalKeywords));
            }

            /** @var MetaTags $metaTagsModule */
            $metaTagsModule = ModuleManager::instance()->get_module(MetaTags::MODULE_NAME);
            return new WP_REST_Response($metaTagsModule->getMetaTagsData($postId), 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::save: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    /**
     * Swap a keyword with the current primary keyword.
     *
     * The frontend sends `{ "keyword": "<additional keyword to promote>" }`
     * (see KeywordsMetaTagsKeywordRequestDto). The promoted keyword becomes
     * the primary keyword and the previous primary keyword takes its place
     * in the additional keywords list. The legacy `newKeyword` body field is
     * accepted as a fallback.
     */
    public function swapKeyword(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId  = (int) $request->get_param('postId');
            $body    = $request->get_json_params() ?: [];
            $keyword = sanitize_text_field($body['keyword'] ?? ($body['newKeyword'] ?? ''));

            $expectedRows = (new MetaTagsStorage())->read($postId, self::TAG_TYPE_KEYWORDS);
            $kwData     = $this->getKeywordsContent($expectedRows);
            $oldPrimary = (string) $kwData['primaryKeyword'];

            if ($keyword !== '' && $keyword !== $oldPrimary) {
                $additional = array_values((array) $kwData['additionalKeywords']);
                $index      = array_search($keyword, $additional, true);

                if ($index !== false) {
                    if ($oldPrimary !== '') {
                        // Demote the old primary into the promoted keyword's slot.
                        $additional[$index] = $oldPrimary;
                    } else {
                        array_splice($additional, $index, 1);
                    }
                } elseif ($oldPrimary !== '') {
                    // Promoted keyword was not listed; still demote the old primary.
                    $additional[] = $oldPrimary;
                }

                // Safety: the new primary must not remain in the additional list.
                $additional = array_values(array_unique(array_filter(
                    $additional,
                    fn($kw) => $kw !== '' && $kw !== $keyword
                )));

                $kwData['primaryKeyword']     = $keyword;
                $kwData['additionalKeywords'] = $additional;

                $this->upsertTagRow($postId, self::TAG_TYPE_KEYWORDS, [
                    'template' => wp_json_encode($kwData),
                ], $expectedRows);
            }

            /** @var MetaTags $metaTagsModule */
            $metaTagsModule = ModuleManager::instance()->get_module(MetaTags::MODULE_NAME);
            return new WP_REST_Response($metaTagsModule->getMetaTagsData($postId), 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::swapKeyword: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function assignKeyword(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId  = (int) $request->get_param('postId');
            $body    = $request->get_json_params() ?: [];
            $keyword = sanitize_text_field($body['keyword'] ?? '');

            $expectedRows = (new MetaTagsStorage())->read($postId, self::TAG_TYPE_KEYWORDS);
            $kwData = $this->getKeywordsContent($expectedRows);

            if ($kwData['primaryKeyword'] === '') {
                $kwData['primaryKeyword'] = $keyword;
            } elseif (
                $keyword !== $kwData['primaryKeyword'] &&
                !in_array($keyword, $kwData['additionalKeywords'], true)
            ) {
                $kwData['additionalKeywords'][] = $keyword;
            }

            $this->upsertTagRow($postId, self::TAG_TYPE_KEYWORDS, [
                'template' => wp_json_encode($kwData),
            ], $expectedRows);
            $this->rememberKeywords([$keyword]);

            /** @var MetaTags $metaTagsModule */
            $metaTagsModule = ModuleManager::instance()->get_module(MetaTags::MODULE_NAME);
            return new WP_REST_Response($metaTagsModule->getMetaTagsData($postId), 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::assignKeyword: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function detachKeyword(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId  = (int) $request->get_param('postId');
            $body    = $request->get_json_params() ?: [];
            $keyword = sanitize_text_field($body['keyword'] ?? '');

            $expectedRows = (new MetaTagsStorage())->read($postId, self::TAG_TYPE_KEYWORDS);
            $kwData = $this->getKeywordsContent($expectedRows);

            if ($kwData['primaryKeyword'] === $keyword) {
                $kwData['primaryKeyword'] = '';
            }

            $kwData['additionalKeywords'] = array_values(
                array_filter($kwData['additionalKeywords'], fn($kw) => $kw !== $keyword)
            );

            $this->upsertTagRow($postId, self::TAG_TYPE_KEYWORDS, [
                'template' => wp_json_encode($kwData),
            ], $expectedRows);

            /** @var MetaTags $metaTagsModule */
            $metaTagsModule = ModuleManager::instance()->get_module(MetaTags::MODULE_NAME);
            return new WP_REST_Response($metaTagsModule->getMetaTagsData($postId), 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::detachKeyword: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    /**
     * Remember locally added keywords (plugin option, newest first, capped) so both keyword pickers can
     * suggest them again after they are detached. Kept apart from the account keyword table, which the
     * rankingCoach sync owns.
     */
    private function rememberKeywords(array $keywords): void
    {
        $remembered = self::rememberedKeywords();
        $known = array_fill_keys(array_map([self::class, 'keywordKey'], $remembered), true);
        $added = false;

        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '' || mb_strlen($keyword) > 255 || isset($known[self::keywordKey($keyword)])) {
                continue;
            }
            array_unshift($remembered, $keyword);
            $known[self::keywordKey($keyword)] = true;
            $added = true;
        }

        if ($added) {
            update_option(
                BaseConstants::OPTION_REMEMBERED_KEYWORDS,
                array_slice($remembered, 0, self::REMEMBERED_KEYWORDS_LIMIT),
                false
            );
        }
    }

    private static function rememberedKeywords(): array
    {
        $stored = get_option(BaseConstants::OPTION_REMEMBERED_KEYWORDS, []);

        return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
    }

    /** Case-insensitive identity of a keyword. */
    private static function keywordKey(string $keyword): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($keyword) : strtolower($keyword);
    }

    public function listKeywords(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $keywords = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
                ->select(['*'])
                ->get();

            $keywords = is_array($keywords)
                ? array_map(fn($k) => is_object($k) ? (array) $k : $k, $keywords)
                : [];

            // Locally remembered keywords follow the account keywords, without duplicating them.
            $known = [];
            foreach ($keywords as $keyword) {
                $known[self::keywordKey((string) ($keyword['name'] ?? ''))] = true;
            }
            foreach (self::rememberedKeywords() as $keyword) {
                if (!isset($known[self::keywordKey($keyword)])) {
                    $known[self::keywordKey($keyword)] = true;
                    $keywords[] = ['id' => null, 'externalId' => null, 'name' => $keyword, 'alias' => null, 'hash' => null];
                }
            }

            return new WP_REST_Response(['keywords' => $keywords], 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::listKeywords: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function extractContentKeywords(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');
            $post   = get_post($postId);

            if (!$post) {
                return $this->generateErrorResponse(null, 'Post not found', 404);
            }

            $content = wp_strip_all_tags($post->post_content);
            $words   = preg_split('/\s+/', strtolower($content), -1, PREG_SPLIT_NO_EMPTY);

            $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'it', 'as', 'be', 'was', 'are', 'were', 'this', 'that', 'from', 'not', 'have', 'has', 'had', 'do', 'does', 'did'];

            $filtered   = array_filter($words, fn($w) => strlen($w) > 3 && !in_array($w, $stopWords, true));
            $counts     = array_count_values(array_map('sanitize_text_field', $filtered));
            arsort($counts);

            $topKeywords = array_slice($counts, 0, 20, true);
            $result      = [];
            foreach ($topKeywords as $word => $count) {
                $result[] = ['keyword' => $word, 'occurrences' => $count];
            }

            return new WP_REST_Response(['keywords' => $result], 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::extractContentKeywords: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function getSeparator(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId    = (int) $request->get_param('postId');
            $separator = get_post_meta($postId, 'rankingcoach_title_separator', true);

            return new WP_REST_Response(['separator' => $separator ?: '|'], 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::getSeparator: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function saveSeparator(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId    = (int) $request->get_param('postId');
            $body      = $request->get_json_params() ?: [];
            $separator = sanitize_text_field($body['separator'] ?? '|');

            update_post_meta($postId, 'rankingcoach_title_separator', $separator);

            return new WP_REST_Response(['separator' => $separator], 200);
        } catch (Throwable $e) {
            $this->log('Error in MetaTagsController::saveSeparator: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }
}
