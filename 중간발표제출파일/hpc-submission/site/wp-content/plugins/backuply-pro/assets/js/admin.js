jQuery(document).ready(function(){

	let preUpdateActive = false,
	updateButton = null,
	checkCompleteInterval = null,
	originalForm = null;

	jQuery(document).on('click', '#upgrade', function (e){

		if(preUpdateActive){
			return true;
		}

		preUpdateActive = true;
		updateButton = jQuery(this);

		// Get the original form
		originalForm = updateButton.closest('form');

		if(originalForm.length === 0){
			originalForm = jQuery('form').first();
		}

		// Prevent default to stop immediate submission
		e.preventDefault();
		e.stopImmediatePropagation();

		// Disable button
		updateButton.prop('disabled', true);

		// Show existing Backuply modal
		let modal = jQuery('#backuply-backup-progress');
		backuply_update_modal_str(false);
		modal.show();
    	jQuery('.backuply-backup-finish').hide();

		backuply_status = {
			has_ended: false,
			fail_count: 0,
			progress: 0,
			last_status: 0,
			is_restore: false
		};

		// Start backup via AJAX
		jQuery.ajax({
			url: backuply_obj.ajax_url,
			method: 'POST',
			data: {
				action: 'backuply_pre_update_backup',
				security: backuply_obj.nonce
			},
			success: function (res){
				if(!res.success){
					alert(res.data || res.message || 'Failed to start backup');
					modal.hide();
					updateButton.prop('disabled', false);
					preUpdateActive = false;
					return;
				}

				// Start polling progress
				backuply_backup_progress();

				checkCompleteInterval = setInterval(function(){
					checkPreUpdateComplete();
				}, 2000);
			},
			error: function () {
				alert('Network error. Please try again.');
				modal.hide();
				updateButton.prop('disabled', false);
				preUpdateActive = false;
			}
		});

		return false;
	});

	function checkPreUpdateComplete(){

		if(!backuply_status.has_ended){
			return;
		}

		clearInterval(checkCompleteInterval);

		// Backup done - check for errors via the machine-readable marker
		// emitted by free's status renderer (class="backuply-log-error").
		let statusBox = jQuery('.backuply-backup-status');
		let hasError = statusBox.find('.backuply-log-error').length > 0;

		if(hasError){
			alert('Backup failed. WordPress update aborted for safety.');
			updateButton.prop('disabled', false);
			preUpdateActive = false;
			return;
		}

		setTimeout(function(){

			jQuery('#backuply-backup-progress').hide();

			if(originalForm && originalForm.length > 0){

				let action = originalForm.attr('action') || window.location.href;
				let method = originalForm.attr('method') || 'post';

				// Create hidden form
				let newForm = jQuery('<form></form>');
				newForm.attr('action', action);
				newForm.attr('method', method);

				newForm.css({
					position: 'absolute',
					left: '-9999px',
					top: '-9999px'
				});

				// Copy only non-submit fields (inputs, selects, textareas)
				originalForm.find('input, select, textarea').each(function(){

					let field = jQuery(this);

					// Skip submit button
					if(field.attr('type') === 'submit'){
						return;
					}

					let clone = field.clone();
					newForm.append(clone);
				});

				// Manually add the clicked button's value
				if(updateButton && updateButton.attr('name')){

					let btnClone = jQuery('<input type="hidden">');

					btnClone.attr('name', updateButton.attr('name'));
					btnClone.val(updateButton.val());
					newForm.append(btnClone);
				}

				jQuery('body').append(newForm);
				newForm.trigger('submit');

			}else{
				window.location.href = window.location.href + '&action=do-core-upgrade';
			}

		}, 1500);
	}
});