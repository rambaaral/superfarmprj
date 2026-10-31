<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Person graph class.
 * Generates Schema.org Person structured data with comprehensive properties.
 */
class Person extends Graphs\Graph {

    use Graphs\Traits\Image;

    /**
     * Supported social platforms with their base URLs.
     */
    private const SOCIAL_PLATFORMS = [
        'facebook' => 'https://facebook.com/',
        'twitter' => 'https://x.com/',
        'instagram' => 'https://instagram.com/',
        'linkedin' => 'https://linkedin.com/in/',
        'youtube' => 'https://youtube.com/',
        'tiktok' => 'https://tiktok.com/@',
        'pinterest' => 'https://pinterest.com/',
        'github' => 'https://github.com/',
        'tumblr' => 'https://tumblr.com/',
        'snapchat' => 'https://snapchat.com/add/',
        'wikipedia' => 'https://en.wikipedia.org/wiki/',
        'personal_website' => '',
    ];

    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array The parsed graph data.
     * @throws Exception
     */
	public function get( $graphData = null ): array {

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

        // Build base person data
        $personId = ! empty( $graphData->id ) ? $schema->context['url'] . $graphData->id : $schema->context['url'] . '#person';
        
        $data = [
            '@type' => 'Person',
            '@id'   => $personId,
            'name'  => $this->getPersonName( $graphData ),
        ];

        // Add URL if available
        $personUrl = $this->getPersonUrl( $graphData );
        if ( $personUrl ) {
            $data['url'] = $personUrl;
        }

        // Add description if available
        $description = $this->getPersonDescription( $graphData );
        if ( $description ) {
            $data['description'] = $description;
        }

        // Add contact information
        $this->addContactInformation( $data, $graphData );

        // Add professional information
        $this->addProfessionalInformation( $data, $graphData );

        // Add image
        $image = $this->getPersonImage( $graphData );
        if ( $image ) {
            $data['image'] = $image;
        }

        // Add address only if we have meaningful data
        $address = $this->getPersonAddress( $graphData );
        if ( $address ) {
            $data['address'] = $address;
        }

        // Add social profiles
        $socialProfiles = $this->getPersonSocialProfiles( $graphData );
        if ( ! empty( $socialProfiles ) ) {
            $data['sameAs'] = array_values( array_filter( $socialProfiles ) );
        }

        // Add biographical information
        $this->addBiographicalInformation( $data, $graphData );

        // Add skills and knowledge
        $this->addSkillsAndKnowledge( $data, $graphData );

        return array_filter( $data, function( $value ) {
            return ! empty( $value ) || is_numeric( $value );
        });
	}

    /**
     * Gets the person's name with intelligent fallbacks.
     *
     * @param object|null $graphData The graph data.
     * @return string The person's name.
     */
    private function getPersonName( $graphData ): string {
        if ( ! empty( $graphData->properties->name ) ) {
            return sanitize_text_field( $graphData->properties->name );
        }

        // Try to get from current post author if we're on a singular page
        if ( is_singular() ) {
            $post = get_post();
            if ( $post && $post->post_author ) {
                $authorName = get_the_author_meta( 'display_name', $post->post_author );
                if ( $authorName ) {
                    return $authorName;
                }
            }
        }

        // Try to get from queried author if we're on an author page
        if ( is_author() ) {
            $author = get_queried_object();
            if ( $author && isset( $author->display_name ) ) {
                return $author->display_name;
            }
        }

        // Fallback to post title (original behavior)
        return get_the_title() ?: '';
    }

    /**
     * Gets the person's URL.
     *
     * @param object|null $graphData The graph data.
     * @return string|null The person's URL.
     */
    private function getPersonUrl( $graphData ): ?string {
        if ( ! empty( $graphData->properties->url ) ) {
            return esc_url( $graphData->properties->url );
        }

        // Try to get author URL if we're dealing with an author
        if ( is_singular() ) {
            $post = get_post();
            if ( $post && $post->post_author ) {
                return get_author_posts_url( $post->post_author );
            }
        }

        if ( is_author() ) {
            $author = get_queried_object();
            if ( $author && isset( $author->ID ) ) {
                return get_author_posts_url( $author->ID );
            }
        }

        return null;
    }

    /**
     * Gets the person's description.
     *
     * @param object|null $graphData The graph data.
     * @return string|null The person's description.
     */
    private function getPersonDescription( $graphData ): ?string {
        if ( ! empty( $graphData->properties->description ) ) {
            return wp_strip_all_tags( $graphData->properties->description );
        }

        // Try to get author bio
        if ( is_singular() ) {
            $post = get_post();
            if ( $post && $post->post_author ) {
                $bio = get_the_author_meta( 'description', $post->post_author );
                if ( $bio ) {
                    return wp_strip_all_tags( $bio );
                }
            }
        }

        if ( is_author() ) {
            $author = get_queried_object();
            if ( $author && isset( $author->ID ) ) {
                $bio = get_the_author_meta( 'description', $author->ID );
                if ( $bio ) {
                    return wp_strip_all_tags( $bio );
                }
            }
        }

        return null;
    }

    /**
     * Adds contact information to the person data.
     *
     * @param array $data The person data array.
     * @param object|null $graphData The graph data.
     */
    private function addContactInformation( array &$data, $graphData ): void {
        // Email
        if ( ! empty( $graphData->properties->email ) ) {
            $data['email'] = sanitize_email( $graphData->properties->email );
        }

        // Telephone
        if ( ! empty( $graphData->properties->telephone ) ) {
            $data['telephone'] = sanitize_text_field( $graphData->properties->telephone );
        }
    }

    /**
     * Adds professional information to the person data.
     *
     * @param array $data The person data array.
     * @param object|null $graphData The graph data.
     */
    private function addProfessionalInformation( array &$data, $graphData ): void {
        // Job title
        if ( ! empty( $graphData->properties->jobTitle ) ) {
            $data['jobTitle'] = sanitize_text_field( $graphData->properties->jobTitle );
        }

        // Works for organization
        if ( ! empty( $graphData->properties->worksFor ) ) {
            $data['worksFor'] = [
                '@type' => 'Organization',
                'name' => sanitize_text_field( $graphData->properties->worksFor )
            ];
        }

        // Affiliation
        if ( ! empty( $graphData->properties->affiliation ) ) {
            $data['affiliation'] = [
                '@type' => 'Organization',
                'name' => sanitize_text_field( $graphData->properties->affiliation )
            ];
        }
    }

    /**
     * Gets the person's image.
     *
     * @param object|null $graphData The graph data.
     * @return array|null The image data.
     * @throws Exception
     */
    private function getPersonImage( $graphData ): ?array {
        if ( ! empty( $graphData->properties->image ) ) {
            return $this->image( $graphData->properties->image );
        }

        // Try to get author avatar
        if ( is_singular() ) {
            $post = get_post();
            if ( $post && $post->post_author ) {
                return $this->avatar( $post->post_author, 'personImage' );
            }
        }

        if ( is_author() ) {
            $author = get_queried_object();
            if ( $author && isset( $author->ID ) ) {
                return $this->avatar( $author->ID, 'personImage' );
            }
        }

        // Fallback to featured image
        return $this->getFeaturedImage();
    }

    /**
     * Gets the person's address if meaningful data is available.
     *
     * @param object|null $graphData The graph data.
     * @return array|null The address data.
     */
    private function getPersonAddress( $graphData ): ?array {
        if ( empty( $graphData->properties->location ) ) {
            return null;
        }

        $location = $graphData->properties->location;
        $addressData = [];

        if ( ! empty( $location->streetAddress ) ) {
            $addressData['streetAddress'] = sanitize_text_field( $location->streetAddress );
        }
        if ( ! empty( $location->locality ) ) {
            $addressData['addressLocality'] = sanitize_text_field( $location->locality );
        }
        if ( ! empty( $location->postalCode ) ) {
            $addressData['postalCode'] = sanitize_text_field( $location->postalCode );
        }
        if ( ! empty( $location->region ) ) {
            $addressData['addressRegion'] = sanitize_text_field( $location->region );
        }
        if ( ! empty( $location->country ) ) {
            $addressData['addressCountry'] = sanitize_text_field( $location->country );
        }

        // Only return address if we have meaningful data
        if ( empty( $addressData ) ) {
            return null;
        }

        return array_merge( [ '@type' => 'PostalAddress' ], $addressData );
    }

    /**
     * Gets the person's social profiles.
     *
     * @param object|null $graphData The graph data.
     * @return array The social profile URLs.
     */
    private function getPersonSocialProfiles( $graphData ): array {
        $profiles = [];

        // Get from graph data
        if ( ! empty( $graphData->properties->socialProfiles ) ) {
            if ( is_array( $graphData->properties->socialProfiles ) ) {
                $profiles = array_merge( $profiles, $graphData->properties->socialProfiles );
            }
        }

        // Get individual social platform URLs
        foreach ( self::SOCIAL_PLATFORMS as $platform => $baseUrl ) {
            $platformKey = 'social' . ucfirst( $platform );
            if ( ! empty( $graphData->properties->$platformKey ) ) {
                $profileData = $graphData->properties->$platformKey;
                
                if ( filter_var( $profileData, FILTER_VALIDATE_URL ) ) {
                    $profiles[] = $profileData;
                } elseif ( ! empty( $baseUrl ) ) {
                    $profiles[] = $baseUrl . ltrim( $profileData, '@/' );
                } else {
                    $profiles[] = $profileData;
                }
            }
        }

        return array_unique( array_filter( $profiles, function( $url ) {
            return filter_var( $url, FILTER_VALIDATE_URL );
        }));
    }

    /**
     * Adds biographical information to the person data.
     *
     * @param array $data The person data array.
     * @param object|null $graphData The graph data.
     */
    private function addBiographicalInformation( array &$data, $graphData ): void {
        // Birth date
        if ( ! empty( $graphData->properties->birthDate ) ) {
            $data['birthDate'] = sanitize_text_field( $graphData->properties->birthDate );
        }

        // Nationality
        if ( ! empty( $graphData->properties->nationality ) ) {
            $data['nationality'] = sanitize_text_field( $graphData->properties->nationality );
        }

        // Gender
        if ( ! empty( $graphData->properties->gender ) ) {
            $data['gender'] = sanitize_text_field( $graphData->properties->gender );
        }

        // Alumni of
        if ( ! empty( $graphData->properties->alumniOf ) ) {
            $data['alumniOf'] = [
                '@type' => 'EducationalOrganization',
                'name' => sanitize_text_field( $graphData->properties->alumniOf )
            ];
        }
    }

    /**
     * Adds skills and knowledge information to the person data.
     *
     * @param array $data The person data array.
     * @param object|null $graphData The graph data.
     */
    private function addSkillsAndKnowledge( array &$data, $graphData ): void {
        // Knows about (expertise areas)
        if ( ! empty( $graphData->properties->knowsAbout ) ) {
            if ( is_array( $graphData->properties->knowsAbout ) ) {
                $data['knowsAbout'] = array_map( 'sanitize_text_field', $graphData->properties->knowsAbout );
            } else {
                $data['knowsAbout'] = sanitize_text_field( $graphData->properties->knowsAbout );
            }
        }

        // Knows languages
        if ( ! empty( $graphData->properties->knowsLanguage ) ) {
            if ( is_array( $graphData->properties->knowsLanguage ) ) {
                $data['knowsLanguage'] = array_map( 'sanitize_text_field', $graphData->properties->knowsLanguage );
            } else {
                $data['knowsLanguage'] = sanitize_text_field( $graphData->properties->knowsLanguage );
            }
        }

        // Skills
        if ( ! empty( $graphData->properties->skills ) ) {
            if ( is_array( $graphData->properties->skills ) ) {
                $data['skills'] = array_map( 'sanitize_text_field', $graphData->properties->skills );
            } else {
                $data['skills'] = sanitize_text_field( $graphData->properties->skills );
            }
        }
    }
}