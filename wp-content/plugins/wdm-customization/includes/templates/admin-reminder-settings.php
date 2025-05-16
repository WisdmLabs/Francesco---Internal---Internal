<?php
/**
 * Admin settings template for booking reminder emails.
 *
 * @package wdm-customization
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get saved settings.
$settings = get_option( 'wdm_booking_reminder_settings', array() );

// Set default values.
$email_subject     = isset( $settings['email_subject'] ) ? $settings['email_subject'] : __( 'Payment Reminder for Order #{booking_id}: Your booking for {product_name}', 'wdm-customization' );
$email_body        = isset( $settings['email_body'] ) ? $settings['email_body'] : __(
	"Hello {customer_name},\n\nThis is a reminder that your booking #{booking_id} for {product_name} on {start_date} to {end_date} is confirmed but still requires payment.\n\nTo complete your booking, please proceed with payment through the link below:\n\n{payment_link}\n\nIf you have any questions, please contact us.",
	'wdm-customization'
);
$reminder_interval = isset( $settings['reminder_interval'] ) ? $settings['reminder_interval'] : 3;

?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Booking Reminder Email Settings', 'wdm-customization' ); ?></h1>
	<?php // phpcs:disable ?>
	<?php if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'wdm-customization' ); ?></p>
		</div>
	<?php endif; ?>
	<?php // phpcs:enable ?>
	<form method="post" action="options.php">
		<?php settings_fields( 'wdm_booking_reminder_settings_group' ); ?>
		
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wdm_booking_reminder_settings[email_subject]"><?php echo esc_html__( 'Email Subject', 'wdm-customization' ); ?></label>
				</th>
				<td>
					<input type="text" id="wdm_booking_reminder_settings[email_subject]" name="wdm_booking_reminder_settings[email_subject]" value="<?php echo esc_attr( $email_subject ); ?>" class="regular-text">
					<p class="description"><?php echo esc_html__( 'Subject line for the reminder email. You can use placeholders.', 'wdm-customization' ); ?></p>
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<label for="wdm_booking_reminder_settings[email_body]"><?php echo esc_html__( 'Email Body', 'wdm-customization' ); ?></label>
				</th>
				<td>
					<textarea id="wdm_booking_reminder_settings[email_body]" name="wdm_booking_reminder_settings[email_body]" rows="15" cols="80" class="large-text code"><?php echo esc_textarea( $email_body ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'Content of the reminder email. You can use HTML and placeholders.', 'wdm-customization' ); ?></p>
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<label for="wdm_booking_reminder_settings[reminder_interval]"><?php echo esc_html__( 'Reminder Interval (Days)', 'wdm-customization' ); ?></label>
				</th>
				<td>
					<input type="number" id="wdm_booking_reminder_settings[reminder_interval]" name="wdm_booking_reminder_settings[reminder_interval]" value="<?php echo esc_attr( $reminder_interval ); ?>" class="small-text" min="1" max="14">
					<p class="description"><?php echo esc_html__( 'Minimum days between reminder emails for the same booking.', 'wdm-customization' ); ?></p>
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<?php echo esc_html__( 'Available Placeholders', 'wdm-customization' ); ?>
				</th>
				<td>
					<p><code>{booking_id}</code> - <?php echo esc_html__( 'The booking ID number', 'wdm-customization' ); ?></p>
					<p><code>{customer_name}</code> - <?php echo esc_html__( 'Customer\'s name', 'wdm-customization' ); ?></p>
					<p><code>{customer_email}</code> - <?php echo esc_html__( 'Customer\'s email address', 'wdm-customization' ); ?></p>
					<p><code>{product_name}</code> - <?php echo esc_html__( 'Name of the booked product/service', 'wdm-customization' ); ?></p>
					<p><code>{start_date}</code> - <?php echo esc_html__( 'The booking start date and time', 'wdm-customization' ); ?></p>
					<p><code>{end_date}</code> - <?php echo esc_html__( 'The booking end date and time', 'wdm-customization' ); ?></p>
					<p><code>{payment_link}</code> - <?php echo esc_html__( 'Link to the payment page (automatically converted to button)', 'wdm-customization' ); ?></p>
					<p><code>{site_title}</code> - <?php echo esc_html__( 'Your website name', 'wdm-customization' ); ?></p>
				</td>
			</tr>
		</table>
		
		<?php submit_button(); ?>
	</form>
</div>
