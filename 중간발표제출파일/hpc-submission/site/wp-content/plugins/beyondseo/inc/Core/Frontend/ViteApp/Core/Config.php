<?php

namespace RankingCoach\Inc\Core\Frontend\ViteApp\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {
    private array $config = [];

    public function __construct() {
        $this->config = [
            'version'  => wp_get_environment_type() === 'local' ? time() : RANKINGCOACH_REACT_VERSION,
            'env'      => [
                'type' => wp_get_environment_type(),
                'mode' => 'plugin',
            ],
            'hmr'      => [
                'uri'     => RANKINGCOACH_REACT_URI,
                'client'  => RANKINGCOACH_REACT_HMR_URI . '/@vite/client',

                'sources' => RANKINGCOACH_REACT_HMR_URI . '/src',
                'active'  => wp_get_environment_type() === 'local' && ! empty( RANKINGCOACH_REACT_HMR_HOST ),
            ],
            'manifest' => [
                'path' => RANKINGCOACH_REACT_ASSETS_PATH . DIRECTORY_SEPARATOR . 'manifest.json',
            ],
            'cache'    => [
                'path' => wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'react',
            ],
            'src'      => [
                'path' => RANKINGCOACH_REACT_PATH . DIRECTORY_SEPARATOR . 'src',
            ]
        ];
    }
    public function get( string $key ): mixed {
        $value = $this->config;

        foreach ( explode( '.', $key ) as $key ) {
            if ( isset( $value[ $key ] ) ) {
                $value = $value[ $key ];
            } else {
                return null;
            }
        }

        return $value;
    }
}
