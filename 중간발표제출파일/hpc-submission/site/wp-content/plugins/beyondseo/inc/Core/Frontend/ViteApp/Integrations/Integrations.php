<?php

namespace RankingCoach\Inc\Core\Frontend\ViteApp\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;

class Integrations {

    /**
     * @action init
     */
    public function init(): void {
        if ( ReactApp::get()->config()->get( 'hmr.active' ) ) {
            ReactApp::init( new Vite() );
        }
    }
}
