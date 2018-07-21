<?php
/**
 * Implements payment schedule and partial payments
 * */
class WC_Payment_Schedule {

    const TEXTDOMAIN = 'wc-payment-schedule';

    const CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE = 'payment_schedule';

    // option key constants

    // meta keys

    // action and filter keys

    // nonce key

    // ajax action

    public function __construct() {
        // Action hooks
        add_action( 'woocommerce_register_shop_order_post_statuses', array($this,'register_shop_order_post_statuses'),10,1);
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 10, 2 );

        // business logic
        add_filter( 'wc_payment_schedule_create_cart_item_terms', array($this,'create_cart_item_terms' ), 10, 3);

        // UI
        add_action( 'woocommerce_cart_collaterals', array($this,'cart_collaterals'));

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

            $first_term = round($line_total * 0.5);

            $payment_schedule->add_term($first_term, date('m/d/Y'));
            $payment_schedule->add_term($line_total - $first_term, date('m/d/Y',strtotime('-60 days',$delivery_timestamp)));

            $cart_item_data['payment_schedule'] = $payment_schedule;
        }

        return $cart_item_data;
    }
    /**
     * Print the payment schedule on the cart page, if applicable
     * @hook woocommerce_after_cart_contents
     */
    public function cart_collaterals(){
        wp_enqueue_style('wc-payment-schedule', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/css/wc-payment-schedule.css', [] , WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);

        /*
		if ( is_checkout() ) {
			return;
		}
        */

        $cart_payment_schedule = array();

        foreach ( WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item[self::CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE])) {
                $item_payment_terms = $cart_item[self::CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE]->get_terms();
                foreach ($item_payment_terms as $item_payment_term) {
                    if (isset($cart_payment_schedule[$item_payment_term['date']])){
                        $cart_payment_schedule[$item_payment_term['date']] += $item_payment_term['amount'];
                    } else {
                        $cart_payment_schedule[$item_payment_term['date']]  = $item_payment_term['amount'];
                    }
                }
            }
        }

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