<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Admin\Pages\UpsellPage;

/** @var UpsellPage $this */

$beyondSeoUpsellDCCss = '
    #upsell-rankingcoach-page {
        position: fixed;
        top: 32px;
        left: 160px;
        right: 0;
        bottom: 0;
        background: #fff;
        display: block;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        box-sizing: border-box;
        transition: left 0.1s;
        z-index: 1;
    }

    body.folded #upsell-rankingcoach-page {
        left: 36px;
    }

    @media screen and (max-width: 960px) {
        #upsell-rankingcoach-page {
            left: 0;
        }
    }

    @media screen and (max-width: 782px) {
        #upsell-rankingcoach-page {
            top: 46px;
            padding: 0 12px 40px;
        }
    }
';

wp_register_style( 'rankingcoach-connect-dc-inline', false, [], RANKINGCOACH_VERSION );
wp_enqueue_style( 'rankingcoach-connect-dc-inline', false, [], RANKINGCOACH_VERSION );
wp_add_inline_style( 'rankingcoach-connect-dc-inline', $beyondSeoUpsellDCCss );
?>
<div id="upsell-rankingcoach-page" class="beyondseo">
</div>
<?php
$beyondSeoUpsellDCJs = "
    console.log('[PHP] upsell-dc-page-react.php template loaded');
    console.log('[PHP] Container element:', document.getElementById('upsell-rankingcoach-page'));
";
wp_add_inline_script( 'rankingcoach-connect-page-js', $beyondSeoUpsellDCJs );
?>
