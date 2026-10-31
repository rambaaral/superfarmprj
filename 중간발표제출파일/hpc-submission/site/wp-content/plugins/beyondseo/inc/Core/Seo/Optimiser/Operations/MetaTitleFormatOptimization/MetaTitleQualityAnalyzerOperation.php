<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleFormatOptimization;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class MetaTitleQualityAnalyzerOperation
 *
 * Analyzes meta title quality including word count, structure, formatting, and clickbait detection for optimal SEO performance.
 */
#[SeoMeta(
    name: 'Meta Title Quality Analyzer',
    weight: WeightConfiguration::WEIGHT_META_TITLE_QUALITY_ANALYZER_OPERATION,
    description: 'Analyzes meta title quality including word count, structure, formatting, and clickbait detection for optimal SEO performance.',
)]
class MetaTitleQualityAnalyzerOperation extends Operation implements OperationInterface
{
    // Title quality thresholds
    private const MIN_WORD_COUNT = 3;
    private const MAX_WORD_COUNT = 12;
    private const OPTIMAL_WORD_COUNT = 6;

    // Special character thresholds
    private const MAX_SPECIAL_CHAR_PERCENTAGE = 15; // percent of characters

    // Title structure patterns
    private const TITLE_SEPARATOR_PATTERN = '/[-|:]/';

    // Clickbait
    private const CLICKBAIT_SCORE_THRESHOLD = 0.6;

    /**
     * Performs general quality checks on the meta-title.
     * Fetches meta-title and analyzes various aspects of its structure and formatting.
     *
     * @return array|null Validation results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;

        // Get the page URL for the post (used as availability check)
        $pageUrl = $this->contentProvider->getPostUrl($postId);
        if (empty($pageUrl)) {
            return [
                'success' => false,
                'message' => __('Unable to get post URL', 'beyondseo'),
            ];
        }

        // Extract title from the HTML
        $html = $this->contentProvider->getContent($postId);
        $metaTitle = $this->contentProvider->extractMetaTitleFromHTML($html);

        // If title extraction fails, fall back to WordPress meta-data
        if (empty($metaTitle)) {
            $metaTitle = $this->contentProvider->getFallbackMetaTitle($postId);
        }

        // If still no meta-title found, return error
        if (empty($metaTitle)) {
            return [
                'success' => false,
                'message' => __('No meta title found', 'beyondseo'),
                'title_text' => '',
            ];
        }

        // Analyze title structure and quality
        $titleMetrics = $this->analyzeTitleQuality($metaTitle);

        return [
            'success' => true,
            'message' => __('Meta title quality analysis completed successfully', 'beyondseo'),
            'title_text' => $metaTitle,
            'title_metrics' => $titleMetrics,
        ];
    }

    /**
     * Calculates a score based on the overall quality of the meta-title.
     * The score considers word count, capitalization, special characters,
     * structure, and potential clickbait elements.
     *
     * Weights:
     * - Length: 45%
     * - Structure: 45%
     * - Format: 5%
     * - Content: 5%
     *
     * @return float A score from 0 to 1 based on title quality
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // If no title or metrics, return 0
        if (empty($factorData['title_metrics']) || empty($factorData['title_text'])) {
            return 0.0;
        }

        $metrics = $factorData['title_metrics'];

        // 1. Length score (45% of total)
        $lengthScore = 0.0;
        if ($metrics['word_count'] >= self::MIN_WORD_COUNT && $metrics['word_count'] <= self::MAX_WORD_COUNT) {
            // Optimal score when word count is close to the ideal
            $lengthScore = 0.45 * (1 - min(1, abs($metrics['word_count'] - self::OPTIMAL_WORD_COUNT) / 6));
        }

        // 2. Structure score (45% of total)
        $structureScore = 0.45 * (
            ($metrics['has_brand_separator'] ? 0.5 : 0.0) +
            ($metrics['has_proper_capitalization'] ? 0.5 : 0.0)
        );

        // 3. Format score (5% of total)
        $specialCharPenalty = min(1, $metrics['special_char_percentage'] / self::MAX_SPECIAL_CHAR_PERCENTAGE);
        $formatScore = 0.05 * (1 - $specialCharPenalty);

        // 4. Content quality score (5% of total)
        $contentScore = 0.05 * (
            ($metrics['is_clickbait'] ? 0.0 : 0.7) +
            ($metrics['has_numbers'] ? 0.3 : 0.0)
        );

        // Final score [0..1]
        $finalScore = $lengthScore + $structureScore + $formatScore + $contentScore;
        return min(1.0, max(0.0, $finalScore));
    }

    /**
     * Provides suggestions for improving the meta-title quality.
     * Focus on structure, length, formatting, and content.
     *
     * @return array<int, Suggestion> An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];

        // If no title or metrics, suggest creating a title
        if (empty($factorData['title_metrics']) || empty($factorData['title_text'])) {
            $suggestions[] = Suggestion::META_TITLE_MISSING;
            return $suggestions;
        }

        $metrics = $factorData['title_metrics'];

        // Over-optimal but not exceeding maximum
        if ($metrics['word_count'] > self::OPTIMAL_WORD_COUNT && $metrics['word_count'] <= self::MAX_WORD_COUNT) {
            $suggestions[] = Suggestion::META_TITLE_OVER_OPTIMAL_WORD_COUNT;
        }

        // Check title length bounds
        if ($metrics['word_count'] < self::MIN_WORD_COUNT) {
            $suggestions[] = Suggestion::META_TITLE_TOO_SHORT;
        } elseif ($metrics['word_count'] > self::MAX_WORD_COUNT) {
            $suggestions[] = Suggestion::META_TITLE_TOO_LONG;
        }

        // Check for clickbait patterns
        if ($metrics['is_clickbait']) {
            $suggestions[] = Suggestion::META_TITLE_CLICKBAIT;
        }

        if (!$metrics['has_proper_capitalization']) {
            $suggestions[] = Suggestion::META_TITLE_POOR_CAPITALIZATION;
        }

        // Check for brand separator structure
        if (!$metrics['has_brand_separator']) {
            $suggestions[] = Suggestion::META_TITLE_MISSING_BRAND_SEPARATOR;
        }

        return $suggestions;
    }

    /**
     * Analyzes various aspects of the meta-title quality.
     *
     * @param string $title The meta title to analyze
     * @return array<string, int|float|bool|string> Metrics about the title quality
     */
    private function analyzeTitleQuality(string $title): array
    {
        $title = trim($title);

        // Helper: multibyte-safe string length
        $strlen = static function (string $s): int {
            return function_exists('mb_strlen') ? (int) mb_strlen($s, 'UTF-8') : strlen($s);
        };

        // Tokenize words (Unicode-aware)
        $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        // Capitalization analysis (accept Uppercase start or acronyms; consider first/last and words >3 chars)
        $capitalizedWords = 0;
        foreach ($words as $i => $word) {
            $isFirstOrLast = ($i === 0 || $i === $wordCount - 1);
            $startsUpper = (bool) preg_match('/^\p{Lu}/u', $word);
            $isAcronym = (bool) preg_match('/^[A-Z0-9]{2,}$/', $word);
            $len = $strlen($word);
            if (($len > 3 || $isFirstOrLast) && ($startsUpper || $isAcronym)) {
                $capitalizedWords++;
            }
        }
        $hasProperCapitalization = ($capitalizedWords / max(1, $wordCount)) >= 0.5;

        // Special character analysis (Unicode-aware)
        $specialCharCount = preg_match_all('/[^\p{L}\p{N}\s]/u', $title, $m) ?: 0;
        $charCount = max(1, $strlen($title));
        $specialCharPercentage = ($specialCharCount / $charCount) * 100.0;

        // Structure analysis
        $hasBrandSeparator = preg_match(self::TITLE_SEPARATOR_PATTERN, $title) > 0;

        // Clickbait detection (localized scoring)
        $locale = $this->resolveClickbaitLanguage();
        [$clickbaitScore, $clickbaitReasons] = $this->computeClickbaitScoreLocalized($title, $locale);
        $isClickbait = $clickbaitScore >= self::CLICKBAIT_SCORE_THRESHOLD;

        // Number presence (often good for listicles and specific content)
        $hasNumbers = preg_match('/\d+/', $title) > 0;

        return [
            'word_count' => $wordCount,
            'has_proper_capitalization' => $hasProperCapitalization,
            'special_char_count' => $specialCharCount,
            'special_char_percentage' => $specialCharPercentage,
            'has_brand_separator' => $hasBrandSeparator,
            'is_clickbait' => $isClickbait,
            'clickbait_score' => $clickbaitScore,
            // 'clickbait_reasons' => $clickbaitReasons, // uncomment if needed downstream
            'clickbait_locale' => $locale,
            'has_numbers' => $hasNumbers,
        ];
    }

    /**
     * Determine locale key for clickbait detection.
     * Maps WP locales to compact keys: en, de, es, fr, it, nl, pl, pt
     */
    private function resolveClickbaitLanguage(): string
    {
        $wpLocale = function_exists('get_locale') ? (string) \get_locale() : 'en_US';
        $wpLocale = $wpLocale ?: 'en_US';

        $map = [
            'en' => 'en', 'en_US' => 'en', 'en_GB' => 'en',
            'de' => 'de', 'de_DE' => 'de',
            'es' => 'es', 'es_ES' => 'es',
            'fr' => 'fr', 'fr_FR' => 'fr',
            'it' => 'it', 'it_IT' => 'it',
            'nl' => 'nl', 'nl_NL' => 'nl',
            'pl' => 'pl', 'pl_PL' => 'pl',
            'pt' => 'pt', 'pt_BR' => 'pt', 'pt_PT' => 'pt',
        ];
        if (isset($map[$wpLocale])) {
            return $map[$wpLocale];
        }
        $prefix = substr($wpLocale, 0, 2);
        return $map[$prefix] ?? 'en';
    }

    /**
     * Compute clickbait score using localized weighted signals.
     * Returns [float $score, string[] $reasons]
     */
    private function computeClickbaitScoreLocalized(string $title, string $lang): array
    {
        $signals = $this->getClickbaitSignalsForLocale($lang);

        $score = 0.0;
        $reasons = [];

        $lc = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);

        // Curiosity hooks
        foreach ($signals['curiosity'] as $p) {
            if (preg_match($p, $lc)) {
                $score += 0.35;
                $reasons[] = 'curiosity_hook';
                break;
            }
        }

        // Sensational adjectives
        foreach ($signals['sensational'] as $p) {
            if (preg_match($p, $lc)) {
                $score += 0.20;
                $reasons[] = 'sensational_adj';
                break;
            }
        }

        // Listicle cues
        foreach ($signals['listicle'] as $p) {
            if (preg_match($p, $lc)) {
                $score += 0.10;
                $reasons[] = 'listicle';
                break;
            }
        }

        // Excessive punctuation (language-agnostic)
        if (preg_match('/([!?]{2,})/u', $title)) {
            $score += 0.10;
            $reasons[] = 'excessive_punctuation';
        }

        // ALL CAPS ratio (words >= 3 chars)
        $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $capsWords = 0;
        foreach ($words as $w) {
            if (preg_match('/^[A-Z0-9]{3,}$/', $w)) {
                $capsWords++;
            }
        }
        if (count($words) > 0 && ($capsWords / max(1, count($words))) >= 0.2) {
            $score += 0.10;
            $reasons[] = 'all_caps_ratio';
        }

        // Deictic vagueness
        foreach ($signals['deictic'] as $p) {
            if (preg_match($p, $lc)) {
                $score += 0.10;
                $reasons[] = 'vague_deictic';
                break;
            }
        }

        // CTA + second person
        foreach ($signals['cta'] as $p) {
            if (preg_match($p, $lc)) {
                $score += 0.05;
                $reasons[] = 'second_person_cta';
                break;
            }
        }

        // Clip to [0,1]
        $score = min(1.0, max(0.0, $score));
        return [$score, $reasons];
    }

    /**
     * Localized clickbait signals (regex patterns with /ui where needed)
     * @return array<string, array<int, string>>
     */
    private function getClickbaitSignalsForLocale(string $lang): array
    {
        switch ($lang) {
            case 'de':
                return [
                    'curiosity' => [
                        '/\bdu wirst es nicht glauben\b/ui',
                        '/\bwas als nächstes passiert\b/ui',
                        '/\bdies(?:es|er|e) (?:einfache|simple) (?:trick|hack)\b/ui',
                        '/\bniemand (?:sagt|erzählt) dir\b/ui',
                        '/\bverändert dein leben\b/ui',
                        '/\bbevor du stirbst\b/ui',
                        '/\bdie (?:wahrheit) (?:über|hinter)\b/ui',
                    ],
                    'sensational' => [
                        '/\bschockierend\b/ui',
                        '/\bunglaublich\b/ui',
                        '/\bwahnsinnig\b/ui',
                        '/\batemberaubend\b/ui',
                        '/\bepisch|episch\b/ui',
                        '/\bultimativ\b/ui',
                        '/\bkrass\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|besten|schlechtesten|gründe|wege|tipps|tricks)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\bdies(?:es|e|er)\s+(?:ding|dinge|trick|tricks|hack|hacks|weg|wege)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:du|dein|deine|ihr|euer)\b.*\b(?:musst|solltest|sollt|müsst)\b/ui',
                    ],
                ];
            case 'es':
                return [
                    'curiosity' => [
                        '/\bno lo vas a creer\b/ui',
                        '/\bqué pasa(?:rá)? después\b/ui',
                        '/\beste (?:simple|sencillo) truco\b/ui',
                        '/\bnadie te (?:dice|contó|cuenta)\b/ui',
                        '/\bcambiará tu vida\b/ui',
                        '/\bantes de morir\b/ui',
                        '/\bla verdad (?:sobre|detrás de)\b/ui',
                    ],
                    'sensational' => [
                        '/\bimpactante\b/ui',
                        '/\bincreíble\b/ui',
                        '/\balucinante\b/ui',
                        '/\bimpresionante\b/ui',
                        '/\bépico\b/ui',
                        '/\bdefinitivo\b/ui',
                        '/\bsorprendente\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|mejores|peores|razones|formas|maneras|consejos|trucos)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\b(?:este|esta|estos|estas)\s+(?:cosa|cosas|truco|trucos|hack|hacks|manera|maneras|forma|formas)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:tú|tu|su)\b.*\b(?:debes|debe|necesitas|necesita)\b/ui',
                    ],
                ];
            case 'fr':
                return [
                    'curiosity' => [
                        '/\b(tu ne vas pas|vous n\'allez pas) y croire\b/ui',
                        '/\bce qui se passe ensuite\b/ui',
                        '/\bce (?:simple|petit) (?:truc|hack|astuce)\b/ui',
                        '/\bpersonne ne (?:te|vous) le dit\b/ui',
                        '/\bchangera (?:ta|votre) vie\b/ui',
                        '/\bavant de mourir\b/ui',
                        '/\bla vérité (?:sur|derrière)\b/ui',
                    ],
                    'sensational' => [
                        '/\bchoquant\b/ui',
                        '/\bincroyable\b/ui',
                        '/\bhallucinant\b/ui',
                        '/\bépoustouflant\b/ui',
                        '/\bépique\b/ui',
                        '/\builtime\b/ui',
                        '/\bimpressionnant\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|meilleurs|pires|raisons|façons|manières|astuces|conseils|trucs)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\b(?:ce|cet|cette|ces)\s+(?:truc|trucs|astuce|astuces|hack|hacks|manière|manières|façon|façons)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:tu|ton|votre|vous)\b.*\b(?:dois|devez|devrais|il faut)\b/ui',
                    ],
                ];
            case 'it':
                return [
                    'curiosity' => [
                        '/\bnon ci crederai\b/ui',
                        '/\bcosa succede dopo\b/ui',
                        '/\bquesto (?:semplice|piccolo) trucco\b/ui',
                        '/\bnessuno te lo dice\b/ui',
                        '/\bcambierà la tua vita\b/ui',
                        '/\bprima di morire\b/ui',
                        '/\bla verità (?:su|dietro)\b/ui',
                    ],
                    'sensational' => [
                        '/\bscioccante\b/ui',
                        '/\bincredibile\b/ui',
                        '/\bpazzesco\b/ui',
                        '/\bmozzafiato\b/ui',
                        '/\bepico\b/ui',
                        '/\bdefinitivo\b/ui',
                        '/\bstupefacente\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|migliori|peggiori|motivi|modi|consigli|trucchi)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\b(?:questo|questa|questi|queste)\s+(?:cosa|cose|trucco|trucchi|hack|hacks|modo|modi)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:tu|tua|tuo|voi|vostro)\b.*\b(?:devi|dovresti|bisogna)\b/ui',
                    ],
                ];
            case 'nl':
                return [
                    'curiosity' => [
                        '/\bje gelooft het niet\b/ui',
                        '/\bwat er daarna gebeurt\b/ui',
                        '/\bdeze (?:simpele|eenvoudige) truc\b/ui',
                        '/\bniemand vertelt je\b/ui',
                        '/\bverandert je leven\b/ui',
                        '/\bvoor je sterft\b/ui',
                        '/\bde waarheid (?:over|achter)\b/ui',
                    ],
                    'sensational' => [
                        '/\bschokkend\b/ui',
                        '/\bongelooflijk\b/ui',
                        '/\bbizar\b/ui',
                        '/\bverbluffend\b/ui',
                        '/\bepisch|episch\b/ui',
                        '/\bultiem\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|beste|slechtste|redenen|manieren|tips|trucs)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\b(?:dit|deze)\s+(?:ding|dingen|truc|trucs|hack|hacks|manier|manieren)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:jij|je|jouw|uw)\b.*\b(?:moet|zou moeten)\b/ui',
                    ],
                ];
            case 'pl':
                return [
                    'curiosity' => [
                        '/\bnie uwierzysz\b/ui',
                        '/\bco stanie się dalej\b/ui',
                        '/\bten (?:prosty)? (?:trik|sztuczka)\b/ui',
                        '/\bnikt ci (?:nie mówi|nie powie)\b/ui',
                        '/\bzmieni twoje życie\b/ui',
                        '/\bzanim umrzesz\b/ui',
                        '/\bprawda (?:o|na temat|za)\b/ui',
                    ],
                    'sensational' => [
                        '/\bszokuj(?:ący|ące)\b/ui',
                        '/\bniewiarygodn(?:y|e)\b/ui',
                        '/\bepick(?:i|ie)\b/ui',
                        '/\bniesamowit(?:y|e)\b/ui',
                        '/\boszałamiając(?:y|e)\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|najlepsze|najgorsze|powody|sposoby|wskazówki|triki)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\b(?:ten|ta|to|te)\s+(?:rzecz|rzeczy|trik|triki|sposób|sposoby)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:ty|twój|twoja|twoje)\b.*\b(?:musisz|powinieneś|powinnaś)\b/ui',
                    ],
                ];
            case 'pt':
                return [
                    'curiosity' => [
                        '/\bvocê não vai acreditar\b/ui',
                        '/\bo que acontece depois\b/ui',
                        '/\beste (?:simples|pequeno) truque\b/ui',
                        '/\bninguém te (?:conta|contou|diz)\b/ui',
                        '/\b(vai|vai) mudar sua vida\b/ui',
                        '/\bantes de morrer\b/ui',
                        '/\ba verdade (?:sobre|por trás de)\b/ui',
                    ],
                    'sensational' => [
                        '/\bchocante\b/ui',
                        '/\bincrível\b/ui',
                        '/\binsano\b/ui',
                        '/\bépico\b/ui',
                        '/\bdefinitivo\b/ui',
                        '/\bsurpreendente\b/ui',
                        '/\bimpressionante\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|melhores|piores|razões|maneiras|formas|dicas|truques)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\b(?:este|esta|estes|estas)\s+(?:coisa|coisas|truque|truques|hack|hacks|maneira|maneiras|forma|formas)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:você|seu|sua|tu|teu|tua)\b.*\b(?:deve|precisa|precisas|deveria)\b/ui',
                    ],
                ];
            case 'en':
            default:
                return [
                    'curiosity' => [
                        '/\byou won\'t believe\b/ui',
                        '/\bwhat happens next\b/ui',
                        '/\bthis (?:simple|one|tiny) (?:trick|hack)\b/ui',
                        '/\bno one (?:tells|told) you\b/ui',
                        '/\bchange your life\b/ui',
                        '/\bbefore you die\b/ui',
                        '/\bthe (?:truth|secret) (?:about|behind)\b/ui',
                    ],
                    'sensational' => [
                        '/\bshocking\b/ui',
                        '/\bunbelievable\b/ui',
                        '/\binsane\b/ui',
                        '/\bmind[- ]?blowing\b/ui',
                        '/\bjaw[- ]?dropping\b/ui',
                        '/\bstunning\b/ui',
                        '/\bepic\b/ui',
                        '/\bultimate\b/ui',
                        '/\bamazing\b/ui',
                        '/\bincredible\b/ui',
                    ],
                    'listicle' => [
                        '/\b(?:top|best|worst|reasons|ways|tips|tricks)\s+\d+\b/ui',
                    ],
                    'deictic' => [
                        '/\b(?:this|these)\s+(?:thing|things|trick|tricks|hack|hacks|way|ways)\b/ui',
                    ],
                    'cta' => [
                        '/\b(?:you|your)\b.*\b(?:must|need to|should)\b/ui',
                    ],
                ];
        }
    }
}