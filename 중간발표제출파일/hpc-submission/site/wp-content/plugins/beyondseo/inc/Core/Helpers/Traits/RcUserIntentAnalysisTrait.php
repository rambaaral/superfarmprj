<?php
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Trait UserIntentAnalysisTrait
 *
 * This trait provides methods for analyzing user intent in content.
 */
trait RcUserIntentAnalysisTrait
{
    use RcLoggerTrait;

    /**
     * Analyze content for user intent satisfaction
     *
     * @param string $content Raw HTML content
     * @param string $cleanContent Clean text content
     * @param string $primaryKeyword Primary keyword
     * @param string $postType Post type
     * @return array User intent analysis results
     */
    public function analyzeUserIntentLocally(
        string $content,
        string $cleanContent,
        string $primaryKeyword,
        string $postType
    ): array {
        // Detect likely user intent based on keyword and post type
        $detectedIntent = $this->detectUserIntentBasedOnKeywordAndPostType($primaryKeyword, $postType);

        // Check for intent satisfaction markers
        $intentMarkers = $this->checkIntentSatisfactionMarkers($content, $cleanContent, $detectedIntent);

        // Calculate user intent satisfaction score
        $intentScore = $this->calculateIntentSatisfactionScore($intentMarkers, $detectedIntent);

        return [
            'detected_intent' => $detectedIntent,
            'intent_markers' => $intentMarkers,
            'intent_satisfaction_score' => $intentScore
        ];
    }

    /**
     * Detect likely user intent based on keyword and post type
     *
     * @param string $keyword Primary keyword
     * @param string $postType Post type
     * @return string Detected user intent
     */
    public function detectUserIntentBasedOnKeywordAndPostType(string $keyword, string $postType): string {
        $normalizedKeyword = strtolower(trim($keyword));

        // Intent scoring system - collect signals for each intent type
        $scores = [
            'informational' => 0,
            'transactional' => 0,
            'navigational' => 0,
            'commercial' => 0, // Adding commercial investigation as a distinct category
        ];

        // Informational intent patterns (more comprehensive)
        $informationalPatterns = [
            // Question-based patterns (strong signals)
            '/^(?:what|who|when|where|why|how|which|is|are|can|does|do|did|was|were|will|should|could|would|may|might)/i' => 3,
            '/(?:how to|what is|why do|how do|why is|what are|who is|where is|when is)/i' => 2.5,

            // Topic exploration patterns
            '/(?:guide|tutorial|learn|explanation|examples?|tips|advice|ideas|ways to|steps|strategy|benefits of)/i' => 2,
            '/(?:meaning|definition|concept|difference between|vs|versus|compare|comparison|review|history of)/i' => 2,
            '/(?:list of|top|best|facts about|overview|complete|ultimate|beginners?|introduction)/i' => 1.5,

            // Research-oriented keywords
            '/(?:research|study|analysis|statistics|data|report|survey|results|findings|theory|methodology)/i' => 2
        ];

        // Transactional intent patterns
        $transactionalPatterns = [
            // Direct purchase intent (strong signals)
            '/(?:buy|purchase|order|shop|get|subscribe|book|reserve|apply for|hire|rent|lease)/i' => 3,

            // Price-related patterns
            '/(?:price|cost|pricing|cheap|affordable|discount|deal|coupon|sale|free shipping|budget)/i' => 2.5,

            // Download/acquisition intent
            '/(?:download|free download|get free|sign up|register|join|activate|install)/i' => 2,

            // Provider-seeking patterns
            '/(?:service|provider|supplier|agency|company for|professional|near me|online)/i' => 1.5
        ];

        // Commercial investigation intent patterns (comparing options before transaction)
        $commercialPatterns = [
            // Comparison shopping
            '/(?:best|top|vs|versus|compared to|cheapest|review|rating|worth it|recommended)/i' => 3,

            // Product research
            '/(?:features|specs|specifications|comparison|alternatives|options|models|brands)/i' => 2.5,

            // Evaluation terms
            '/(?:pros and cons|advantages|disadvantages|benefits|drawbacks|problems with)/i' => 2,

            // Pre-purchase research
            '/(?:before buying|should i buy|should i get|is it worth|which to choose|choose|select)/i' => 2.5
        ];

        // Navigational intent patterns
        $navigationalPatterns = [
            // Brand/site-specific navigation (strong signals)
            '/(?:login|sign in|account|dashboard|website|official site|homepage)/i' => 3,

            // Location-finding patterns
            '/(?:directions to|location of|address|map|store locator|near me|hours)/i' => 2.5,

            // Specific page/function navigation
            '/(?:contact|support|help center|customer service|download page|careers)/i' => 2
        ];

        // Apply all pattern sets to the keyword
        foreach ($informationalPatterns as $pattern => $weight) {
            if (preg_match($pattern, $normalizedKeyword)) {
                $scores['informational'] += $weight;
            }
        }

        foreach ($transactionalPatterns as $pattern => $weight) {
            if (preg_match($pattern, $normalizedKeyword)) {
                $scores['transactional'] += $weight;
            }
        }

        foreach ($commercialPatterns as $pattern => $weight) {
            if (preg_match($pattern, $normalizedKeyword)) {
                $scores['commercial'] += $weight;
            }
        }

        foreach ($navigationalPatterns as $pattern => $weight) {
            if (preg_match($pattern, $normalizedKeyword)) {
                $scores['navigational'] += $weight;
            }
        }

        // Post-type influences intent probability
        switch ($postType) {
            case 'product':
                $scores['transactional'] += 2;
                $scores['commercial'] += 1;
                break;

            case 'page':
                // Static pages often serve navigational purposes
                $scores['navigational'] += 1;
                break;

            case 'post':
                // Blog posts typically serve informational intent
                $scores['informational'] += 1;
                break;

            case 'location':
            case 'store':
                $scores['navigational'] += 2;
                break;

            case 'review':
                $scores['commercial'] += 2;
                break;
        }

        // Domain-specific keywords often indicate navigational intent
        if (preg_match('/(?:facebook|twitter|instagram|linkedin|youtube|amazon|google|reddit)/i', $normalizedKeyword)) {
            $scores['navigational'] += 2;
        }

        // Words that typically indicate product searches
        $productTerms = ['iphone', 'samsung', 'tv', 'laptop', 'camera', 'shoes', 'dress', 'furniture',
            'car', 'bike', 'smartphone', 'monitor', 'headphones', 'watch', 'tablet'];

        foreach ($productTerms as $term) {
            if (str_contains($normalizedKeyword, $term)) {
                // Boost commercial and transactional intent for product keywords
                $scores['commercial'] += 1;
                $scores['transactional'] += 0.5;
            }
        }

        // Sort intents by score
        arsort($scores);

        // If the highest scoring intent is commercial and the keyword has "buy", prioritize transactional
        if (key($scores) === 'commercial' && str_contains($normalizedKeyword, 'buy')) {
            $scores['transactional'] = max($scores);
            arsort($scores);
        }

        // Determine primary intent (the highest score)
        $primaryIntent = key($scores);

        // Check if we have a very low-confidence classification or mixed intent
        $highestScore = reset($scores);
        //$runnerUpScore = next($scores);

        // If all scores are 0, default to informational
        if ($highestScore === 0) {
            return 'informational';
        }

        // Commercial intent is a subset of transactional for backward compatibility
        if ($primaryIntent === 'commercial') {
            return 'transactional';
        }

        return $primaryIntent;
    }

    /**
     * Check for intent satisfaction markers in content
     *
     * @param string $content Raw HTML content
     * @param string $cleanContent Clean text content
     * @param string $intent Detected user intent
     * @return array Intent satisfaction markers
     */
    public function checkIntentSatisfactionMarkers(string $content, string $cleanContent, string $intent): array {
        $markers = []; // Initialize empty markers array
        
        // Common content quality markers across all intents
        $markers['has_structured_content'] = $this->hasStructuredContent($content);
        $markers['has_multimedia'] = $this->hasMultimediaContent($content);
        $markers['has_semantic_markup'] = $this->hasSemanticMarkup($content);
        
        // Check markers specific to the detected intent
        switch ($intent) {
            case 'informational':
                // Core informational markers
                $markers['has_definition'] = preg_match('/is a|refers to|defined as|means|describes|represents|constitutes|signifies|denotes|stands for|indicates/', $cleanContent) > 0;
                $markers['has_examples'] = preg_match('/example|for instance|such as|e\.g\.|to illustrate|case in point|specifically|in particular|notably|for example|like/', $cleanContent) > 0;
                
                // Content structure markers
                $markers['has_step_by_step'] = 
                    preg_match('/<ol[^>]*>.*?<\/ol>/is', $content) > 0 ||
                    preg_match('/step \d|first|second|third|fourth|fifth|next|finally|lastly|initially|begin by|start with|follow with/', $cleanContent) > 0;
                
                $markers['has_faq'] = 
                    stripos($content, 'FAQ') !== false ||
                    preg_match('/<h[1-6][^>]*>.*?(?:frequently asked questions|common questions|questions and answers).*?<\/h[1-6]>/is', $content) > 0 ||
                    preg_match('/<div[^>]*(?:faq|accordion).*?>.*?<\/div>/is', $content) > 0;
                
                // Data presentation markers
                $markers['has_data_tables'] = preg_match('/<table[^>]*>.*?<\/table>/is', $content) > 0;
                $markers['has_statistics'] = preg_match('/\d+%|\d+\s*percent|statistics|data shows|research indicates|according to|study found|survey|poll results/', $cleanContent) > 0;
                
                // Educational content markers
                $markers['has_explanations'] = preg_match('/because|therefore|thus|hence|as a result|consequently|due to|since|explains why|reason for|cause of/', $cleanContent) > 0;
                $markers['has_comparisons'] = preg_match('/compared to|in contrast|on the other hand|whereas|while|unlike|similarly|likewise|however|although|despite/', $cleanContent) > 0;
                
                // Visual aids
                $markers['has_diagrams'] = preg_match('/<img[^>]*(?:diagram|chart|graph|infographic).*?>/is', $content) > 0;
                break;

            case 'transactional':
                // Core transactional markers
                $markers['has_pricing'] = 
                    preg_match('/\$\d+|\d+\s*(?:dollars|USD|EUR|GBP)|(?:price|cost|pricing|fee|charge|payment|subscription|plan)(?:\s+(?:is|of|at))?\s+\$?\d+/', $cleanContent) > 0;
                
                // Call to action markers - expanded to catch more variations
                $markers['has_call_to_action'] = 
                    preg_match('/<button[^>]*>.*?<\/button>/is', $content) > 0 ||
                    preg_match('/<a[^>]*(?:btn|button|cta).*?>.*?<\/a>/is', $content) > 0 ||
                    preg_match('/<a[^>]*>.*?(?:buy|shop|order|get|purchase|add to cart|checkout|subscribe|sign up|register|join now|start|try|download|book|reserve).*?<\/a>/is', $content) > 0;
                
                // Product information markers
                $markers['has_product_details'] = 
                    preg_match('/specifications|features|details|dimensions|weight|size|measurements|materials?|ingredients|components|technical specs/', $cleanContent) > 0;
                
                $markers['has_purchase_options'] = 
                    preg_match('/options|variations|models|packages|bundles|plans|tiers|editions|versions|colors|sizes|styles|configurations/', $cleanContent) > 0;
                
                // Trust signals
                $markers['has_trust_signals'] = 
                    preg_match('/guarantee|warranty|secure checkout|money back|return policy|free returns|satisfaction|trusted|certified|official|authorized/', $cleanContent) > 0;
                
                // Urgency and scarcity markers
                $markers['has_urgency'] = 
                    preg_match('/limited time|offer ends|sale ends|expires|only \d+ left|while supplies last|act now|don\'t miss|hurry|today only/', $cleanContent) > 0;
                
                // Shopping functionality
                $markers['has_shopping_cart'] = 
                    preg_match('/<(?:form|div|button|a)[^>]*(?:cart|checkout|basket).*?>/is', $content) > 0;
                break;

            case 'navigational':
                // Core navigational markers
                $markers['has_direct_links'] = 
                    preg_match('/<a[^>]*>.*?(?:official|website|login|sign in|portal|dashboard|account|homepage|main page).*?<\/a>/is', $content) > 0;
                
                // Contact information markers
                $markers['has_contact_info'] = 
                    preg_match('/contact|email|phone|call us|reach us|get in touch|support team|help desk|customer service/', $cleanContent) > 0 ||
                    preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', $cleanContent) > 0 || // Email pattern
                    preg_match('/\b(?:\+\d{1,3}[-\s]?)?\(?\d{3}\)?[-\s]?\d{3}[-\s]?\d{4}\b/', $cleanContent) > 0; // Phone pattern
                
                // Location markers
                $markers['has_location_details'] = 
                    preg_match('/address|location|map|directions|where to find|how to get to|visit us|our office|headquarters|branch|store location/', $cleanContent) > 0 ||
                    preg_match('/<iframe[^>]*(?:maps?\.google|maps?\.apple|openstreetmap).*?>/is', $content) > 0;
                
                // Navigation aids
                $markers['has_navigation_menu'] = 
                    preg_match('/<(?:nav|ul|ol|div)[^>]*(?:menu|navigation|navbar|nav-bar).*?>/is', $content) > 0;
                
                $markers['has_search_functionality'] = 
                    preg_match('/<(?:form|input|div)[^>]*(?:search|find).*?>/is', $content) > 0;
                
                // Hours and availability
                $markers['has_hours_info'] = 
                    preg_match('/hours|open from|available from|schedule|availability|opening times|business hours|working hours/', $cleanContent) > 0;
                break;

            case 'commercial':
                // Core commercial investigation markers
                $markers['has_comparison'] = 
                    preg_match('/compare|vs\.|versus|alternative|differences?|similarities|better than|worse than|compared to|in contrast to/', $cleanContent) > 0;
                
                // Review markers
                $markers['has_reviews'] = 
                    preg_match('/review|rating|stars?\b|score|feedback|testimonials?|opinions?|experiences?|what others say|customer reviews/', $cleanContent) > 0 ||
                    preg_match('/<(?:div|span)[^>]*(?:rating|stars|reviews).*?>/is', $content) > 0;
                
                // Evaluation markers
                $markers['has_pros_cons'] = 
                    preg_match('/pros?\b|cons?\b|advantages?|disadvantages?|benefits?|drawbacks?|strengths?|weaknesses?|positives?|negatives?|good points|bad points/', $cleanContent) > 0;
                
                // Recommendation markers
                $markers['has_recommendations'] = 
                    preg_match('/recommend|best|top|suggested|ideal for|perfect for|suited for|designed for|made for|great for|excellent for|suitable for/', $cleanContent) > 0;
                
                // Decision-making aids
                $markers['has_decision_aids'] = 
                    preg_match('/buying guide|comparison chart|decision matrix|feature comparison|side by side|head to head|face off|showdown/', $cleanContent) > 0;
                
                // Expert opinions
                $markers['has_expert_opinions'] = 
                    preg_match('/expert|specialist|professional opinion|according to|authority|industry leader|thought leader/', $cleanContent) > 0;
                
                // Value assessment
                $markers['has_value_assessment'] = 
                    preg_match('/value for money|worth the price|investment|cost-effective|budget-friendly|premium|luxury|affordable|expensive|overpriced|underpriced/', $cleanContent) > 0;
                break;

            default:
                break;
        }

        return $markers;
    }
    
    /**
     * Check if content has structured content elements
     *
     * @param string $content Raw HTML content
     * @return bool True if structured content is detected
     */
    private function hasStructuredContent(string $content): bool
    {
        // Check for headings and subheadings
        $hasHeadings = preg_match('/<h[1-6][^>]*>.*?<\/h[1-6]>/is', $content) > 0;
        
        // Check for lists
        $hasLists = preg_match('/<(?:ul|ol)[^>]*>.*?<\/(?:ul|ol)>/is', $content) > 0;
        
        // Check for paragraphs with reasonable length
        $hasParagraphs = preg_match('/<p[^>]*>.{40,}<\/p>/is', $content) > 0;
        
        // Check for sections or divs with semantic class names
        $hasSections = preg_match('/<(?:section|div)[^>]*(?:class|id)="[^"]*(?:section|container|wrapper|block)[^"]*"[^>]*>/i', $content) > 0;
        
        return $hasHeadings && ($hasLists || $hasParagraphs || $hasSections);
    }
    
    /**
     * Check if content has multimedia elements
     *
     * @param string $content Raw HTML content
     * @return bool True if multimedia content is detected
     */
    private function hasMultimediaContent(string $content): bool
    {
        // Check for images
        $hasImages = preg_match('/<img[^>]*src="[^"]+"[^>]*>/i', $content) > 0;
        
        // Check for videos
        $hasVideos = preg_match('/<(?:video|iframe)[^>]*>.*?<\/(?:video|iframe)>/is', $content) > 0 ||
                    preg_match('/<iframe[^>]*(?:youtube|vimeo|wistia|loom|vidyard)[^>]*>/i', $content) > 0;
        
        // Check for audio
        $hasAudio = preg_match('/<audio[^>]*>.*?<\/audio>/is', $content) > 0 ||
                   preg_match('/<iframe[^>]*(?:spotify|soundcloud|apple.com\/podcast)[^>]*>/is', $content) > 0;
        
        // Check for interactive elements
        $hasInteractive = preg_match('/<(?:canvas|svg|object|embed)[^>]*>.*?<\/(?:canvas|svg|object|embed)>/is', $content) > 0;
        
        return $hasImages || $hasVideos || $hasAudio || $hasInteractive;
    }
    
    /**
     * Check if content has semantic markup
     *
     * @param string $content Raw HTML content
     * @return bool True if semantic markup is detected
     */
    private function hasSemanticMarkup(string $content): bool
    {
        // Check for schema.org markup
        $hasSchemaOrg = preg_match('/itemscope|itemtype="https?:\/\/schema\.org/i', $content) > 0 ||
                       preg_match('/<script[^>]*type="application\/ld\+json"[^>]*>.*?<\/script>/is', $content) > 0;
        
        // Check for semantic HTML5 elements
        $hasSemanticHTML = preg_match('/<(?:article|section|nav|aside|header|footer|main|figure|figcaption|time|mark)[^>]*>/i', $content) > 0;
        
        // Check for ARIA attributes
        $hasARIA = preg_match('/aria-[a-z]+="[^"]*"/i', $content) > 0;
        
        // Check for Open Graph or Twitter Card meta tags
        $hasMetaTags = preg_match('/<meta[^>]*(?:og:|twitter:|property="og:|name="twitter:)[^>]*>/i', $content) > 0;
        
        return $hasSchemaOrg || $hasSemanticHTML || $hasARIA || $hasMetaTags;
    }

    /**
     * Calculate intent satisfaction score based on markers
     *
     * @param array $markers Intent satisfaction markers
     * @param string $intent Detected user intent
     * @return float Intent satisfaction score (0-1)
     */
    public function calculateIntentSatisfactionScore(array $markers, string $intent): float {
        if (empty($markers)) {
            return 0;
        }

        // Define marker weights - not all markers are equally important
        $markerWeights = $this->getMarkerWeights($intent);
        
        // Calculate weighted score
        $weightedScore = 0;
        $totalWeight = 0;
        
        foreach ($markers as $markerKey => $markerValue) {
            // Get the weight for this marker (default to 1 if not specified)
            $weight = $markerWeights[$markerKey] ?? 1;
            
            // Add to weighted score if marker is satisfied
            if ($markerValue) {
                $weightedScore += $weight;
            }
            
            // Add to total possible weight
            $totalWeight += $weight;
        }
        
        // Calculate base score as percentage of weighted markers satisfied
        $baseScore = $totalWeight > 0 ? $weightedScore / $totalWeight : 0;
        
        // Apply content quality multipliers
        $qualityMultiplier = $this->calculateQualityMultiplier($markers);
        $baseScore *= $qualityMultiplier;
        
        // Apply intent-specific adjustments
        switch ($intent) {
            case 'informational':
                // Critical markers for informational content
                if (isset($markers['has_definition']) && $markers['has_definition'] &&
                    isset($markers['has_examples']) && $markers['has_examples']) {
                    $baseScore = min(1.0, $baseScore * 1.15); // Boost by 15%
                }
                
                // Comprehensive informational content bonus
                if (isset($markers['has_definition']) && $markers['has_definition'] &&
                    isset($markers['has_examples']) && $markers['has_examples'] &&
                    isset($markers['has_step_by_step']) && $markers['has_step_by_step'] &&
                    isset($markers['has_explanations']) && $markers['has_explanations']) {
                    $baseScore = min(1.0, $baseScore * 1.1); // Additional 10% boost
                }
                break;

            case 'transactional':
                // Critical markers for transactional content
                if (isset($markers['has_call_to_action']) && $markers['has_call_to_action'] &&
                    isset($markers['has_pricing']) && $markers['has_pricing']) {
                    $baseScore = min(1.0, $baseScore * 1.15); // Boost by 15%
                }
                
                // Strongly penalize transactional content without CTAs
                if (isset($markers['has_call_to_action']) && !$markers['has_call_to_action']) {
                    $baseScore *= 0.6; // Reduce by 40% if missing CTA
                }
                
                // Complete purchase funnel bonus
                if (isset($markers['has_call_to_action']) && $markers['has_call_to_action'] &&
                    isset($markers['has_pricing']) && $markers['has_pricing'] &&
                    isset($markers['has_product_details']) && $markers['has_product_details'] &&
                    isset($markers['has_trust_signals']) && $markers['has_trust_signals']) {
                    $baseScore = min(1.0, $baseScore * 1.1); // Additional 10% boost
                }
                break;

            case 'navigational':
                // Critical markers for navigational content
                if (isset($markers['has_direct_links']) && $markers['has_direct_links']) {
                    $baseScore = min(1.0, $baseScore * 1.15); // Boost by 15%
                }
                
                // Strongly penalize navigational content without direct links
                if (isset($markers['has_direct_links']) && !$markers['has_direct_links']) {
                    $baseScore *= 0.5; // Reduce by 50% if missing direct links
                }
                
                // Complete navigation experience bonus
                if (isset($markers['has_direct_links']) && $markers['has_direct_links'] &&
                    isset($markers['has_navigation_menu']) && $markers['has_navigation_menu'] &&
                    isset($markers['has_search_functionality']) && $markers['has_search_functionality']) {
                    $baseScore = min(1.0, $baseScore * 1.1); // Additional 10% boost
                }
                break;
                
            case 'commercial':
                // Critical markers for commercial investigation content
                if (isset($markers['has_comparison']) && $markers['has_comparison'] &&
                    isset($markers['has_reviews']) && $markers['has_reviews']) {
                    $baseScore = min(1.0, $baseScore * 1.15); // Boost by 15%
                }
                
                // Strongly penalize commercial content without comparisons
                if (isset($markers['has_comparison']) && !$markers['has_comparison']) {
                    $baseScore *= 0.7; // Reduce by 30% if missing comparisons
                }
                
                // Complete decision-making content bonus
                if (isset($markers['has_comparison']) && $markers['has_comparison'] &&
                    isset($markers['has_reviews']) && $markers['has_reviews'] &&
                    isset($markers['has_pros_cons']) && $markers['has_pros_cons'] &&
                    isset($markers['has_recommendations']) && $markers['has_recommendations']) {
                    $baseScore = min(1.0, $baseScore * 1.1); // Additional 10% boost
                }
                break;
        }

        return max(0, min(1, $baseScore));
    }
    
    /**
     * Get marker weights based on intent type
     *
     * @param string $intent Detected user intent
     * @return array Associative array of marker weights
     */
    private function getMarkerWeights(string $intent): array
    {
        // Common content quality markers (apply to all intents)
        $weights = [
            'has_structured_content' => 1.5,
            'has_multimedia' => 1.2,
            'has_semantic_markup' => 1.0,
        ];
        
        // Add intent-specific weights
        return match ($intent) {
            'informational' => array_merge($weights, [
                'has_definition' => 2.5,
                'has_examples' => 2.0,
                'has_step_by_step' => 1.8,
                'has_faq' => 1.5,
                'has_data_tables' => 1.3,
                'has_statistics' => 1.5,
                'has_explanations' => 2.0,
                'has_comparisons' => 1.5,
                'has_diagrams' => 1.3,
            ]),
            'transactional' => array_merge($weights, [
                'has_pricing' => 2.5,
                'has_call_to_action' => 3.0,
                'has_product_details' => 2.0,
                'has_purchase_options' => 1.8,
                'has_trust_signals' => 1.5,
                'has_urgency' => 1.2,
                'has_shopping_cart' => 1.5,
            ]),
            'navigational' => array_merge($weights, [
                'has_direct_links' => 3.0,
                'has_contact_info' => 2.0,
                'has_location_details' => 2.0,
                'has_navigation_menu' => 1.8,
                'has_search_functionality' => 1.5,
                'has_hours_info' => 1.5,
            ]),
            'commercial' => array_merge($weights, [
                'has_comparison' => 2.5,
                'has_reviews' => 2.5,
                'has_pros_cons' => 2.0,
                'has_recommendations' => 2.0,
                'has_decision_aids' => 1.8,
                'has_expert_opinions' => 1.5,
                'has_value_assessment' => 1.5,
            ]),
            default => $weights,
        };
    }
    
    /**
     * Calculate quality multiplier based on content quality markers
     *
     * @param array $markers Intent satisfaction markers
     * @return float Quality multiplier (0.8-1.2)
     */
    private function calculateQualityMultiplier(array $markers): float
    {
        $multiplier = 1.0;
        
        // Boost for high-quality content with all quality markers
        if (isset($markers['has_structured_content']) && $markers['has_structured_content'] &&
            isset($markers['has_multimedia']) && $markers['has_multimedia'] &&
            isset($markers['has_semantic_markup']) && $markers['has_semantic_markup']) {
            $multiplier += 0.2; // 20% boost for high-quality content
        }
        // Partial boost for content with structured content and multimedia
        elseif (isset($markers['has_structured_content']) && $markers['has_structured_content'] &&
                isset($markers['has_multimedia']) && $markers['has_multimedia']) {
            $multiplier += 0.1; // 10% boost
        }
        // Slight boost for content with just structured content
        elseif (isset($markers['has_structured_content']) && $markers['has_structured_content']) {
            $multiplier += 0.05; // 5% boost
        }
        // Penalty for content with no quality markers
        elseif ((!isset($markers['has_multimedia']) || !$markers['has_multimedia']) &&
                (!isset($markers['has_semantic_markup']) || !$markers['has_semantic_markup'])) {
            $multiplier -= 0.2; // 20% penalty
        }
        
        return $multiplier;
    }

    /**
     * Gets target audience settings from post-meta or defaults if not set.
     * Attempts to infer settings from content type and categories when explicit settings are missing.
     *
     * @param int $postId The post-ID to get settings for
     * @return array Target audience settings
     */
    public function getTargetAudienceSettings(int $postId): array
    {
        // Try to get audience settings from post-meta
        $educationLevel = get_post_meta($postId, 'target_audience_education', true) ?: 'general';
        $industry = get_post_meta($postId, 'target_audience_industry', true) ?: 'general';
        $technicalProficiency = get_post_meta($postId, 'target_audience_technical', true) ?: 'medium';

        // If not set in post-meta, try to infer from the content type or category
        if ($educationLevel === 'general' || $industry === 'general') {
            // Get post-type to help infer audience
            $postType = get_post_type($postId);

            // Get the first category to help determine industry
            $firstCategory = $this->getFirstCategoryName($postId);

            // Infer education level from post-type if not explicitly set
            if ($educationLevel === 'general') {
                if ($postType === 'technical_guide' || $postType === 'whitepaper') {
                    $educationLevel = 'graduate';
                } elseif ($postType === 'tutorial' || $postType === 'study_guide') {
                    $educationLevel = 'college';
                } elseif ($postType === 'beginner_guide') {
                    $educationLevel = 'high_school';
                }
            }

            // Infer industry from a category if not explicitly set
            if ($industry === 'general' && !empty($firstCategory)) {
                $categoryToIndustryMap = [
                    'Health' => 'healthcare',
                    'Finance' => 'finance',
                    'Education' => 'education',
                    'Technology' => 'technology',
                    'Entertainment' => 'entertainment',
                    'Legal' => 'legal',
                    'E-commerce' => 'ecommerce',
                    'Travel' => 'travel',
                    'Food' => 'food',
                ];

                foreach ($categoryToIndustryMap as $category => $mappedIndustry) {
                    if (stripos($firstCategory, $category) !== false) {
                        $industry = $mappedIndustry;
                        break;
                    }
                }
            }
        }

        return [
            'education_level' => $educationLevel,
            'industry' => $industry,
            'technical_proficiency' => $technicalProficiency
        ];
    }

    /**
     * Analyzes the tone of content based on word choice and style patterns.
     * Detects formal, casual, technical, and other tone characteristics.
     *
     * @param string $content The content to analyze
     * @return array Tone analysis results
     */
    public function analyzeTone(string $content): array
    {
        $toneScores = [];
        $totalWords = str_word_count($content);
        $contentLower = strtolower($content);

        // Analyze tone based on indicator words
        foreach (SeoOptimiserConfig::AUDIENCE_TARGETED_TONE_INDICATORS as $tone => $indicators) {
            $occurrences = 0;

            foreach ($indicators as $indicator) {
                $count = substr_count($contentLower, strtolower($indicator));
                $occurrences += $count;
            }

            // Calculate a normalized score (out of 10)
            // Different tones will naturally have different densities of indicator words
            $normalizedScore = min(10, ($occurrences / max(1, $totalWords)) * 1000);

            $toneScores[$tone] = round($normalizedScore, 2);
        }

        // Determine dominant tones (the ones with the highest scores)
        arsort($toneScores);
        $dominantTones = array_slice($toneScores, 0, 3, true);

        // Additional tone characteristics
        $sentenceCount = count($this->splitIntoSentences($content));
        $avgSentenceLength = $sentenceCount > 0 ? $totalWords / $sentenceCount : 0;

        // Question ratio (percentage of sentences that are questions)
        $questionCount = substr_count($content, '?');
        $questionRatio = $sentenceCount > 0 ? ($questionCount / $sentenceCount) * 100 : 0;

        // Exclamation ratio (indicates enthusiasm or emphasis)
        $exclamationCount = substr_count($content, '!');
        $exclamationRatio = $sentenceCount > 0 ? ($exclamationCount / $sentenceCount) * 100 : 0;

        // First/second-person usage (indicates conversational/personal tone)
        $firstPersonCount = preg_match_all('/\b(?:I|we|our|us|myself|ourselves)\b/i', $content);
        $secondPersonCount = preg_match_all('/\b(?:you|your|yours|yourself|yourselves)\b/i', $content);

        $firstPersonRatio = $totalWords > 0 ? ($firstPersonCount / $totalWords) * 100 : 0;
        $secondPersonRatio = $totalWords > 0 ? ($secondPersonCount / $totalWords) * 100 : 0;

        return [
            'tone_scores' => $toneScores,
            'dominant_tones' => $dominantTones,
            'sentence_characteristics' => [
                'avg_sentence_length' => round($avgSentenceLength, 2),
                'question_ratio' => round($questionRatio, 2),
                'exclamation_ratio' => round($exclamationRatio, 2),
                'first_person_ratio' => round($firstPersonRatio, 2),
                'second_person_ratio' => round($secondPersonRatio, 2)
            ]
        ];
    }

    /**
     * Compares content readability and tone with target audience requirements.
     * Examines reading level, tone, and technical complexity against the
     * target audience's expected preferences.
     *
     * @param array $readabilityMetrics Content readability metrics
     * @param array $toneAnalysis Content tone analysis
     * @param array $targetAudience Target audience settings
     * @return array Audience match results
     */
    public function compareWithTargetAudience(array $readabilityMetrics, array $toneAnalysis, array $targetAudience): array
    {
        $educationLevel = $targetAudience['education_level'];
        $industry = $targetAudience['industry'];
        $technicalProficiency = $targetAudience['technical_proficiency'];

        // Match reading level to education level
        $readingLevelMatch = $this->matchReadingLevel($readabilityMetrics, $educationLevel);

        // Match tone to industry preferences
        $toneMatch = $this->matchTone($toneAnalysis, $industry);

        // Match technical vocabulary to technical proficiency
        $technicalMatch = $this->matchTechnicalLevel($readabilityMetrics, $toneAnalysis, $technicalProficiency);

        return [
            'reading_level_match' => $readingLevelMatch,
            'tone_match' => $toneMatch,
            'technical_match' => $technicalMatch
        ];
    }

    /**
     * Matches content reading level with the target education level.
     * Uses Flesch-Kincaid scores to evaluate if the content matches the reading
     * level expected for the target education level.
     *
     * @param array $readabilityMetrics Content readability metrics
     * @param string $educationLevel Target education level
     * @return array Reading level match results
     */
    public function matchReadingLevel(array $readabilityMetrics, string $educationLevel): array
    {
        // Get the target Flesch-Kincaid score range for the education level
        $targetRange = SeoOptimiserConfig::AUDIENCE_TARGETED_EDUCATION_LEVEL_TARGETS[$educationLevel] ?? SeoOptimiserConfig::AUDIENCE_TARGETED_EDUCATION_LEVEL_TARGETS['general'];

        // Get the actual Flesch-Kincaid score from the metrics
        $fleschScore = $readabilityMetrics['flesch_kincaid_score'] ?? 0;

        // Determine if content is too complex, too simple, or appropriate
        $tooComplex = $fleschScore < $targetRange['min'];
        $tooSimple = $fleschScore > $targetRange['max'];
        $ideal = $fleschScore >= $targetRange['min'] && $fleschScore <= $targetRange['max'];

        // Calculate match score (0-1)
        if ($ideal) {
            // If within the ideal range, calculate how close to the ideal midpoint
            $midpoint = $targetRange['ideal'];
            $distanceFromMidpoint = abs($fleschScore - $midpoint);
            $maxDistance = max($midpoint - $targetRange['min'], $targetRange['max'] - $midpoint);
            $matchScore = 1 - min(1, $distanceFromMidpoint / max(1, $maxDistance));
        } else {
            // If outside the ideal range, calculate how far outside
            $distance = $tooComplex ? $targetRange['min'] - $fleschScore : $fleschScore - $targetRange['max'];
            $maxOutsideDistance = 30; // Maximum distance to consider (arbitrary but reasonable)
            $matchScore = max(0, 1 - ($distance / $maxOutsideDistance));
        }

        return [
            'score' => $matchScore,
            'ideal' => $ideal,
            'too_complex' => $tooComplex,
            'too_simple' => $tooSimple,
            'actual_score' => $fleschScore,
            'target_range' => $targetRange
        ];
    }

    /**
     * Matches content tone with industry tone preferences.
     * Analyzes how well the detected tone matches the expected tone for the target industry.
     *
     * @param array $toneAnalysis Content tone analysis
     * @param string $industry Target industry
     * @return array Tone match results
     */
    public function matchTone(array $toneAnalysis, string $industry): array
    {
        // Get preferred tones for the industry
        $preferredTones = SeoOptimiserConfig::AUDIENCE_TARGETED_INDUSTRY_TONE_PREFERENCES[$industry] ?? SeoOptimiserConfig::AUDIENCE_TARGETED_INDUSTRY_TONE_PREFERENCES['general'];

        // Get the dominant tones from the analysis
        $dominantTones = array_keys($toneAnalysis['dominant_tones']);

        // Count how many of the preferred tones are among the dominant tones
        $matchingTones = array_intersect($preferredTones, $dominantTones);
        $matchCount = count($matchingTones);

        // Calculate match score (0-1)
        $matchScore = min(1, $matchCount / count($preferredTones));

        // Additional factors that can adjust the score
        $adjustments = 0;

        // Personal/conversational adjustment
        $sentenceCharacteristics = $toneAnalysis['sentence_characteristics'];
        $isConversational = ($sentenceCharacteristics['first_person_ratio'] > 0.5 ||
            $sentenceCharacteristics['second_person_ratio'] > 1);

        // For some industries, a conversational tone is preferred
        $preferConversational = in_array($industry, ['entertainment', 'ecommerce', 'travel', 'food']);

        if ($preferConversational && $isConversational) {
            $adjustments += 0.1;
        } elseif (!$preferConversational && $isConversational) {
            $adjustments -= 0.1;
        }

        // Question frequency adjustment
        $hasQuestions = $sentenceCharacteristics['question_ratio'] > 5;
        $preferQuestions = in_array($industry, ['education', 'entertainment']);

        if ($preferQuestions && $hasQuestions) {
            $adjustments += 0.1;
        } elseif (!$preferQuestions && $hasQuestions) {
            $adjustments -= 0.1;
        }

        // Final score with adjustments
        $finalScore = max(0, min(1, $matchScore + $adjustments));

        return [
            'score' => $finalScore,
            'matching_tones' => $matchingTones,
            'preferred_tones' => $preferredTones,
            'dominant_tones' => $dominantTones,
            'is_conversational' => $isConversational
        ];
    }

    /**
     * Matches content technical level with target technical proficiency.
     * Compares complex words percentage and technical tone scores with the expected
     * levels for the target audience's technical proficiency.
     *
     * @param array $readabilityMetrics Content readability metrics
     * @param array $toneAnalysis Content tone analysis
     * @param string $technicalProficiency Target technical proficiency
     * @return array Technical match results
     */
    public function matchTechnicalLevel(array $readabilityMetrics, array $toneAnalysis, string $technicalProficiency): array
    {
        switch ($technicalProficiency) {
            case 'beginner':
                $targetComplexWords = 5; // 5%
                $targetTechnicalTone = 2; // Low technical tone score
                break;
            case 'medium':
                $targetComplexWords = 10; // 10%
                $targetTechnicalTone = 5; // Medium technical tone score
                break;
            case 'expert':
                $targetComplexWords = 15; // 15%
                $targetTechnicalTone = 8; // High technical tone score
                break;
            default:
                $targetComplexWords = 8; // Default
                $targetTechnicalTone = 4; // Default
        }

        // Get actual values
        $actualComplexWords = $readabilityMetrics['complex_words_percentage'] ?? 0;
        $actualTechnicalTone = $toneAnalysis['tone_scores']['technical'] ?? 0;

        // Determine if content is too technical, not technical enough, or appropriate
        $tooTechnical = $actualComplexWords > ($targetComplexWords + 5) ||
            $actualTechnicalTone > ($targetTechnicalTone + 3);

        $notTechnicalEnough = $actualComplexWords < ($targetComplexWords - 3) &&
            $actualTechnicalTone < ($targetTechnicalTone - 2);

        $appropriate = !$tooTechnical && !$notTechnicalEnough;

        // Calculate complex words match score (0-1)
        $maxDeviation = 10; // Maximum percentage points deviation to consider
        $complexWordsDeviation = abs($actualComplexWords - $targetComplexWords);
        $complexWordsMatchScore = max(0, 1 - ($complexWordsDeviation / $maxDeviation));

        // Calculate technical tone match score (0-1)
        $maxToneDeviation = 5; // Maximum tone score deviation to consider
        $technicalToneDeviation = abs($actualTechnicalTone - $targetTechnicalTone);
        $technicalToneMatchScore = max(0, 1 - ($technicalToneDeviation / $maxToneDeviation));

        // Combined match score (weighted average)
        $combinedScore = ($complexWordsMatchScore * 0.6) + ($technicalToneMatchScore * 0.4);

        return [
            'score' => $combinedScore,
            'appropriate' => $appropriate,
            'too_technical' => $tooTechnical,
            'not_technical_enough' => $notTechnicalEnough,
            'complex_words' => [
                'actual' => $actualComplexWords,
                'target' => $targetComplexWords,
                'match_score' => $complexWordsMatchScore
            ],
            'technical_tone' => [
                'actual' => $actualTechnicalTone,
                'target' => $targetTechnicalTone,
                'match_score' => $technicalToneMatchScore
            ]
        ];
    }
}
