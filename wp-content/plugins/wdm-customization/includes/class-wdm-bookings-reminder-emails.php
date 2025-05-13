<?php
/**
 * WDM Bookings Reminder Emails.
 *
 * @package wdm-customization
 */

/**
 * Class WDM_Bookings_Reminder_Emails
 *
 * Handles sending reminder emails for YITH WooCommerce Booking.
 *
 * @package wdm-customization
 */
class WDM_Bookings_Reminder_Emails {

	/**
	 * The single instance of the class.
	 *
	 * @var WDM_Bookings_Reminder_Emails
	 */
	protected static $instance = null;

	/**
	 * Gets the instance of the WDM_Bookings_Reminder_Emails class.
	 *
	 * @since 1.0.0
	 * @return WDM_Bookings_Reminder_Emails
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Initialize hooks and filters for reminder emails.
		add_action( 'init', array( $this, 'register_reminder_email_hooks' ) );

		// Register the settings page.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Hook the event to our reminder function.
		add_action( 'wdm_daily_booking_payment_reminder', array( $this, 'process_pending_payment_reminders' ) );
	}

	/**
	 * Register hooks and filters for reminder emails.
	 *
	 * @return void
	 */
	public function register_reminder_email_hooks() {
		// Schedule the daily check for pending payment bookings if not already scheduled.
		if ( ! wp_next_scheduled( 'wdm_daily_booking_payment_reminder' ) ) {
			wp_schedule_event( time(), 'daily', 'wdm_daily_booking_payment_reminder' );
		}
	}

	/**
	 * Add settings page to WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Booking Reminder Settings', 'wdm-customization' ),
			__( 'Booking Reminders', 'wdm-customization' ),
			'manage_options',
			'wdm-booking-reminder-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-email-alt',
			58 // Position after WooCommerce.
		);
	}

	/**
	 * Register settings for the reminder emails.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wdm_booking_reminder_settings_group',
			'wdm_booking_reminder_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize the settings before saving.
	 *
	 * @param array $input The settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['email_subject'] ) ) {
			$sanitized['email_subject'] = sanitize_text_field( $input['email_subject'] );
		}

		if ( isset( $input['email_body'] ) ) {
			$sanitized['email_body'] = wp_kses_post( $input['email_body'] );
		}

		if ( isset( $input['reminder_interval'] ) ) {
			$sanitized['reminder_interval'] = absint( $input['reminder_interval'] );
			if ( $sanitized['reminder_interval'] < 1 ) {
				$sanitized['reminder_interval'] = 1;
			}
			if ( $sanitized['reminder_interval'] > 14 ) {
				$sanitized['reminder_interval'] = 14;
			}
		}

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// Make sure the template exists.
		$template_file = WDM_CUSTOMIZATION_PATH . 'includes/templates/admin-reminder-settings.php';

		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Settings template file not found.', 'wdm-customization' );
			echo '</p></div>';
		}
	}

	/**
	 * Get settings for the reminder emails.
	 *
	 * @return array Settings array.
	 */
	public function get_settings() {
		$defaults = array(
			'email_subject'     => __( 'Payment Reminder for Order #{booking_id}: Your booking for {product_name}', 'wdm-customization' ),
			'email_body'        => __(
				"Hello {customer_name},\n\nThis is a reminder that your booking #{booking_id} for {product_name} on {start_date} to {end_date} is confirmed but still requires payment.\n\nTo complete your booking, please proceed with payment through the link below:\n\n{payment_link}\n\nIf you have any questions, please contact us.",
				'wdm-customization'
			),
			'reminder_interval' => 3,
		);

		$settings = get_option( 'wdm_booking_reminder_settings', array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get IDs of confirmed bookings with pending payment and future start dates.
	 *
	 * @return array Array of booking IDs that meet the criteria.
	 */
	public function get_pending_payment_future_bookings() {
		// Get current timestamp and 30 days in the future (fixed threshold instead of setting).
		$current_timestamp   = time();
		$threshold_timestamp = strtotime( '+30 days', $current_timestamp );

		// Build the query args correctly for YITH Bookings.
		$args = array(
			'status'    => 'confirmed', // Admin confirmed booking.
			'date_from' => $current_timestamp, // Only bookings from now.
			'date_to'   => $threshold_timestamp, // Up to threshold.
			'return'    => 'ids', // Return only IDs for memory efficiency.
		);

		// Use the YITH function to query bookings.
		$booking_ids = array();
		if ( function_exists( 'yith_wcbk_get_bookings' ) ) {
			$booking_ids = yith_wcbk_get_bookings( $args );
		}

		return $booking_ids;
	}

	/**
	 * Send payment reminder email for a booking.
	 *
	 * @param object $booking The booking object.
	 * @return void
	 */
	public function send_payment_reminder_email( $booking ) {
		if ( ! $booking || ! is_a( $booking, 'YITH_WCBK_Booking' ) || ! $booking->is_valid() ) {
			return;
		}

		// Ensure booking is confirmed but not paid.
		if ( ! $booking->has_status( 'confirmed' ) || $booking->has_status( 'paid' ) ) {
			return;
		}

		// Get customer info.
		$customer_email = $booking->get_user_email();

		if ( empty( $customer_email ) ) {
			return;
		}

		// Get settings.
		$settings = $this->get_settings();

		// Get booking details.
		$booking_id   = $booking->get_id();
		$product      = $booking->get_product();
		$product_name = $product ? $product->get_title() : __( 'your booking', 'wdm-customization' );

		// Use YITH's date formatting.
		$from_formatted = $booking->get_formatted_from(); // This uses YITH's formatting.
		$to_formatted   = $booking->get_formatted_to(); // End date formatting.
		$payment_url    = $booking->get_confirmed_booking_payment_url(); // YITH's payment URL.

		$user_name = $booking->get_user()->display_name;
		// Set up placeholders.
		$placeholders = array(
			'{booking_id}'     => $booking_id,
			'{customer_name}'  => '' === $user_name ? 'Customer' : $user_name,
			'{customer_email}' => $customer_email,
			'{product_name}'   => $product_name,
			'{start_date}'     => $from_formatted,
			'{end_date}'       => $to_formatted,
			'{payment_link}'   => '<a href="' . esc_url( $payment_url ) . '" target="_blank" rel="noopener noreferrer" style="background-color: #2271b1; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __( 'Pay Now', 'wdm-customization' ) . '</a>',
			'{site_title}'     => get_bloginfo( 'name' ),
		);

		// Replace placeholders in subject and body.
		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $settings['email_subject'] );
		$body    = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $settings['email_body'] );

		// Prepare email content - use WooCommerce's template system.
		$mailer = WC()->mailer();

		// Prepare the template.
		$email_heading = __( 'Payment Reminder', 'wdm-customization' );

		ob_start();

		// Get email template header.
		wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );

		// Email body with placeholders already replaced.
		echo wp_kses_post( wpautop( $body ) );

		// Get email template footer.
		wc_get_template( 'emails/email-footer.php' );

		$message = ob_get_clean();

		// Send the email.
		$headers = 'Content-Type: text/html; charset=UTF-8';

		$mailer->send( $customer_email, $subject, $message, $headers );

		// Record that we sent a reminder.
		update_post_meta( $booking_id, '_wdm_payment_reminder_sent', time() );
	}

	/**
	 * Process all eligible bookings and send reminders.
	 *
	 * @return void
	 */
	public function process_pending_payment_reminders() {
		// Get settings.
		$settings          = $this->get_settings();
		$reminder_interval = $settings['reminder_interval'];

		// Get all eligible booking IDs.
		$booking_ids = $this->get_pending_payment_future_bookings();

		foreach ( $booking_ids as $booking_id ) {
			// First check if a reminder has been sent recently without loading the full booking object.
			$last_reminder = get_post_meta( $booking_id, '_wdm_payment_reminder_sent', true );

			if ( $last_reminder ) {
				$last_reminder_time = (int) $last_reminder;
				$interval_ago       = time() - $reminder_interval * 24 * 60 * 60;

				if ( $last_reminder_time > $interval_ago ) {
					// Skip this booking if a reminder was sent recently.
					continue;
				}
			}

			// Only now load the booking object since we need to check status.
			$booking = yith_get_booking( $booking_id );

			// Skip invalid bookings.
			if ( ! $booking || ! is_a( $booking, 'YITH_WCBK_Booking' ) || ! $booking->is_valid() ) {
				continue;
			}

			// Skip if not confirmed or already paid.
			if ( ! $booking->has_status( 'confirmed' ) || $booking->has_status( 'paid' ) ) {
				continue;
			}

			// Send the reminder.
			$this->send_payment_reminder_email( $booking );

			// Free memory.
			unset( $booking );
		}
	}
}
