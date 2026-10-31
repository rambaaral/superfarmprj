<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionFormatOptimization;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class MetaDescriptionCtaValidationOperation
 *
 * Validates whether the meta-description includes a clear and engaging call to action.
 */
#[SeoMeta(
    name: 'Meta Description Cta Validation',
    weight: WeightConfiguration::WEIGHT_META_DESCRIPTION_CTA_VALIDATION_OPERATION,
    description: 'Evaluates meta descriptions for a clear and compelling call to action. Scans for common CTA phrases and measures their effectiveness, guiding improvements to boost click-through rates from search results.',
)]
class MetaDescriptionCtaValidationOperation extends Operation implements OperationInterface
{
    // CTA scoring thresholds
    private const CTA_SCORE_THRESHOLD_HAS_CTA = 0.30; // minimum score to mark has_cta=true

    /**
     * Localized CTA regex signals (Unicode-aware). Organized by language -> category -> patterns.
     * Supported languages: en, de, es, fr, it, nl, pl, pt
     * Categories: action, imperative, urgency, benefit, question, cta_person
     * Note: Patterns are evaluated case-insensitively with /ui.
     */
    public const CTA_PATTERNS = [
        'en' => [
            'action' => [
                '/\b(?:discover|learn|find|get|download|read|try|start|join|sign up|register|buy|order|shop|contact|call|visit|explore|check out|see|view)\b/ui',
            ],
            'imperative' => [
                '/\b(?:click here|learn more|read more|find out more|get started|sign up now|register today|order now|shop now|call us|contact us|visit us|explore more|try it now)\b/ui',
            ],
            'urgency' => [
                '/\b(?:today|now|limited time|exclusive|special|free|discount|save|offer|don\'t miss|limited offer|act now|hurry|ends soon)\b/ui',
            ],
            'benefit' => [
                '/\b(?:improve|enhance|boost|increase|reduce|save|gain|benefit)\b/ui',
            ],
            'question' => [
                '/\b(?:want to|looking for|need|interested in)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:you|your)\b/ui',
            ],
        ],
        'de' => [
            'action' => [
                '/\b(?:entdecken|lernen|finden|holen|herunterladen|lesen|probieren|starten|beitreten|anmelden|registrieren|kaufen|bestellen|shoppen|kontaktieren|anrufen|besuchen|erkunden|ansehen)\b/ui',
            ],
            'imperative' => [
                '/\b(?:hier klicken|mehr erfahren|weiterlesen|jetzt anmelden|heute registrieren|jetzt bestellen|jetzt einkaufen|rufen sie uns an|besuchen sie uns|mehr erkunden|jetzt starten)\b/ui',
            ],
            'urgency' => [
                '/\b(?:heute|jetzt|begrenzte zeit|exklusiv|spezial|kostenlos|rabatt|sparen|angebot|nicht verpassen|begrenztes angebot|jetzt handeln|beeilen|endet bald)\b/ui',
            ],
            'benefit' => [
                '/\b(?:verbessern|steigern|erhöhen|reduzieren|sparen|gewinnen|vorteil)\b/ui',
            ],
            'question' => [
                '/\b(?:willst du|möchten sie|suchst du|suchen sie|brauchst du|brauchen sie|interessiert an)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:du|dein|deine|ihr|ihre|ihr)\b/ui',
            ],
        ],
        'es' => [
            'action' => [
                '/\b(?:descubre|aprender|aprende|encontrar|encuentra|obtén|descargar|descarga|leer|lee|prueba|empieza|comienza|únete|regístrate|compra|ordenar|ordena|tienda|contacta|llama|visita|explora|ver|mira)\b/ui',
            ],
            'imperative' => [
                '/\b(?:haz clic aquí|más información|leer más|descubre más|comienza ahora|regístrate ahora|regístrate hoy|ordena ahora|compra ahora|llámanos|visítanos|explora más|pruébalo ahora)\b/ui',
            ],
            'urgency' => [
                '/\b(?:hoy|ahora|por tiempo limitado|exclusivo|especial|gratis|descuento|ahorra|oferta|no te lo pierdas|oferta limitada|actúa ahora|apúrate|termina pronto)\b/ui',
            ],
            'benefit' => [
                '/\b(?:mejora|mejorar|potencia|incrementa|aumenta|reduce|ahorra|gana|beneficio)\b/ui',
            ],
            'question' => [
                '/\b(?:quieres|buscas|necesitas|interesado en|interesada en)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:tú|tu|tus|su|sus)\b/ui',
            ],
        ],
        'fr' => [
            'action' => [
                '/\b(?:découvrez|apprenez|trouvez|obtenez|téléchargez|lisez|essayez|commencez|rejoignez|inscrivez-vous|achetez|commandez|magasinez|contactez|appelez|visitez|explorez|voir|regardez)\b/ui',
            ],
            'imperative' => [
                '/\b(?:cliquez ici|en savoir plus|lire la suite|découvrir plus|commencez maintenant|inscrivez-vous maintenant|inscrivez-vous aujourd\'hui|commandez maintenant|achetez maintenant|appelez-nous|visitez-nous|explorez plus|essayez-le maintenant)\b/ui',
            ],
            'urgency' => [
                '/\b(?:aujourd\'hui|maintenant|temps limité|exclusif|spécial|gratuit|réduction|économisez|offre|ne manquez pas|offre limitée|agissez maintenant|dépêchez-vous|se termine bientôt)\b/ui',
            ],
            'benefit' => [
                '/\b(?:améliorez|améliorer|renforcez|augmentez|réduisez|économisez|gagnez|bénéfice)\b/ui',
            ],
            'question' => [
                '/\b(?:vous voulez|vous cherchez|avez[- ]vous besoin|intéressé par|intéressée par)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:vous|votre|vos|toi|ton|tes)\b/ui',
            ],
        ],
        'it' => [
            'action' => [
                '/\b(?:scopri|impara|trova|ottieni|scarica|leggi|prova|inizia|unisciti|iscriviti|compra|ordina|acquista|contatta|chiama|visita|esplora|guarda|vedi)\b/ui',
            ],
            'imperative' => [
                '/\b(?:clicca qui|scopri di più|leggi di più|scopri di piu|inizia ora|iscriviti ora|registrati oggi|ordina ora|acquista ora|chiamaci|visitaci|esplora di più|provalo ora)\b/ui',
            ],
            'urgency' => [
                '/\b(?:oggi|ora|tempo limitato|esclusivo|speciale|gratis|sconto|risparmia|offerta|non perderlo|offerta limitata|agisci ora|affrettati|termina presto)\b/ui',
            ],
            'benefit' => [
                '/\b(?:migliora|potenzia|aumenta|incrementa|riduci|risparmia|ottieni|beneficio)\b/ui',
            ],
            'question' => [
                '/\b(?:vuoi|cerchi|hai bisogno|interessato a|interessata a)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:tu|tua|tuo|vostro|voi)\b/ui',
            ],
        ],
        'nl' => [
            'action' => [
                '/\b(?:ontdek|leer|vind|krijg|download|lees|probeer|start|word lid|meld je aan|registreer|koop|bestel|shop|neem contact op|bel|bezoek|verken|bekijk|zie)\b/ui',
            ],
            'imperative' => [
                '/\b(?:klik hier|meer informatie|lees meer|kom meer te weten|ga aan de slag|meld je nu aan|registreer vandaag|bestel nu|shop nu|bel ons|bezoek ons|verken meer|probeer het nu)\b/ui',
            ],
            'urgency' => [
                '/\b(?:vandaag|nu|beperkte tijd|exclusief|speciaal|gratis|korting|bespaar|aanbod|mis het niet|beperkt aanbod|handel nu|haast je|eindigt binnenkort)\b/ui',
            ],
            'benefit' => [
                '/\b(?:verbeter|verhoog|versterk|verminder|bespaar|krijg|voordeel)\b/ui',
            ],
            'question' => [
                '/\b(?:wil je|ben je op zoek naar|heb je nodig|geïnteresseerd in)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:jij|je|jouw|uw)\b/ui',
            ],
        ],
        'pl' => [
            'action' => [
                '/\b(?:odkryj|naucz się|znajdź|zdobądź|pobierz|czytaj|przetestuj|spróbuj|zacznij|dołącz|zarejestruj się|kup|zamów|sklep|skontaktuj się|zadzwoń|odwiedź|eksploruj|zobacz)\b/ui',
            ],
            'imperative' => [
                '/\b(?:kliknij tutaj|dowiedz się więcej|czytaj więcej|poznaj więcej|zacznij teraz|zarejestruj się teraz|zarejestruj się dziś|zamów teraz|kup teraz|zadzwoń do nas|odwiedź nas|eksploruj więcej|wypróbuj teraz)\b/ui',
            ],
            'urgency' => [
                '/\b(?:dziś|dzisiaj|teraz|ograniczony czas|wyjątkowy|specjalny|za darmo|rabat|oszczędzaj|oferta|nie przegap|oferta ograniczona|działaj teraz|pośpiesz się|kończy się wkrótce)\b/ui',
            ],
            'benefit' => [
                '/\b(?:popraw|ulepsz|zwiększ|zmniejsz|oszczędź|zyskaj|korzyść)\b/ui',
            ],
            'question' => [
                '/\b(?:chcesz|szukasz|potrzebujesz|zainteresowany|zainteresowana)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:ty|twój|twoja|twoje|twoi|twoje)\b/ui',
            ],
        ],
        'pt' => [
            'action' => [
                '/\b(?:descubra|aprender|aprenda|encontre|obtenha|baixe|ler|leia|experimente|comece|junte-se|inscreva-se|compre|peça|pedir|loja|contate|entre em contato|ligue|visite|explore|veja)\b/ui',
            ],
            'imperative' => [
                '/\b(?:clique aqui|saiba mais|leia mais|descubra mais|comece agora|inscreva-se agora|registe-se hoje|peça agora|compre agora|ligue para nós|visite-nos|explore mais|experimente agora)\b/ui',
            ],
            'urgency' => [
                '/\b(?:hoje|agora|por tempo limitado|exclusivo|especial|grátis|desconto|economize|oferta|não perca|oferta limitada|aja agora|apresse-se|termina em breve)\b/ui',
            ],
            'benefit' => [
                '/\b(?:melhore|aprimore|impulsione|aumente|reduza|economize|ganhe|benefício)\b/ui',
            ],
            'question' => [
                '/\b(?:quer|procurando|precisa|interessado em|interessada em)\b.*\?/ui',
            ],
            'cta_person' => [
                '/\b(?:você|seu|sua|tu|teu|tua)\b/ui',
            ],
        ],
    ];

    /**
     * Performs the call-to-action validation for the given post-ID.
     * Fetches the meta-description and analyzes its CTA effectiveness.
     *
     * @return array|null The validation results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        $metaDescription = $this->contentProvider->getMetaDescription($postId);

        if (empty($metaDescription)) {
            return [
                'success' => false,
                'message' => __('No meta description found', 'beyondseo'),
                'has_cta' => false,
                'meta_description' => '',
                'cta_strength' => 0.0,
                'detected_cta_patterns' => [],
            ];
        }

        $ctaAnalysis = $this->analyzeCta($metaDescription);

        return [
            'success' => true,
            'message' => __('Meta description CTA analysis completed successfully', 'beyondseo'),
            'has_cta' => $ctaAnalysis['has_cta'],
            'meta_description' => $metaDescription,
            'cta_strength' => $ctaAnalysis['cta_strength'],
            'detected_cta_patterns' => $ctaAnalysis['detected_patterns'],
            'recommendations' => $this->getCtaRecommendations($ctaAnalysis['has_cta'], $ctaAnalysis['cta_strength']),
            // Diagnostic extras (non-breaking)
            'cta_locale' => $ctaAnalysis['cta_locale'],
            // 'detected_cta_categories' => $ctaAnalysis['detected_categories'],
        ];
    }

    /**
     * Calculates a score based on the presence and strength of a call to action in the meta-description.
     *
     * @return float A score from 0 to 1 based on CTA effectiveness
     */
    public function calculateScore(): float
    {
        return (float) ($this->value['cta_strength'] ?? 0.0);
    }

    /**
     * Provides suggestions for improving the call to action in the meta-description.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];

        if (empty($factorData['meta_description'])) {
            return $suggestions;
        }

        if (!($factorData['has_cta'] ?? false)) {
            $suggestions[] = Suggestion::META_DESCRIPTION_INTENT_NOT_SATISFIED;
        } elseif (($factorData['cta_strength'] ?? 0.0) <= 0.5) {
            $suggestions[] = Suggestion::META_DESCRIPTION_WEAK_CALL_TO_ACTION;
        }

        return $suggestions;
    }

    /**
     * Analyze CTA presence and strength using localized signals and weighted scoring.
     *
     * @param string $metaDescription
     * @return array{has_cta: bool, cta_strength: float, detected_patterns: array<int,string>, detected_categories: array<int,string>, cta_locale: string}
     */
    private function analyzeCta(string $metaDescription): array
    {
        $locale = $this->resolveCtaLanguage();
        [$score, $matched, $matchedCategories] = $this->computeCtaScoreLocalized($metaDescription, $locale);

        $hasCta = $score >= self::CTA_SCORE_THRESHOLD_HAS_CTA;

        return [
            'has_cta' => $hasCta,
            'cta_strength' => $score,
            'detected_patterns' => array_values(array_unique($matched)),
            'detected_categories' => array_values(array_unique($matchedCategories)),
            'cta_locale' => $locale,
        ];
    }

    /**
     * Resolve WP locale to supported CTA language key.
     */
    private function resolveCtaLanguage(): string
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
     * Weighted CTA scoring composed of category presences. Returns [score, matched patterns, matched categories].
     */
    private function computeCtaScoreLocalized(string $text, string $lang): array
    {
        $signals = self::CTA_PATTERNS[$lang] ?? self::CTA_PATTERNS['en'];

        // Weights per category (sum may exceed 1, we clamp later)
        $weights = [
            'imperative' => 0.35,
            'action' => 0.25,
            'urgency' => 0.20,
            'benefit' => 0.15,
            'question' => 0.10,
            'cta_person' => 0.05,
        ];

        $score = 0.0;
        $matched = [];
        $matchedCategories = [];

        // Normalize for case-insensitive Unicode matching (regex uses /ui, but we also keep original for output)
        $lc = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        foreach ($weights as $category => $w) {
            if (!isset($signals[$category])) {
                continue;
            }
            $found = false;
            foreach ($signals[$category] as $pattern) {
                if (preg_match($pattern, $lc, $m)) {
                    $matched[] = $m[0];
                    $found = true;
                    // For categories like action/imperative, a single match is enough to award weight.
                    // Avoid inflation by multiple matches in the same category.
                    break;
                }
            }
            if ($found) {
                $score += $w;
                $matchedCategories[] = $category;
            }
        }

        // Clamp score to [0,1]
        $score = min(1.0, max(0.0, $score));

        return [$score, $matched, $matchedCategories];
    }

    /**
     * Generates specific recommendations for improving the CTA in the meta-description.
     */
    private function getCtaRecommendations(bool $hasCta, float $ctaStrength): array
    {
        if (!$hasCta) {
            return [
                __('Add an action verb (like "discover", "learn", or "find out") to encourage user action', 'beyondseo'),
                __('Include a clear directive about what the user should do next', 'beyondseo'),
                __('Consider adding urgency elements like "today" or "now" if appropriate', 'beyondseo'),
            ];
        }

        if ($ctaStrength < 0.5) {
            return [
                __('Strengthen your CTA by making it more specific and compelling', 'beyondseo'),
                __('Consider adding a benefit that users will gain by clicking through', 'beyondseo'),
            ];
        }

        if ($ctaStrength < 0.8) {
            return [
                __('Your CTA is good, but could be improved by adding urgency or clarifying the benefit', 'beyondseo'),
            ];
        }

        return [__('Your call-to-action is strong and effective', 'beyondseo')];
    }
}