<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Admin\Pages\SetupWizardPage;

/** @var SetupWizardPage $this */

// Full-height page beside the WordPress admin menu; the React stylesheet handles the inner layout.
$beyondSeoSetupWizardCss = '
    #setupWizard-rankingcoach-page {
        position: fixed;
        top: 32px;
        left: 160px;
        right: 0;
        bottom: 0;
        background: #fff;
        overflow-y: auto;
        overflow-x: hidden;
        transition: left 0.1s;
    }

    body.folded #setupWizard-rankingcoach-page {
        left: 36px;
    }

    @media screen and (max-width: 960px) {
        #setupWizard-rankingcoach-page {
            left: 36px;
        }
    }

    @media screen and (max-width: 782px) {
        #setupWizard-rankingcoach-page {
            top: 46px;
            left: 0;
        }
    }
';

wp_register_style( 'rankingcoach-setup-wizard-inline', false, [], RANKINGCOACH_VERSION );
wp_enqueue_style( 'rankingcoach-setup-wizard-inline' );
wp_add_inline_style( 'rankingcoach-setup-wizard-inline', $beyondSeoSetupWizardCss );
?>
<div id="setupWizard-rankingcoach-page" class="beyondseo"></div>
