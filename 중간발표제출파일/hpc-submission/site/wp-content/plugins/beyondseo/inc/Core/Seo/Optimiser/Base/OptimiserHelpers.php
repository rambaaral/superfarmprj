<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use BackedEnum;

/**
 * Class OptimiserHelpers
 *
 * This class provides helper methods for extracting information from HTML content.
 * It includes methods to extract meta-tag content, title tag content, and link tag href.
 */
abstract class OptimiserHelpers
{
    /**
     * Helper method to extract meta-tag content
     *
     * @param string $html The HTML content
     * @param string $name The meta-tag name
     * @return string|null The content of the meta-tag or null if not found
     */
    public static function extractMetaTagContent(string $html, string $name): ?string
    {
        preg_match('/<meta name="' . preg_quote($name, '/') . '" content="([^"]+)"/i', $html, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Helper method to extract title tag content
     *
     * @param string $html The HTML content
     * @return string|null The content of the title tag or null if not found
     */
    public static function extractTitleTag(string $html): ?string
    {
        preg_match('/<title>(.*?)<\/title>/i', $html, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Helper method to extract link tag with specific rel attribute
     *
     * @param string $html The HTML content
     * @param string $rel The rel attribute value
     * @return string|null The href value of the link tag or null if not found
     */
    public static function extractLinkTagHref(string $html, string $rel): ?string
    {
        preg_match('/<link[^>]*rel=["\']' . preg_quote($rel, '/') . '["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Deduplicates an array of backed enum cases based on their value.
     *
     * @template T of BackedEnum
     * @param T[] $enumArray
     * @return T[]
     */
    public static function unique_enums(array $enumArray): array {
        if (empty($enumArray)) return [];

        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = get_class($enumArray[0]); // Dynamically get the enum class

        $values = array_map(static fn($enum) => $enum->value, $enumArray);
        $uniqueValues = array_unique($values);

        return array_map(static fn($val) => $enumClass::from($val), $uniqueValues);
    }
}
