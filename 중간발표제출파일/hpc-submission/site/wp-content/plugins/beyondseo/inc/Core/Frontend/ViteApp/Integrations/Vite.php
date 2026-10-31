<?php

namespace RankingCoach\Inc\Core\Frontend\ViteApp\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;

class Vite {
    /**
     * @action wp_enqueue_scripts 1
     */
    public function client(): void {
        wp_enqueue_script(
            'rankingcoach-vite-hmr-client',
            ReactApp::get()?->config()->get( 'hmr.client' ),
            [],
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
            false,
            [ 'in_footer' => false ]
        );
        wp_script_add_data( 'rankingcoach-vite-hmr-client', 'type', 'module' );
    }

    /**
     * @filter rc_assets_resolver_url 1 2
     */
    public function url( string $url, string $path ): string {
		if ($url) {
			return $url;
		}
		return ReactApp::get()?->config()->get( 'hmr.sources' ) . "/{$path}";
	}
}
