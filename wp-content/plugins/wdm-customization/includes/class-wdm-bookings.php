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
		if ( isset( $cart_item_data['yith_booking_data'] ) &&
			is_array( $cart_item_data['yith_booking_data'] ) &&
			isset( $cart_item_data['yith_booking_data']['_booking_id'] ) ) {

			$booking_id = absint( $cart_item_data['yith_booking_data']['_booking_id'] );
			$raw_args   = get_post_meta( $booking_id, '_wdm_booking_raw_args', true );

			if ( ! empty( $raw_args ) && is_array( $raw_args ) && class_exists( 'THEMECOMPLETE_EPO_Cart' ) && method_exists( THEMECOMPLETE_EPO_Cart::instance(), 'add_cart_item_data_helper' ) ) {
				$tm_epo_processed_data = THEMECOMPLETE_EPO_Cart::instance()->add_cart_item_data_helper( array(), $product_id_from_wc, $raw_args );
				return $tm_epo_processed_data;
			}
		}
		return $cart_item_data;
	}
}
