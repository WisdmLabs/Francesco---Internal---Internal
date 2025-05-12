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
	 * Instance of the class.
	 *
	 * @since 1.0.0
	 * @var WDM_Bookings
	 */
	private static $instance;

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
		add_action( 'yith_wcbk_after_request_confirmation_action', array( $this, 'wdm_save_tm_extra_options_with_booking_request' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'wdm_merge_tm_epo_into_yith_booking_cart_item' ), 20, 3 );

		// User-side: "My Account" > View Booking page.
		add_action( 'yith_wcbk_view_booking', array( $this, 'wdm_display_additional_data_on_view_booking_page' ), 15, 1 );

		// Admin-side: Booking Edit page - after the second column of details.
		add_action( 'yith_wcbk_booking_metabox_info_after_second_column', array( $this, 'wdm_display_additional_data_on_admin_booking_edit' ), 10, 1 );
	}

	/**
	 * Save TM Extra Product Options and their raw arguments when a booking request requiring confirmation is made.
	 *
	 * @param bool               $success Whether the action was successful.
	 * @param YITH_WCBK_Booking  $booking The booking object.
	 * @param WC_Product_Booking $product The product object.
	 * @param array              $props   The booking properties.
	 * @param array              $args    The arguments from the booking form submission.
	 */
	public function wdm_save_tm_extra_options_with_booking_request( $success, $booking, $product, $props, $args ) {
		if ( ! $success || ! $booking ) {
			return;
		}
		$booking_id = $booking->get_id();
		update_post_meta( $booking_id, '_wdm_booking_raw_args', is_array( $args ) ? $args : array() );

		// --- Store Extra options in meta to display on the booking page ---
		if ( ! empty( $args ) && is_array( $args ) && $product && method_exists( $product, 'get_id' ) && class_exists( 'THEMECOMPLETE_EPO_Cart' ) && method_exists( THEMECOMPLETE_EPO_Cart::instance(), 'add_cart_item_data_helper' ) ) {
			$product_id = $product->get_id();
			if ( $product_id ) {
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
				return $tm_epo_processed_data;
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
}
