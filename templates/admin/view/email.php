<div class="wrap">
<?php
	$data = ['re_subject', 're_heading', 're_remind_days', 're_content', 're_first_remind'];
	if(isset($_POST['re_save_option'])){

		// if our nonce isn't there, or we can't verify it, bail
    	if( isset( $_POST['re_mail_remind_nonce'] ) && wp_verify_nonce( $_POST['re_mail_remind_nonce'], 're_mail_remind_nonce_dt' ) ){
    		foreach ($data as $k) {
    			if( $k == ''){
    				$dataOptions[$k] = update_option($k, wp_kses_allowed_html($_POST[$k]) );
    			}else{
					$dataOptions[$k] = update_option($k, $_POST[$k]);
				}
			}
    	}

	}
	
	$dataOptions = [];
	foreach ($data as $k) {
		$dataOptions[$k] = get_option($k);
	}
	$content = isset($dataOptions['re_content']) && !empty($dataOptions['re_content']) ? $dataOptions['re_content'] : '';
	$editor_id = 're_content';
?>
	<h1>Subscription Reminders</h1>
	<form action="" method="post">
		<?php wp_nonce_field( 're_mail_remind_nonce_dt', 're_mail_remind_nonce' ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="subject">Subject</label></th>
					<td><input name="re_subject" type="text" id="subject" value="<?php echo isset($dataOptions['re_subject']) && !empty($dataOptions['re_subject']) ? $dataOptions['re_subject'] : 'Email Remind'; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="heading">Heading</label></th>
					<td><input name="re_heading" type="text" id="heading" value="<?php echo isset($dataOptions['re_heading']) && !empty($dataOptions['re_heading']) ? $dataOptions['re_heading'] : 'Email Remind'; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="re_first_remind">Days until first reminder</label></th>
					<td><input name="re_first_remind" type="text" id="re_first_remind" value="<?php echo isset($dataOptions['re_first_remind']) && !empty($dataOptions['re_first_remind']) ? $dataOptions['re_first_remind'] : 0; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="re_remind_days">Days until second reminder</label></th>
					<td><input name="re_remind_days" type="text" id="re_remind_days" value="<?php echo isset($dataOptions['re_remind_days']) && !empty($dataOptions['re_remind_days']) ? $dataOptions['re_remind_days'] : 0; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="content">Content</label></th>
					<td>
						<?php wp_editor( stripslashes( $content ), $editor_id ); ?> 
					</td>
				</tr>
				<tr>
					<th colspan="2"><?php submit_button("Save change", 'primary', 're_save_option'); ?></th>
				</tr>
			</tbody>
		</table>
	</form>
</div>