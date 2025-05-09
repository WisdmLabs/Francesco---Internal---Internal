<?php
/**
 * File comment.
 *
 * @package wdm-customization
 */

/**
 * Class WDM_Bookings
 *
 * @package wdm-customization
 */
class WDM_Bookings {


	/**
	 * Instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @var WDM_Bookings
	 */
	private static $instance;


	/**
	 * Gets the instance of the WDM_Bookings class.
	 *
	 * @since 1.0.0
	 *
	 * @return WDM_Bookings The instance of the WDM_Bookings class.
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
	}
}
