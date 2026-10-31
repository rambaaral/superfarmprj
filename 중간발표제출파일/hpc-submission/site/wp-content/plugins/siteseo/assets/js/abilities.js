/*
* SiteSEO - Abilities / MCP
* Client-side interactions for the Abilities settings page.
*/
jQuery(document).ready(function($){

	if(typeof siteseo_abilities === 'undefined'){
		return;
	}

	var CFG = window.siteseo_abilities;
	var $doc = $(document);

	// =====================================================================
	// State
	// =====================================================================

	var state = {
		password: null,           // freshly generated application password (memory only)
		username: '',
		activeClient: 'claude-desktop'
	};

	// =====================================================================
	// Helpers
	// =====================================================================

	function toast(message, type){
		type = type || 'success';

		var el = $('<div />')
			.addClass('siteseo-toast')
			.addClass(type)
			.html('<span class="dashicons dashicons-yes"></span> ' + message);

		$('body').append(el);
		el.fadeIn(300).delay(3000).fadeOut(300, function(){
			$(this).remove();
		});
	}

	function setMsg($area, message, isError){
		if(!$area || !$area.length){
			return;
		}
		$area.removeClass('is-error is-success');
		if(message){
			$area
				.attr('hidden', false)
				.addClass(isError ? 'is-error' : 'is-success')
				.html(message);
		}else{
			$area.attr('hidden', true).empty();
		}
	}

	function basicAuthHeader(){
		try{
			return 'Basic ' + window.btoa(state.username + ':' + state.password);
		}catch(e){
			return 'Basic <base64-of-username:application-password>';
		}
	}

	// =====================================================================
	// Step 2 - MCP Adapter install / activate
	// =====================================================================

	$doc.on('click', '.siteseo-abilities-adapter-btn', function(){
		var $btn = $(this);
		var action = $btn.data('action');
		var $area = $('.siteseo-abilities-feedback[data-area="adapter"]');

		if($btn.prop('disabled')){
			return;
		}

		$btn.prop('disabled', true);
		var original = $btn.html();
		$btn.html(action === 'install' ? CFG.i18n_installing : CFG.i18n_activating);
		setMsg($area, '');

		$.ajax({
			url: CFG.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_install_mcp_adapter',
				adapter_action: action,
				nonce: CFG.nonce
			},
			success: function(response){
				if(response.success){
					setMsg($area, response.data.message, false);
					toast(response.data.message);
					// Reload to refresh the whole setup status (abilities, adapter state).
					setTimeout(function(){ window.location.reload(); }, 1200);
				}else{
					var msg = (response.data && (response.data.message || response.data)) || 'Install failed.';
					setMsg($area, msg, true);
					$btn.prop('disabled', false).html(original);
				}
			},
			error: function(){
				setMsg($area, 'Unable to contact the server. Please try again.', true);
				$btn.prop('disabled', false).html(original);
			}
		});
	});

	// =====================================================================
	// Step 3 - Application Password generation
	// =====================================================================

	$doc.on('click', '.siteseo-abilities-gen-pass-btn', function(){
		var $btn = $('.siteseo-abilities-gen-pass-btn');
		var $area = $('.siteseo-abilities-feedback[data-area="app-pass"]');

		if($btn.prop('disabled')){
			return;
		}

		$btn.prop('disabled', true);
		var original = $btn.html();
		$btn.html(CFG.i18n_generating);
		setMsg($area, '');

		$.ajax({
			url: CFG.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_generate_app_password',
				nonce: CFG.nonce
			},
			success: function(response){
				if(response.success && response.data.password){
					state.password = response.data.password;
					state.username = response.data.username || state.username;

					$('.siteseo-abilities-app-pass-fields')
						.attr('data-hidden', 'false')
						.show();

					$('.siteseo-abilities-username').val(state.username);
					$('.siteseo-abilities-password').val(state.password);

					setMsg($area, response.data.message, false);
					toast(response.data.message);

					renderClientSnippet();
					$btn.prop('disabled', false).html(original);
				}else{
					var msg = (response.data && (response.data.message || response.data)) || CFG.i18n_genErrTitle;
					setMsg($area, msg, true);
					$btn.prop('disabled', false).html(original);
				}
			},
			error: function(){
				setMsg($area, 'Unable to contact the server. Please try again.', true);
				$btn.prop('disabled', false).html(original);
			}
		});
	});

	// =====================================================================
	// Step 4 - Test connection (client-side first; server fallback)
	// =====================================================================

	$doc.on('click', '.siteseo-abilities-test-btn', function(){
		var $btn = $('.siteseo-abilities-test-btn');
		var $result = $('.siteseo-abilities-test-result');
		var $pill = $('.siteseo-abilities-test-pill');

		if($btn.prop('disabled')){
			return;
		}

		if(!state.password){
			$result.attr('data-hidden', 'false').show()
				.removeClass('siteseo-abilities-test-result-ok siteseo-abilities-test-result-error')
				.addClass('siteseo-abilities-test-result-error')
				.html('Please generate an Application Password first.');
			return;
		}

		$btn.prop('disabled', true);
		var original = $btn.html();
		$btn.html(CFG.i18n_testing);
		$pill.removeClass('siteseo-abilities-pill-success siteseo-abilities-pill-error siteseo-abilities-pill-warning siteseo-abilities-pill-neutral').addClass('siteseo-abilities-pill-warning').text('Testing...');
		$result.attr('data-hidden', 'false').show().empty();

		var endpoint = CFG.endpoint_url;
		var start = Date.now();

		// Try a direct browser fetch with Basic auth. If CORS blocks it,
		// fall back to the server-side tester.
		$.ajax({
			url: endpoint,
			type: 'GET',
			headers: { 'Authorization': basicAuthHeader() },
			timeout: 15000,
			dataType: 'json'
		}).done(function(data, textStatus, jqXHR){
			var elapsed = Date.now() - start;
			if(jqXHR.status >= 200 && jqXHR.status < 300 && Array.isArray(data)){
				var count = 0;
				$.each(data, function(_, ability){
					var name = (ability && (ability.name || ability.id)) || '';
					if(typeof name === 'string' && name.indexOf('siteseo-') === 0){
						count++;
					}
				});
				showTestResult(true, 'Authenticated with your Application Password and discovered ' + count + ' SiteSEO abilities in ' + elapsed + 'ms. Your site is ready to connect an AI client below.');
			}else{
				showTestResult(false, 'The abilities endpoint responded but the data was unexpected. Check the MCP Adapter is active and try again.');
			}
		}).fail(function(jqXHR){
			// Likely CORS - fall back to server-side check.
			serverTestConnection();
		}).always(function(){
			$btn.prop('disabled', false).html(original);
		});

		function showTestResult(ok, message){
			$result.attr('data-hidden', 'false').show()
				.removeClass('siteseo-abilities-test-result-ok siteseo-abilities-test-result-error')
				.addClass(ok ? 'siteseo-abilities-test-result-ok' : 'siteseo-abilities-test-result-error')
				.html((ok ? CFG.i18n_testOkPrefix + ' ' : CFG.i18n_testFailPrefix + ' ') + message);
			$pill.removeClass('siteseo-abilities-pill-success siteseo-abilities-pill-error siteseo-abilities-pill-neutral')
				.addClass(ok ? 'siteseo-abilities-pill-success' : 'siteseo-abilities-pill-error')
				.text(ok ? 'Verified' : 'Failed');

			// Toggle the row's "done" marker so the progress bar reflects the result.
			$btn.closest('.siteseo-abilities-row').toggleClass('siteseo-abilities-row-done', !!ok);
			$btn.closest('.siteseo-abilities-row').toggleClass('siteseo-abilities-row-progress', !ok);

			if(ok){
				toast(message);
			}

			// Persist the result server-side so the pill keeps its state on reload.
			persistTestStatus(ok, message);
		}

		function persistTestStatus(ok, message){
			$.ajax({
				url: CFG.ajax_url,
				type: 'POST',
				data: {
					action: 'siteseo_save_test_status',
					ok: ok ? 1 : 0,
					message: message || '',
					nonce: CFG.nonce
				}
			});
		}

		function serverTestConnection(){
			$.ajax({
				url: CFG.ajax_url,
				type: 'POST',
				data: {
					action: 'siteseo_test_mcp_connection',
					nonce: CFG.nonce,
					username : state.username,
					password : state.password
				},
				success: function(response){
					if(response.success){
						showTestResult(true, response.data.message);
					}else{
						var msg = (response.data && (response.data.message || response.data)) || 'Connection test failed.';
						showTestResult(false, msg);
					}
				},
				error: function(){
					showTestResult(false, 'Could not reach the abilities endpoint. Check the site is reachable and try again.');
				}
			});
		}
	});

	// =====================================================================
	// AI Client Configuration - snippet builder
	// =====================================================================

	var CLIENTS = {
		'claude-desktop': {
			pathLabel: 'Save to:',
			pathValue: 'macOS: ~/Library/Application Support/Claude/claude_desktop_config.json\nWindows: %APPDATA%\\Claude\\claude_desktop_config.json',
			instructions: [
				'Open the file for your OS shown above (create it if it doesn\'t exist). On Linux, use the Claude Code CLI tab instead.',
				'Paste the snippet above into the file.',
				'Restart Claude Desktop to load the SiteSEO abilities.'
			]
		},
		'claude-code': {
			pathLabel: 'Run in:',
			pathValue: 'Terminal (Claude Code CLI)',
			instructions: [
				'Open a terminal in your project directory.',
				'Paste the command above and press enter.',
				'Exit Claude Code (/exit) and start a new session to load the SiteSEO abilities.'
			]
		},
		'cursor': {
			pathLabel: 'Add in:',
			pathValue: 'Cursor Settings → MCP → Add Server',
			instructions: [
				'In Cursor, go to Settings → MCP → Add Server.',
				'Paste the snippet above as a new server.',
				'Enable the server in the MCP list to load the SiteSEO abilities.'
			]
		},
		'vscode': {
			pathLabel: 'Save to:',
			pathValue: '.vscode/mcp.json',
			instructions: [
				'Create a .vscode/mcp.json file in your project root.',
				'Paste the snippet above into the file.',
				'Reload the VS Code window to discover the SiteSEO MCP server.'
			]
		},
		'gemini-cli': {
			pathLabel: 'Save to:',
			pathValue: '~/.gemini/settings.json',
			instructions: [
				'Open ~/.gemini/settings.json (create it if it doesn\'t exist).',
				'Paste the snippet above into the file.',
				'Restart Gemini CLI to load the SiteSEO abilities.'
			]
		},
		'antigravity': {
			pathLabel: 'Save to:',
			pathValue: '~/.gemini/config/mcp_config.json',
			instructions: [
				'Open ~/.gemini/config/mcp_config.json (create it if it doesn\'t exist).',
				'Paste the snippet above into the file.',
				'Restart Antigravity CLI to load the SiteSEO abilities.'
			]
		}
	};

	function buildSnippet(clientId){
		var data = window.siteseoAbilitiesData || {};
		var siteUrl = data.siteUrl || '';
		var mcpServerUrl = data.mcpServerUrl || (siteUrl + 'wp-json/mcp/mcp-adapter-default-server');
		var serverName = data.serverName || 'siteseo-site';
		var username = state.username || data.username || data.userPlaceholder || '<your-username>';
		var password = state.password || data.passwordHolder || 'your-application-password';

		// stdio config used by Claude Desktop / Cursor / Gemini CLI.
		var stdioConfig = {
			command: 'npx',
			args: ['-y', '@automattic/mcp-wordpress-remote'],
			env: {
				WP_API_URL: siteUrl + 'wp-json/mcp/mcp-adapter-default-server',
				WP_API_USERNAME: username,
				WP_API_PASSWORD: password
			}
		};

		var basic;
		try{
			basic = window.btoa(username + ':' + password);
		}catch(e){
			basic = '<base64-of-username:application-password>';
		}

		switch(clientId){
			case 'claude-code':
				return 'claude mcp add ' + serverName + ' \\\n' +
					'  --transport http "' + mcpServerUrl + '" \\\n' +
					'  --header "Authorization: Basic ' + basic + '"';

			case 'vscode': {
				var obj = {
					servers: {}
				};
				obj.servers[serverName] = {
					type: 'http',
					url: mcpServerUrl,
					headers: { 'Authorization': 'Basic ' + basic }
				};
				return JSON.stringify(obj, null, 2);
			}

			case 'cursor': {
				var cur = { name: 'SiteSEO Site' };
				$.extend(cur, stdioConfig);
				return JSON.stringify(cur, null, 2);
			}

			case 'gemini-cli': {
				var gem = { mcpServers: {} };
				gem.mcpServers[serverName] = stdioConfig;
				return JSON.stringify(gem, null, 2);
			}

			case 'antigravity': {
				var agy = {
					mcpServers: {}
				};
				agy.mcpServers[serverName] = {
					serverUrl: mcpServerUrl,
					headers: { 'Authorization': 'Basic ' + basic }
				};
				return JSON.stringify(agy, null, 2);
			}

			case 'claude-desktop':
			default: {
				var desk = { mcpServers: {} };
				desk.mcpServers[serverName] = stdioConfig;
				return JSON.stringify(desk, null, 2);
			}
		}
	}

	function renderClientSnippet(){
		var clientId = state.activeClient;
		var meta = CLIENTS[clientId] || CLIENTS['claude-desktop'];
		var snippet = buildSnippet(clientId);

		$('.siteseo-abilities-client-path-label').text(meta.pathLabel);
		$('.siteseo-abilities-client-path-value').text(meta.pathValue);

		var $snippet = $('.siteseo-abilities-snippet');
		$snippet.text(snippet);

		var $instructions = $('.siteseo-abilities-instructions').empty();
		if(meta.unsupported){
			$snippet.addClass('unsupported');
			$('.siteseo-abilities-copy-btn').prop('disabled', true);
		}else{
			$snippet.removeClass('unsupported');
			$('.siteseo-abilities-copy-btn').prop('disabled', false);
		}

		$.each(meta.instructions, function(i, line){
			$instructions.append('<li>' + line + '</li>');
		});
	}

	$doc.on('click', '.siteseo-abilities-segmented-btn', function(){
		var $tab = $(this);
		$('.siteseo-abilities-segmented-btn').removeClass('active').attr('aria-selected', 'false');
		$tab.addClass('active').attr('aria-selected', 'true');
		state.activeClient = $tab.data('client');
		renderClientSnippet();
	});

	// Row accordion toggling
	$doc.on('click', '.siteseo-abilities-row-toggle', function(){
		var $btn = $(this);
		var $body = $btn.next('.siteseo-abilities-row-body');
		var expanded = $btn.attr('aria-expanded') === 'true';
		$btn.attr('aria-expanded', expanded ? 'false' : 'true');
		if(expanded){
			$body.hide();
		}else{
			$body.show();
		}
	});

	$doc.on('click', '.siteseo-abilities-copy-btn', function(){
		var $btn = $(this);
		var text = $('.siteseo-abilities-snippet').text();

		if(!text){
			return;
		}

		var done = function(){
			var $label = $btn.find('.siteseo-abilities-copy-btn-label');
			var original = $label.text();
			$btn.addClass('is-copied');
			$label.text('Copied!');
			setTimeout(function(){
				$btn.removeClass('is-copied');
				$label.text(original);
			}, 1600);
		};

		if(navigator.clipboard && navigator.clipboard.writeText){
			navigator.clipboard.writeText(text).then(done, function(){
				legacyCopy(text); done();
			});
		}else{
			legacyCopy(text); done();
		}
	});

	function legacyCopy(text){
		var $ta = $('<textarea />').val(text).appendTo('body').select();
		try{ document.execCommand('copy'); }catch(e){}
		$ta.remove();
	}

	// =====================================================================
	// Boot
	// =====================================================================

	$(function(){
		// Pull server-injected config from the <script type="application/json"> tag.
		var $dataTag = $('#siteseo-abilities-data');
		if($dataTag.length){
			try{
				window.siteseoAbilitiesData = JSON.parse($dataTag.text());
				var d = window.siteseoAbilitiesData;
				if(d.username){
					state.username = d.username;
				}
			}catch(e){
				window.siteseoAbilitiesData = {};
			}
		}

		// Pre-fill i18n strings if the localized object didn't provide them.
		var defaults = {
			i18n_installing:  'Installing...',
			i18n_activating:  'Activating...',
			i18n_generating:  'Generating...',
			i18n_testing:     'Testing...',
			i18n_genErrTitle: 'Could not generate the password.',
			i18n_testOkPrefix:'Success:',
			i18n_testFailPrefix: 'Failed:'
		};
		$.each(defaults, function(k, v){
			if(typeof CFG[k] === 'undefined'){
				CFG[k] = v;
			}
		});

		renderClientSnippet();
	});

});