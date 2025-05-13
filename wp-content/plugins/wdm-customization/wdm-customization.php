<?php
/**
 * WDM Customization Plugin
 *
 * @package wdm-customization
 */

/**
 * Plugin Name: WDM Customization
 * Plugin URI:  https://www.wisdmlabs.com
 * Description: This plugin is used to customize the behaviour of the site for YITH Booking Plugin
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0.30
 * Author:      Wisdmlabs
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wdm-customization
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 * Requires Plugins: woocommerce, yith-woocommerce-booking-premium, woocommerce-tm-extra-product-options
 *
 * @package wdm-customization
 */

// Define plugin constants.
if ( ! defined( 'WDM_CUSTOMIZATION_PATH' ) ) {
	define( 'WDM_CUSTOMIZATION_PATH', plugin_dir_path( __FILE__ ) );
}


require_once __DIR__ . '/includes/class-wdm-bookings.php';
require_once __DIR__ . '/includes/class-wdm-bookings-reminder-emails.php';

WDM_Bookings::get_instance();
WDM_Bookings_Reminder_Emails::get_instance();
