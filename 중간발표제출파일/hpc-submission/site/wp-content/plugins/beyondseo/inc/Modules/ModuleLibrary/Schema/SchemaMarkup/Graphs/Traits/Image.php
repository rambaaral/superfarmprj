<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Trait that handles images for the graphs.
 */
trait Image {
    /**
     * Builds the graph data for a given image with a given schema ID.
     *
     * @param int $imageId The image ID.
     * @param string $graphId The graph ID (optional).
     * @return array $data    The image graph data.
     * @throws Exception
     *
     */
	protected function image( $imageId, $graphId = '' ) {

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();

        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		// Validate image ID
		if ( ! is_numeric( $imageId ) && ! filter_var( $imageId, FILTER_VALIDATE_URL ) ) {
			return [];
		}

		$imageUrl = '';
		if ( is_numeric( $imageId ) ) {
			$imageUrl = wp_get_attachment_image_url( $imageId, 'full' );
			// Validate that the attachment exists and is an image
			if ( ! $imageUrl || ! wp_attachment_is_image( $imageId ) ) {
				return [];
			}
		} else {
			// If it's a URL, validate it
			$imageUrl = filter_var( $imageId, FILTER_VALIDATE_URL ) ? $imageId : '';
		}

		if ( ! $imageUrl ) {
			return [];
		}

		$data = [
			'@type'      => 'ImageObject',
			'url'        => esc_url( $imageUrl ),
			'contentUrl' => esc_url( $imageUrl ),
		];

		if ( $graphId ) {
			$baseUrl     = $schema->context['url'] ?? home_url();
			$data['@id'] = trailingslashit( esc_url( $baseUrl ) ) . '#' . sanitize_key( $graphId );
		}

		// Add image dimensions - prioritize WordPress attachment metadata
		if ( is_numeric( $imageId ) ) {
			// Use wp_get_attachment_metadata() for Media Library images
			$metaData = wp_get_attachment_metadata( $imageId );
			if ( $metaData && ! empty( $metaData['width'] ) && ! empty( $metaData['height'] ) ) {
				$data['width']  = (int) $metaData['width'];
				$data['height'] = (int) $metaData['height'];
				
				// Add thumbnailUrl for smaller images or generate thumbnail
				$thumbnailUrl = $this->getThumbnailUrl( $imageId, $imageUrl );
				if ( $thumbnailUrl ) {
					$data['thumbnailUrl'] = $thumbnailUrl;
				}
			}

			$caption = $this->getImageCaption( $imageId );
			if ( ! empty( $caption ) ) {
				$data['caption'] = $caption;
			}
			
			// Add name property from attachment title
			$attachment = get_post( $imageId );
			if ( $attachment && ! empty( $attachment->post_title ) ) {
				$data['name'] = wp_strip_all_tags( $attachment->post_title );
			}
		} else {
			// For URLs, only try local files using getimagesize()
			$imageDimensions = $this->getLocalImageDimensions( $imageUrl );
			if ( $imageDimensions ) {
				$data['width']  = $imageDimensions['width'];
				$data['height'] = $imageDimensions['height'];
				
				// Add thumbnailUrl for local images
				$data['thumbnailUrl'] = esc_url( $imageUrl );
			}
		}

		return $data;
	}

	/**
	 * Get the image caption.
	 *
	 * @param  int    $attachmentId The attachment ID.
	 * @return string               The caption.
	 */
	protected function getImageCaption( $attachmentId ) {
		$caption = wp_get_attachment_caption( $attachmentId );
		if ( ! empty( $caption ) ) {
			return wp_strip_all_tags( $caption );
		}

		$altText = get_post_meta( $attachmentId, '_wp_attachment_image_alt', true );
		return $altText ? wp_strip_all_tags( $altText ) : '';
	}

	/**
	 * Get thumbnail URL for an image attachment.
	 *
	 * @param int    $attachmentId The attachment ID.
	 * @param string $originalUrl  The original image URL.
	 * @return string|null         The thumbnail URL or null if unavailable.
	 */
	protected function getThumbnailUrl( $attachmentId, $originalUrl ): ?string {
		// Try to get medium size first (good balance for thumbnails)
		$thumbnailUrl = wp_get_attachment_image_url( $attachmentId, 'medium' );
		
		// If medium doesn't exist, try thumbnail size
		if ( ! $thumbnailUrl ) {
			$thumbnailUrl = wp_get_attachment_image_url( $attachmentId, 'thumbnail' );
		}
		
		// If no thumbnail sizes exist, use original if it's reasonably sized
		if ( ! $thumbnailUrl ) {
			$metadata = wp_get_attachment_metadata( $attachmentId );
			if ( $metadata && isset( $metadata['width'] ) && $metadata['width'] <= 800 ) {
				$thumbnailUrl = $originalUrl;
			}
		}
		
		return $thumbnailUrl ? esc_url( $thumbnailUrl ) : null;
	}

	/**
	 * Get dimensions for local image files only.
	 * Avoids remote requests to prevent loading delays.
	 *
	 * @param string $imageUrl The image URL.
	 * @return array|null      Array with width/height or null if unavailable.
	 */
	protected function getLocalImageDimensions( string $imageUrl ): ?array {
		// Check if this is a local WordPress URL
		$siteUrl = home_url();
		$uploadDir = wp_upload_dir();
		
		// Only process if it's a local URL from uploads directory
		if ( ! $this->stringStartsWith( $imageUrl, $siteUrl ) && 
			 ! $this->stringStartsWith( $imageUrl, $uploadDir['baseurl'] ) ) {
			return null;
		}
		
		// Convert URL to local file path
		$localPath = $this->convertUrlToLocalPath( $imageUrl );
		if ( ! $localPath || ! file_exists( $localPath ) ) {
			return null;
		}
		
		// Use getimagesize for local files only
		$imageSize = @getimagesize( $localPath );
		
		if ( $imageSize && isset( $imageSize[0], $imageSize[1] ) ) {
			return [
				'width'  => (int) $imageSize[0],
				'height' => (int) $imageSize[1]
			];
		}

		return null;
	}
	
	/**
	 * Convert image URL to local file path.
	 *
	 * @param string $imageUrl The image URL.
	 * @return string|null     Local file path or null if not local.
	 */
	protected function convertUrlToLocalPath( string $imageUrl ): ?string {
		$uploadDir = wp_upload_dir();
		
		// Remove query parameters and fragments
		$cleanUrl = strtok( $imageUrl, '?' );
		
		// Try uploads directory first
		if ( $this->stringStartsWith( $cleanUrl, $uploadDir['baseurl'] ) ) {
			return str_replace( $uploadDir['baseurl'], $uploadDir['basedir'], $cleanUrl );
		}
		
		// Try site URL
		$siteUrl = home_url();
		if ( $this->stringStartsWith( $cleanUrl, $siteUrl ) ) {
			$relativePath = str_replace( $siteUrl, '', $cleanUrl );
			$absolutePath = ABSPATH . ltrim( $relativePath, '/' );
			
			// Security check - ensure path is within WordPress directory
			$realPath = realpath( $absolutePath );
			$realAbsPath = realpath( ABSPATH );
			
			if ( $realPath && $realAbsPath && $this->stringStartsWith( $realPath, $realAbsPath ) ) {
				return $realPath;
			}
		}
		
		return null;
	}

	/**
	 * PHP 7.4 compatible string starts with check.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 * @return bool            True if haystack starts with needle.
	 */
	protected function stringStartsWith( string $haystack, string $needle ): bool {
		return function_exists( 'str_starts_with' ) 
			? str_starts_with( $haystack, $needle )
			: substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}

    /**
     * Returns the graph data for the avatar of a given user.
     *
     * @param int $userId The user ID.
     * @param string $graphId The graph ID.
     * @return array           The graph data.
     * @throws Exception
     *
     */
	protected function avatar( $userId, $graphId ) {

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		if ( ! get_option( 'show_avatars' ) ) {
			return [];
		}

		$avatar = get_avatar_data( $userId );
		if ( ! $avatar['found_avatar'] ) {
			return [];
		}

		return array_filter( [
			'@type'   => 'ImageObject',
			'@id'     => $schema->context['url'] . "#$graphId",
			'url'     => $avatar['url'],
			'width'   => $avatar['width'],
			'height'  => $avatar['height'],
			'caption' => get_the_author_meta( 'display_name', $userId )
		] );
	}

    /**
     * Returns the graph data for the post's featured image.
     *
     * @return array The featured image data array.
     * @throws Exception
     */
	protected function getFeaturedImage(): array
    {
        $post = is_singular() ? get_post() : null;

		return has_post_thumbnail( $post ) ? $this->image( get_post_thumbnail_id() ) : [];
	}

    /**
     * Get image size from a remote URL.
     * @param string $image_url
     * @return array|null
     */

    protected function getImageSizeFromUrl(string $image_url): ?array
    {
        $response = wp_remote_get($image_url, [
            'timeout' => 10,
            'retry'   => 2,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $image_data = wp_remote_retrieve_body($response);

        if (empty($image_data)) {
            return null;
        }

        $size = getimagesizefromstring($image_data);

        return $size;
    }

}
