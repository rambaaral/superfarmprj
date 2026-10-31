<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Third-Party Cookie detection UI and controller script.
// Expects the following variables from the controller (IframePage):
// - string $cookieTitle
// - string $cookieDescription
// - array  $browserInstructions (associative: browser => string[] steps)
// - string $iframeUrl

if (!isset($cookieTitle, $cookieDescription, $browserInstructions, $beyondSeoIframeUrl)) {
    // Load centralized defaults when controller didn't provide values
    $beyondseo_cookieText = require __DIR__ . '/cookie-text.php';
    $beyondseo_cookieTitle = $cookieTitle ?? ($beyondseo_cookieText['cookieTitle'] ?? '');
    $beyondseo_cookieDescription = $cookieDescription ?? ($beyondseo_cookieText['cookieDescription'] ?? '');
    $beyondseo_browserInstructions = $browserInstructions ?? ($beyondseo_cookieText['browserInstructions'] ?? []);
    $beyondseo_iframeUrl = $beyondSeoIframeUrl ?? '';
}

// Apply beyondseo_ prefix to all variables for PHPCS compliance
$beyondseo_cookieTitle = $beyondseo_cookieTitle ?? '';
$beyondseo_cookieDescription = $beyondseo_cookieDescription ?? '';
$beyondseo_browserInstructions = $beyondseo_browserInstructions ?? [];
$beyondseo_iframeUrl = $beyondSeoIframeUrl ?? '';
?>

<!-- Third-Party Cookie Check iframe -->
<iframe id="rc-cookie-check-iframe" src="https://thirdpartycookies.rankingcoach.com/start.html" style="display:none"></iframe>

<!-- Third-Party Cookie Warning -->
<div id="rc-cookie-warning" style="display: none; position: fixed; top: 28px; left: 142px; width: calc(100% - 142px); height: calc(100% - 28px); background: #fff; z-index: 99999; padding: 40px; box-sizing: border-box;">
    <div style="max-width: 600px; margin: 0 auto; text-align: center; padding-top: 100px;">
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 48px; margin-bottom: 20px;">🍪</div>
            <h2 style="color: #495057; margin-bottom: 15px; font-size: 24px;"><?php echo esc_html($beyondseo_cookieTitle); ?></h2>
            <p style="color: #6c757d; margin-bottom: 25px; font-size: 16px; line-height: 1.5;">
                <?php echo esc_html($beyondseo_cookieDescription); ?>
            </p>

            <div style="background: #e9ecef; border-radius: 6px; padding: 20px; margin-bottom: 25px; text-align: left;">
                <h4 style="color: #495057; margin-bottom: 10px; font-size: 16px;"><?php echo esc_html(__('How to enable:', 'beyondseo')); ?></h4>
                <div id="browser-instructions">
                    <?php
                    // Render instruction lists with IDs: {browser}-instructions
                    foreach ($beyondseo_browserInstructions as $beyondseo_browser => $beyondseo_instructions): ?>
                        <ol id="<?php echo esc_attr($beyondseo_browser); ?>-instructions"
                            style="color:#6c757d;margin:0;padding-left:20px;line-height:1.6;display:none;">
                            <?php foreach ((array)$beyondseo_instructions as $beyondseo_instruction): ?>
                                <li><?php echo esc_html($beyondseo_instruction); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="location.reload()" style="background: #007cba; color: white; border: none; padding: 12px 24px; border-radius: 4px; font-size: 16px; cursor: pointer; transition: background-color 0.2s;">
                    <?php echo esc_html(__('Refresh Page', 'beyondseo')); ?>
                </button>
                <a href="<?php echo esc_url($beyondseo_iframeUrl); ?>" target="_blank" style="background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 4px; font-size: 16px; cursor: pointer; transition: background-color 0.2s; text-decoration: none; display: inline-block;">
                    <?php echo esc_html(__('Open Dashboard in New Tab', 'beyondseo')); ?>
                </a>
            </div>
        </div>
    </div>
</div>
