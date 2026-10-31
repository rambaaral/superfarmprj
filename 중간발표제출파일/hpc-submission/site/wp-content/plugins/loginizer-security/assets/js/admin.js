
jQuery(document).ready(function($) {
	$('#captcha_wc_block_checkout').on('change', function(){
		let jEle = $(this),
		other_checkout = $('#captcha_wc_checkout');
		
		if(other_checkout.is(':checked')){
			other_checkout.prop('checked', false);
			alert('A Checkout could be either classic or block based, can\'t be both');
		}
	});
	
	$('#captcha_wc_checkout').on('change', function(){
		let jEle = $(this),
		other_checkout = $('#captcha_wc_block_checkout');

		if(other_checkout.is(':checked')){
			other_checkout.prop('checked', false);
			alert('A Checkout could be either classic or block based, can\'t be both');
		}
	});

	// ======================= Firewall tab =================

	// All Security Rules Section
	$('#lz_pro_firewall_type').on('change', function(){
		var jEle = $(this);

		if(jEle.val() === 'waf_firewall'){
			$('.lz_pro_security_rules').css('display', 'block');
			$('.lz_pro_nG_rules').css('display', 'none');
		}

		if(jEle.val() === '8g_firewall'){
			$('.lz_pro_security_rules').css('display', 'none');
			$('.lz_pro_nG_rules').css('display', 'block');
		}
	});

	// Select and diselect all security rules
	$('#lz_pro_select_all_rules').on('click', function(){
		$(this).closest('.lz_pro_security_rules').find('input[type="checkbox"]').prop('checked', true);
	});

	$('#lz_pro_diselect_all_rules').on('click', function(){
		$(this).closest('.lz_pro_security_rules').find('input[type="checkbox"]').prop('checked', false);
	});

	// ============= Country management tab ==================

	// Search country name suggestion
	$('#lz_pro_country_search_chars').on('input', function(){
		var value = $(this).val().toLowerCase().trim(),
		visible = false;

		$('#lz_pro_country_container li').each(function(){
			var match = $(this).text().toLowerCase().indexOf(value) > -1;

			$(this).toggle(match);
			
			if(match){
				visible = true;
			}
		});
		
		if(visible){
			$('.lz_pro_country_header').show();
			$('#lz_pro_country_container').show();
			$('#lz_pro_country_container li:last').hide();
		}else{
			$('#lz_pro_country_container li:last').show();
		}
	});

	loginizer_pro_update_select_all();

	$('.lz_pro_select_country').on('change', function(){
		loginizer_pro_update_select_all();
	});

	function loginizer_pro_update_select_all(){
		var cb_len = $('.lz_pro_select_country').length,
		cb_checked = $('.lz_pro_select_country:checked').length;
		$('#lz_pro_country_header_checkbox').prop('checked', cb_len == cb_checked);
	}

	// Add / remove selected country
	$('.lz_pro_select_country').on('change', function(){
		var jEle = $(this),
		country_code = jEle.attr('id').replace('lz_pro_cb_code_', '').trim(),
		country_name = jEle.parent('label').text().trim();

		if(jEle.is(':checked')){
			var append_html = '<div><span>' + country_name +'</span><span class="dashicons dashicons-no-alt lz_pro_remove_country" id="' + country_code + '"></span></div>';
			$('.lz_pro_blocked_country_container').append($(append_html));
		}else{
			$('.lz_pro_blocked_country_container').find('#' + country_code).parent('div').remove();
		}
	});
	
	// Used for closing the country chip in the Country block tab
	$('.lz_pro_blocked_country_container').on('click', '.lz_pro_remove_country', function(){
		var jEle = $(this),
		id = jEle.attr('id');

		$('#lz_pro_cb_code_' + id).prop('checked', false);
		jEle.parent('div').remove();
	});

	// =============== Update .htaccess for the Firewall ================
	$('#loginizer_htaccess_popup').on('click', function(e){
		e.preventDefault();
		$('.loginizer-htaccess-popup-modal').show();
	});

	$('#loginizer_htaccess_popup_close').on('click', function(e){
		e.preventDefault();
		$('.loginizer-htaccess-popup-modal').hide();
	});
	
	$('#lz-cb-firewall-loader').on('change', function(){
		let loader_value = $(this).val(),
		server_loader_btn = $('.lz-enable-server-loader');
		if(loader_value == 'server'){
			server_loader_btn.show();
		} else {
			server_loader_btn.hide();
		}
	})

	$('#lz_pro_configure_htaccess').on('click', function(){

		var task = $(this).data('task'),
		auto_prepend_file_action = $('#loginizer_auto_prepended_file_action').val();

		$.ajax({
			url : loginizer_security.url,
			method : 'POST',
			data : {
				action : 'loginizer_pro_enable_auto_prepend',
				security : loginizer_security.nonce,
				task : task,
				file_action : auto_prepend_file_action
			},
			success : function(res){
				// console.log(res);
				if(res.success){
					$('.loginizer-htaccess-popup-modal').hide();
					location.reload();
				}else{
					$('.loginizer_htaccess_popup_error').text(res.data).show();
					$('#lz_pro_configure_htaccess').hide();
				}
			},
			error : function(xhr){
				console.log(xhr.status);
			}
		});
	});
	
	var total_buttons = $('.lz-download-backup-btn').length;
	var clicked_button_count = 0;
	if(total_buttons == 0){
		$('#lz_pro_configure_htaccess').prop('disabled', false);
	}

	$('.lz-download-backup-btn').on('click', function(){
		clicked_button_count++;
		if(total_buttons == clicked_button_count){
			$('#lz_pro_configure_htaccess').prop('disabled', false);
		}
	});

	// Download Database
	$('.lz-country-db-status-wrapper').on('click', '.lz-country-db-download, #lz-update-cb-db-link', function(e){
		e.preventDefault();
		
		let jEle = $(this);
		if(jEle.attr('disabled')){
			return;
		}

		jEle.attr('disabled', true);
		let old_text = jEle.text();
		jEle.text('Downloading');

		let spinner = jEle.siblings('.spinner');
		spinner.addClass('is-active');
		
		$.ajax({
			url:loginizer_security.url,
			method: 'POST',
			data: {
				security: loginizer_security.nonce,
				action: 'loginizer_pro_cb_download_db',
			}, success:function(res){
				if(res.success){
					window.location.reload();
					return;
				}
				
				if(res.data){
					alert(res.data);
					return;
				}
				
				alert('There was some issue downloading the Database file');
			}, error: function (request, status, error) {
        alert(error);
			}
    }).always(function() {
			jEle.attr('disabled', false);
			jEle.text(old_text);
			spinner.removeClass('is-active');
		});
	});
	
	// 2FA Enforce admin script
	$('#lz_2fa_enforce_type').on('change', function(){
		let obj = $(this);
		
		if(obj.val() === 'grace_enforce'){
			$('.lz_2fa_grace_time').show();
		}else{
			$('.lz_2fa_grace_time').hide();
		}
	});
	
	$('#lz_email_2fa_enforce').on('change', function(){
		let obj = $(this);
		
		if(obj.is(':checked')){
			$('.lz_2fa_enforce_block').hide();
		}else{
			$('.lz_2fa_enforce_block').show();
		}
	});

	// ======================= Login Page Branding =================

	if($.fn.wpColorPicker){
		$('.lz-color-picker').wpColorPicker();
	}

	$('input[name="layout"]').on('change', function(){
		$('#lz-row-panel_width').toggle(this.value != 'center');
	});

	$('input[name="logo_id"]').on('change', function(){
		$('#lz-row-logo_width').toggle(this.value != '');
	});

	$('.lz-media-select').on('click', function(e){
		e.preventDefault();

		let wrap = $(this).closest('.lz-media-field'),
		frame = wp.media({library: {type: 'image'}, multiple: false});

		frame.on('select', function(){
			let img = frame.state().get('selection').first().toJSON();

			wrap.find('input').val(img.id).trigger('change');
			wrap.find('img').attr('src', img.sizes && img.sizes.medium ? img.sizes.medium.url : img.url).show();
			wrap.find('.lz-media-remove').show();
		});

		frame.open();
	});

	$('.lz-media-remove').on('click', function(e){
		e.preventDefault();

		let wrap = $(this).closest('.lz-media-field');

		wrap.find('input').val('').trigger('change');
		wrap.find('img').attr('src', '').hide();
		$(this).hide();
	});
});