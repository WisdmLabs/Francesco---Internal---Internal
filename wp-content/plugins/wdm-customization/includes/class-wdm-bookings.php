<?php
/**
 * WDM Bookings Customizations.
 *
 * @package wdm-customization
 */

/**
 * Class WDM_Bookings
 *
 * Handles customizations for YITH WooCommerce Booking and TM Extra Product Options.
 *
 * @package wdm-customization
 */
class WDM_Bookings {

	/**
	 * The single instance of the class.
	 *
	 * @var WDM_Bookings
	 */
	protected static $instance = null;

	/**
	 * Allowed email IDs for the additional_details placeholder.
	 *
	 * @var array
	 */
	private $allowed_email_ids_for_extra_placeholder;

	/**
	 * Gets the instance of the WDM_Bookings class.
	 *
	 * @since 1.0.0
	 * @return WDM_Bookings
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
		// Initialize allowed email IDs to add custom placeholder to.
		$this->allowed_email_ids_for_extra_placeholder = array(
			'yith_wcbk_admin_new_booking',
			'yith_wcbk_customer_new_booking',
			'yith_wcbk_customer_confirmed_booking',
			'yith_wcbk_customer_paid_booking',
		);

		// Using yith_wcbk_booking_created hook because it runs before the "New Booking" email is sent.
		add_action( 'yith_wcbk_booking_created', array( $this, 'wdm_save_tm_extra_options_on_booking_created' ), 1, 1 );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'wdm_merge_tm_epo_into_yith_booking_cart_item' ), 20, 3 );

		// User-side: "My Account" > View Booking page.
		add_action( 'yith_wcbk_view_booking', array( $this, 'wdm_display_additional_data_on_view_booking_page' ), 15, 1 );

		// Admin-side: Booking Edit page - after the second column of details.
		add_action( 'yith_wcbk_booking_metabox_info_after_second_column', array( $this, 'wdm_display_additional_data_on_admin_booking_edit' ), 10, 1 );

		// Process the placeholder value when emails are sent.
		add_filter( 'yith_wcbk_email_placeholders', array( $this, 'wdm_additional_details_email_placeholder' ), 50, 2 );

		// Add our placeholder to the settings UI using direct email IDs - admin only.
		add_action( 'admin_init', array( $this, 'hook_form_fields_for_yith_emails' ) );
	}

	/**
	 * Save TM Extra Product Options when a booking is created.
	 * This function runs as early as possible when a booking is created.
	 *
	 * @param YITH_WCBK_Booking $booking The booking object.
	 */
	public function wdm_save_tm_extra_options_on_booking_created( $booking ) {
		if ( ! $booking || ! $booking->is_valid() ) {
			return;
		}

		$booking_id = $booking->get_id();
		$product_id = $booking->get_product_id();
		$product    = $booking->get_product();

		if ( ! $product || ! $product_id ) {
			return;
		}

		// Save the raw request args.
		$args = $_REQUEST; // phpcs:ignore
		update_post_meta( $booking_id, '_wdm_booking_raw_args', is_array( $args ) ? $args : array() );

		// Store Extra options in meta to display on the booking page.
		if ( ! empty( $args ) && is_array( $args ) && class_exists( 'THEMECOMPLETE_EPO_Cart' ) && method_exists( THEMECOMPLETE_EPO_Cart::instance(), 'add_cart_item_data_helper' ) ) {
			// We are using the add_cart_item_data_helper method because Labels are not provided in the args and we need to get them from the EPO.
			// In processed_epo_data_for_cart, we get the labels in the 'name' key.
			$processed_epo_data_for_cart = THEMECOMPLETE_EPO_Cart::instance()->add_cart_item_data_helper( array(), $product_id, $args );

			if ( isset( $processed_epo_data_for_cart['tmcartepo'] ) && is_array( $processed_epo_data_for_cart['tmcartepo'] ) ) {
				$tmcartepo_data                   = $processed_epo_data_for_cart['tmcartepo'];
				$booking_display_details_for_meta = array();

				if ( ! empty( $tmcartepo_data ) ) {
					foreach ( $tmcartepo_data as $epo_item ) {
						if ( isset( $epo_item['name'] ) && isset( $epo_item['value'] ) ) {
							// Skip items where the value might be empty or not suitable for display, or if it's a hidden field.
							if ( empty( $epo_item['value'] ) && '0' !== (string) $epo_item['value'] ) {
								continue;
							}

							$display_key        = esc_html( (string) $epo_item['name'] );
							$temp_display_value = is_array( $epo_item['value'] ) ? implode( ', ', array_map( 'esc_html', $epo_item['value'] ) ) : esc_html( (string) $epo_item['value'] );

							$booking_display_details_for_meta[] = array(
								'label' => $display_key,
								'value' => $temp_display_value,
							);
						}
					}
				}

				if ( ! empty( $booking_display_details_for_meta ) ) {
					update_post_meta( $booking_id, '_wdm_booking_display_details', $booking_display_details_for_meta );
				}
			}
		}
	}

	/**
	 * Merge TM Extra Product Options into cart item data for confirmed YITH Bookings.
	 *
	 * @param array $cart_item_data         WooCommerce cart item data.
	 * @param int   $product_id_from_wc     Product ID being added.
	 * @param int   $variation_id_from_wc   Variation ID, if any.
	 * @return array Modified cart item data.
	 */
	public function wdm_merge_tm_epo_into_yith_booking_cart_item( $cart_item_data, $product_id_from_wc, $variation_id_from_wc ) { // phpcs:ignore
		if ( isset( $cart_item_data['yith_booking_data'] ) && is_array( $cart_item_data['yith_booking_data'] ) && isset( $cart_item_data['yith_booking_data']['_booking_id'] ) ) {

			$booking_id = absint( $cart_item_data['yith_booking_data']['_booking_id'] );
			$raw_args   = get_post_meta( $booking_id, '_wdm_booking_raw_args', true );

			if ( ! empty( $raw_args ) && is_array( $raw_args ) && class_exists( 'THEMECOMPLETE_EPO_Cart' ) && method_exists( THEMECOMPLETE_EPO_Cart::instance(), 'add_cart_item_data_helper' ) ) {
				$tm_epo_processed_data = THEMECOMPLETE_EPO_Cart::instance()->add_cart_item_data_helper( array(), $product_id_from_wc, $raw_args );
				return array_merge( $cart_item_data, $tm_epo_processed_data );
			}
		}
		return $cart_item_data;
	}

	/**
	 * Display additional data (from TM EPO) on the "View Booking" page in My Account.
	 *
	 * @param int $booking_id The ID of the booking being viewed.
	 */
	public function wdm_display_additional_data_on_view_booking_page( $booking_id ) {
		$display_details = get_post_meta( $booking_id, '_wdm_booking_display_details', true );

		// Check if $display_details is an array and represents the new list format (or an empty list).
		if ( is_array( $display_details ) && ( empty( $display_details ) || ( is_array( reset( $display_details ) ) && isset( reset( $display_details )['label'] ) && isset( reset( $display_details )['value'] ) ) ) ) {
			if ( ! empty( $display_details ) ) {
				echo '<h2>' . esc_html__( 'Additional Details', 'wdm-customization' ) . '</h2>';
				echo '<table class="shop_table booking_details">';
				echo '<tbody>';
				foreach ( $display_details as $item ) {
					if ( isset( $item['label'] ) && isset( $item['value'] ) ) {
						echo '<tr>';
						echo '<th>' . esc_html( $item['label'] ) . '</th>';
						echo '<td>' . wp_kses_post( $item['value'] ) . '</td>';
						echo '</tr>';
					}
				}
				echo '</tbody>';
				echo '</table>';
			}
		}
	}

	/**
	 * Display additional data (from TM EPO) on the admin booking edit page.
	 *
	 * @param YITH_WCBK_Booking $booking The YITH Booking object.
	 */
	public function wdm_display_additional_data_on_admin_booking_edit( $booking ) {
		if ( ! $booking || ! is_a( $booking, 'YITH_WCBK_Booking' ) ) {
			return;
		}
		$booking_id      = $booking->get_id();
		$display_details = get_post_meta( $booking_id, '_wdm_booking_display_details', true );

		// Check if $display_details is an array and represents the new list format (or an empty list).
		if ( is_array( $display_details ) && ( empty( $display_details ) || ( is_array( reset( $display_details ) ) && isset( reset( $display_details )['label'] ) && isset( reset( $display_details )['value'] ) ) ) ) {
			if ( ! empty( $display_details ) ) {
				echo '<div class="options_group booking-data__column">';
				echo '<h4 class="booking-data__title">' . esc_html__( 'Additional Submitted Details', 'wdm-customization' ) . '</h4>';

				foreach ( $display_details as $item ) {
					// Inner check for robustness, though outer condition implies structure.
					if ( isset( $item['label'] ) && isset( $item['value'] ) ) {
						echo '<div class="form-field form-field-wide">';
						echo '<label style="font-weight: normal;">' . esc_html( $item['label'] ) . ':</label> ';
						echo '<span class="booking-data__value">' . wp_kses_post( $item['value'] ) . '</span>';
						echo '</div>';
					}
				}
				echo '</div>';
			}
		}
	}

	/**
	 * Add {additional_details} placeholder for YITH Booking emails.
	 *
	 * @param array    $placeholders The current placeholders.
	 * @param WC_Email $context      The email object or booking object.
	 * @return array Modified placeholders with {additional_details}.
	 */
	public function wdm_additional_details_email_placeholder( $placeholders, $context ) {
		// Only add placeholder for allowed email IDs.
		if ( is_object( $context ) && isset( $context->id ) && ! in_array( $context->id, $this->allowed_email_ids_for_extra_placeholder, true ) ) {
			$placeholders['{additional_details}'] = '';
			return $placeholders;
		}

		$booking = null;
		// If context is a YITH_WCBK_Email or WC_Email, extract booking from ->object.
		if ( is_object( $context ) && isset( $context->object ) && is_object( $context->object ) && method_exists( $context->object, 'get_id' ) ) {
			$booking = $context->object;
		} else {
			$placeholders['{additional_details}'] = '';
			return $placeholders;
		}
		if ( ! $booking ) {
			$placeholders['{additional_details}'] = '';
			return $placeholders;
		}
		$booking_id      = $booking->get_id();
		$display_details = get_post_meta( $booking_id, '_wdm_booking_display_details', true );

		if ( is_array( $display_details ) && ! empty( $display_details ) ) {
			ob_start();
			?>
			<div style="margin-bottom: 40px; padding: 0;">
				<h2 style="margin: 0 0 10px 0; padding: 10px 15px; background-color: #f8f8f8; color: #000; font-size: 16px; font-weight: bold; line-height: 1.5;"><?php esc_html_e( 'Additional Details', 'wdm-customization' ); ?></h2>
				<table style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border-collapse: collapse; background-color: #f8f8f8;" cellpadding="6" cellspacing="0">
					<?php foreach ( $display_details as $item ) : ?>
						<?php if ( isset( $item['label'] ) && isset( $item['value'] ) ) : ?>
							<tr>
								<th scope="row" style="padding: 12px 15px; text-align: left; font-weight: bold; width: 50%; vertical-align: top; border-top: 1px solid #e5e5e5;"><?php echo esc_html( $item['label'] ); ?></th>
								<td style="padding: 12px 15px; text-align: left; width: 50%; vertical-align: top; border-top: 1px solid #e5e5e5;"><?php echo wp_kses_post( $item['value'] ); ?></td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</table>
			</div>
			<?php
			$placeholders['{additional_details}'] = ob_get_clean();
		} else {
			$placeholders['{additional_details}'] = '';
		}
		return $placeholders;
	}

	/**
	 * Add our placeholder directly to the form fields of YITH Booking email classes
	 * without iterating through all email classes.
	 * This approach uses a list of YITH email IDs.
	 */
	public function hook_form_fields_for_yith_emails() {
		// Use the class property for email IDs instead of redefining the array.
		foreach ( $this->allowed_email_ids_for_extra_placeholder as $email_id ) {
			add_filter( 'woocommerce_settings_api_form_fields_' . $email_id, array( $this, 'add_custom_placeholder_description' ), 10, 1 );
		}
	}

	/**
	 * Modifies the description of the 'custom_message' field to include
	 * the {additional_details} placeholder.
	 *
	 * @param array $form_fields The email settings form fields.
	 * @return array Modified form fields.
	 */
	public function add_custom_placeholder_description( $form_fields ) {
		if ( isset( $form_fields['custom_message']['description'] ) ) {
			// Check if our placeholder is already there (e.g., from another run).
			if ( false === strpos( $form_fields['custom_message']['description'], '{additional_details}' ) ) {
				// The target is to insert just before the final </span> of the placeholder block.
				$closing_span_tag = '</span>';
				$position         = strrpos( $form_fields['custom_message']['description'], $closing_span_tag );

				if ( false !== $position ) {
					// Insert our new placeholder, correctly formatted, before the closing span.
					$new_placeholder_html                         = ' <code>{additional_details}</code>';
					$form_fields['custom_message']['description'] = substr_replace(
						$form_fields['custom_message']['description'],
						$new_placeholder_html,
						$position,
						0 // insert, don't replace.
					);
				}
			}
		}
		return $form_fields;
	}
}
