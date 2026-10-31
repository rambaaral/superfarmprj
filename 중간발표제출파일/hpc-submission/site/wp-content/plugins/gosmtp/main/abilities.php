<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

namespace GOSMTP;

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

/**
 * GoSMTP Abilities settings page.
 *
 * Renders the AI Abilities setup wizard (MCP Adapter install, Application
 * Password generation, connection test) and the AI client configuration
 * snippet. The ability catalogue merges Free and Pro abilities so the UI
 * always reflects what is actually registered.
 *
 * Note: the actual wp_register_ability() calls live in:
 *  - GOSMTP\AbilitiesRegister  (Free, always registered)
 *  - GOSMTP\AbilitiesPro       (Pro, only when GoSMTP Pro is active)
*/

class Abilities{

	// Marker used for the WordPress Application Password created by GoSMTP.
	static $APP_PASSWORD_NAME = 'GoSMTP AI Agents';
	static $APP_PASSWORD_APP_ID = 'gosmtp-mcp';

	// WordPress.org slug of the official MCP Adapter plugin.
	static $MCP_ADAPTER_SLUG = 'mcp-adapter';

	// REST endpoint (provided by the adapter / WP 6.9+) used to test the connection.
	static $ABILITIES_ENDPOINT = '/wp-json/wp-abilities/v1/abilities';

	// MCP HTTP transport endpoint exposed by the adapter.
	static $MCP_SERVER_ENDPOINT = '/wp-json/mcp/mcp-adapter-default-server';

	// User meta key used to persist the last "Test connection" result so the
	// pill stays in the correct state after a page reload.
	static $TEST_STATUS_META = 'gosmtp_mcp_test_status';

	/**
	 * Render the AI Abilities settings page.
	 *
	 * @return void
	 */
	static function ui_abilities(){
		global $gosmtp;

		// Capability gate - mirrored on every admin page.
		if(!current_user_can('manage_options')){
			return;
		}

		// Enqueue dedicated assets for this page.
		wp_enqueue_style('gosmtp-admin');
		wp_enqueue_style('gosmtp-abilities');
		wp_enqueue_script('gosmtp-abilities');
		wp_localize_script('gosmtp-abilities', 'gosmtp_abilities', [
			'ajax_url'     => admin_url('admin-ajax.php'),
			'nonce'        => wp_create_nonce('gosmtp_ajax'),
			'endpoint_url' => trailingslashit(home_url()) . ltrim(self::$ABILITIES_ENDPOINT, '/'),
			'i18n'         => [
				'installing'    => esc_html__('Installing...', 'gosmtp'),
				'activating'    => esc_html__('Activating...', 'gosmtp'),
				'generating'    => esc_html__('Generating...', 'gosmtp'),
				'testing'       => esc_html__('Testing...', 'gosmtp'),
				'genErrTitle'   => esc_html__('Could not generate the password.', 'gosmtp'),
				'testOkPrefix'  => esc_html__('Success:', 'gosmtp'),
				'testFailPrefix'=> esc_html__('Failed:', 'gosmtp'),
			],
		]);

		// Status probes (kept cheap; safe to run on each render).
		$abilities_api_available = function_exists('wp_register_ability');
		$adapter_active = is_plugin_active('mcp-adapter/mcp-adapter.php');
		$adapter_installed = '' !== self::get_installed_mcp_adapter_file();
		$has_app_password = self::current_user_has_mcp_app_password();
		$test_status = self::get_test_connection_status();
		$site_url = trailingslashit(rtrim(home_url(), '/'));
		$endpoint_url = $site_url . ltrim(self::$ABILITIES_ENDPOINT, '/');
		$mcp_server_url = $site_url . ltrim(self::$MCP_SERVER_ENDPOINT, '/');
		$username = function_exists('wp_get_current_user') ? wp_get_current_user()->user_login : '';
		$app_pass_placeholder = esc_html__('your-application-password', 'gosmtp');
		$user_placeholder = $username ? $username : esc_html__('<your-username>', 'gosmtp');
		?>
		<div class="wrap gosmtp-abilities-root">

			<!-- Page header -->
			<header class="gosmtp-abilities-header">
				<div class="gosmtp-abilities-header-text">
					<h1 class="gosmtp-abilities-title"><?php esc_html_e('AI Abilities', 'gosmtp'); ?></h1>
					<p class="gosmtp-abilities-subtitle"><?php esc_html_e('Connect any MCP-compatible AI client to your site. The setup walks you through installing the MCP Adapter, generating a WordPress Application Password and verifying the connection.', 'gosmtp'); ?></p>
				</div>
			</header>

			<!-- Main 2-column layout -->
			<div class="gosmtp-abilities-layout">

				<!-- LEFT COLUMN -->
				<div class="gosmtp-abilities-main">

					<!-- Setup checklist card -->
					<?php
						$total_steps = 4;
						$done_count  = 0;
						$next_step = '';
						if(!$abilities_api_available){
							$next_step = esc_html__('Abilities registration', 'gosmtp');
						}elseif(!$adapter_active){
							$done_count++;
							$next_step = esc_html__('MCP Adapter installation', 'gosmtp');
						}elseif(!$has_app_password){
							$done_count += 2;
							$next_step = esc_html__('Application Password', 'gosmtp');
						}elseif(empty($test_status['ok'])){
							$done_count+= 3;
							$next_step = esc_html__('Connection test', 'gosmtp');
						}else{
							$done_count+= 4;
						}
					?>

					<div class="gosmtp-abilities-card gosmtp-abilities-checklist">
						<div class="gosmtp-abilities-card-head">
							<h2 class="gosmtp-abilities-h2"><?php esc_html_e('Setup', 'gosmtp'); ?></h2>
							<div class="gosmtp-abilities-meta"><?php echo esc_html(sprintf(__( '%1$d of %2$d steps complete', 'gosmtp' ), $done_count, $total_steps)); ?></div>
						</div>

						<!-- Connector progress bar -->
						<div class="gosmtp-abilities-stepper" role="list">
							<?php
								$step_states = [
									$abilities_api_available ? 'done' : 'todo',
									$adapter_active ? 'done' : 'todo',
									$has_app_password ? 'done' : 'todo',
									!empty($test_status['ok']) ? 'done' : 'todo',
								];
							?>
							<div class="gosmtp-abilities-stepper-track" aria-hidden="true"><span class="gosmtp-abilities-stepper-fill"></span></div>
							<?php
								$chips = [
									['label' => esc_html__('Abilities', 'gosmtp'),  'state' => $abilities_api_available ? 'done' : 'todo'],
									['label' => esc_html__('MCP Adapter', 'gosmtp'),'state' => $adapter_active ? 'done' : 'todo'],
									['label' => esc_html__('Password', 'gosmtp'),   'state' => $has_app_password ? 'done' : 'todo'],
									['label' => esc_html__('Test', 'gosmtp'),       'state' => !empty($test_status['ok']) ? 'done' : 'todo'],
								];
								foreach($step_states as $idx => $st){
									echo '<div class="gosmtp-abilities-stepper-node gosmtp-abilities-stepper-node-' . esc_attr($st) . '" role="listitem">';
									echo '<span class="gosmtp-abilities-stepper-circle">' . ($st === 'done' ? '&#10003;' : esc_html((string)($idx + 1))) . '</span>';
									echo '<span class="gosmtp-abilities-stepper-label">' . esc_html($chips[$idx]['label']) . '</span>';
									echo '</div>';
								}
							?>
						</div>
						<?php
							if($next_step){
								echo '<p class="gosmtp-abilities-nextup">' . sprintf(esc_html__('Next up: %s', 'gosmtp'), '<strong>' . esc_html($next_step) . '</strong>') . '</p>';
							}else{
								echo '<p class="gosmtp-abilities-nextup gosmtp-abilities-nextup-done">' . esc_html__('All steps complete. Your AI client can now connect.', 'gosmtp') . '</p>';
							}
						?>

						<!-- Step rows -->
						<div class="gosmtp-abilities-rows">

							<!-- Step 1 Abilities registered -->
							<div class="gosmtp-abilities-row gosmtp-abilities-row-<?php echo ($abilities_api_available ? 'done' : 'todo'); ?>" data-step="1">
								<button type="button" class="gosmtp-abilities-row-toggle" aria-expanded="true">
									<span class="gosmtp-abilities-row-indicator"><?php echo ($abilities_api_available ? '&#10003;' : '1'); ?></span>
									<span class="gosmtp-abilities-row-text">
										<span class="gosmtp-abilities-row-title"><?php esc_html_e('GoSMTP abilities registered', 'gosmtp'); ?></span>
										<span class="gosmtp-abilities-row-sub"><?php esc_html_e('Exposed via the WordPress 6.9+ Abilities API', 'gosmtp'); ?></span>
									</span>
									<span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($abilities_api_available ? 'success' : 'warning'); ?>"><?php echo ($abilities_api_available ? esc_html__('Ready', 'gosmtp') : esc_html__('Requires WP 6.9+', 'gosmtp')); ?></span>
								</button>
								<div class="gosmtp-abilities-row-body">
									<?php
										if($abilities_api_available){
											echo '<p>' . esc_html__('GoSMTP exposes the abilities listed below. They are picked up automatically by any installed MCP adapter and surfaced to AI clients as tools.', 'gosmtp') . '</p>';
											echo wp_kses_post(self::render_abilities_list());
										}else{
											echo '<p>' . esc_html__('The WordPress Abilities API ships in WordPress 6.9+. GoSMTP can still work today — installing the MCP Adapter backfills the API so your AI client can connect immediately. Consider updating WordPress for native abilities support.', 'gosmtp') . '</p>';
										}
									?>
								</div>
							</div>

							<!-- Step 2 MCP Adapter -->
							<?php
								$adapter_state = $adapter_active ? 'done' : ($adapter_installed ? 'progress' : 'todo');
								$adapter_label = $adapter_active ? esc_html__('Active', 'gosmtp') : ($adapter_installed ? esc_html__('Inactive', 'gosmtp') : esc_html__('Not installed', 'gosmtp'));
							?>
							<div class="gosmtp-abilities-row gosmtp-abilities-row-<?php echo esc_attr($adapter_state); ?>" data-step="2">
								<button type="button" class="gosmtp-abilities-row-toggle" aria-expanded="<?php echo ($adapter_active ? 'false' : 'true'); ?>">
									<span class="gosmtp-abilities-row-indicator"><?php echo ($adapter_state === 'done' ? '&#10003;' : '2'); ?></span>
									<span class="gosmtp-abilities-row-text">
										<span class="gosmtp-abilities-row-title"><?php esc_html_e('MCP Adapter installed', 'gosmtp'); ?></span>
										<span class="gosmtp-abilities-row-sub"><?php esc_html_e('Bridges your site to MCP-compatible AI clients', 'gosmtp'); ?></span>
									</span>
									<span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($adapter_active ? 'success' : 'warning'); ?>"><?php echo esc_html($adapter_label); ?></span>
								</button>
							<div class="gosmtp-abilities-row-body">
								<?php
									if($adapter_active){
										echo '<p>' . esc_html__('The MCP Adapter plugin is installed and active. Your site now exposes an MCP server that AI clients can connect to.', 'gosmtp') . '</p>';
									}elseif($adapter_installed){
										echo '<p>' . esc_html__('The MCP Adapter is installed but not active. Activate it to enable the MCP server.', 'gosmtp') . '</p>';
										echo '<button type="button" class="button button-primary gosmtp-abilities-btn gosmtp-abilities-adapter-btn" data-action="activate">' . esc_html__('Activate MCP Adapter', 'gosmtp') . '</button>';
									}else{
										echo '<p>' . esc_html__('Install the official WordPress MCP Adapter plugin with a single click. It bridges your site to any MCP-compatible AI client. GoSMTP requires the MCP Adapter to expose its abilities to AI clients.', 'gosmtp') . '</p>';
										echo '<button type="button" class="button button-primary gosmtp-abilities-btn gosmtp-abilities-adapter-btn" data-action="install">' . esc_html__('Install MCP Adapter', 'gosmtp') . '</button>';
									}
								?>
								<div class="gosmtp-abilities-feedback" data-area="adapter" hidden></div>
							</div>
							</div>

							<!-- Step 3 Application Password -->
							<div class="gosmtp-abilities-row gosmtp-abilities-row-<?php echo ($has_app_password ? 'done' : 'todo'); ?>" data-step="3">
								<button type="button" class="gosmtp-abilities-row-toggle" aria-expanded="<?php echo ($has_app_password ? 'false' : 'true'); ?>">
									<span class="gosmtp-abilities-row-indicator"><?php echo ($has_app_password ? '&#10003;' : '3'); ?></span>
									<span class="gosmtp-abilities-row-text">
										<span class="gosmtp-abilities-row-title"><?php esc_html_e('Application Password generated', 'gosmtp'); ?></span>
										<span class="gosmtp-abilities-row-sub"><?php esc_html_e('Authenticates your AI client against your site', 'gosmtp'); ?></span>
									</span>
									<span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($has_app_password ? 'success' : 'warning'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'gosmtp') : esc_html__('Action needed', 'gosmtp')); ?></span>
								</button>
								<div class="gosmtp-abilities-row-body">
									<p> <?php esc_html_e('Generate a WordPress Application Password for your AI client. The password is shown only once — copy it somewhere safe.', 'gosmtp'); ?></p>
									<div class="gosmtp-abilities-app-pass-fields" data-hidden="true">
										<label class="gosmtp-abilities-field"><span><?php esc_html_e('Username', 'gosmtp'); ?></span><input type="text" readonly class="gosmtp-abilities-username" value="<?php echo esc_attr($username); ?>" /></label>
										<label class="gosmtp-abilities-field"><span><?php esc_html_e('Application Password', 'gosmtp'); ?></span><input type="text" readonly class="gosmtp-abilities-password" value="" /></label>
										<a class="gosmtp-abilities-profile-link" href="<?php echo esc_url(admin_url('profile.php#application-passwords-section')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage in profile', 'gosmtp'); ?> &nbsp;&rarr;</a>
									</div>
									<button type="button" class="button button-secondary gosmtp-abilities-btn gosmtp-abilities-gen-pass-btn"><?php echo ($has_app_password ? esc_html__('Generate a new Application Password', 'gosmtp') : esc_html__('Generate Application Password', 'gosmtp')); ?></button>
									<div class="gosmtp-abilities-feedback" data-area="app-pass" hidden></div>
								</div>
							</div>

							<!-- Step 4 Test Connection -->
							<?php
							$test_ok = !empty($test_status['ok']);
							$test_message = !empty($test_status['message']) ? $test_status['message'] : '';
							$test_class = $test_ok ? 'done' : ($test_message ? 'progress' : 'todo'); ?>

							<div class="gosmtp-abilities-row gosmtp-abilities-row-<?php echo esc_attr($test_class); ?>" data-step="4">
								<button type="button" class="gosmtp-abilities-row-toggle" aria-expanded=" <?php echo ($test_ok ? 'false' : 'true'); ?>">
									<span class="gosmtp-abilities-row-indicator"><?php echo ($test_ok ? '&#10003;' : '4'); ?></span>
									<span class="gosmtp-abilities-row-text">
										<span class="gosmtp-abilities-row-title"><?php esc_html_e('Test the connection', 'gosmtp'); ?></span>
										<span class="gosmtp-abilities-row-sub"><?php esc_html_e('Verify authentication and ability discovery', 'gosmtp'); ?></span>
									</span>
									<span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($test_ok ? 'success' : 'neutral'); ?> gosmtp-abilities-test-pill"><?php echo ($test_ok ? esc_html__('Verified', 'gosmtp') : esc_html__('Pending', 'gosmtp')); ?></span>
								</button>
								<div class="gosmtp-abilities-row-body">
									<p><?php esc_html_e('Verify that your AI client can authenticate against your site and discover GoSMTP abilities.', 'gosmtp'); ?></p>
									<button type="button" class="button button-secondary gosmtp-abilities-btn gosmtp-abilities-test-btn"><?php esc_html_e('Test connection', 'gosmtp'); ?></button>
									<?php
										if($test_message){
											echo '<div class="gosmtp-abilities-test-result gosmtp-abilities-test-result-' . ($test_ok ? 'ok' : 'error') . '">' . esc_html(($test_ok ? __('Success: ', 'gosmtp') : __('Failed: ', 'gosmtp')) . $test_message) . '</div>';
										}else{
											echo '<div class="gosmtp-abilities-test-result" data-hidden="true"></div>';
										}
									?>
								</div>
							</div>
						</div> <!-- .gosmtp-abilities-rows -->
					</div><!-- .gosmtp-abilities-checklist -->

					<!-- AI Client Configuration -->
					<div class="gosmtp-abilities-card gosmtp-abilities-clients">
						<div class="gosmtp-abilities-card-head">
							<h2 class="gosmtp-abilities-h2"><?php esc_html_e('Connect to AI Client', 'gosmtp'); ?></h2>
							<div class="gosmtp-abilities-meta"><?php esc_html_e('Pick a client, copy the snippet, paste it in the right place.', 'gosmtp'); ?></div>
						</div>

						<!-- Hidden data for snippet building -->
						<?php
							echo '<script type="application/json" id="gosmtp-abilities-data">' . wp_json_encode([
								'siteUrl'        => $site_url,
								'mcpServerUrl'   => $mcp_server_url,
								'endpointUrl'    => $endpoint_url,
								'username'       => $username,
								'passwordHolder' => $app_pass_placeholder,
								'userPlaceholder'=> $user_placeholder,
								'serverName'     => 'gosmtp-site',
								'adapterActive'  => $adapter_active,
								'hasAppPassword' => $has_app_password,
								'i18n'           => [
									'copy'          => esc_html__('Copy snippet', 'gosmtp'),
									'copied'        => esc_html__('Copied!', 'gosmtp'),
									'installing'    => esc_html__('Installing...', 'gosmtp'),
									'activating'    => esc_html__('Activating...', 'gosmtp'),
									'generating'    => esc_html__('Generating...', 'gosmtp'),
									'testing'       => esc_html__('Testing...', 'gosmtp'),
									'genErrTitle'   => esc_html__('Could not generate the password.', 'gosmtp'),
									'testOkPrefix'  => esc_html__('Success:', 'gosmtp'),
									'testFailPrefix'=> esc_html__('Failed:', 'gosmtp'),
								],
							]) . '</script>';

							// Segmented tab bar
							$clients = [
								'claude-desktop' => ['label' => esc_html__('Claude Desktop', 'gosmtp'), 'icon' => 'C'],
								'claude-code'    => ['label' => esc_html__('Claude Code CLI', 'gosmtp'), 'icon' => '>_'],
								'cursor'         => ['label' => esc_html__('Cursor', 'gosmtp'),          'icon' => 'Cu'],
								'vscode'         => ['label' => esc_html__('VS Code', 'gosmtp'),          'icon' => 'VS'],
								'antigravity'    => ['label' => esc_html__('Antigravity', 'gosmtp'), 'icon' => 'AG'],
								'opencode'       => ['label' => esc_html__('OpenCode', 'gosmtp'), 'icon' => 'OC'],
								'gemini-cli'     => ['label' => esc_html__('Gemini CLI', 'siteseo-pro'),       'icon' => 'G', 'deprecated' => true],
							];

							echo '<div class="gosmtp-abilities-segmented" role="tablist" aria-label="' . esc_attr__('AI clients', 'gosmtp') . '">';
							$first = true;
							foreach($clients as $id => $data){
								$badge = '';
								if(!empty($data['deprecated'])){
									$badge = ' <span class="gosmtp-deprecated-badge">' . esc_html__('Deprecated', 'gosmtp') . '</span>';
								}
								echo '<button type="button" class="gosmtp-abilities-segmented-btn' . ($first ? ' active' : '') . '" data-client="' . esc_attr($id) . '" role="tab" aria-selected="' . ($first ? 'true' : 'false') . '">' . esc_html($data['label']) . wp_kses_post($badge) . '</button>';
								$first = false;
							}
						?>

						</div>

						<!-- Client panel -->
						<div class="gosmtp-abilities-client-panel">
							<div class="gosmtp-abilities-client-meta">
								<span class="gosmtp-abilities-client-path-label"></span>
								<code class="gosmtp-abilities-client-path-value"></code>
							</div>

							<div class="gosmtp-abilities-snippet-wrap">
								<pre class="gosmtp-abilities-snippet" tabindex="0"></pre>
								<button type="button" class="gosmtp-abilities-copy-btn">
									<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
									<span class="gosmtp-abilities-copy-btn-label"><?php esc_html_e('Copy', 'gosmtp'); ?></span>
								</button>
							</div>

							<ol class="gosmtp-abilities-instructions"></ol>
						</div> <!-- .gosmtp-abilities-client-panel -->

					</div> <!-- gosmtp-abilities-clients -->

				</div> <!-- gosmtp-abilities-main (LEFT) -->

				<!-- RIGHT COLUMN (sidebar) -->
				<div class="gosmtp-abilities-side">

					<!-- abilites enabled card -->
					<div class="gosmtp-abilities-card gosmtp-abilities-side-card">
						<h3 class="gosmtp-abilities-h3"><?php esc_html_e('Enable Abilities', 'gosmtp'); ?></h3>
						<div class="gosmtp-abilities-checkbox">
							<div class="gosmtp-abilities"><?php esc_html_e('Enable AI Abilities', 'gosmtp'); ?></div>
							<?php 
								$smtp_options = get_option('gosmtp_options', []);
								$is_enabled = !empty($smtp_options['ai_abilities']['enabled']) ? 1 : 0;
							?>
							<input class="gosmtp_ai_abilities" id="gosmtp_ai_abilities" name="ai_abilities" type="checkbox" value="1" data-nonce="<?php echo esc_attr(wp_create_nonce('gosmtp_ajax')); ?>" <?php checked(1, $is_enabled); ?>>
						</div>
						<p class="description"><?php esc_html_e('Allow AI clients to securely access GoSMTP features through the WordPress Abilities API.', 'gosmtp'); ?></p>
					</div>

					<!-- Status card -->
					<div class="gosmtp-abilities-card gosmtp-abilities-side-card">
						<h3 class="gosmtp-abilities-h3"><?php esc_html_e('Site status', 'gosmtp'); ?></h3>
						<dl class="gosmtp-abilities-status-list">
							<div><dt><?php esc_html_e('Abilities API', 'gosmtp'); ?></dt><dd><span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($abilities_api_available ? 'success' : 'neutral'); ?>"><?php echo ($abilities_api_available ? esc_html__('Available', 'gosmtp') : esc_html__('Unavailable', 'gosmtp')); ?></span></dd></div>
							<div><dt><?php esc_html_e('MCP Adapter', 'gosmtp'); ?></dt><dd><span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($adapter_active ? 'success' : ($adapter_installed ? 'warning' : 'neutral')); ?>"><?php echo esc_html($adapter_label); ?></span></dd></div>
							<div><dt><?php esc_html_e('Application Password', 'gosmtp'); ?></dt><dd><span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($has_app_password ? 'success' : 'neutral'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'gosmtp') : esc_html__('Not generated', 'gosmtp')); ?></span></dd></div>
							<div><dt><?php esc_html_e('Last test', 'gosmtp'); ?></dt><dd><span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo ($test_ok ? 'success' : ($test_message ? 'error' : 'neutral')); ?>"><?php echo ($test_ok ? esc_html__('Tested', 'gosmtp') : ($test_message ? esc_html__('Failed', 'gosmtp') : esc_html__('Never', 'gosmtp'))); ?></span></dd></div>
							<div><dt><?php esc_html_e('GoSMTP Pro', 'gosmtp'); ?></dt><dd><span class="gosmtp-abilities-pill gosmtp-abilities-pill-<?php echo defined('GOSMTP_PREMIUM') ? 'success' : 'neutral'; ?>"><?php echo defined('GOSMTP_PREMIUM') ? esc_html__('Active', 'gosmtp') : esc_html__('Inactive', 'gosmtp'); ?></span></dd></div>
						</dl>
					</div>

					<!-- Quick tips card -->
					<div class="gosmtp-abilities-card gosmtp-abilities-side-card">
						<h3 class="gosmtp-abilities-h3"><?php esc_html_e('Quick tips', 'gosmtp'); ?></h3>
						<ul class="gosmtp-abilities-tip-list">
							<li><?php esc_html_e('Generate one Application Password per AI client so you can revoke them individually.', 'gosmtp'); ?></li>
							<li><?php esc_html_e('The password is shown only once. Copy it before navigating away.', 'gosmtp'); ?></li>
							<li><?php esc_html_e('Application Passwords are stored in your user profile and can be revoked at any time.', 'gosmtp'); ?></li>
							<li><?php esc_html_e('Pro abilities (Email Logs, Reports, Export) are only available when GoSMTP Pro is active.', 'gosmtp'); ?></li>
						</ul>
					</div>

					<!-- Resources card -->
					<div class="gosmtp-abilities-card gosmtp-abilities-side-card">
						<h3 class="gosmtp-abilities-h3"><?php esc_html_e('Resources', 'gosmtp'); ?></h3>
						<ul class="gosmtp-abilities-resource-list">
							<li><a href="https://gosmtp.net/docs/ai-tools/how-to-setup-mcp-adapter-and-abilities/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('GoSMTP documentation', 'gosmtp'); ?></a><span class="gosmtp-abilities-resource-sub"><?php esc_html_e('Guides and tutorials', 'gosmtp'); ?></span></li>
							<li><a href="https://modelcontextprotocol.io/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Model Context Protocol', 'gosmtp'); ?></a><span class="gosmtp-abilities-resource-sub"><?php esc_html_e('What MCP is and how it works', 'gosmtp'); ?></span></li>
							<li><a href="https://wordpress.org/plugins/mcp-adapter/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('WordPress MCP Adapter', 'gosmtp'); ?></a><span class="gosmtp-abilities-resource-sub"><?php esc_html_e('Official plugin repository', 'gosmtp'); ?></span></li>
						</ul>
					</div>

				</div> <!-- .gosmtp-abilities-side -->
			</div><!-- .gosmtp-abilities-layout -->
		</div><!-- .gosmtp-abilities-root -->
		<?php
	}

	/**
	 * Render the list of abilities GoSMTP exposes through the MCP adapter.
	 * Merges Free and (when active) Pro ability catalogues.
	 *
	 * @return string
	 */
	static function render_abilities_list(){
		$groups = self::get_registered_abilities();

		if(empty($groups)){
			return '<p class="gosmtp-abilities-empty">' . esc_html__('No GoSMTP abilities are currently exposed. Activate the MCP Adapter to surface them.', 'gosmtp') . '</p>';
		}

		$html = '<div class="gosmtp-abilities-list">';
		foreach($groups as $group => $items){
			$html .= '<div class="gosmtp-abilities-group">';
			$html .= '<h4>' . esc_html($group) . '</h4>';
			$html .= '<ul>';
			foreach($items as $ability){
				$html .= '<li>';
				$html .= '<span class="gosmtp-abilities-ability-name">' . esc_html($ability['label']) . '</span>';
				if(!empty($ability['description'])){
					$html .= '<span class="gosmtp-abilities-ability-desc">' . esc_html($ability['description']) . '</span>';
				}
				if(!empty($ability['pro'])){
					$html .= '<span class="gosmtp-abilities-ability-pro">' . esc_html__('Pro', 'gosmtp') . '</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * The catalogue of GoSMTP abilities exposed to MCP clients. Mirrors the
	 * abilities registered in main/abilitiesregister.php (Free) and
	 * main/abilitiesregister.php in Pro. The Pro section is only merged when
	 * the GoSMTP Pro plugin is installed and active.
	 *
	 * @return array
	 */
	static function get_registered_abilities(){
		$free = [
			esc_html__('Settings', 'gosmtp') => [
				[
					'label'       => esc_html__('Get GoSMTP Settings', 'gosmtp'),
					'description' => esc_html__('Returns global from email/name, force flags, return path and logging toggles. Credentials are never exposed.', 'gosmtp'),
				],
			],
			esc_html__('Mailer', 'gosmtp') => [
				[
					'label'       => esc_html__('List Configured Mailers', 'gosmtp'),
					'description' => esc_html__('Lists configured SMTP connections with their nickname, mailer type and backup connection.', 'gosmtp'),
				],
			],
			esc_html__('Test', 'gosmtp') => [
				[
					'label'       => esc_html__('Send a Test Email', 'gosmtp'),
					'description' => esc_html__('Sends a test email through the configured mailer and reports the result.', 'gosmtp'),
				],
			],
		];

		// Allow GoSMTP Pro to register additional abilities.
		return apply_filters('gosmtp_registered_abilities', $free);
		
	}

	/**
	 * Scan installed plugins for the MCP Adapter (folder prefix: mcp-adapter/).
	 *
	 * @return string
	 */
	static function get_installed_mcp_adapter_file(){

		if(!function_exists('get_plugins')){
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$all_plugins = get_plugins();
		$target_file = 'mcp-adapter/mcp-adapter.php';
        
		if(isset($all_plugins[ $target_file ])){
			return $target_file;
		}
        
		return '';
	}

	/**
	 * Read the installed MCP Adapter version from the plugin headers.
	 *
	 * @return string Empty string when the adapter is not installed.
	 */
	static function get_installed_mcp_adapter_version(){

		$file = self::get_installed_mcp_adapter_file();

		if('' === $file){
			return '';
		}

		if(!function_exists('get_plugins')){
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		if(!isset($all_plugins[ $file ]['Version'])){
			return '';
		}
		return (string)$all_plugins[ $file ]['Version'];
	}

	/**
	 * Does the current user already have an MCP app password created by GoSMTP?
	 *
	 * @return bool
	 */
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

	/**
	 * Read the saved test-connection status for the current user.
	 *
	 * @return array
	 */
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

	/**
	 * Persist the test-connection status for the current user.
	 *
	 * @param bool   $ok
	 * @param string $message
	 * @return void
	 */
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
