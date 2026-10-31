<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEO\Settings;

// Are we being accessed directly ?
if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

class Abilities{

	// Marker used for the WordPress Application Password created by SiteSEO.
	static $APP_PASSWORD_NAME = 'SiteSEO AI Agents';
	static $APP_PASSWORD_APP_ID = 'siteseo-mcp';

	// GitHub release endpoint for the official WordPress MCP Adapter plugin.
	static $MCP_ADAPTER_RELEASE_URL = 'https://api.github.com/repos/WordPress/mcp-adapter/releases/latest';
	static $MCP_ADAPTER_RELEASE_CACHE = 'siteseo_pro_mcp_adapter_release';

	// REST endpoint (provided by the adapter / WP 6.9+) used to test the connection.
	static $ABILITIES_ENDPOINT = '/wp-json/wp-abilities/v1/abilities';

	// MCP HTTP transport endpoint exposed by the adapter.
	static $MCP_SERVER_ENDPOINT = '/wp-json/mcp/mcp-adapter-default-server';
	
	// User meta key used to persist the last "Test connection" result so the
	// pill stays in the correct state after a page reload.
	static $TEST_STATUS_META = 'siteseo_pro_mcp_test_status';

	// Render the Abilities settings page.
	static function home(){
		global $siteseo;

		// Capability gate - mirrored on every Pro page.
		if(!current_user_can('manage_options')){
			return;
		}

		// Status probes (kept cheap; safe to run on each render).
		$abilities_api_available = function_exists('wp_register_ability');
		$adapter_active = class_exists('\\WP\\MCP\\Core\\McpAdapter');
		$adapter_installed = '' !== self::get_installed_mcp_adapter_file();
		$has_app_password = self::current_user_has_mcp_app_password();
		$test_status = self::get_test_connection_status();
		$site_url = trailingslashit(rtrim(home_url(), '/'));
		$endpoint_url = $site_url . ltrim(self::$ABILITIES_ENDPOINT, '/');
		$mcp_server_url = $site_url . ltrim(self::$MCP_SERVER_ENDPOINT, '/');
		$username = function_exists('wp_get_current_user') ? wp_get_current_user()->user_login : '';
		$app_pass_placeholder = esc_html__('your-application-password', 'siteseo');
		$user_placeholder = $username ?: esc_html__('<your-username>', 'siteseo');

		?>
		<div id="siteseo-root">
		<?php
			if(function_exists('siteseo_admin_header')){
				siteseo_admin_header();
			}
		?>

		<div class="siteseo-abilities">

			<!-- Page header -->
			<header class="siteseo-abilities-header">
				<div class="siteseo-abilities-header-text">
					<?php
					$toggle_state_abilities = !empty($siteseo->setting_enabled['toggle-abilities']) ? $siteseo->setting_enabled['toggle-abilities'] : '';
					$toggle_nonce = wp_create_nonce('siteseo_toggle_nonce');
					?>
					<div class="siteseo-toggle-container siteseo-abilities-title-toggle">
						<?php \SiteSEO\Settings\Util::render_toggle(__('Abilities - SiteSEO', 'siteseo'), 'abilities', $toggle_state_abilities, $toggle_nonce); ?>
					</div>
					<p class="siteseo-abilities-subtitle"><?php esc_html_e('Connect any MCP-compatible AI client to your site. The setup walks you through installing the MCP Adapter, generating a WordPress Application Password and verifying the connection.', 'siteseo'); ?></p>
				</div>
			</header>

			<!-- Main 2-column layout -->
			<div class="siteseo-abilities-layout">

				<!-- LEFT COLUMN -->
				<div class="siteseo-abilities-main">

					<!-- Setup checklist card -->
					<?php
						$total_steps = 4;
						$done_count  = 0;
						$next_step = '';
						if(!$abilities_api_available){
							$next_step = esc_html__('Abilities registration', 'siteseo');
						}elseif(!$adapter_active){
							$done_count++;
							$next_step = esc_html__('MCP Adapter installation', 'siteseo');
						}elseif(!$has_app_password){
							$done_count += 2;
							$next_step = esc_html__('Application Password', 'siteseo');
						}elseif(empty($test_status['ok'])){
							$done_count+= 3;
							$next_step = esc_html__('Connection test', 'siteseo');
						}else{
							$done_count+= 4;
						}

					?>

					<div class="siteseo-abilities-card siteseo-abilities-checklist">
						<div class="siteseo-abilities-card-head">
							<h2 class="siteseo-abilities-h2"><?php esc_html_e('Setup', 'siteseo'); ?></h2>
							<div class="siteseo-abilities-meta"><?php echo esc_html(sprintf(__('%1$d of %2$d steps complete', 'siteseo'), $done_count, $total_steps)); ?></div>
						</div>

						<!-- Connector progress bar -->
						<div class="siteseo-abilities-stepper" role="list">
							<?php 
								$step_states = [
									$abilities_api_available ? 'done' : 'todo',
									$adapter_active ? 'done' : 'todo',
									$has_app_password ? 'done' : 'todo',
									!empty($test_status['ok']) ? 'done' : 'todo',
								];
							?>
							<div class="siteseo-abilities-stepper-track" aria-hidden="true"><span class="siteseo-abilities-stepper-fill"></span></div>
							<?php
								$chips = [
									['label' => esc_html__('Abilities', 'siteseo'),  'state' => $abilities_api_available ? 'done' : 'todo'],
									['label' => esc_html__('MCP Adapter', 'siteseo'),'state' => $adapter_active ? 'done' : 'todo'],
									['label' => esc_html__('Password', 'siteseo'),   'state' => $has_app_password ? 'done' : 'todo'],
									['label' => esc_html__('Test', 'siteseo'),       'state' => !empty($test_status['ok']) ? 'done' : 'todo'],
								];
								foreach($step_states as $idx => $st){
									echo '<div class="siteseo-abilities-stepper-node siteseo-abilities-stepper-node-' . esc_attr($st) . '" role="listitem">';
									echo '<span class="siteseo-abilities-stepper-circle">' . ($st === 'done' ? '&#10003;' : esc_html((string)($idx + 1))) . '</span>';
									echo '<span class="siteseo-abilities-stepper-label">' . esc_html($chips[$idx]['label']) . '</span>';
									echo '</div>';
								}
							?>
						</div>
						<?php
							if($next_step){
								echo '<p class="siteseo-abilities-nextup">' . sprintf(esc_html__('Next up: %s', 'siteseo'), '<strong>' . esc_html($next_step) . '</strong>') . '</p>';
							}else{
								echo '<p class="siteseo-abilities-nextup siteseo-abilities-nextup-done">' . esc_html__('All steps complete. Your AI client can now connect.', 'siteseo') . '</p>';
							}
						?>

						<!-- Step rows -->
						<div class="siteseo-abilities-rows">

							<!-- Step 1 Abilities registered -->
							<div class="siteseo-abilities-row siteseo-abilities-row-<?php echo ($abilities_api_available ? 'done' : 'todo'); ?>" data-step="1">
								<button type="button" class="siteseo-abilities-row-toggle" aria-expanded="true">
									<span class="siteseo-abilities-row-indicator"><?php echo ($abilities_api_available ? '&#10003;' : '1'); ?></span>
									<span class="siteseo-abilities-row-text">
										<span class="siteseo-abilities-row-title"><?php esc_html_e('SiteSEO abilities registered', 'siteseo'); ?></span>
										<span class="siteseo-abilities-row-sub"><?php esc_html_e('Exposed via the WordPress 6.9+ Abilities API', 'siteseo'); ?></span>
									</span>
									<span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($abilities_api_available ? 'success' : 'warning'); ?>"><?php echo ($abilities_api_available ? esc_html__('Ready', 'siteseo') : esc_html__('Requires WP 6.9+', 'siteseo')); ?></span>
								</button>
								<div class="siteseo-abilities-row-body">
									<?php
										if($abilities_api_available){
											echo '<p>' . esc_html__('SiteSEO registers 12 SEO abilities across 5 categories. They are picked up automatically by any installed MCP adapter and surfaced to AI clients as tools.', 'siteseo') . '</p>';
											echo wp_kses_post( self::render_abilities_list() );
										}else{
											echo '<p>' . esc_html__('The WordPress Abilities API ships in WordPress 6.9+. SiteSEO can still work today — installing the MCP Adapter backfills the API so your AI client can connect immediately. Consider updating WordPress for native abilities support.', 'siteseo') . '</p>';
										}
									?>
								</div>
							</div>

							<!-- Step 2 MCP Adapter -->
							<?php
								$adapter_state = $adapter_active ? 'done' : ($adapter_installed ? 'progress' : 'todo');
								$adapter_label = $adapter_active ? esc_html__('Active', 'siteseo') : ($adapter_installed ? esc_html__('Inactive', 'siteseo') : esc_html__('Not installed', 'siteseo'));
							?>
							<div class="siteseo-abilities-row siteseo-abilities-row-<?php echo esc_attr($adapter_state); ?>" data-step="2">
								<button type="button" class="siteseo-abilities-row-toggle" aria-expanded="<?php echo ($adapter_active ? 'false' : 'true'); ?>">
									<span class="siteseo-abilities-row-indicator"><?php echo ($adapter_state === 'done' ? '&#10003;' : '2'); ?></span>
									<span class="siteseo-abilities-row-text">
										<span class="siteseo-abilities-row-title"><?php esc_html_e('MCP Adapter installed', 'siteseo'); ?></span>
										<span class="siteseo-abilities-row-sub"><?php esc_html_e('Bridges your site to MCP-compatible AI clients', 'siteseo'); ?></span>
									</span>
									<span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($adapter_active ? 'success' : 'warning'); ?>"><?php echo esc_html($adapter_label); ?></span>
								</button>
								<div class="siteseo-abilities-row-body">
									<?php
										if($adapter_active){
											echo '<p>' . esc_html__('The MCP Adapter plugin is installed and active. Your site now exposes an MCP server that AI clients can connect to.', 'siteseo') . '</p>';
										}elseif($adapter_installed){
											echo '<p>' . esc_html__('The MCP Adapter is installed but not active. Activate it to enable the MCP server.', 'siteseo') . '</p>';
											echo '<button type="button" class="button button-primary siteseo-abilities-btn siteseo-abilities-adapter-btn" data-action="activate">' . esc_html__('Activate MCP Adapter', 'siteseo') . '</button>';
										}else{
											echo '<p>' . esc_html__('Install the official WordPress MCP Adapter plugin with a single click. It bridges your site to any MCP-compatible AI client.', 'siteseo') . '</p>';
											echo '<button type="button" class="button button-primary siteseo-abilities-btn siteseo-abilities-adapter-btn" data-action="install">' . esc_html__('Install MCP Adapter', 'siteseo') . '</button>';
										}
									?>
									<div class="siteseo-abilities-feedback" data-area="adapter" hidden></div>
								</div>
							</div>

							<!-- Step 3 Application Password -->
							<div class="siteseo-abilities-row siteseo-abilities-row-<?php echo ($has_app_password ? 'done' : 'todo'); ?>" data-step="3">
								<button type="button" class="siteseo-abilities-row-toggle" aria-expanded="<?php echo ($has_app_password ? 'false' : 'true'); ?>">
									<span class="siteseo-abilities-row-indicator"><?php echo ($has_app_password ? '&#10003;' : '3'); ?></span>
									<span class="siteseo-abilities-row-text">
										<span class="siteseo-abilities-row-title"><?php esc_html_e('Application Password generated', 'siteseo'); ?></span>
										<span class="siteseo-abilities-row-sub"><?php esc_html_e('Authenticates your AI client against your site', 'siteseo'); ?></span>
									</span>
									<span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($has_app_password ? 'success' : 'warning'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'siteseo') : esc_html__('Action needed', 'siteseo')); ?></span>
								</button>
								<div class="siteseo-abilities-row-body">
									<p> <?php esc_html_e('Generate a WordPress Application Password for your AI client. The password is shown only once — copy it somewhere safe.', 'siteseo'); ?></p>
									<div class="siteseo-abilities-app-pass-fields" data-hidden="true">
										<label class="siteseo-abilities-field"><span><?php esc_html_e('Username', 'siteseo'); ?></span><input type="text" readonly class="siteseo-abilities-username" value="<?php echo esc_attr($username); ?>" /></label>
										<label class="siteseo-abilities-field"><span><?php esc_html_e('Application Password', 'siteseo'); ?></span><input type="text" readonly class="siteseo-abilities-password" value="" /></label>
										<a class="siteseo-abilities-profile-link" href="<?php echo esc_url(admin_url('profile.php#application-passwords-section')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage in profile', 'siteseo'); ?> &nbsp;&rarr;</a>
									</div>
									<button type="button" class="button button-secondary siteseo-abilities-btn siteseo-abilities-gen-pass-btn"><?php echo ($has_app_password ? esc_html__('Generate a new Application Password', 'siteseo') : esc_html__('Generate Application Password', 'siteseo')); ?></button>
									<div class="siteseo-abilities-feedback" data-area="app-pass" hidden></div>
								</div>
							</div>

							<!-- Step 4 Test Connection -->
							<?php
							$test_ok = !empty($test_status['ok']);
							$test_message = !empty($test_status['message']) ? $test_status['message'] : '';
							$test_class = $test_ok ? 'done' : ($test_message ? 'progress' : 'todo'); ?>

							<div class="siteseo-abilities-row siteseo-abilities-row-<?php echo esc_attr($test_class); ?>" data-step="4">
								<button type="button" class="siteseo-abilities-row-toggle" aria-expanded=" <?php echo ($test_ok ? 'false' : 'true'); ?>">
									<span class="siteseo-abilities-row-indicator"><?php echo ($test_ok ? '&#10003;' : '4'); ?></span>
									<span class="siteseo-abilities-row-text">
										<span class="siteseo-abilities-row-title"><?php esc_html_e('Test the connection', 'siteseo'); ?></span>
										<span class="siteseo-abilities-row-sub"><?php esc_html_e('Verify authentication and ability discovery', 'siteseo'); ?></span>
									</span>
									<span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($test_ok ? 'success' : 'neutral'); ?> siteseo-abilities-test-pill"><?php echo ($test_ok ? esc_html__('Verified', 'siteseo') : esc_html__('Pending', 'siteseo')); ?></span>
								</button>
								<div class="siteseo-abilities-row-body">
									<p><?php esc_html_e('Verify that your AI client can authenticate against your site and discover SiteSEO abilities.', 'siteseo'); ?></p>
									<button type="button" class="button button-secondary siteseo-abilities-btn siteseo-abilities-test-btn"><?php esc_html_e('Test connection', 'siteseo'); ?></button>
									<?php
										if($test_message){
											echo '<div class="siteseo-abilities-test-result siteseo-abilities-test-result-' . ($test_ok ? 'ok' : 'error') . '">' . esc_html(($test_ok ? __('Success: ', 'siteseo') : __('Failed: ', 'siteseo')) . $test_message) . '</div>';
										}else{
											echo '<div class="siteseo-abilities-test-result" data-hidden="true"></div>';
										}
									?>
								</div>
							</div>
						</div> <!-- .siteseo-abilities-rows -->
					</div><!-- .siteseo-abilities-checklist -->

					<!-- AI Client Configuration -->
					<div class="siteseo-abilities-card siteseo-abilities-clients">
						<div class="siteseo-abilities-card-head">
							<h2 class="siteseo-abilities-h2"><?php esc_html_e('Connect to AI Client', 'siteseo'); ?></h2>
							<div class="siteseo-abilities-meta"><?php esc_html_e('Pick a client, copy the snippet, paste it in the right place.', 'siteseo'); ?></div>
						</div>

						<!-- Hidden data for snippet building -->
						<?php
							echo '<script type="application/json" id="siteseo-abilities-data">' . wp_json_encode([
								'siteUrl'        => $site_url,
								'mcpServerUrl'   => $mcp_server_url,
								'endpointUrl'    => $endpoint_url,
								'username'       => $username,
								'passwordHolder' => $app_pass_placeholder,
								'userPlaceholder'=> $user_placeholder,
								'serverName'     => 'siteseo-site',
								'adapterActive'  => $adapter_active,
								'hasAppPassword' => $has_app_password,
								'i18n'           => [
									'copy'          => esc_html__('Copy snippet', 'siteseo'),
									'copied'        => esc_html__('Copied!', 'siteseo'),
									'installing'    => esc_html__('Installing...', 'siteseo'),
									'activating'    => esc_html__('Activating...', 'siteseo'),
									'generating'    => esc_html__('Generating...', 'siteseo'),
									'testing'       => esc_html__('Testing...', 'siteseo'),
									'genErrTitle'   => esc_html__('Could not generate the password.', 'siteseo'),
									'testOkPrefix'  => esc_html__('Success:', 'siteseo'),
									'testFailPrefix'=> esc_html__('Failed:', 'siteseo'),
								],
							]) . '</script>';

							// Segmented tab bar
							$clients = [
								'claude-desktop' => ['label' => esc_html__('Claude Desktop', 'siteseo'), 'icon' => 'C'],
								'claude-code'    => ['label' => esc_html__('Claude Code CLI', 'siteseo'), 'icon' => '>_'],
								'cursor'         => ['label' => esc_html__('Cursor', 'siteseo'),          'icon' => 'Cu'],
								'vscode'         => ['label' => esc_html__('VS Code', 'siteseo'),          'icon' => 'VS'],
								'antigravity'    => ['label' => esc_html__('Antigravity', 'siteseo'),          'icon' => 'AG'],
								'gemini-cli'     => ['label' => esc_html__('Gemini CLI', 'siteseo'),       'icon' => 'G', 'deprecated' => true],
							];

							echo '<div class="siteseo-abilities-segmented" role="tablist" aria-label="' . esc_attr__('AI clients', 'siteseo') . '">';
							$first = true;
							foreach($clients as $id => $data){
								$badge = '';
								if(!empty($data['deprecated'])){
									$badge = ' <span class="siteseo-deprecated-badge">' . esc_html__('Deprecated', 'siteseo') . '</span>';
								}
								echo '<button type="button" class="siteseo-abilities-segmented-btn' . ($first ? ' active' : '') . '" data-client="' . esc_attr($id) . '" role="tab" aria-selected="' . ($first ? 'true' : 'false') . '">' . esc_html($data['label']) . wp_kses_post($badge) . '</button>';
								$first = false;
							}
						?>

						</div>

						<!-- Client panel -->
						<div class="siteseo-abilities-client-panel">
							<div class="siteseo-abilities-client-meta">
								<span class="siteseo-abilities-client-path-label"></span>
								<code class="siteseo-abilities-client-path-value"></code>
							</div>

							<div class="siteseo-abilities-snippet-wrap">
								<pre class="siteseo-abilities-snippet" tabindex="0"></pre>
								<button type="button" class="siteseo-abilities-copy-btn">
									<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
									<span class="siteseo-abilities-copy-btn-label"><?php esc_html_e('Copy', 'siteseo'); ?></span>
								</button>
							</div>

							<ol class="siteseo-abilities-instructions"></ol>
						</div> <!-- .siteseo-abilities-client-panel -->

					</div> <!-- siteseo-abilities-clients -->

				</div> <!-- siteseo-abilities-main (LEFT) -->

				<!-- RIGHT COLUMN (sidebar) -->
				<div class="siteseo-abilities-side">

					<!-- Status card -->
					<div class="siteseo-abilities-card siteseo-abilities-side-card">
						<h3 class="siteseo-abilities-h3"><?php esc_html_e('Site status', 'siteseo'); ?></h3>
						<dl class="siteseo-abilities-status-list">
							<div><dt><?php esc_html_e('Abilities API', 'siteseo'); ?></dt><dd><span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($abilities_api_available ? 'success' : 'neutral'); ?>"><?php echo ($abilities_api_available ? esc_html__('Available', 'siteseo') : esc_html__('Unavailable', 'siteseo')); ?></span></dd></div>
							<div><dt><?php esc_html_e('MCP Adapter', 'siteseo'); ?></dt><dd><span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($adapter_active ? 'success' : ($adapter_installed ? 'warning' : 'neutral')); ?>"><?php echo esc_html($adapter_label); ?></span></dd></div>
							<div><dt><?php esc_html_e('Application Password', 'siteseo'); ?></dt><dd><span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($has_app_password ? 'success' : 'neutral'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'siteseo') : esc_html__('Not generated', 'siteseo')); ?></span></dd></div>
							<div><dt><?php esc_html_e('Last test', 'siteseo'); ?></dt><dd><span class="siteseo-abilities-pill siteseo-abilities-pill-<?php echo ($test_ok ? 'success' : ($test_message ? 'error' : 'neutral')); ?>"><?php echo ($test_ok ? esc_html__('Tested', 'siteseo') : ($test_message ? esc_html__('Failed', 'siteseo') : esc_html__('Never', 'siteseo'))); ?></span></dd></div>
						</dl>
					</div>

					<!-- Quick tips card -->
					<div class="siteseo-abilities-card siteseo-abilities-side-card">
						<h3 class="siteseo-abilities-h3"><?php esc_html_e('Quick tips', 'siteseo'); ?></h3>
						<ul class="siteseo-abilities-tip-list">
							<li><?php esc_html_e('Generate one Application Password per AI client so you can revoke them individually.', 'siteseo'); ?></li>
							<li><?php esc_html_e('The password is shown only once. Copy it before navigating away.', 'siteseo'); ?></li>
							<li><?php esc_html_e('Application Passwords are stored in your user profile and can be revoked at any time.', 'siteseo'); ?></li>
							<li><?php esc_html_e('Each ability is permission-gated - your AI client can only act within your user capabilities.', 'siteseo'); ?></li>
						</ul>
					</div>

					<!-- Resources card -->
					<div class="siteseo-abilities-card siteseo-abilities-side-card">
						<h3 class="siteseo-abilities-h3"><?php esc_html_e('Resources', 'siteseo'); ?></h3>
						<ul class="siteseo-abilities-resource-list">
							<li><a href="https://siteseo.io/docs/ai/how-to-setup-mcp-adapter-and-abilities/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('SiteSEO documentation', 'siteseo'); ?></a><span class="siteseo-abilities-resource-sub"><?php esc_html_e('Guides', 'siteseo'); ?></span></li>
							<li><a href="https://modelcontextprotocol.io/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Model Context Protocol', 'siteseo'); ?></a><span class="siteseo-abilities-resource-sub"><?php esc_html_e('What MCP is and how it works', 'siteseo'); ?></span></li>
							<li><a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer"><?php esc_html_e('WordPress MCP Adapter', 'siteseo'); ?></a><span class="siteseo-abilities-resource-sub"><?php esc_html_e('Official plugin repository', 'siteseo'); ?></span></li>
						</ul>
					</div>

				</div> <!-- .siteseo-abilities-side -->
			</div><!-- .siteseo-abilities-layout -->
		</div><!-- .siteseo-abilities-v2 -->
		</div> <!-- #siteseo-root -->
		<?php
	}

	// Render the list of abilities SiteSEO exposes through the MCP adapter.
	static function render_abilities_list(){
		$groups = self::get_registered_abilities();

		if(empty($groups)){
			return '<p class="siteseo-abilities-empty">' . esc_html__('No SiteSEO abilities are currently exposed. Activate the MCP Adapter to surface them.', 'siteseo') . '</p>';
		}

		$html = '<div class="siteseo-abilities-list">';
		foreach($groups as $group => $items){
			$html .= '<div class="siteseo-abilities-group">';
			$html .= '<h4>' . esc_html($group) . '</h4>';
			$html .= '<ul>';
			foreach($items as $ability){
				$html .= '<li>';
				$html .= '<span class="siteseo-abilities-ability-name">' . esc_html($ability['label']) . '</span>';
				if(!empty($ability['description'])){
					$html .= '<span class="siteseo-abilities-ability-desc">' . esc_html($ability['description']) . '</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	// The catalogue of SiteSEO abilities exposed to MCP clients. Mirrors the
	// abilities registered in main/abilitiesregister.php so the UI listing
	// stays in sync with the real wp_register_ability() calls. Kept in one
	// place so future AI integrations can simply append a new entry here.
	static function get_registered_abilities(){
		return [
			esc_html__('Posts', 'siteseo') => [
				[
					'label'       => esc_html__('Get Post SEO Data', 'siteseo'),
					'description' => esc_html__('Returns the SEO snapshot for a post: title, meta description, focus keyphrase, robots flags, canonical URL and social meta.', 'siteseo'),
				],
				[
					'label'       => esc_html__('Update Post SEO Data', 'siteseo'),
					'description' => esc_html__('Updates SEO fields for a post: title, description, focus keyphrase, robots flags, canonical URL and social meta.', 'siteseo'),
				],
				[
					'label'       => esc_html__('List Posts Missing SEO Data', 'siteseo'),
					'description' => esc_html__('Returns posts where one or more SEO fields are unset (title, description, or focus keyphrase).', 'siteseo'),
				],
				[
					'label'       => esc_html__('List Posts by SEO Score', 'siteseo'),
					'description' => esc_html__('Returns posts with their cached SEO content-analysis score.', 'siteseo'),
				],
			],
			esc_html__('Settings', 'siteseo') => [
				[
					'label'       => esc_html__('Get SiteSEO Settings', 'siteseo'),
					'description' => esc_html__('Returns the SiteSEO settings tree (titles, social, advanced, sitemap, analytics).', 'siteseo'),
				],
			],
			esc_html__('Robots.txt', 'siteseo') => [
				[
					'label'       => esc_html__('Get Robots.txt Output', 'siteseo'),
					'description' => esc_html__('Returns the active robots.txt content served for this site.', 'siteseo'),
				],
				[
					'label'       => esc_html__('List Robots.txt Rules', 'siteseo'),
					'description' => esc_html__('Lists the parsed custom robots.txt rules.', 'siteseo'),
				],
				[
					'label'       => esc_html__('Add Robots.txt Rule', 'siteseo'),
					'description' => esc_html__('Adds a new custom robots.txt rule for a given user agent.', 'siteseo'),
				],
				[
					'label'       => esc_html__('Delete Robots.txt Rule', 'siteseo'),
					'description' => esc_html__('Deletes a custom robots.txt rule from the physical robots.txt file.', 'siteseo'),
				],
			],
			esc_html__('Audit', 'siteseo') => [
				[
					'label'       => esc_html__('Get Homepage SEO Audit', 'siteseo'),
					'description' => esc_html__('Returns an SEO snapshot of the homepage: title, meta description, robots flags and a content-analysis score.', 'siteseo'),
				],
				[
					'label'       => esc_html__('Get Site SEO Audit', 'siteseo'),
					'description' => esc_html__('Returns a site-wide SEO health snapshot: homepage score, sitemap state, robots rule count and public post types.', 'siteseo'),
				],
			],
			esc_html__('Sitemap', 'siteseo') => [
				[
					'label'       => esc_html__('Get Sitemap Status', 'siteseo'),
					'description' => esc_html__('Returns whether the XML sitemap is enabled and the sitemap index URL.', 'siteseo'),
				],
			],
		];
	}

	// Human readable label for the current adapter state.
	protected static function adapter_status_label($state){
		switch($state){
			case 'active':
				return esc_html__('Active', 'siteseo');
			case 'inactive':
				return esc_html__('Inactive', 'siteseo');
			default:
				return esc_html__('Not installed', 'siteseo');
		}
	}

	// Scan installed plugins for the MCP Adapter (folder prefix: mcp-adapter/).
	static function get_installed_mcp_adapter_file(){
		if(!function_exists('get_plugins')){
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach(array_keys(get_plugins()) as $plugin_file){
			if(0 === strpos($plugin_file, 'mcp-adapter/')){
				return $plugin_file;
			}
		}
		return '';
	}

	// Does the current user already have an MCP app password created by SiteSEO?
	static function current_user_has_mcp_app_password(){
		$user_id = get_current_user_id();
		if(!$user_id || !class_exists('WP_Application_Passwords')){
			return false;
		}
		foreach(\WP_Application_Passwords::get_user_application_passwords($user_id) as $app){
			if(!empty($app['app_id']) && $app['app_id'] === self::$APP_PASSWORD_APP_ID){
				return true;
			}
		}
		return false;
	}

	// Read the saved test-connection status for the current user.
	static function get_test_connection_status(){
		$user_id = get_current_user_id();
		if(!$user_id){
			return ['ok' => false, 'message' => ''];
		}
		$stored = get_user_meta($user_id, self::$TEST_STATUS_META, true);
		if(!is_array($stored)){
			return ['ok' => false, 'message' => ''];
		}
		return [
			'ok'      => !empty($stored['ok']),
			'message' => !empty($stored['message']) ? $stored['message'] : '',
		];
	}

	// Persist the test-connection status for the current user.
	static function save_test_connection_status($ok, $message){
		$user_id = get_current_user_id();
		if(!$user_id){
			return;
		}
		update_user_meta($user_id, self::$TEST_STATUS_META, [
			'ok'      => !empty($ok),
			'message' => (string)$message,
		]);
	}
}
