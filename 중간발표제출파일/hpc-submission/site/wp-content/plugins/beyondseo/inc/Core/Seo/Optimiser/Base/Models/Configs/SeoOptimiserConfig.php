<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs;

if (!defined('ABSPATH')) { exit; }

/**
 * Class SeoOptimiserConfig
 *
 * This class contains constants used for SEO optimization factors and operations.
 * These constants are used to define thresholds, limits, and other parameters
 * that are essential for the SEO optimization evaluation.
 *
 */
class SeoOptimiserConfig
{
    /** Minimum optimal keyword density percentage */
    public const OPTIMAL_DENSITY_MIN = 0.5;

    /** Maximum optimal keyword density percentage */
    public const OPTIMAL_DENSITY_MAX = 3.0;

    /** Threshold for severe keyword overuse */
    public const SEVERE_OVERUSE_THRESHOLD = 5.0;

    /** Threshold for keyword underuse */
    public const UNDERUSE_THRESHOLD = 0.1;

    /** Percentage similarity that indicates cannibalization */
    public const CANNIBALIZATION_THRESHOLD = 70;

    /** Minimum pages needed for a comprehensive analysis */
    public const MIN_PAGES_FOR_COMPLETE_ANALYSIS = 5;

    /** Threshold for determining natural distribution */
    public const NATURAL_DISTRIBUTION_THRESHOLD = 0.3;

    /** Threshold for determining semantic relevance */
    public const SEMANTIC_CONTEXT_THRESHOLD = 0.5;

    /** Thresholds for keyword difficulty levels in percentage */
    public const DIFFICULTY_THRESHOLDS = [
        'easy' => 30,
        'moderate' => 60
    ];

    /** Thresholds for keyword search volume (searches per month) */
    public const VOLUME_THRESHOLDS = [
        'low' => 500,
        'medium' => 5000
    ];

    /** Thresholds for cost-per-click values in USD */
    public const CPC_THRESHOLDS = [
        'low' => 1.0,
        'medium' => 5.0
    ];

    /** Thresholds for content length benchmarks in words */
    public const CONTENT_LENGTH_BENCHMARKS = [
        'post' => [
            'min' => 1500,  // Minimum recommended for blog posts
            'optimal' => 2500, // Optimal length for blog posts
            'max' => 4000   // Upper threshold for blog posts
        ],
        'page' => [
            'min' => 1000,  // Minimum recommended for pages
            'optimal' => 2000, // Optimal length for pages
            'max' => 3000   // Upper threshold for pages
        ],
        'product' => [
            'min' => 1000,  // Minimum recommended for product pages
            'optimal' => 1500, // Optimal length for product pages
            'max' => 2500   // Upper threshold for product pages
        ],
        'default' => [
            'min' => 600,   // Minimum recommended for any content
            'optimal' => 1500, // Optimal length for general content
            'max' => 3000   // Upper threshold for general content
        ]
    ];

    // Readability thresholds
    public const FLESCH_READING_EASE_THRESHOLDS = [
        'very_difficult' => 30,
        'difficult' => 50,
        'fairly_difficult' => 60,
        'standard' => 70,
        'fairly_easy' => 80,
        'easy' => 90
    ];

    // Grade level thresholds
    public const GRADE_LEVEL_THRESHOLDS = [
        'ideal_max' => 9,  // 9th grade level is generally considered good for most content
        'acceptable_max' => 12  // 12th grade is still acceptable but less ideal
    ];

    // Sentence and paragraph length thresholds
    public const SENTENCE_LENGTH_THRESHOLDS = [
        'ideal_max' => 20,  // 20 words is the ideal max for most sentences
        'acceptable_max' => 25,  // 25 words is still acceptable
        'too_long' => 30  // 30+ words are considered too long
    ];

    // Paragraph length thresholds
    public const PARAGRAPH_LENGTH_THRESHOLDS = [
        'ideal_max' => 3,  // 3 sentences is the ideal max for most paragraphs
        'acceptable_max' => 5,  // 5 sentences is still acceptable
        'too_long' => 6  // 6+ sentences are considered too long
    ];

    public const PARAGRAPH_WORD_COUNT_THRESHOLD = [
        'short' => 50,      // Short paragraphs: Less than or equal to 50 words
        'long' => 100,   // Average paragraphs: Between 51 and 100 words
    ];

    // Readability factors
    public const PASSIVE_VOICE_THRESHOLD = 10; // Percentage threshold for passive voice usage
    public const TRANSITION_WORDS_THRESHOLD = 30; // Percentage of sentences that should have transition words
    public const COMPLEX_WORDS_THRESHOLD = 10; // Percentage threshold for complex word usage

    // Media elements thresholds
    public const MIN_RECOMMENDED_MEDIA_COUNT = 2; // Minimum recommended media elements
    public const OPTIMAL_MEDIA_RATIO = 0.05; // Approximately 1 media per 300-500 words
    public const MEDIA_ALT_TAG_THRESHOLD = 0.8; // 80% of images should have alt tags

    // Media types to check - selectors for different media elements
    public const MEDIA_TYPES = [
        'image' => ['img'],
        'video' => ['video', 'iframe[src*="youtube"]', 'iframe[src*="vimeo"]', 'iframe[src*="wistia"]'],
        'audio' => ['audio', 'iframe[src*="spotify"]', 'iframe[src*="soundcloud"]'],
        'interactive' => ['iframe:not([src*="youtube"]):not([src*="vimeo"])', 'object', 'embed']
    ];

    // First paragraph keyword usage thresholds
    public const FIRST_PARAGRAPH_OPTIMAL_WORD_POSITION = 15;  // Ideal position within the first 15 words
    public const FIRST_PARAGRAPH_GOOD_WORD_POSITION = 30;     // Good position within the first 30 words
    public const FIRST_PARAGRAPH_ACCEPTABLE_WORD_POSITION = 50; // Acceptable position within the first 50 words
    public const FIRST_PARAGRAPH_MAXIMUM_ANALYZED_WORDS = 150;  // Maximum words to analyze in the first paragraph

    // Content areas to check about keyword, with their relative importance
    public const KEYWORD_CONTENT_AREAS = [
        'title' => ['weight' => 0.25, 'threshold' => 0.8],
        'headings' => ['weight' => 0.25, 'threshold' => 0.6],
        'first_paragraph' => ['weight' => 0.2, 'threshold' => 0.7],
        'body' => ['weight' => 0.15, 'threshold' => 0.5],
        'meta_description' => ['weight' => 0.15, 'threshold' => 0.7]
    ];

    // Required properties for LocalBusiness schema
    public const SCHEMA_REQUIRED_PROPERTIES = [
        'name',
        'address',
        'telephone',
        'openingHours',
        'geo',
        'priceRange'
    ];

    // Recommended properties for better local SEO
    public const SCHEMA_RECOMMENDED_PROPERTIES = [
        'description',
        'image',
        'url',
        'sameAs',
        'review',
        'aggregateRating',
        'hasMap'
    ];

    // Centralized definition for other types
    public const SCHEMA_REQUIRED_PROPERTIES_BY_TYPE = [
        'Article' => ['headline', 'author', 'datePublished', 'publisher'],
        'BlogPosting' => ['headline', 'author', 'datePublished', 'publisher'],
        'NewsArticle' => ['headline', 'author', 'datePublished', 'publisher'],
        'Product' => ['name', 'offers'],
        // 'LocalBusiness' => self::SCHEMA_REQUIRED_PROPERTIES,
        'FAQPage' => ['mainEntity'],
        'Person' => ['name'],
        'Organization' => ['name', 'url'],
        'BreadcrumbList' => ['itemListElement'],
        'VideoObject' => ['name', 'description', 'thumbnailUrl', 'uploadDate'],
        'Recipe' => ['name', 'author', 'recipeIngredient', 'recipeInstructions'],
        'Event' => ['name', 'startDate', 'location'],
        'Review' => ['itemReviewed', 'reviewRating', 'author'],
        'HowTo' => ['name', 'step']
    ];

    public const SCHEMA_RECOMMENDED_PROPERTIES_BY_TYPE = [
        'Article' => ['image', 'dateModified', 'mainEntityOfPage'],
        'BlogPosting' => ['image', 'dateModified', 'mainEntityOfPage'],
        'NewsArticle' => ['image', 'dateModified', 'mainEntityOfPage'],
        'Product' => ['image', 'description', 'brand', 'aggregateRating', 'review'],
        'Organization' => ['logo', 'contactPoint', 'sameAs', 'address'],
        'Person' => ['image', 'jobTitle', 'worksFor', 'sameAs'],
        'Event' => ['image', 'description', 'endDate', 'offers', 'performer'],
        'HowTo' => ['image', 'description', 'totalTime', 'supply', 'tool'],
        'VideoObject' => ['contentUrl', 'embedUrl', 'duration', 'interactionCount']
    ];

    public const LOCAL_BUSINESS_SUBTYPES = [
        'AnimalShelter', 'AutomotiveBusiness', 'ChildCare', 'Dentist',
        'DryCleaningOrLaundry', 'EmergencyService', 'EmploymentAgency',
        'EntertainmentBusiness', 'FinancialService', 'FoodEstablishment',
        'GovernmentOffice', 'HealthAndBeautyBusiness', 'HomeAndConstructionBusiness',
        'InternetCafe', 'LegalService', 'Library', 'LodgingBusiness',
        'MedicalBusiness', 'ProfessionalService', 'RadioStation',
        'RealEstateAgent', 'RecyclingCenter', 'SelfStorage', 'ShoppingCenter',
        'SportsActivityLocation', 'Store', 'TelevisionStation',
        'TouristInformationCenter', 'TravelAgency', 'Restaurant',
        'Cafe', 'Bar', 'Hotel', 'Motel', 'Resort'
    ];

    public const BUSINESS_TYPE_KEYWORDS = [
        'Restaurant' => ['restaurant', 'cafe', 'dining', 'eatery', 'food', 'bistro', 'menu', 'lunch', 'dinner', 'breakfast'],
        'Hotel' => ['hotel', 'motel', 'inn', 'lodge', 'lodging', 'accommodation', 'stay', 'room', 'booking'],
        'Store' => ['store', 'shop', 'retail', 'market', 'mart', 'boutique', 'buy', 'purchase'],
        'MedicalBusiness' => ['medical', 'doctor', 'physician', 'clinic', 'hospital', 'healthcare', 'health', 'patient'],
        'ProfessionalService' => ['lawyer', 'attorney', 'accountant', 'consultant', 'professional', 'service', 'advisor'],
        'AutomotiveBusiness' => ['auto', 'car', 'vehicle', 'repair', 'mechanic', 'automotive', 'garage', 'dealership'],
        'FoodEstablishment' => ['food', 'restaurant', 'cafe', 'bar', 'pub', 'diner', 'cuisine', 'menu'],
        'HealthAndBeautyBusiness' => ['salon', 'spa', 'beauty', 'hair', 'nail', 'barber', 'stylist', 'massage'],
        'HomeAndConstructionBusiness' => ['contractor', 'construction', 'builder', 'remodel', 'renovation', 'home improvement'],
        'RealEstateAgent' => ['real estate', 'realtor', 'property', 'home', 'apartment', 'house', 'listing']
    ];


    // Expanded list of common local/business-related CPT slugs (generic, plural, and popular plugin CPTs)
    public const LOCAL_POST_TYPES = [
        // Existing
        'location','store','branch','local','office',
        // Plurals
        'locations','stores','branches','offices',
        // Generic place/business nouns
        'place','places','shop','shops','showroom','showrooms','venue','venues',
        'dealer','dealership','clinic','clinics','hospital','hospitals','pharmacy','pharmacies',
        'practice','practices','studio','studios','gym','gyms','salon','salons','spa','spas',
        'restaurant','restaurants','cafe','cafes','bar','bars','pub','pubs',
        'hotel','hotels','motel','motels','lodging','lodgings','center','centre','service_center',
        // Plugin ecosystem CPTs
        'wpseo_locations','wpsl_stores','tribe_venue','gd_place','gd_location',
    ];
    // Expanded local page slug patterns covering common contact, location, store, hours, and appointment pages
    public const LOCAL_PAGE_PATTERNS = [
        // Contact/About
        '/contact/i',
        '/contact-us/i',
        '/about/i',
        '/about-us/i',
        // Location/Store
        '/location/i',
        '/locations/i',
        '/our-location/i',
        '/our-locations/i',
        '/store/i',
        '/stores/i',
        '/store-?locator/i',
        // Find/Visit/Directions/Map
        '/find/i',
        '/find-us/i',
        '/find-?a-?store/i',
        '/where-?to-?buy/i',
        '/visit/i',
        '/visit-us/i',
        '/directions/i',
        '/map/i',
        '/parking/i',
        // Hours
        '/hours/i',
        '/opening-?hours/i',
        '/business-?hours/i',
        // Facilities/Branches
        '/office/i',
        '/branch/i',
        '/branches/i',
        '/showroom/i',
        '/showrooms/i',
        // Appointments/Reservations
        '/appointment/i',
        '/appointments/i',
        '/book-?appointment/i',
        '/schedule-?appointment/i',
        '/reservation/i',
        '/reserve-?table/i',
        // Local intent helpers
        '/near-?me/i',
        '/store-?pickup/i',
    ];
    // Robust street address detection: number + street name + type, optional direction, unit, and optional city/state/ZIP
    // Examples matched: 123 N Main St, 55 Broadway Ave., 742 Evergreen Terrace Apt 2B, 1600 Pennsylvania Ave NW, 10 Downing St
    public const STREET_ADDRESS_PATTERN = '/\b\d{1,6}\s*(?:N|S|E|W|NE|NW|SE|SW)?\.?\s*(?:[A-Za-z0-9]+(?:[\'\-][A-Za-z0-9]+)?(?:\s+[A-Za-z0-9]+(?:[\'\-][A-Za-z0-9]+)?){0,4})\s+(?:Street|St\.?|Avenue|Ave\.?|Road|Rd\.?|Boulevard|Blvd\.?|Drive|Dr\.?|Lane|Ln\.?|Court|Ct\.?|Circle|Cir\.?|Place|Pl\.?|Terrace|Ter\.?|Way|Parkway|Pkwy|Square|Sq\.?|Trail|Trl\.?|Highway|Hwy|Route|Rte\.?|Crescent|Cres\.?|Close|Cl\.?|Grove|Grv\.?|Alley|Aly|Mews|Row|Gardens|Gdns\.?)\s*(?:N|S|E|W|NE|NW|SE|SW)?\.?\s*(?:,?-?\s*(?:Apt|Apartment|Unit|Suite|Ste|Floor|Fl|Bldg|Building|#)\s*[\w\-]+)?(?:\s*,\s*[A-Za-z .\-]{2,}(?:\s*,\s*[A-Z]{2})?\s*\d{5}(?:-\d{4})?)?\b/i';

    // Thresholds for paragraph length (in words)
    public const CONTENT_MAX_OPTIMAL_PARAGRAPH_LENGTH = 75;
    public const CONTENT_MAX_ACCEPTABLE_PARAGRAPH_LENGTH = 120;

    // Thresholds for heading density
    public const CONTENT_MIN_RECOMMENDED_HEADINGS_PER_1000_WORDS = 3;
    public const CONTENT_MAX_RECOMMENDED_HEADINGS_PER_1000_WORDS = 10;

    // Minimum recommended number of bullet points for articles > 1000 words
    public const CONTENT_MIN_RECOMMENDED_BULLET_POINTS = 1;

    // Maximum recommended length for headings (in characters)
    public const CONTENT_MAX_OPTIMAL_HEADING_LENGTH = 70;

    // List recommendation keywords - when these words appear, lists might be useful
    public const LIST_RECOMMENDATION_KEYWORDS_PATTERNS = [
        '/steps to/i',
        '/ways to/i',
        '/tips for/i',
        '/benefits of/i',
        '/advantages of/i',
        '/examples of/i',
        '/types of/i',
        '/methods for/i',
        '/strategies for/i',
        '/reasons why/i',
    ];

    // Education level mappings to target Flesch-Kincaid scores
    public const AUDIENCE_TARGETED_EDUCATION_LEVEL_TARGETS = [
        'elementary' => ['min' => 80, 'max' => 100, 'ideal' => 90],
        'middle_school' => ['min' => 70, 'max' => 90, 'ideal' => 80],
        'high_school' => ['min' => 60, 'max' => 80, 'ideal' => 70],
        'college' => ['min' => 50, 'max' => 70, 'ideal' => 60],
        'graduate' => ['min' => 30, 'max' => 60, 'ideal' => 50],
        'technical' => ['min' => 40, 'max' => 70, 'ideal' => 50],
        'general' => ['min' => 60, 'max' => 80, 'ideal' => 70]
    ];

    // Industry-specific tone preferences
    public const AUDIENCE_TARGETED_INDUSTRY_TONE_PREFERENCES = [
        'healthcare' => ['professional', 'empathetic', 'clear'],
        'finance' => ['professional', 'authoritative', 'precise'],
        'education' => ['clear', 'supportive', 'informative'],
        'technology' => ['technical', 'innovative', 'precise'],
        'entertainment' => ['casual', 'engaging', 'conversational'],
        'legal' => ['formal', 'precise', 'authoritative'],
        'ecommerce' => ['persuasive', 'direct', 'engaging'],
        'travel' => ['descriptive', 'enthusiastic', 'engaging'],
        'food' => ['descriptive', 'sensory', 'enthusiastic'],
        'general' => ['balanced', 'clear', 'engaging']
    ];

    // Tone indicators - words that indicate different writing tones
    public const AUDIENCE_TARGETED_TONE_INDICATORS = [
        'formal' => ['furthermore', 'consequently', 'nevertheless', 'thus', 'hence', 'subsequently', 'therefore', 'accordingly', 'indeed', 'whereas'],
        'casual' => ['anyway', 'basically', 'actually', 'honestly', 'literally', 'like', 'pretty', 'really', 'totally', 'kinda', 'sort of', 'you know'],
        'technical' => ['algorithm', 'functionality', 'interface', 'implementation', 'protocol', 'schema', 'syntax', 'module', 'component', 'parameter'],
        'authoritative' => ['definitively', 'unquestionably', 'undoubtedly', 'certainly', 'conclusively', 'absolutely', 'evidently', 'demonstrably', 'decidedly', 'unequivocally'],
        'persuasive' => ['importantly', 'crucially', 'notably', 'significantly', 'remarkably', 'essentially', 'undeniably', 'unquestionably', 'surely', 'necessarily'],
        'empathetic' => ['understand', 'feel', 'experience', 'concern', 'support', 'help', 'struggle', 'challenge', 'journey', 'together'],
        'engaging' => ['discover', 'imagine', 'explore', 'consider', 'picture', 'visualize', 'wonder', 'experience', 'journey', 'adventure'],
        'descriptive' => ['vibrant', 'stunning', 'extraordinary', 'exquisite', 'magnificent', 'elegant', 'breathtaking', 'spectacular', 'gorgeous', 'impressive'],
        'informative' => ['research', 'studies', 'evidence', 'data', 'findings', 'results', 'demonstrate', 'indicate', 'suggest', 'reveal'],
        'clear' => ['simply', 'clearly', 'specifically', 'precisely', 'exactly', 'directly', 'obviously', 'plainly', 'evidently', 'explicitly'],
        'precise' => ['specifically', 'precisely', 'exactly', 'particularly', 'distinctly', 'explicitly', 'definitely', 'unambiguously', 'clearly', 'accurately']
    ];

    // Meta-title length thresholds
    public const META_TITLE_MIN_OPTIMAL_LENGTH = 50;
    public const META_TITLE_MAX_OPTIMAL_LENGTH = 60;

    // Meta-title length limits
    public const META_TITLE_MIN_ACCEPTABLE_LENGTH = 30;
    public const META_TITLE_MAX_ACCEPTABLE_LENGTH = 70;

    // Define optimal meta description length range
    public const META_DESCRIPTION_MIN_OPTIMAL_LENGTH = 150;
    public const META_DESCRIPTION_MAX_OPTIMAL_LENGTH = 160;

    // Define an acceptable meta-description length range (slightly wider than optimal)
    public const META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH = 120;
    public const META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH = 170;

    // Patterns that indicate a call to action
    public const META_DESCRIPTION_CTA_PATTERNS = [
        // Action verbs
        '/\b(discover|learn|find|get|download|read|try|start|join|sign up|register|buy|order|shop|contact|call|visit|explore|check out|see|view)\b/i',

        // Imperative phrases
        '/\b(click here|learn more|read more|find out more|contact us|get started|sign up now|register today|order now|shop now|call us|visit us|explore more)\b/i',

        // Urgency indicators
        '/\b(today|now|limited time|exclusive|special|free|discount|save|offer|don\'t miss|limited offer|act now|hurry)\b/i',

        // Question CTAs (engaging questions)
        '/\b(want to|looking for|need|interested in)\b.*\?/i',

        // Benefit-driven CTAs
        '/\b(improve|enhance|boost|increase|reduce|save|gain|benefit)\b/i'
    ];

    // Maximum recommended URL length for optimal readability
    public const MAX_RECOMMENDED_URL_LENGTH = 60;

    // Maximum acceptable URL length before severe penalties
    public const MAX_ACCEPTABLE_URL_LENGTH = 100;

    // Maximum recommended number of slug segments (words)
    public const MAX_RECOMMENDED_SLUG_SEGMENTS = 4;

    // Similarity threshold for considering content as duplicate
    public const SIMILARITY_THRESHOLD = 0.7; // 70% similarity
    
    // Image size thresholds in bytes
    public const OPTIMAL_IMAGE_SIZE_THRESHOLD = 204800; // 200KB
    public const ACCEPTABLE_IMAGE_SIZE_THRESHOLD = 512000; // 500KB
    public const CRITICAL_IMAGE_SIZE_THRESHOLD = 1048576; // 1MB

    // Legacy image formats that should be converted to next-gen formats
    public const IMAGE_LEGACY_FORMATS = ['jpeg', 'jpg', 'png', 'gif'];

    // Next-gen image formats that provide better compression and quality
    public const NEXT_GEN_IMAGE_FORMATS = ['webp', 'avif'];

    // Thresholds for keyword occurrence in alt text
    public const MIN_IMAGES_TO_ANALYZE_KEYWORD_IN_ALT = 3; // Minimum number of images to analyze for keyword presence in alt text
    public const MAX_KEYWORD_OCCURRENCES_IN_ALT = 2; // Maximum keyword occurrences in a single alt text
    public const MIN_PERCENTAGE_IMAGES_WITH_KEYWORD_IN_ALT = 30; // At least 30% of images should have the keyword
    public const MAX_PERCENTAGE_IMAGES_WITH_KEYWORD_IN_ALT = 80; // No more than 80% should have the keyword (avoid overuse)
}
