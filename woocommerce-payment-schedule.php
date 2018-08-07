<?php
/*
Plugin Name: WooCommerce Payment Schedule
Plugin URI: https://github.com/mkaboldy/woocommerce-payment-schedule
Description: Extends WooCommerce with payment schedule and partial payments
Version: 0.2
Author: Miklos Kaboldy
WC tested up to: 3.4.4
Text Domain: wc-payment-schedule
 */

if (!defined('ABSPATH')) {
    exit;
}

define( 'WC_PAYMENT_SCHEDULE_PLUGIN_VERSION','0.2');
define( 'WC_PAYMENT_SCHEDULE_PLUGIN_PATH' , dirname( __FILE__ ));
define( 'WC_PAYMENT_SCHEDULE_PLUGIN_URL' , plugins_url('', __FILE__ ));


// Make sure WooCommerce is active

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

/**
 * Loads the required classes
 */
function wc_payment_schedule_init() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class.wc_payment_schedule.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class.wc_ps_order.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class.payment_schedule.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class.payment_history.php';
}

add_action( 'plugins_loaded', 'wc_payment_schedule_init', 11 );

