<?php
/**
 * Implements payment schedule and partial payments
 * */
class WC_Payment_Schedule {

    const TEXTDOMAIN = 'wc-payment-schedule';

    const CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE = 'payment_schedule';

    // option key constants

    // action and filter keys

    const FILTER_CREATE_CART_ITEM_TERMS = 'wc_payment_schedule_create_cart_item_terms';

    // nonce key

    // ajax action

    public function __construct() {
        // Action hooks
        add_action( 'woocommerce_register_shop_order_post_statuses', array($this,'register_shop_order_post_statuses'),10,1);
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 10, 2 );

        // business logic
        add_filter( self::FILTER_CREATE_CART_ITEM_TERMS , array($this,'create_cart_item_terms' ), 10, 3); // private hook, host app must call it
        add_action( 'woocommerce_checkout_update_order_meta', array($this,'checkout_update_order_meta'), 10, 2); // store payment schedule in order record
        add_filter( 'woocommerce_order_get_total' , array($this,'checkout_payment_amount'), 10, 2); // get the first term for checkout

        // UI
        add_action( 'woocommerce_cart_totals_after_order_total', array($this,'after_order_total'));
        add_action( 'woocommerce_review_order_after_order_total', array($this,'after_order_total'));
    }

    /**
     * Add payment terms info to a cart item
     * @hook wc_payment_schedule_create_cart_item_terms
     * @param mixed $cart_item_data
     * @param mixed $line_total
     * @param mixed $delivery_timestamp
     * @return mixed
     */
    public function create_cart_item_terms($cart_item_data,$line_total,$delivery_timestamp) {

        if ($delivery_timestamp - time() > strtotime("+60 days",0)) {

            $payment_schedule = new Payment_Schedule();
            $payment_schedule->create_line_payment_schedule($line_total,$delivery_timestamp);

            $cart_item_data[self::CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE] = $payment_schedule;
        }

        return $cart_item_data;
    }
    /**
     * Summary of update_order_meta
     * @param mixed $order_id
     * @param mixed $data
     * @return string
     */
    public function checkout_update_order_meta($order_id, $data) {
        $order = new WC_PS_Order(wc_get_order($order_id));
        $order->create_payment_schedule();
        return;
    }
    /**
     * Return the first term of payment for checkout
     * @hook woocommerce_order_get_total
     * @param mixed $amount
     * @param mixed $order
     * @return mixed
     */
    public function checkout_payment_amount($amount, $order) {
		if (is_checkout() ) {

            // do this only once
            remove_filter(current_filter(),array($this,__FUNCTION__));

            $order = new WC_PS_Order(wc_get_order($order));

            if ($order->has_payment_schedule()) {
                return $order->get_first_amount();
            }
		}
        return $amount;
    }

    /**
     * Collect and print the payment schedule on the cart page, if applicable
     * @hook woocommerce_cart_totals_after_order_total
     * @hook woocommerce_review_order_after_order_total
     */
    public function after_order_total(){
        wp_enqueue_style('wc-payment-schedule', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/css/wc-payment-schedule.css', [] , WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);

        $cart_payment_schedule = Payment_Schedule::create_cart_payment_schedule();

        if (count($cart_payment_schedule) > 1 ) {
            wc_get_template( 'cart/payment_schedule.php', array('payment_schedule'    => $cart_payment_schedule),'',WC_PAYMENT_SCHEDULE_PLUGIN_PATH . '/templates/' );
        }
    }
    /**
     * add post status "partial"
     * @hook woocommerce_register_shop_order_post_statuses
     * @param mixed $statuses
     * @return array
     */
    function register_shop_order_post_statuses($statuses){
        $statuses = array_merge($statuses,array(
				'wc-partial'    => array(
					'label'                     => _x( 'Partial payment', 'Order status', 'woocommerce' ),
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop( 'Partial payment <span class="count">(%s)</span>', 'Partial payment <span class="count">(%s)</span>', 'woocommerce' ),
				),
            ));
        return $statuses;
    }
    /**
     * Define the admin metaboxes
     * @hook add_meta_boxes
     */
    function register_meta_box() {
        global $post;
        if ($wc_order = wc_get_order($post->ID)) {
            $PS_order = new WC_PS_Order($wc_order);
            if ($PS_order->has_payment_history()) {
                add_meta_box( 'payment-history', 'Payment History', [ $this, 'payment_history_meta_box_content' ], 'shop_order', 'side' );
            }
            if ($PS_order->has_payment_schedule()) {
                add_meta_box( 'payment-schedule', 'Payment Schedule', [ $this, 'payment_schedule_meta_box_content' ], 'shop_order', 'side' );
            }
        }
    }
    /**
     * Payment history metabox callback
     * @param WP_Post $post Current post object.
     * @return void
     */
    function payment_history_meta_box_content(WP_Post $post) {
        wp_enqueue_script('wc-payment-schedule-admin', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/js/admin.js', [], WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);
        wp_enqueue_style('wc-payment-schedule-admin', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/css/admin.css', [] , WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);

        global $pagenow;
        $order = new WC_Order( wc_get_order() );

        if (! $order->is_editable()) {
            echo __('<p>This order is closed, payment cannot be processed anymore.</p>',self::TEXTDOMAIN);
            return;
        }

        if (! $order->needs_payment()) {
            echo __('<p>This order doesn\'t need any payment.</p>',self::TEXTDOMAIN);
            if ( in_array( $pagenow, array( 'post-new.php' )) ) {
                echo __('<p>Add items and fees first, then come back to manage the payment.</p>',self::TEXTDOMAIN);
            }
            return;
        }

        require_once WC_PAYMENT_SCHEDULE_PLUGIN_PATH . '/templates/admin/payment-history-meta-box.php';
    }
    /**
     * Payment history metabox callback
     * @param WP_Post $post Current post object.
     * @return void
     */
    function payment_schedule_meta_box_content(WP_Post $post) {
        global $pagenow;
        wp_enqueue_script('wc-payment-schedule-admin', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/js/admin.js', [], WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);
        wp_enqueue_style('wc-payment-schedule-admin', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/css/admin.css', [] , WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);

        $order = new WC_Order( wc_get_order() );

        if (! $order->is_editable()) {
            echo __('<p>This order is closed, payment cannot be processed anymore.</p>',self::TEXTDOMAIN);
            return;
        }

        if (! $order->needs_payment()) {
            echo __('<p>This order doesn\'t need any payment.</p>',self::TEXTDOMAIN);
            if ( in_array( $pagenow, array( 'post-new.php' )) ) {
                echo __('<p>Add items and fees first, then come back to manage the payment.</p>',self::TEXTDOMAIN);
            }
            return;
        }

        require_once WC_PAYMENT_SCHEDULE_PLUGIN_PATH . '/templates/admin/payment-schedule-meta-box.php';
    }
} // end WC_Payment_Schedule

new WC_Payment_Schedule;
?>