<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Classes\Images\SocialImageSource;
use RankingCoach\Inc\Core\Classes\Images\SocialImageSourceCollection;

/**
 * Class SocialMediaHelper
 * Helper class for social media related functionality
 */
class SocialMediaHelper
{
    /**
     * Meta-key for storing the selected social image source
     */
    private const SELECTED_IMAGE_SOURCE_META_KEY = BaseConstants::OPTION_SELECTED_SOCIAL_IMAGE_SOURCE;
    
    /**
     * Retrieves the social image sources of a post.
     *
     * @param int $postId
     * @return array The social image sources as an array.
     */
    public static function getSocialImageSources(int $postId): array
    {
        return self::getSocialImageSourceCollection($postId)->toArray();
    }
    
    /**
     * Saves the selected social image source for a post.
     * Only the source identifier is saved, the URL is determined dynamically when needed.
     *
     * @param int $postId The post-ID
     * @param string $sourceIdentifier The source identifier to save
     * @return bool True if the source was saved successfully, false otherwise
     */
    public static function saveSelectedSocialImageSource(int $postId, string $sourceIdentifier): bool
    {
        // Validate that the source identifier exists in the available sources
        $collection = self::getSocialImageSourceCollection($postId);
        $validSource = false;

        /** @var SocialImageSource $source */
        foreach ($collection as $source) {
            if ($source->getSource() === $sourceIdentifier) {
                $validSource = true;
                break;
            }
        }
        
        // Only save if it's a valid source
        if ($validSource) {
            return (bool)update_post_meta($postId, self::SELECTED_IMAGE_SOURCE_META_KEY, $sourceIdentifier);
        }
        
        return false;
    }
    
    /**
     * Gets the selected social image source for a post.
     *
     * @param int $postId The post-ID
     * @return string|null The selected source identifier or null if not set
     */
    public static function getSelectedSocialImageSource(int $postId): ?string
    {
        $source = get_post_meta($postId, self::SELECTED_IMAGE_SOURCE_META_KEY, true);
        return $source ? (string)$source : null;
    }
    
    /**
     * Gets the URL of the selected social image source.
     *
     * @param int $postId The post-ID
     * @return string|null The URL of the selected image or null if not available
     */
    public static function getSelectedSocialImageUrl(int $postId): ?string
    {
        $sourceIdentifier = self::getSelectedSocialImageSource($postId);
        if (!$sourceIdentifier) {
            return null;
        }
        
        $collection = self::getSocialImageSourceCollection($postId);
        /** @var SocialImageSource $source */
        foreach ($collection as $source) {
            if ($source->getSource() === $sourceIdentifier && $source->getValue() !== null) {
                return $source->getValue();
            }
        }
        
        return null;
    }
    
    /**
     * Retrieves the social image sources of a post as a collection.
     *
     * @param int $postId
     * @return SocialImageSourceCollection Collection of social image sources.
     */
    public static function getSocialImageSourceCollection(int $postId): SocialImageSourceCollection
    {
        $collection = new SocialImageSourceCollection();

        // 1. Default Image Source (e.g., from plugin settings)
//        $defaultImage = get_option(BaseConstants::OPTION_DEFAULT_SOCIAL_IMAGE);
//        $collection->addNew(
//            __('Set in Social Networks', 'beyondseo'),
//            $defaultImage ? esc_url($defaultImage) : null,
//            'default'
//        );

        // 2. Featured Image
        $featuredImageId = get_post_thumbnail_id($postId);
        $featuredImageUrl = $featuredImageId ? wp_get_attachment_url($featuredImageId) : null;
        $collection->addDefaultNew(
            __('Featured Image', 'beyondseo'),
            $featuredImageUrl ? esc_url($featuredImageUrl) : null,
            'featured'
        );

        // 3. Attached Images
        $attachedImageUrl = null;
        $attachedImages = get_attached_media('image', $postId);
        if (!empty($attachedImages)) {
            // get the last uploaded image
            usort($attachedImages, function ($a, $b) {
                return $b->post_date <=> $a->post_date; // Sort by date descending
            });
            $image = reset($attachedImages); // Get first image
            $attachedImageUrl = wp_get_attachment_url($image->ID);
        }
        $collection->addNew(
            __('Attached Image', 'beyondseo'),
            $attachedImageUrl ? esc_url($attachedImageUrl) : null,
            'attached'
        );

        // 4. First Image in Content
        $contentImageUrl = null;
        $content = get_post_field('post_content', $postId);
        if (preg_match('/<img[^>]+src="([^">]+)"/i', $content, $matches)) {
            $contentImageUrl = esc_url($matches[1]);
        }
        $collection->addNew(
            __('First Image in Content', 'beyondseo'),
            $contentImageUrl,
            'first_in_content'
        );

        // 5. Image from Custom Field
        $customFieldImage = get_post_meta($postId, BaseConstants::META_KEY_CUSTOM_SOCIAL_IMAGE, true);
        $collection->addNew(
            sprintf(
                /* translators: %s: custom field meta key name */
                __('Image from custom field (%s)', 'beyondseo'),
                esc_html(BaseConstants::META_KEY_CUSTOM_SOCIAL_IMAGE)
            ),
            $customFieldImage ? esc_url($customFieldImage) : null,
            'custom_field'
        );

        // 6. Post-Author Image (Gravatar)
        $authorId = get_post_field('post_author', $postId);
        $authorEmail = get_the_author_meta('user_email', $authorId);
        $gravatar = get_avatar_url($authorEmail);
        $collection->addNew(
            __('Post Author Image', 'beyondseo'),
            $gravatar ? esc_url($gravatar) : null,
            'author'
        );

//        // 7. Custom Image (manual set by user in plugin meta-box)
//        $manualImage = get_post_meta($postId, BaseConstants::META_KEY_MANUAL_IMAGE, true);
//        $collection->addNew(
//            __('Custom Image', 'beyondseo'),
//            $manualImage ? esc_url($manualImage) : null,
//            'custom_manual'
//        );

        // Apply filters and convert back to a collection
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
        $filteredArray = apply_filters(BaseConstants::OPTION_SOCIAL_IMAGE_SOURCES, $collection->toArray(), $postId);
        return SocialImageSourceCollection::fromArray($filteredArray);
    }
}
