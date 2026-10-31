<?php
// We need the ABSPATH
if (!defined('ABSPATH')) exit;

wp_enqueue_style( 'gosmtp-pro-admin' );
wp_enqueue_script( 'gosmtp-pro-admin' );

// Add setting tabs
add_filter('gosmtp_settings_tabs_nav', 'gosmtp_pro_settings_tabs_nav');
function gosmtp_pro_settings_tabs_nav($navs){
	
	$offset = 1;
	$_navs = array(
		'logs-settings' => __('Logs Settings'),
		'gosmtp-connections-settings' => __('Additional Connections'),
		'gosmtp-notifications-settings' => __('Notifications', 'gosmtp-pro'),
		'gosmtp-smart-routing-settings' => __('Smart Routing', 'gosmtp-pro'),
	);
	
	// Add the $_navs array in 1 position of $navs;
	$navs = array_slice( $navs, 0, $offset, true ) + $_navs  + array_slice( $navs, $offset, null, true );
	
	return $navs;
}

// Add settings tab panel
add_action('gosmtp_after_settings_tab_panel', 'gosmtp_pro_after_settings_tab_panel');
function gosmtp_pro_after_settings_tab_panel(){
	$smtp_options = get_option('gosmtp_options', array());
	
	$mailer_count = !empty($smtp_options['mailer']) ? count($smtp_options['mailer']) : 0;

	// Default mailer set mail
	if(!isset($smtp_options['mailer']) || !is_array($smtp_options['mailer']) || empty($smtp_options['mailer'][0])){
		$smtp_options['mailer'] = [];
		$smtp_options['mailer'][0]['mail_type'] = 'mail';
	}
?>

	<div class="gosmtp-tab-panel" id="logs-settings" style="display:none">
		<form class="gosmtp-logs-settings" name="logs-settings" method="post" action="">
			<?php wp_nonce_field('gosmtp-settings'); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e('Enable Logs'); ?></th>
					<td>
						<input id="enable_logs" name="enable_logs" type="checkbox" <?php if(!empty($smtp_options['logs']['enable_logs'])){
							echo "checked";
						}?>>
						<label for="enable_logs"><?php _e('Keep a logs of all emails sent');?></label>
						<p class="description" id="tagline-description"><?php _e( 'This will allow you to store a log and view all information about all emails sent.' ); ?></p>
					</td>
				</tr>
				<tr class="gosmtp-logs-options <?php echo empty($smtp_options['logs']['enable_logs']) ? 'gosmtp-hide' : '' ?>">
					<th scope="row"><?php _e('Save Attachments'); ?></th>
					<td>
						<input id="log_attachments" name="log_attachments" type="checkbox" <?php if(!empty($smtp_options['logs']['log_attachments'])){
							echo "checked";
						}?>>
						<label for="log_attachments"><?php _e('Save the sent attachments. ');?></label>
						<p class="description" id="tagline-description"><?php _e( 'This will allow to save all sent attachments to the logs.' ); ?></p>
						<p class="description" id="tagline-description"><i><?php _e( 'Please note, all sent attachments will be stored to your uploads folder. This could potentially cause some disk space issue.' ); ?></i></p>
					</td>
				</tr>
				<tr class="gosmtp-logs-options <?php echo empty($smtp_options['logs']['enable_logs']) ? 'gosmtp-hide' : '' ?>">
					<th scope="row"><?php _e('Log Columns'); ?></th>
					<td>
						<?php
							$logs_cols = !empty($smtp_options['logs']['log_columns']) ? maybe_unserialize($smtp_options['logs']['log_columns']) : '';
						?>
						<input name="log_columns[from]" type="checkbox" <?php if((!empty($logs_cols['from']) && $logs_cols['from']=='on') || empty($logs_cols)){
							echo "checked";
						}?>>
						<label><?php _e('Show From');?></label>
						<br>
						<input name="log_columns[to]" type="checkbox" <?php if((!empty($logs_cols['to']) && $logs_cols['to']=='on' ) || empty($logs_cols)){
							echo "checked";
						}?>>
						<label><?php _e('Show To');?></label>
						<br>
						<input name="log_columns[source]" type="checkbox" <?php if((!empty($logs_cols['source']) && $logs_cols['source']=='on' ) || empty($logs_cols)){
							echo "checked";
						}?>>
						<label><?php _e('Show Source');?></label>
							<br>
						<input name="log_columns[provider]" type="checkbox" <?php if((!empty($logs_cols['provider']) && $logs_cols['provider']=='on' ) || empty($logs_cols)){
							echo "checked";
						}?>>
						<label><?php _e('Show Provider');?></label>
						<p class="description" id="tagline-description"><?php _e( 'By using this you can show and hide above field from email logs table.' ); ?></p>
					</td>
				</tr>
				<tr class="gosmtp-logs-options <?php echo empty($smtp_options['logs']['enable_logs']) ? 'gosmtp-hide' : '' ?>">
					<th scope="row"><?php _e('Log Retention Period'); ?></th>
					<td>
						<?php
							$list_key = empty($smtp_options['logs']['retention_period']) ? '' : $smtp_options['logs']['retention_period'];
						?>
						<select name="retention_period">
							<option value="" <?php selected($list_key, '', true) ?>><?php _e('Forever'); ?></option>
							<option value="86400" <?php selected($list_key, '86400', true) ?>><?php _e('1 Day'); ?></option>
							<option value="604800" <?php selected($list_key, '604800', true) ?>><?php _e('1 Week'); ?></option>
							<option value="2628000" <?php selected($list_key, '2628000', true) ?>><?php _e('1 Month'); ?></option>
							<option value="15770000" <?php selected($list_key, '15770000', true) ?>><?php _e('6 Months'); ?></option>
							<option value="31540000" <?php selected($list_key, '31540000', true) ?>><?php _e('1 Year'); ?></option>
						</select>
						<p class="description" id="tagline-description"><?php _e( 'Email logs will be permanently deleted once they are older than the selected period.' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Enable Weekly Reports'); ?></th>
					<td>
						<input id="enable_weekly_reports" name="enable_weekly_reports" type="checkbox" <?php if(!empty($smtp_options['weekly_reports']['enable_weekly_reports'])){
							echo "checked";
						}?>>
						<label for="enable_weekly_reports"><?php _e('Get weekly reports');?></label>
						<p class="description" id="tagline-description"><?php _e( 'check and get weekly email reports.' ); ?></p>
					</td>
				</tr>
				<tr id="gosmtp-week-list">
					<th scope="row"><?php _e('Email Reports Weekday'); ?></th>
					<td>
						<?php
							$list_key = empty($smtp_options['weekly_reports']['weekday']) ? '' : $smtp_options['weekly_reports']['weekday'];
							$week = array(
								'monday' => __('Monday'),
								'tuesday' => __('Tuesday'),
								'wednesday' => __('Wednesday'),
								'thursday' => __('Thursday'),
								'friday' => __('Friday'),
								'saturday' => __('Saturday'),
								'sunday' => __('Sunday'),
							);
						?>
						<select name="weekday">
							<?php foreach($week as $week_key => $week_val){
								echo "<option value='".$week_key."' ".selected($list_key, $week_key, true).">".$week_val."</option>";
							}?>
						</select>
						<a title="preview" href="<?php echo admin_url().'admin.php?page=weekly_email_reports'?>" class="gosmtp_preview"><span class="dashicons dashicons-visibility"></span></a>
						<p class="description" id="tagline-description"><?php _e( 'Select which day you want email reports delivered.' ); ?></p>
					</td>
				</tr>
				<!-- <tr>
					<th scope="row"><?php _e('Clear Logs'); ?></th>
					<td>
						<button type="submit"><?php _e('Clear Logs'); ?></button>
					</td>
				</tr> -->
			</table>
			
			<p>
				<input type="submit" name="save_settings" class="button button-primary" value="Save Changes">
			</p>
		</form>	
	</div>
	<?php

	$conn_data = [];

	$conn_id = gosmtp_optget('conn_id');
	$conn_type = gosmtp_optget('type');
	$is_visible = (!empty($conn_type) && ($conn_type == 'edit')) ? true : false;

	if($is_visible && !empty($conn_id) && isset($smtp_options['mailer'][$conn_id])){
		$conn_data = $smtp_options['mailer'][$conn_id];
		$conn_data['conn_id'] = $conn_id;
	}
	?>
		
	<div class="gosmtp-tab-panel <?php echo $is_visible ? 'gosmtp-edit-conn-open' : ''; ?>" id="gosmtp-connections-settings" style="display:none">
		<div class="gosmtp-row gosmtp-conn-title-wrap">
			<div class="gosmtp-conn-left">
				<button title="Go To Existing Connections" id="gosmtp-back-trigger"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
				<h1 class="gosmtp-conn-title-existing"><?php echo __('Existing Connections'); ?></h1>
				<h1 class="gosmtp-conn-title-edit"><?php echo __('Edit Connection'); ?></h1>
				<h1 class="gosmtp-conn-title-new"><?php echo __('New Connection'); ?></h1>
			</div>
			<?php if($mailer_count > 1){ ?>
			<div class="gosmtp-conn-right">
				<button id="gosmtp-new-conn" type="button"><i class="dashicons dashicons-plus-alt"></i><span><?php echo __('Add New Connection'); ?></span></button>
			</div>
			<?php } ?>
		</div>
			
		<div class="gosmtp-row gosmtp-existing-conn-wrap">
			<form class="gosmtp-smtp-conn" name="smtp-manage-connections" method="post" action="">	
			<?php 
			
			wp_nonce_field('gosmtp-options');
			if($mailer_count == 0){
				echo "<div class='gosmtp-conn-empty-text'>".__("It appears that you haven't yet set up the primary connection! ").'<a href="#smtpsetting">'.__('click here').'</a>'.__(' to setup primary connection.').'</div>';
			}

			if($mailer_count == 1){
				echo '<div class="gosmtp-conn-empty-text">'.__('Connections not found, ').'<span id="gosmtp-new-conn-link">'.__('create one?').'</div>';
			}

			if($mailer_count > 1){

				foreach($smtp_options['mailer'] as $key => $mailer){

					if($key === 0){
						continue;
					}

					$_class = '';
					if(!empty($smtp_options['mailer'][0]['backup_connection']) && $smtp_options['mailer'][0]['backup_connection'] == $key){
						$_class = 'gosmtp-active-conn';
					}

					$icon = GOSMTP_URL .'/images/'.$mailer['mail_type'].'.svg';
					
					?>
					<div class="gosmtp-col-4">
						<div class="gosmtp-conn-item <?= $_class ?>">
							<div class="gosmtp-conn-icon">
								<img src="<?php echo $icon; ?>" class="mailer<?php echo $mailer['mail_type'] == 'postmark' || $mailer['mail_type'] == 'smtpcom' ? ' gosmtp-sm-img' : '' ?>">
							</div>
							<div class="gosmtp-conn-content">
								<span><?php echo !empty($mailer['nickname']) ? $mailer['nickname'] : __('No Name'); ?></span>
								<span><?php echo !empty($mailer['from_email']) ? __('From:'). $mailer['from_email'] : ''; ?></span>
							</div>
							<div class="gosmtp-conn-actions">
								<?php
								echo empty($_class) ? '<button title="Set As Backup Connection" class="gosmtp-backup-conn" name="make_backup_connection" type="submit" value="'.$key.'"><span class="dashicons dashicons-admin-post"></span></button>' : '<button class="gosmtp-backup-conn-clear" title="Reset Backup Connection" name="clear_backup_connection" type="submit" value="'.$key.'"><span class="dashicons dashicons-editor-unlink"></span></button>';
								?>										
								<a title="Edit Connection" class="gosmtp-edit-conn" href="<?php echo admin_url('admin.php?page=gosmtp&type=edit&conn_id='.$key.'#gosmtp-connections-settings'); ?>"><span class="dashicons dashicons-edit"></span></a>
								<button title="Delete Connection" class="gosmtp-delete-conn" name="delete_connection" type="submit" value="<?php echo $key; ?>"><span class="dashicons dashicons-trash"></span></button>
							</div>
						</div>
					</div>
					<?php
				}
			}
			?>
			</form>
		</div>

		<div class="gosmtp-row gosmtp-new-conn-wrap">
			<form class="gosmtp-smtp-conn" name="smtp-connections" method="post" action="">
				<?php  
					gosmtp_mailer_settings($conn_data, true);
				?>
			</form>
		</div>
	</div>

	<div class="gosmtp-tab-panel" id="gosmtp-notifications-settings" style="display:none">
		<form class="gosmtp-mail-notifications-settings" name="gosmtp-notifications-settings" method="post" action="">
			<?php 
			$all_services = gosmtp_pro_load_notifications_service_list();
			wp_nonce_field('gosmtp-settings');?>

			<h1 class="gosmtp-pro-notifications-tab-title"><?php esc_html_e('Notification Settings', 'gosmtp-pro'); ?></h1>

			<?php
			$is_enabled = !empty($smtp_options['notifications']['notifications_enabled']);
			?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Enable Notification', 'gosmtp-pro'); ?></th>
					<td>
						<input type="checkbox" name="gosmtp-pro-notifications-checkbox" class="service_always_active" value="1" <?php checked(true, $is_enabled); ?>>
						<label><?php esc_html_e('Enable the Notification Service', 'gosmtp-pro');?></label>
						<p class="description" id="tagline-description"><?php esc_html_e('Enable it to send notifications on email delivery failure', 'gosmtp-pro'); ?></p>
					</td>
				</tr>	
				<tr>
					<th scope="row"><?php esc_html_e('Notification Services', 'gosmtp-pro'); ?></th>
					<td class="gosmtp-pro-email-notification-container">
						<?php
						if(!isset($smtp_options['notifications']) || empty($smtp_options['notifications']['notification_service'])){
							$smtp_options['notifications'] = [];
							$smtp_options['notifications']['notification_service'] = 'email';
						}

						$service_list = gosmtp_pro_get_notifications_service_list();
						foreach($service_list as $key => $service){
							$is_pro = $disabled = $after_icon = '';
							$active_service = (isset($smtp_options['notifications']['notification_service']) && $smtp_options['notifications']['notification_service'] == $key) ? 'service_active' : '';
							$icon = isset($service['icon']) ? $service['icon'] : GOSMTP_PRO_PLUGIN_URL .'/assets/images/'.$key.'.svg';

							if(!class_exists($service['class'])){
								$is_pro = 'pro';
								$disabled = 'disabled';
								$after_icon='<div class="lock_icon">
									<span class="dashicons dashicons-lock"></span>
								</div>';
							}

							echo '<div class="gosmtp-notification-input service_always_active'.esc_attr($is_pro).'">
								<label class="label">'. esc_html($service['title']) .'</label>
								<div for="'.esc_attr($key).'" class="service_label '.esc_attr($active_service).'" data-name="'.esc_attr($key).'">
									<img src="'.esc_attr($icon).'" class="service">
									'.wp_kses_post($after_icon).'
								</div>
								<input id="'.esc_attr($key).'" class="service_check" data-name="'.esc_attr($key).'" name="service" type="radio" '.esc_attr($disabled).' value="'. esc_attr($key) .'" '. checked( $key, (isset($smtp_options['notification_service'])  ? $smtp_options['notification_service'] : ''),false ).'>
							</div>';
						}
						?>
					</td>
				</tr>
				<?php
					foreach($all_services as $key => $service){
						if(!method_exists($service, 'load_services_field')){
							continue;
						}

						echo '<tr>
							<td><h1 class="'.esc_attr($key).' smtp_heading">'.esc_html($service->title).'</h1></td>
						</tr>';

						$service->load_options();

						echo gosmtp_create_notification_field($service->load_services_field(), $service);
					}
				?>
			</table>
			
			<p>
				<input type="submit" name="save_notification_settings" class="button button-primary" value="Save Changes">
			</p>
		</form>	
	</div>

	<div class="gosmtp-tab-panel" id="gosmtp-smart-routing-settings" style="display:none">
		<form class="gosmtp-mail-smartrouting-settings" name="gosmtp-smart-routing-settings" method="post" action="">
			<?php
				wp_nonce_field('gosmtp-settings');
				$is_enabled = !empty($smtp_options['smart_routing']['enabled']);
			?>
			<div class="gosmtp-pro-smart-routing-title">
				<div class="gosmtp-pro-smart-routing-titlebox">
					<h1 class="gosmtp-pro-smart-routing-tab-title"><?php esc_html_e('Smart Routing', 'gosmtp-pro'); ?></h1>
					<p class="description" id="gosmtp-pro-tagline-description"><?php esc_html_e('Send emails from different additional connections based on your configured conditions. Emails that do not match any of the conditions below will be sent via your Primary Connection.', 'gosmtp-pro'); ?></p>
				</div>
			</div>
			<div class="gosmtp-pro-add-connection">
				<button class="button button-secondary gosmtp-pro-connection-btn"><?php esc_html_e('Add New Rule', 'gosmtp-pro'); ?></button>
			</div>
			<div class="gosmtp-pro-smart-routing-toggle">
				<span><?php esc_html_e('Enabled Smart Routing', 'gosmtp-pro'); ?></span>
				<div class="gosmtp-pro-smart-routing-checkbox">
					<input type="checkbox" name="gosmtp-pro-smart-routing-checkbox" value="1" <?php checked(true, $is_enabled); ?>>
					<label><?php esc_html_e('Enable the Smart Routing', 'gosmtp-pro');?></label>
				</div>
			</div>
			<div class="gosmtp-pro-routing-block">
				<?php
					$rules_list = !empty($smtp_options['smart_routing']['rules']) ? $smtp_options['smart_routing']['rules'] : [[]];
					foreach($rules_list as $rule_index => $group_data){
					$current_mailer = !empty($group_data['connection_id']) ? $group_data['connection_id'] : '';
				?>
				<div class="gosmtp-pro-routing-table-wrap">
					<table class="form-table gosmtp-pro-routing-table">
						<thead>
							<tr>
								<th colspan="4"><?php esc_html_e('Send With', 'gosmtp-pro'); ?>
									<select name="<?php echo esc_attr("smartrouting[$rule_index][connection_id]");?>" class="regular-text">
										<option value=""><?php esc_html_e('--Select a Connection--', 'gosmtp-pro'); ?></option>
										<?php
											if(!empty($smtp_options['mailer'])){
												foreach($smtp_options['mailer'] as $key => $mailer){
													if($key === 0){
														continue; // Skip Primary
													}
													$conn_name = !empty($mailer['nickname']) ? $mailer['nickname'] : __('(No Name)', 'gosmtp-pro');
													$conn_type = ucfirst(isset($mailer['mail_type']) ? $mailer['mail_type'] : '');
													echo "<option value='".esc_attr($key)."' ".selected($current_mailer, $key, false).">".esc_html($conn_name).' - ['.esc_html($conn_type)."]</option>";
												}
											}
										?>
									</select>
									<span><?php esc_html_e('if the following conditions are met...', 'gosmtp-pro'); ?></span>
									<span class="gosmtp-pro-remove-connection"><?php esc_html_e('Delete Connection', 'gosmtp-pro');?></span>
								</th>
							</tr>
						</thead>
						<?php
							$groups = !empty($group_data['groups']) ? $group_data['groups'] : [[]];
							foreach($groups as $g_index => $condition){
						?>
						<tbody class="gosmtp-pro-conditions-body">
							<?php if($g_index > 0){ ?>
							<tr class="gosmtp-pro-group-separator-row">
								<td colspan="4">
									<span class="gosmtp-pro-group-separator"><?php esc_html_e('or', 'gosmtp-pro');?></span>
									<span class="gosmtp-pro-remove-group"><span><?php esc_html_e('Delete Group', 'gosmtp-pro') ?></span></span>
								</td>
							</tr>
							<?php } ?>
							<?php
								$condition = !empty($condition) ? $condition : [[]];
								foreach($condition as $condition_index => $single_cond){
							?>
							<tr class="gosmtp-pro-condition-row">
								<td>
									<select name="<?php echo esc_attr("smartrouting[$rule_index][rules][groups][$g_index][$condition_index][type]");?>" style="width: 100%;">
										<option value="subject" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'subject'); ?>><?php esc_html_e('Subject','gosmtp-pro');?></option>
										<option value="message" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'message'); ?>><?php esc_html_e('Message','gosmtp-pro');?></option>
										<option value="from" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'from'); ?>><?php esc_html_e('From Email','gosmtp-pro');?></option>
										<option value="fromname" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'fromname'); ?>><?php esc_html_e('From Name','gosmtp-pro');?></option>
										<option value="to" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'to'); ?>><?php esc_html_e('To','gosmtp-pro');?></option>
										<option value="cc" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'cc'); ?>><?php esc_html_e('CC','gosmtp-pro');?></option>
										<option value="bcc" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'bcc'); ?>><?php esc_html_e('BCC','gosmtp-pro');?></option>
										<option value="reply-to" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'reply-to'); ?>><?php esc_html_e('Reply To','gosmtp-pro');?></option>
										<option value="header_name" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'header_name'); ?>><?php esc_html_e('Header Name','gosmtp-pro');?></option>
										<option value="header_value" <?php selected(isset($single_cond['type']) ? $single_cond['type'] : '', 'header_value'); ?>><?php esc_html_e('Header Value','gosmtp-pro');?></option>
									</select>
								</td>
								<td>
									<select name="<?php echo esc_attr("smartrouting[$rule_index][rules][groups][$g_index][$condition_index][operator]")?>" style="width: 100%;">
										<option value="contains" <?php selected(isset($single_cond['operator']) ? $single_cond['operator'] : '', 'contains'); ?>><?php esc_html_e('Contains','gosmtp-pro');?></option>
										<option value="does_not_contain" <?php selected(isset($single_cond['operator']) ? $single_cond['operator'] : '', 'does_not_contain'); ?>><?php esc_html_e('Does not contain','gosmtp-pro');?></option>
										<option value="is" <?php selected(isset($single_cond['operator']) ? $single_cond['operator'] : '', 'is'); ?>><?php esc_html_e('Is','gosmtp-pro');?></option>
										<option value="is_not" <?php selected(isset($single_cond['operator']) ? $single_cond['operator'] : '', 'is_not'); ?>><?php esc_html_e('Is not','gosmtp-pro');?></option>
										<option value="starts_with" <?php selected(isset($single_cond['operator']) ? $single_cond['operator'] : '', 'starts_with')?>><?php esc_html_e('Starts with','gosmtp-pro');?></option>
										<option value="ends_with" <?php selected(isset($single_cond['operator']) ? $single_cond['operator'] : '', 'ends_with'); ?>><?php esc_html_e('Ends with','gosmtp-pro');?></option>
									</select>
								</td>
								<td>
									<input type="text" name="<?php echo esc_attr("smartrouting[$rule_index][rules][groups][$g_index][$condition_index][value]")?>" value="<?php echo esc_attr(isset($single_cond['value']) ? $single_cond['value'] : ''); ?>" style="width: 100%;">
								</td>
								<td>
									<div class="gosmtp-pro-smart-routing-and-btn">
										<div>
											<button type="button" class="button button-secondary gosmtp-pro-add-condition"><?php esc_html_e('And', 'gosmtp-pro'); ?></button>
										</div>
										<div class="gosmtp-pro-smart-routing-trash-icon">
											<span class="dashicons dashicons-trash gosmtp-pro-remove-condition" title="Delete Rule"></span>
										</div>
									</div>
								</td>
							</tr>
							<?php } ?>
						</tbody>
						<?php } ?>
					</table>
					<table style="display:none;">
						<tbody class="gosmtp-pro-group-separator-tbody">
							<tr class="gosmtp-pro-group-separator-row">
								<td colspan="4">
									<span class="gosmtp-pro-group-separator"><?php esc_html_e('or', 'gosmtp-pro'); ?></span>
									<span class="gosmtp-pro-remove-group"><?php esc_html_e('Delete Group', 'gosmtp-pro');?></span>
								</td>
							</tr>
						</tbody>
					</table>
					<div class="gosmtp-pro-routing-footer">
						<button type="button" class="button button-secondary gosmtp-pro-add-new-group"><?php esc_html_e('Add New Group (OR)', 'gosmtp-pro'); ?></button>
					</div>
				</div>
				<?php } ?>
			</div>
			<p>
				<input type="submit" name="save_smart_routing_settings" class="button button-primary" value="Save Changes">
			</p>
		</form>
	</div>
	<?php
}

function gosmtp_create_notification_field($fields, $service){
	$html = '';
	$service_class = esc_attr($service->service);

	foreach($fields as $key => $field){
		$val = $service->get_option($key, $service->service);
		if($val === '' && isset($field['default'])){
			$val = $field['default'];
		}

		$attrs = 'name="' . esc_attr($service_class . '[' . $key . ']') . '"';

		if(!empty($field['type']) && $field['type'] != 'select'){
			$attrs .= 'type="' . esc_attr($field['type']) . '"';
		}

		$input_html = '';

		if(in_array($field['type'], ['text', 'password', 'number'])){
			$input_html = '<input class="regular-text '.$service_class.'" value="' . esc_attr($val) . '" ' . $attrs . '>';
		} elseif($field['type'] === 'email'){
			$input_html = '<input class="regular-text '.$service_class.'" placeholder="notifications@example.com" value="' . esc_attr($val) . '" ' . $attrs . '>';
		}

		$description = empty($field['desc']) ? '' : wp_kses_post($field['desc']);

		$html .= '<tr>
			<th scope="row">' . esc_html($field['title']) . '</th>
			<td>
				'.$input_html.'
				<p class="description" id="tagline-description">' .wp_kses_post($description).'</p>
			</td>
		</tr>';
	}

	return $html;
}

add_action('gosmtp_pro_test_connection_and_template', 'gosmtp_pro_connection_and_template_settings');

function gosmtp_pro_connection_and_template_settings(){
	global $gosmtp;

	$smtp_options = $gosmtp->options;
	$mailer_count = !empty($smtp_options['mailer']) ? count($smtp_options['mailer']) : 0;

	if($mailer_count > 1){
		?>
		<tr>
			<th scope="row"><?php esc_html_e('Test Connection', 'gosmtp-pro'); ?></th>
			<td>
				<select name="smtp_test_connection" class="regular-text" rows="10" required>
					<option value="0" selected><?php esc_html_e('Default Connection', 'gosmtp-pro') ?></option>
					<?php

					foreach($smtp_options['mailer'] as $key => $mailer){
						if($key === 0){
							continue;
						}

						$conn_name = !empty($mailer['nickname']) ? $mailer['nickname'] : __('No Name', 'gosmtp-pro');
						$conn_type = !empty($mailer['mail_type']) ? ucfirst($mailer['mail_type']) : '';
						echo '<option value="'.esc_attr($key).'">'.esc_html($conn_name).' - ['.esc_html($conn_type).']</option>';
					}
					?>
				</select>
				<p class="description" id="tagline-description"><?php esc_html_e('Select the connection for sending your message', 'gosmtp-pro'); ?></p>
				<p class="description" id="tagline-description"><i><?php esc_html_e('Please note that the Default Connection uses your current SMTP Settings while others use Additional Connections.', 'gosmtp-pro'); ?></i></p>
			</td>
		</tr>
		<?php
	}
	?>
	<tr>
		<th scope="row"><?php esc_html_e('Use Template', 'gosmtp-pro'); ?></th>
		<td>
			<input type="checkbox" name="use_html_template" class="gosmtp-test-html-template" value="1">
			<?php esc_html_e('Send HTML Template Email', 'gosmtp-pro'); ?>
			<p class="description" id="tagline-description"><?php esc_html_e('Send test email using HTML template instead of custom message.', 'gosmtp-pro'); ?></p>
		</td>
	</tr>
<?php
}

add_action('gosmtp_pro_save_notification_settings', 'gosmtp_pro_save_notifications', 10, 1);

function gosmtp_pro_save_notifications(){
	if(!isset($_REQUEST['save_notification_settings'])){
		return;
	}

	if(!current_user_can('manage_options')){
		return;
	}

	// Check nonce
	check_admin_referer('gosmtp-settings');

	$options = get_option('gosmtp_options', []);

	// Get the services data
	$all_services = gosmtp_pro_load_notifications_service_list();
	$save_service = gosmtp_optreq('service');

	// Update fields of service
	if(!empty($save_service) && isset($all_services[$save_service])){

		$options['notifications'] = [];

		$options['notifications']['notification_service'] = $save_service;

		if(method_exists($all_services[$save_service], 'save_options')){
			$options['notifications'] = $all_services[$save_service]->save_options($options['notifications']);
		}
	}

	// Is notification enabled
	$options['notifications']['notifications_enabled'] = !empty($_POST['gosmtp-pro-notifications-checkbox']);
	
	if(update_option('gosmtp_options', $options)){
		add_settings_error('gosmtp', 'notification_success', __('Notification settings saved successfully!', 'gosmtp-pro'), 'updated');
	}
}

add_action('gosmtp_pro_save_smart_routing_settings', 'gosmtp_pro_save_smart_routing', 10);
function gosmtp_pro_save_smart_routing(){
	if(!isset($_REQUEST['save_smart_routing_settings'])){
		return;
	}

	check_admin_referer('gosmtp-settings');
	if(!current_user_can('manage_options')){
		return;
	}
	$options = get_option('gosmtp_options', []);
	$enabled = isset($_POST['gosmtp-pro-smart-routing-checkbox']);
	$smart_routing = [
		'enabled' => $enabled,
		'rules' => []
	];

	if(!empty($_POST['smartrouting']) && is_array($_POST['smartrouting'])){
		foreach($_POST['smartrouting'] as $key => $block){

			$connection_id = isset($block['connection_id']) ? sanitize_text_field(wp_unslash($block['connection_id'])) : '';
			if(empty($connection_id)){
				continue;
			}

			$new_rule_block = [
				'connection_id' => $connection_id,
				'groups' => []
			];
			
			$groups_input = !empty($block['rules']['groups']) ? $block['rules']['groups'] : [];
			foreach($groups_input as $group_index => $group_data){
				$conditions = [];
				if(is_array($group_data)){
					foreach($group_data as $condition_index => $condition){
						if(is_array($condition) && !empty($condition['value'])){
							$conditions[] = [
								'type' => sanitize_text_field(wp_unslash($condition['type'])),
								'operator' => sanitize_text_field(wp_unslash($condition['operator'])),
								'value' => sanitize_text_field(wp_unslash($condition['value']))
							];
						}
					}
				}
				if(!empty($conditions)){
					$new_rule_block['groups'][$group_index] = $conditions;
				}
			}
			if(!empty($new_rule_block['groups'])){
				$smart_routing['rules'][] = $new_rule_block;
			}
		}
	}
	$options['smart_routing'] = $smart_routing;
	if(update_option('gosmtp_options', $options)){
		add_settings_error('gosmtp', 'notification_success', __('Smart Routing Setting saved Successfully!', 'gosmtp-pro'), 'updated');
	}
}

?>