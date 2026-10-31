jQuery(document).ready(function(){

	let chart;
	let current_type = "weekly";

	function create_chart(type){
		const $canvas = jQuery('.gosmtp-pro-report-chart-canvas');
		if ($canvas.length === 0) return;

		const ctx = jQuery('.gosmtp-pro-report-chart-canvas')[0].getContext("2d");
		const total_success = gosmtp_charts_data[type].successful.reduce((a, b) => a + b, 0);
		const total_failed = gosmtp_charts_data[type].failed.reduce((a, b) => a + b, 0);
		const total_sent = total_success + total_failed;
		const percent_success = total_sent ? Math.round((total_success / total_sent) * 100) : 0;
		const percent_failed  = total_sent ? Math.round((total_failed  / total_sent) * 100) : 0;

		jQuery('#gosmtp-pro-total-sent').text(total_sent);
		jQuery('#gosmtp-pro-total-success').text(total_success);
		jQuery('#gosmtp-pro-total-failed').text(total_failed);
		jQuery('#gosmtp-pro-report-success-percent').text('Success Rate: ('+ percent_success + '%)');
		jQuery('#gosmtp-pro-report-failed-percent').text('Failure Rate: ('+ percent_failed + '%)');

		const chart_height = 500;

		const green_gradient = ctx.createLinearGradient(0, 0, 0, chart_height);
		green_gradient.addColorStop(0, 'rgba(0, 128, 0, 0.3)');
		green_gradient.addColorStop(1, 'rgba(0, 128, 0, 0)');

		const red_gradient = ctx.createLinearGradient(0, 0, 0, chart_height);
		red_gradient.addColorStop(0, 'rgba(255, 0, 0, 0.3)');
		red_gradient.addColorStop(1, 'rgba(255, 0, 0, 0)');

		const data = {
			labels: gosmtp_charts_data[type].labels,
			datasets: [
				{
					label: 'Successful',
					data: gosmtp_charts_data[type].successful,
					borderColor: 'green',
					backgroundColor: green_gradient,
					tension: 0.3,
					fill: {
						target: 'origin',
						above: green_gradient
					},
					pointRadius: 4,
					pointHoverRadius: 6,
					pointHitRadius: 10
				},
				{
					label: 'Failed',
					data: gosmtp_charts_data[type].failed,
					borderColor: 'red',
					backgroundColor: red_gradient,
					tension: 0.3,
					fill: {
						target: 'origin',
						above: red_gradient
					},
				}
			]
		};

		const config = {
			type: 'line',
			data: data,
			options: {
				responsive: true,
				interaction: {
					mode: 'index',
					intersect: false
				},
				plugins: {
					legend: {
						position: 'top',
						labels: {
							font: {
								size: 14
							}
						}
					},
					title: {
						display: true,
						text: type.charAt(0).toUpperCase() + type.slice(1) + ' Report',
						font: {
							size: 18
						}
					},
					tooltip: {
						callbacks: {
							label: function(context){
								const datasetLabel = context.dataset.label || '';
								const value = context.parsed.y !== undefined ? context.parsed.y : context.raw;
								return datasetLabel + ':' + value;
							}
						}
					}
				},
				scales: {
					y: {
						ticks: {
							stepSize: 1
						}
					}
				}
			},
		};

		if(chart) chart.destroy();
		chart = new Chart(ctx, config);
	}

	create_chart(current_type);

	jQuery('.gosmtp-pro-report-chart-tab').on('click', function(){
		const type = jQuery(this).data('type');
		if(type !== current_type){
			current_type = type;
			create_chart(type);
		}

		jQuery('.gosmtp-pro-report-chart-tab').removeClass('active');
		jQuery(this).addClass('active');
	});

	jQuery('.gosmtp-notification-input').click(function(){
		const jEle = jQuery(this);
		const parent = jEle.closest('.gosmtp-tab-panel');

		parent.find('.gosmtp-notification-input').find('.service_label').removeClass('service_active');
		jEle.find('.service_label').addClass('service_active');

		parent.find('tr').hide();
		parent.find('.service_always_active').closest('tr').show();

		const attr_name = parent.find('.service_active').attr('data-name');
		parent.find('.'+attr_name).closest('tr').show();

		jEle.find('[name="service"]').prop('checked', true);
	});

	jQuery('.gosmtp-notification-input').find('.service_active').closest('.gosmtp-notification-input').click();

	// Add New condition
	jQuery('.gosmtp-pro-routing-block').on('click', '.gosmtp-pro-add-condition', function(e){
		e.preventDefault();
		const $row = jQuery(this).closest('tr');
		const $tbody = $row.closest('tbody');
		const $template = $tbody.find('.gosmtp-pro-condition-row').first();
		const $clone = $template.clone();
		
		// Clone the already present condition
		$clone.find('input[type="text"]').val('');
		$clone.find('select').prop('selectedIndex', 0);

		$tbody.append($clone);
		refreshSmartRoutingIndexes();
	});

	// Adding new group
	jQuery('.gosmtp-pro-routing-block').on('click', '.gosmtp-pro-add-new-group', function(e){
		e.preventDefault();
		const $routingWrap = jQuery(this).closest('.gosmtp-pro-routing-table-wrap');
		const $table = $routingWrap.find('.gosmtp-pro-routing-table');
		const $existingGroups = $table.find('.gosmtp-pro-conditions-body');
		const $templateRow = $existingGroups.first().find('.gosmtp-pro-condition-row').first();
		const $rowClone = $templateRow.clone();

		$rowClone.find('input[type="text"]').val('');
		$rowClone.find('select').prop('selectedIndex', 0);

		let $newGroup = jQuery('<tbody class="gosmtp-pro-conditions-body"></tbody>');
		$newGroup.append($rowClone);

		if($existingGroups.length > 0){
			const $separatorRow = $table.next('table').find('.gosmtp-pro-group-separator-row');
			const $separatorClone = $separatorRow.clone();
			$separatorClone.removeClass('gosmtp-pro-group-separator-row');
			$newGroup.prepend($separatorClone);
		}

		$table.append($newGroup);
		refreshSmartRoutingIndexes();
	});

	// Adding new connection
	jQuery('.gosmtp-pro-connection-btn').on('click', function(e){
		e.preventDefault();
		const $firstBlock = jQuery('.gosmtp-pro-routing-table-wrap').first();
		const $clone = $firstBlock.clone();

		$clone.find('input[type="text"]').val('');
		$clone.find('select').prop('selectedIndex', 0);
		
		// Remove any row and group other than the first one
		$clone.find('.gosmtp-pro-conditions-body').not(':first').remove();
		$clone.find('.gosmtp-pro-condition-row').not(':first').remove();
		$clone.find('.gosmtp-pro-conditions-body').first().find('.gosmtp-pro-group-separator-row').remove();

		jQuery('.gosmtp-pro-routing-block').append($clone);
		refreshSmartRoutingIndexes();
	});

	function refreshSmartRoutingIndexes(){
		jQuery('.gosmtp-pro-routing-table-wrap').each(function(ruleindex){
			const $block = jQuery(this);

			$block
				.find('select[name*="[connection_id]"]')
				.attr('name', 'smartrouting[' + ruleindex + '][connection_id]');

			$block.find('.gosmtp-pro-conditions-body').each(function(groupindex) {
				// Built once per group, reused across all rows and fields inside it
				const groupPrefix = 'smartrouting[' + ruleindex + '][rules][groups][' + groupindex + ']';

				jQuery(this).find('.gosmtp-pro-condition-row').each(function(conditionindex){
					// Built once per row, reused across all fields inside it
					const rowPrefix = groupPrefix + '[' + conditionindex + ']';

					jQuery(this).find('select, input').each(function(){
						const $field = jQuery(this);
						const name = $field.attr('name');

						if(!name) return;

						const match = name.match(/\[([^\]]+)\]$/);

						if(!match) return;

						$field.attr('name', rowPrefix + '[' + match[1] + ']');
					});
				});
			});
		});
	}
	
	// Removing a connection
	jQuery('.gosmtp-mail-smartrouting-settings').on('click', '.gosmtp-pro-remove-connection', function(){
		const totalconnections = jQuery('.gosmtp-pro-routing-table-wrap').length;
		if(totalconnections <= 1){
			return;
		}
		if(confirm('Are you sure you want to remove this connection ?')){
			jQuery(this).closest('.gosmtp-pro-routing-table-wrap').remove();
		}
		refreshSmartRoutingIndexes();
	});
	
	// Removing a conditon
	jQuery('.gosmtp-mail-smartrouting-settings').on('click', '.gosmtp-pro-remove-condition', function(){
		const $row = jQuery(this).closest('.gosmtp-pro-condition-row');
		const $tbody = $row.closest('.gosmtp-pro-conditions-body');
		const $tablewrap = $tbody.closest('.gosmtp-pro-routing-table-wrap');
		const $firsttbody = $tablewrap.find('.gosmtp-pro-conditions-body').first();

		if($tbody.is($firsttbody) && $tbody.find('.gosmtp-pro-condition-row').length <= 1){
			return;
		}

		if($tbody.find('.gosmtp-pro-condition-row').length <= 1){
			if(confirm('Are you sure you want to remove this group?')){
				$tbody.remove();
				refreshSmartRoutingIndexes();
			}
			return;
		}

		$row.remove();
		refreshSmartRoutingIndexes();
	});
	
	// Removing a group
	jQuery('.gosmtp-mail-smartrouting-settings').on('click', '.gosmtp-pro-remove-group', function(e){
		e.preventDefault();

		if(confirm('Are you sure you want to remove this group?')){
			jQuery(this).closest('.gosmtp-pro-conditions-body').remove();
		}
		refreshSmartRoutingIndexes();
	});
});
