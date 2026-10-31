<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes;

if (!defined('ABSPATH')) { exit; }

use Attribute;

/**
 * Class SeoMeta attribute
 *
 * This attribute class represents a SEO Meta with its name and weight.
 * Can be used for any number of factors, operations or contexts
 */
#[
    Attribute(Attribute::TARGET_CLASS)
]
class SeoMeta
{
    /**
     * SeoMeta constructor.
     *
     * @param string $name The name of the SEO operation.
     * @param float $weight The weight of the SEO operation.
     * @param string|null $description A description of the SEO operation.
     */
    public function __construct(
        public string $name,
        public float $weight,
        public ?string $description = null,
    ) {
        // Constructor logic can be added here if needed
    }

    /**
     * Get translations for SEO operation names and descriptions.
     *
     * @return array An associative array of translations.
     */
    private static function getTranslations(): array
    {
        return [
            // Names
            "Alt Text Presence Check" => __("Alt Text Presence Check", "beyondseo"),
            "Alt Text To Images" => __("Alt Text To Images", "beyondseo"),
            "Analyze Backlink Profile" => __("Analyze Backlink Profile", "beyondseo"),
            "Assign Keywords" => __("Assign Keywords", "beyondseo"),
            "Audience Targeted Adjustments" => __("Audience Targeted Adjustments", "beyondseo"),
            "Broken Links Identification" => __("Broken Links Identification", "beyondseo"),
            "Canonical Tag Validation" => __("Canonical Tag Validation", "beyondseo"),
            "Content Depth Validation" => __("Content Depth Validation", "beyondseo"),
            "Content Formatting Validation" => __("Content Formatting Validation", "beyondseo"),
            "Content Length Validation" => __("Content Length Validation", "beyondseo"),
            "Content Optimisation" => __("Content Optimisation", "beyondseo"),
            "Content Quality And Length" => __("Content Quality And Length", "beyondseo"),
            "Content Readability" => __("Content Readability", "beyondseo"),
            "Content Update Suggestions" => __("Content Update Suggestions", "beyondseo"),
            "CrossDomain Canonical Check" => __("CrossDomain Canonical Check", "beyondseo"),
            "Description Keyword Overuse" => __("Description Keyword Overuse", "beyondseo"),
            "Descriptive Alt Text" => __("Descriptive Alt Text", "beyondseo"),
            "Duplicate Content Detection" => __("Duplicate Content Detection", "beyondseo"),
            "First Paragraph Keyword Check" => __("First Paragraph Keyword Check", "beyondseo"),
            "First Paragraph Keyword Stuffing" => __("First Paragraph Keyword Stuffing", "beyondseo"),
            "First Paragraph Keyword Usage" => __("First Paragraph Keyword Usage", "beyondseo"),
            "Fix Broken Links On Page" => __("Fix Broken Links On Page", "beyondseo"),
            "Fixing Header Consistency" => __("Fixing Header Consistency", "beyondseo"),
            "Google And Bing Indexation Check" => __("Google And Bing Indexation Check", "beyondseo"),
            "Header Hierarchy Check" => __("Header Hierarchy Check", "beyondseo"),
            "Header Tags Structure" => __("Header Tags Structure", "beyondseo"),
            "Hyphens Instead Of Underscores" => __("Hyphens Instead Of Underscores", "beyondseo"),
            "Image Compression Validation" => __("Image Compression Validation", "beyondseo"),
            "Image Optimization" => __("Image Optimization", "beyondseo"),
            "Keyword Competition Volume Check" => __("Keyword Competition Volume Check", "beyondseo"),
            "Keyword Density Validation" => __("Keyword Density Validation", "beyondseo"),
            "Keyword Distribution" => __("Keyword Distribution", "beyondseo"),
            "Keyword Mapping Content" => __("Keyword Mapping Content", "beyondseo"),
            "Keywords In Header Check" => __("Keywords In Header Check", "beyondseo"),
            "Linking Strategy" => __("Linking Strategy", "beyondseo"),
            "Local Keyword Meta Tag Optimization" => __("Local Keyword Meta Tag Optimization", "beyondseo"),
            "Local Keyword Presence" => __("Local Keyword Presence", "beyondseo"),
            "Local Keywords In Content" => __("Local Keywords In Content", "beyondseo"),
            "Local Schema Markup Suggestion" => __("Local Schema Markup Suggestion", "beyondseo"),
            "Local Schema Validation" => __("Local Schema Validation", "beyondseo"),
            "Meta Description Cta Validation" => __("Meta Description Cta Validation", "beyondseo"),
            "Meta Description Format Optimization" => __("Meta Description Format Optimization", "beyondseo"),
            "Meta Description Keywords" => __("Meta Description Keywords", "beyondseo"),
            "Meta Description Length Check" => __("Meta Description Length Check", "beyondseo"),
            "Meta Title Format Optimization" => __("Meta Title Format Optimization", "beyondseo"),
            "Meta Title Keywords" => __("Meta Title Keywords", "beyondseo"),
            "Meta Title Length Check" => __("Meta Title Length Check", "beyondseo"),
            "Meta Title Quality Analyzer" => __("Meta Title Quality Analyzer", "beyondseo"),
            "Multimedia Inclusion Check" => __("Multimedia Inclusion Check", "beyondseo"),
            "NextGen Image Format Validation" => __("NextGen Image Format Validation", "beyondseo"),
            "Opening Paragraph Engagement Analysis" => __("Opening Paragraph Engagement Analysis", "beyondseo"),
            "Optimize Page Speed" => __("Optimize Page Speed", "beyondseo"),
            "Optimize Url Structure" => __("Optimize Url Structure", "beyondseo"),
            "Page Content Keywords" => __("Page Content Keywords", "beyondseo"),
            "Performance And Speed" => __("Performance And Speed", "beyondseo"),
            "Primary Keyword Check" => __("Primary Keyword Check", "beyondseo"),
            "Primary Keyword In Alt Text" => __("Primary Keyword In Alt Text", "beyondseo"),
            "Primary Keyword In Url" => __("Primary Keyword In Url", "beyondseo"),
            "Primary Secondary Keyword Check" => __("Primary Secondary Keyword Check", "beyondseo"),
            "Primary Secondary Keywords Validation" => __("Primary Secondary Keywords Validation", "beyondseo"),
            "Readability Score Validation" => __("Readability Score Validation", "beyondseo"),
            "Readability Validation" => __("Readability Validation", "beyondseo"),
            "Referring Domains Analysis" => __("Referring Domains Analysis", "beyondseo"),
            "Referring Links Quality Assessment" => __("Referring Links Quality Assessment", "beyondseo"),
            "Related Keyword Inclusion" => __("Related Keyword Inclusion", "beyondseo"),
            "Responsive Image Sizing" => __("Responsive Image Sizing", "beyondseo"),
            "Robots Meta Tag Validation" => __("Robots Meta Tag Validation", "beyondseo"),
            "Robots Txt Validation" => __("Robots Txt Validation", "beyondseo"),
            "Safe Browsing Check" => __("Safe Browsing Check", "beyondseo"),
            "Schema Markup" => __("Schema Markup", "beyondseo"),
            "Schema Markup Validation" => __("Schema Markup Validation", "beyondseo"),
            "Schema Type Identification" => __("Schema Type Identification", "beyondseo"),
            "Search Engine Indexation" => __("Search Engine Indexation", "beyondseo"),
            "Secondary Keywords Check" => __("Secondary Keywords Check", "beyondseo"),
            "Technical Seo" => __("Technical Seo", "beyondseo"),
            "Url Length Check" => __("Url Length Check", "beyondseo"),
            "Url Readability" => __("Url Readability", "beyondseo"),
            "Use Canonical Tags" => __("Use Canonical Tags", "beyondseo"),

            // Descriptions
            "Validates if every image on a page has an alt text attribute for improved accessibility and SEO optimization." => __("Validates if every image on a page has an alt text attribute for improved accessibility and SEO optimization.", "beyondseo"),
            "Analyzes alt text presence, keyword integration, and descriptive clarity to assess image accessibility and enhance SEO performance." => __("Analyzes alt text presence, keyword integration, and descriptive clarity to assess image accessibility and enhance SEO performance.", "beyondseo"),
            "Evaluates referring domains, backlink quality, anchor text distribution, and domain authority to improve site credibility and search rankings." => __("Evaluates referring domains, backlink quality, anchor text distribution, and domain authority to improve site credibility and search rankings.", "beyondseo"),
            "Validates keyword selection, analyzes competition metrics, and prevents cannibalization across content." => __("Validates keyword selection, analyzes competition metrics, and prevents cannibalization across content.", "beyondseo"),
            "Analyzes content readability and tone to ensure it matches the target audience`s expectations. Provides recommendations for adjustments to improve audience targeting." => __("Analyzes content readability and tone to ensure it matches the target audience`s expectations. Provides recommendations for adjustments to improve audience targeting.", "beyondseo"),
            "Scans page content to extract and evaluate internal and external links. Checks link status via internal or external service, returning details about broken or redirecting URLs to maintain healthy backlinks." => __("Scans page content to extract and evaluate internal and external links. Checks link status via internal or external service, returning details about broken or redirecting URLs to maintain healthy backlinks.", "beyondseo"),
            "Verifies that canonical tags are present and correctly point to the preferred URL. Ensures proper formatting to avoid duplicate content issues and guides updates when canonical references are missing or incorrect." => __("Verifies that canonical tags are present and correctly point to the preferred URL. Ensures proper formatting to avoid duplicate content issues and guides updates when canonical references are missing or incorrect.", "beyondseo"),
            "Validates the depth and comprehensiveness of content by analyzing subtopic coverage, semantic richness, and user intent satisfaction. Provides suggestions for improving content quality based on analysis results." => __("Validates the depth and comprehensiveness of content by analyzing subtopic coverage, semantic richness, and user intent satisfaction. Provides suggestions for improving content quality based on analysis results.", "beyondseo"),
            "Validates content formatting for headings, paragraphs, and bullet points to ensure readability and SEO best practices." => __("Validates content formatting for headings, paragraphs, and bullet points to ensure readability and SEO best practices.", "beyondseo"),
            "Validates the content length of a post against predefined benchmarks. Analyzes word count, structure, and provides suggestions for optimization." => __("Validates the content length of a post against predefined benchmarks. Analyzes word count, structure, and provides suggestions for optimization.", "beyondseo"),
            "Analyzes and optimizes content for SEO by focusing on keyword usage, content quality, readability, and meta tags." => __("Analyzes and optimizes content for SEO by focusing on keyword usage, content quality, readability, and meta tags.", "beyondseo"),
            "Evaluates content length, depth, readability, and multimedia inclusion to enhance overall content quality and user engagement." => __("Evaluates content length, depth, readability, and multimedia inclusion to enhance overall content quality and user engagement.", "beyondseo"),
            "Checks how easy the content is to read, how it is structured, and whether it suits the intended audience to improve engagement and clarity." => __("Checks how easy the content is to read, how it is structured, and whether it suits the intended audience to improve engagement and clarity.", "beyondseo"),
            "Analyzes content age and relevance to suggest updates or pruning. Reviews industry trends and competitor changes, generating actionable recommendations for refreshing outdated sections or removing obsolete information to maintain quality." => __("Analyzes content age and relevance to suggest updates or pruning. Reviews industry trends and competitor changes, generating actionable recommendations for refreshing outdated sections or removing obsolete information to maintain quality.", "beyondseo"),
            "Ensures canonical tags referencing external domains are used correctly when content is syndicated. Validates host consistency and suggests adjustments so search engines attribute authority to the original source." => __("Ensures canonical tags referencing external domains are used correctly when content is syndicated. Validates host consistency and suggests adjustments so search engines attribute authority to the original source.", "beyondseo"),
            "Checks meta description for excessive repetition of target keywords. Calculates optimal ratio between primary and secondary terms, flagging when overuse occurs to prevent penalties from keyword stuffing." => __("Checks meta description for excessive repetition of target keywords. Calculates optimal ratio between primary and secondary terms, flagging when overuse occurs to prevent penalties from keyword stuffing.", "beyondseo"),
            "Analyzes the quality of image alt text on a page, ensuring it is descriptive and useful for SEO and accessibility. Flags poor alt text (e.g., filenames) as needing improvement." => __("Analyzes the quality of image alt text on a page, ensuring it is descriptive and useful for SEO and accessibility. Flags poor alt text (e.g., filenames) as needing improvement.", "beyondseo"),
            "Detects duplicate pages within the site and reviews canonical tags to ensure proper consolidation. Uses simulated API results to minimize load and caches findings, guiding resolution of content redundancy." => __("Detects duplicate pages within the site and reviews canonical tags to ensure proper consolidation. Uses simulated API results to minimize load and caches findings, guiding resolution of content redundancy.", "beyondseo"),
            "Checks if the primary keyword is used in the first paragraph of the content. This is important for SEO as it helps search engines understand the main topic of the page early on." => __("Checks if the primary keyword is used in the first paragraph of the content. This is important for SEO as it helps search engines understand the main topic of the page early on.", "beyondseo"),
            "Detects keyword stuffing in the first paragraph and computes language-agnostic distribution metrics for repetition and proximity. This is crucial for maintaining readability and avoiding penalties from search engines." => __("Detects keyword stuffing in the first paragraph and computes language-agnostic distribution metrics for repetition and proximity. This is crucial for maintaining readability and avoiding penalties from search engines.", "beyondseo"),
            "Evaluates keyword placement, density, and engagement in opening paragraphs to optimize topic relevance and reader connection." => __("Evaluates keyword placement, density, and engagement in opening paragraphs to optimize topic relevance and reader connection.", "beyondseo"),
            "Identifies broken internal and external links, prioritizing critical issues affecting user experience and SEO performance. If no links are found on the page, this check is marked as passed by default." => __("Identifies broken internal and external links, prioritizing critical issues affecting user experience and SEO performance. If no links are found on the page, this check is marked as passed by default.", "beyondseo"),
            "Evaluates header tag order and structure to ensure consistent hierarchy. Checks for single H1, avoiding jumps or improper resets, and provides suggestions to improve heading organization for better readability and SEO." => __("Evaluates header tag order and structure to ensure consistent hierarchy. Checks for single H1, avoiding jumps or improper resets, and provides suggestions to improve heading organization for better readability and SEO.", "beyondseo"),
            "Queries external APIs to confirm whether a page is indexed by Google and Bing. Reports indexation status and provides guidance on improving visibility if the URL is missing from search results." => __("Queries external APIs to confirm whether a page is indexed by Google and Bing. Reports indexation status and provides guidance on improving visibility if the URL is missing from search results.", "beyondseo"),
            "Analyzes page headings to verify proper hierarchical structure, single H1 usage, and no missing levels. Computes a score based on nesting consistency and heading quality, offering guidance to improve SEO-friendly headers." => __("Analyzes page headings to verify proper hierarchical structure, single H1 usage, and no missing levels. Computes a score based on nesting consistency and heading quality, offering guidance to improve SEO-friendly headers.", "beyondseo"),
            "Analyzes HTML heading tags (h1-h6) hierarchy, keyword usage, and structural consistency to enhance content organization and SEO effectiveness." => __("Analyzes HTML heading tags (h1-h6) hierarchy, keyword usage, and structural consistency to enhance content organization and SEO effectiveness.", "beyondseo"),
            "Examines page URLs to ensure words are separated with hyphens rather than underscores. Detects any underscores and offers guidance to standardize URL structure, improving readability and search engine recognition." => __("Examines page URLs to ensure words are separated with hyphens rather than underscores. Detects any underscores and offers guidance to standardize URL structure, improving readability and search engine recognition.", "beyondseo"),
            "Checks images for excessive file sizes, assessing compression efficiency. Calculates average size and identifies any files above a set threshold, advising compression improvements to speed up page load and enhance SEO." => __("Checks images for excessive file sizes, assessing compression efficiency. Calculates average size and identifies any files above a set threshold, advising compression improvements to speed up page load and enhance SEO.", "beyondseo"),
            "Evaluates how images are compressed, formatted, and scaled responsively to reduce load times and improve usability across devices. If no images are found on the page, this check is marked as passed by default." => __("Evaluates how images are compressed, formatted, and scaled responsively to reduce load times and improve usability across devices. If no images are found on the page, this check is marked as passed by default.", "beyondseo"),
            "Analyzes keyword competition, search volume, and CPC balance using external SEO API services. Provides insights on keyword difficulty and potential for content optimization." => __("Analyzes keyword competition, search volume, and CPC balance using external SEO API services. Provides insights on keyword difficulty and potential for content optimization.", "beyondseo"),
            "Calculates keyword density across the page to detect excessive or insufficient usage. Evaluates primary and secondary terms, suggesting adjustments to keep frequencies within recommended ranges for natural, SEO-friendly content." => __("Calculates keyword density across the page to detect excessive or insufficient usage. Evaluates primary and secondary terms, suggesting adjustments to keep frequencies within recommended ranges for natural, SEO-friendly content.", "beyondseo"),
            "Assesses how keywords are spread throughout page headings, paragraphs, and meta data. Identifies imbalances like concentration in specific sections and recommends a more even distribution for improved relevance." => __("Assesses how keywords are spread throughout page headings, paragraphs, and meta data. Identifies imbalances like concentration in specific sections and recommends a more even distribution for improved relevance.", "beyondseo"),
            "Performs keyword mapping analysis across site content, detecting cannibalization and coverage issues." => __("Performs keyword mapping analysis across site content, detecting cannibalization and coverage issues.", "beyondseo"),
            "Examines heading elements for primary and secondary keyword usage, measuring frequency and placement. Highlights overuse or absence of keywords in headers to improve relevancy and SEO performance." => __("Examines heading elements for primary and secondary keyword usage, measuring frequency and placement. Highlights overuse or absence of keywords in headers to improve relevancy and SEO performance.", "beyondseo"),
            "Analyzes and optimizes internal and external linking strategies to enhance site authority and user navigation." => __("Analyzes and optimizes internal and external linking strategies to enhance site authority and user navigation.", "beyondseo"),
            "Checks if local keywords appear in meta titles, descriptions, and image alt text to strengthen local SEO signals. Calculates coverage percentages and suggests improvements when location-specific phrases are missing." => __("Checks if local keywords appear in meta titles, descriptions, and image alt text to strengthen local SEO signals. Calculates coverage percentages and suggests improvements when location-specific phrases are missing.", "beyondseo"),
            "Analyzes titles, headings, and body text for location-specific keywords to evaluate local SEO strength. Measures keyword distribution across content elements and highlights gaps where local terms could be incorporated." => __("Analyzes titles, headings, and body text for location-specific keywords to evaluate local SEO strength. Measures keyword distribution across content elements and highlights gaps where local terms could be incorporated.", "beyondseo"),
            "Evaluates location keywords in content, meta tags, validates LocalBusiness schema, and suggests markup improvements for local SEO optimization." => __("Evaluates location keywords in content, meta tags, validates LocalBusiness schema, and suggests markup improvements for local SEO optimization.", "beyondseo"),
            "Examines content for local business indicators and existing structured data. Recommends appropriate schema markup when location signals are missing, helping search engines understand and display local information more effectively." => __("Examines content for local business indicators and existing structured data. Recommends appropriate schema markup when location signals are missing, helping search engines understand and display local information more effectively.", "beyondseo"),
            "Validates LocalBusiness schema markup for completeness and accuracy, ensuring required properties exist. Reviews structured data from page content, reporting any missing fields or incorrect types that could hinder local search visibility." => __("Validates LocalBusiness schema markup for completeness and accuracy, ensuring required properties exist. Reviews structured data from page content, reporting any missing fields or incorrect types that could hinder local search visibility.", "beyondseo"),
            "Evaluates meta descriptions for a clear and compelling call to action. Scans for common CTA phrases and measures their effectiveness, guiding improvements to boost click-through rates from search results." => __("Evaluates meta descriptions for a clear and compelling call to action. Scans for common CTA phrases and measures their effectiveness, guiding improvements to boost click-through rates from search results.", "beyondseo"),
            "Ensures optimal meta descriptions with proper length and compelling call-to-action to improve click-through rates from search results." => __("Ensures optimal meta descriptions with proper length and compelling call-to-action to improve click-through rates from search results.", "beyondseo"),
            "Analyzes keyword usage and positioning within meta descriptions to ensure relevance without oversaturation." => __("Analyzes keyword usage and positioning within meta descriptions to ensure relevance without oversaturation.", "beyondseo"),
            "Checks if meta descriptions fall within the optimal character range to avoid truncation in search results. Provides recommendations when descriptions are too short or lengthy, helping maximize snippet visibility and engagement." => __("Checks if meta descriptions fall within the optimal character range to avoid truncation in search results. Provides recommendations when descriptions are too short or lengthy, helping maximize snippet visibility and engagement.", "beyondseo"),
            "Evaluates meta title length and quality, ensuring optimal character count and structure for search engine visibility." => __("Evaluates meta title length and quality, ensuring optimal character count and structure for search engine visibility.", "beyondseo"),
            "Evaluates primary and secondary keyword placement in meta titles, prioritizing optimal positioning for maximum search visibility." => __("Evaluates primary and secondary keyword placement in meta titles, prioritizing optimal positioning for maximum search visibility.", "beyondseo"),
            "Validates meta title length to ensure optimal display in search results without truncation while containing sufficient information." => __("Validates meta title length to ensure optimal display in search results without truncation while containing sufficient information.", "beyondseo"),
            "Analyzes meta title quality including word count, structure, formatting, and clickbait detection for optimal SEO performance." => __("Analyzes meta title quality including word count, structure, formatting, and clickbait detection for optimal SEO performance.", "beyondseo"),
            "Analyzes multimedia elements (images, videos) in the content for proper optimization, including alt tags, captions, and keyword relevance. Provides suggestions for improving multimedia SEO." => __("Analyzes multimedia elements (images, videos) in the content for proper optimization, including alt tags, captions, and keyword relevance. Provides suggestions for improving multimedia SEO.", "beyondseo"),
            "Assesses whether page images use modern formats like WebP or AVIF instead of older JPEG or PNG. Reports proportion of legacy files and recommends converting them to enhance load speed and compatibility." => __("Assesses whether page images use modern formats like WebP or AVIF instead of older JPEG or PNG. Reports proportion of legacy files and recommends converting them to enhance load speed and compatibility.", "beyondseo"),
            "Analyzes the opening paragraph of a post for engagement effectiveness. It evaluates the hook strength, emotional appeal, curiosity elements, personal connection with readers, and how well the topic is introduced. This helps ensure that the opening paragraph captures reader interest and sets the stage for the content." => __("Analyzes the opening paragraph of a post for engagement effectiveness. It evaluates the hook strength, emotional appeal, curiosity elements, personal connection with readers, and how well the topic is introduced. This helps ensure that the opening paragraph captures reader interest and sets the stage for the content.", "beyondseo"),
            "Analyzes loading speed metrics and identifies performance bottlenecks to improve user experience and search rankings." => __("Analyzes loading speed metrics and identifies performance bottlenecks to improve user experience and search rankings.", "beyondseo"),
            "Analyzes page load speed using internal metrics and optional external APIs. Generates a performance score and identifies elements slowing the site down, suggesting caching or optimization techniques to achieve faster loading." => __("Analyzes page load speed using internal metrics and optional external APIs. Generates a performance score and identifies elements slowing the site down, suggesting caching or optimization techniques to achieve faster loading.", "beyondseo"),
            "Evaluates URLs for keyword inclusion, readability, proper length, and hyphen usage to improve search engine visibility." => __("Evaluates URLs for keyword inclusion, readability, proper length, and hyphen usage to improve search engine visibility.", "beyondseo"),
            "Evaluates keyword frequency, contextual relevance, placement, and content freshness to improve search visibility, and enhance overall user engagement." => __("Evaluates keyword frequency, contextual relevance, placement, and content freshness to improve search visibility, and enhance overall user engagement.", "beyondseo"),
            "Analyzes and optimizes website performance and speed to enhance user experience and search engine rankings." => __("Analyzes and optimizes website performance and speed to enhance user experience and search engine rankings.", "beyondseo"),
            "Checks if the primary keyword appears in the meta title and ensures it meets word count guidelines. Provides feedback on placement and plugin support to optimize title relevance and user engagement." => __("Checks if the primary keyword appears in the meta title and ensures it meets word count guidelines. Provides feedback on placement and plugin support to optimize title relevance and user engagement.", "beyondseo"),
            "Evaluates whether the primary keyword appears in the page URL and how close it is to the domain root. Suggests adjustments when keywords are absent or buried deep in the path." => __("Evaluates whether the primary keyword appears in the page URL and how close it is to the domain root. Suggests adjustments when keywords are absent or buried deep in the path.", "beyondseo"),
            "Analyzes the meta description to ensure both primary and secondary keywords are included naturally. Provides a ratio of keyword usage and points out missing terms to improve search relevance and keyword diversity." => __("Analyzes the meta description to ensure both primary and secondary keywords are included naturally. Provides a ratio of keyword usage and points out missing terms to improve search relevance and keyword diversity.", "beyondseo"),
            "Validates primary and secondary keywords for a post, ensuring they are effectively used in content, titles, headings, and meta descriptions. Analyzes keyword presence, density, and plugin support for SEO optimization." => __("Validates primary and secondary keywords for a post, ensuring they are effectively used in content, titles, headings, and meta descriptions. Analyzes keyword presence, density, and plugin support for SEO optimization.", "beyondseo"),
            "Analyzes content readability and provides suggestions for improvement based on multiple metrics." => __("Analyzes content readability and provides suggestions for improvement based on multiple metrics.", "beyondseo"),
            "Analyzes content readability using Flesch-Kincaid scores, sentence complexity, paragraph structure, passive voice usage, and transition word frequency. Provides detailed metrics and suggestions for improving content readability." => __("Analyzes content readability using Flesch-Kincaid scores, sentence complexity, paragraph structure, passive voice usage, and transition word frequency. Provides detailed metrics and suggestions for improving content readability.", "beyondseo"),
            "Analyzes the referring domains of a website to assess backlink quality, diversity, and relevance. Provides insights into the backlink profile including trust flow, citation flow, topical relevance, and geographic distribution." => __("Analyzes the referring domains of a website to assess backlink quality, diversity, and relevance. Provides insights into the backlink profile including trust flow, citation flow, topical relevance, and geographic distribution.", "beyondseo"),
            "Analyzes the quality of referring links to a page, assessing anchor text distribution, link attributes, and domain ratings" => __("Analyzes the quality of referring links to a page, assessing anchor text distribution, link attributes, and domain ratings", "beyondseo"),
            "Checks how supplementary keywords related to the main topic are integrated into the content. Evaluates their placement and frequency to enhance semantic context without overshadowing the primary keyword focus." => __("Checks how supplementary keywords related to the main topic are integrated into the content. Evaluates their placement and frequency to enhance semantic context without overshadowing the primary keyword focus.", "beyondseo"),
            "Validates usage of the srcset attribute so images load appropriate sizes on different devices. Identifies pages with too many non-responsive images and advises implementing responsive techniques to improve performance and mobile SEO." => __("Validates usage of the srcset attribute so images load appropriate sizes on different devices. Identifies pages with too many non-responsive images and advises implementing responsive techniques to improve performance and mobile SEO.", "beyondseo"),
            "Checks meta robots tags, HTTP headers, and robots.txt to verify that important pages are crawlable while restricting unwanted sections. Highlights misconfigured directives that could prevent indexing." => __("Checks meta robots tags, HTTP headers, and robots.txt to verify that important pages are crawlable while restricting unwanted sections. Highlights misconfigured directives that could prevent indexing.", "beyondseo"),
            "Fetches and analyzes the robots.txt file to confirm correct directives. Ensures important resources are accessible and harmful blocks are removed, providing tips to balance crawler control and SEO." => __("Fetches and analyzes the robots.txt file to confirm correct directives. Ensures important resources are accessible and harmful blocks are removed, providing tips to balance crawler control and SEO.", "beyondseo"),
            "Uses Google`s Safe Browsing API to determine whether a page is flagged for malware or phishing. Reports potential security issues and advises remediation to protect visitors and preserve search engine trust." => __("Uses Google`s Safe Browsing API to determine whether a page is flagged for malware or phishing. Reports potential security issues and advises remediation to protect visitors and preserve search engine trust.", "beyondseo"),
            "Analyzes structured data for proper schema usage and guideline adherence to improve indexing, rich results, and search performance." => __("Analyzes structured data for proper schema usage and guideline adherence to improve indexing, rich results, and search performance.", "beyondseo"),
            "Validates structured data against Google's guidelines to ensure markup is properly implemented. Checks schema types and required fields, reporting errors so search engines can display rich snippets accurately." => __("Validates structured data against Google's guidelines to ensure markup is properly implemented. Checks schema types and required fields, reporting errors so search engines can display rich snippets accurately.", "beyondseo"),
            "Analyzes page content to detect existing schema types and recommends appropriate markup when missing. Validates current structured data implementation to ensure compatibility with search engine standards." => __("Analyzes page content to detect existing schema types and recommends appropriate markup when missing. Validates current structured data implementation to ensure compatibility with search engine standards.", "beyondseo"),
            "Evaluates website indexability through search engine presence, robots.txt configuration, meta directives, and security status checks." => __("Evaluates website indexability through search engine presence, robots.txt configuration, meta directives, and security status checks.", "beyondseo"),
            "Reviews meta titles for the presence of recommended secondary keywords. Calculates how many appear and whether they exceed length guidelines, offering suggestions to balance keyword diversity without overcrowding the title." => __("Reviews meta titles for the presence of recommended secondary keywords. Calculates how many appear and whether they exceed length guidelines, offering suggestions to balance keyword diversity without overcrowding the title.", "beyondseo"),
            "Analyzes and optimizes technical aspects of SEO, including URL structure, canonical tags, schema markup, and search engine indexation." => __("Analyzes and optimizes technical aspects of SEO, including URL structure, canonical tags, schema markup, and search engine indexation.", "beyondseo"),
            "Measures the total length of the page URL and compares it to a recommended threshold. Alerts when URLs become excessively long, offering tips to shorten and simplify paths for better crawling." => __("Measures the total length of the page URL and compares it to a recommended threshold. Alerts when URLs become excessively long, offering tips to shorten and simplify paths for better crawling.", "beyondseo"),
            "Evaluates URL structure for clarity and simplicity, detecting excessive parameters, numeric strings, or confusing paths. Guides improvements to create short, readable URLs that clearly describe page content." => __("Evaluates URL structure for clarity and simplicity, detecting excessive parameters, numeric strings, or confusing paths. Guides improvements to create short, readable URLs that clearly describe page content.", "beyondseo"),
            "Evaluates canonical tag implementation, cross-domain references, and duplicate content detection to prevent search engine indexing issues." => __("Evaluates canonical tag implementation, cross-domain references, and duplicate content detection to prevent search engine indexing issues.", "beyondseo"),
        ];
    }

    /**
     * Get the localized name of the SEO operation.
     *
     * @return string The translated name
     */
    public function getLocalizedName(): string
    {
        $translations = self::getTranslations();
        return $translations[$this->name] ?? $this->name;
    }

    /**
     * Get the localized description of the SEO operation.
     *
     * @return string The translated description
     */
    public function getLocalizedDescription(): string
    {
        $translations = self::getTranslations();
        return $translations[$this->description] ?? $this->description ?? '';
    }

    /**
     * @param string|null $suffix
     * @return string
     */
    public function getKey(?string $suffix = null): string
    {
        return $this->convertToSnakeCase($this->name . ($suffix ? ' ' . $suffix : ''));
    }

    /**
     * Convert a string to snake_case
     * @param string $string
     * @return string
     */
    private function convertToSnakeCase(string $string): string
    {
        // Normalize hyphens and multiple spaces to a single space
        $normalized = preg_replace('/[\s\-.]+/', ' ', trim($string));

        // Split by space, lowercase each word
        $words = explode(' ', $normalized);
        $words = array_map('strtolower', $words);

        return implode('_', $words);
    }
}
