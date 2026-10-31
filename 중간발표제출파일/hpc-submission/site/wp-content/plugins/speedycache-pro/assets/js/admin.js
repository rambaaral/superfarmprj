jQuery(document).ready(function($) {

  $('#speedycache_object_host').on('input', function(){
    let host = $('#speedycache_object_host').val();
    let $portRow = $('#speedycache_object_port').closest('tr');
    if(host.trim().endsWith('.sock')){
      $portRow.hide();
    } else {
      $portRow.show();
    }
  });

	$('.speedycache-test-notice .speedycache-custom-dismiss').on('click', function(e) {
		e.preventDefault();
		$('.speedycache-test-notice').slideUp();
			$.ajax({
				url: speedycache_pro_ajax.url,
				type: 'POST',
				data: {
					action: 'speedycache_dismiss_test_notice',
					security: speedycache_pro_ajax.nonce
				}
			});
		});	

	$('.speedycache-copy-test-settings').on('click', function(e){
		e.preventDefault();
		$.ajax({
			method : 'GET',
			url : speedycache_pro_ajax.url + '?action=speedycache_copy_test_settings&security='+speedycache_pro_ajax.nonce,
			success: function(res){
				if(res.success){
					alert('The settings has been successfully saved!');
					location.reload(true);
					return;
				}
				if(res.data){
					alert(res.data);
				}
			}
		});
	});

	$('#speedycache-update-cf-rules').on('click', function(e){
		e.preventDefault();
		
		let jEle = jQuery(event.target),
		has_error = false;
		
		jEle.find('span.speedycache-spinner').addClass('speedycache-spinner-active');
		$.ajax({
			method : 'GET',
			url : speedycache_pro_ajax.url + '?action=speedycache_pro_update_cf_rules&security='+speedycache_pro_ajax.nonce,
			success: function(res){
				if(res.success){
					alert('The rules has been successfully Updated!');
					return;
				}
				
				has_error = true;
				if(res.data){
					alert(res.data);
				}
			}
		})
		.always(function(){
			jEle.find('span.speedycache-spinner')?.removeClass('speedycache-spinner-active');
			
			// Need to show a tick if the save was success
			if(!has_error){
				let check = jEle.find('svg.speedycache-spinner-done');
				if(check){
					check.addClass('speedycache-spinner-done-active');
					setTimeout(() => {
						check.removeClass('speedycache-spinner-done-active');
					}, 2000);
				}
			}
		});
	});
});

function speedycache_pro_get_db_optm(){
	if(speedycache_pro_ajax.db_load){
		return;
	}

	speedycache_pro_ajax.db_load = true;
	
	jQuery.ajax({
		method : 'GET',
		url : speedycache_pro_ajax.url + '?action=speedycache_pro_get_db_optm&security='+speedycache_pro_ajax.nonce,
		beforeSend: function(){
			jQuery('.speedycache-db-number').text('(Loading...)');
		},
		success: function(res){
			if(res.success && res.data){
				for(let i in res.data){
					jQuery(`[speedycache-db-name=${i}] .speedycache-db-number`).text(`(${res.data[i]})`);
				}

				return;
			}

			if(res.data){
				alert(res.data);
			}
		}
	});
}