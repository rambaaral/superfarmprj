<?php

namespace RankingCoach\Inc\Core\Frontend\ViteApp\Assets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use Exception;

trait Resolver {
    private array $manifest = [];

    /**
     * @return void
     * @throws Exception|\JsonException
     */
    public function load(): void {
        $path = ReactApp::get()?->config()->get( 'manifest.path' );
	    if ( empty( $path ) || ! file_exists( $path ) ) {
		    wp_die( wp_kses_post( __( 'Run <code>npm run build</code> in your application root!', 'beyondseo' ) ) );
	    }
	    try {
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                WP_Filesystem();
            }

		    $data = $wp_filesystem->get_contents( $path );
		    if (!empty($data)) {
                $this->manifest = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch ( Exception $e ) {
            throw new Exception( esc_html( $e->getMessage() ) );
        }
    }

    /**
     * @filter script_loader_tag 1 3
     */
    public function module( string $tag, string $handle, string $url ): string {
        if ( ( str_contains( $url, RANKINGCOACH_REACT_HMR_HOST ) ) || ( str_contains( $url, RANKINGCOACH_REACT_ASSETS_URI ) ) ) {
            $tag = str_replace( '<script ', '<script defer type="module" ', $tag );
        }

        return $tag;
    }

    public function resolve( string $path ): string {
        $url = '';
        if (empty($this->manifest)) {
            $this->load();
        }
        if ( ! empty( $this->manifest["src/{$path}"] ) ) {
	        if(wp_get_environment_type() === 'development'){
		        $url = '/' . RANKINGCOACH_REACT_ROOT . "/{$this->manifest["src/{$path}"]['src']}";
	        } else {
	            $url = RANKINGCOACH_REACT_ASSETS_URI . "/{$this->manifest["src/{$path}"]['file']}";
            }
        }
        return $url;
    }

    public function solveDependencies( array $paths = [] ): array {
        $dependencies = [];
	    if (empty($this->manifest)) {
		    $this->load();
	    }

		foreach ($paths as $path ) {
			if ( ! empty( $this->manifest[ 'src' . DIRECTORY_SEPARATOR . "{$path}"]['css'] ) ) {
				foreach ( $this->manifest[ 'src' . DIRECTORY_SEPARATOR . "{$path}"]['css'] as $css ) {
					$dependencies[] = RANKINGCOACH_REACT_ASSETS_URI . DIRECTORY_SEPARATOR . $css;
				}
			}
		}

        return $dependencies;
    }
}
