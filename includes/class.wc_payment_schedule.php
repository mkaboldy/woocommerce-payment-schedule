<?php
/**
 * Implements payment schedule and partial payments
 * */
class WC_Payment_Schedule {

    const TEXTDOMAIN = 'wc-payment-schedule';

    const CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE = 'payment_schedule';

    const POST_STATUS_PARTIAL = 'wc-partial';

    // option key constants

    // action and filter keys

    const FILTER_CREATE_CART_ITEM_TERMS = 'wc_payment_schedule_create_cart_item_terms';

    // nonce key

    // ajax action

    public function __construct() {

        // Action hooks
        add_action( 'woocommerce_register_shop_order_post_statuses', array($this,'register_shop_order_post_statuses'),10,1);
        add_filter( 'wc_order_statuses', array($this,'wc_order_statuses'), 10, 1);
        add_filter( 'woocommerce_valid_order_statuses_for_payment', array($this,'valid_order_statuses_for_payment'), 10, 2);

        // business logic
        add_filter( self::FILTER_CREATE_CART_ITEM_TERMS , array($this,'create_cart_item_terms' ), 10, 3); // private hook, host app must call it
        add_action( 'woocommerce_checkout_update_order_meta', array($this,'checkout_update_order_meta'), 10, 2); // store payment schedule in order record
        add_filter( 'woocommerce_order_get_total' , array($this,'checkout_payment_amount'), 10, 2); // get the first term for checkout
        add_action( 'wp_loaded', array($this,'wp_loaded')); // conditionally manage order status (partial/completed)
        add_filter( 'wc_order_is_editable', array($this,'order_is_editable'), 10, 2); // partially paid orders should be editable

        // UI
        add_action( 'woocommerce_cart_totals_after_order_total', array($this,'after_cart_order_total'));
        add_action( 'woocommerce_review_order_after_order_total', array($this,'after_cart_order_total'));
        add_action( 'woocommerce_thankyou', array($this,'after_order_total'), 9, 1);
        add_action( 'woocommerce_email_after_order_table', array($this,'email_after_order_table'), 10, 5);

        // admin

        add_filter( 'manage_shop_order_posts_columns', array( $this, 'shop_order_posts_columns' ), 11);
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'shop_order_posts_custom_column' ), 10, 2 );
        add_filter( 'add_meta_boxes', [ $this, 'register_meta_box' ], 10, 2 );

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
        $order->save_payment_schedule();
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

        global $wp;

        if (!is_checkout() ) {
            return $amount;
        }

        if (get_query_var('order-received')) {
            return $amount;
        }

        // do this only once
        remove_filter(current_filter(),array($this,__FUNCTION__));

        $order = new WC_PS_Order(wc_get_order($order));

        if ($order->has_payment_schedule()) {
            return $order->get_first_amount();
        }
        return $amount;
    }

    public function wp_loaded(){
        if (class_exists('WC_Rent_Payment_Gateway')) {
            // mark payment partial/complete upon payment success
            add_filter( WC_Rent_Payment_Gateway::FILTER_PAYMENT_SUCCESS_ORDER_STATUS , array($this,'payment_success_order_status'), 10, 2);
            add_filter( WC_Rent_Payment_Gateway::FILTER_PAYMENT_SUCCESS_ORDER_STATUS_MSG , array($this,'payment_success_order_status_msg'), 10, 2);
        } else {
            add_action( 'woocommerce_payment_successful_result', array($this,'payment_successful_result_status'), 11, 2); // mark payment partial/complete after payment success
        }
        add_action( 'woocommerce_payment_successful_result', array($this,'payment_successful_result_history'), 10, 2); // add item to payment history
    }

    public function payment_successful_result_history( $payment_processing_result, $order_id ) {

        $wc_ps_order = new WC_PS_Order(wc_get_order( $order_id ));

        if ($wc_ps_order->has_payment_schedule()) {
            $first_amount =  $wc_ps_order->get_first_amount();
            $wc_ps_order->add_payment_history_item($first_amount,date('d/m/Y'));
            $wc_ps_order->save_payment_history();
        }
    }

    /**
     * - set order status partial/completed
     * @hook woocommerce_payment_successful_result
     * @param array $result
     * @param int $order_id
     */
    public function payment_successful_result_status($result, $order_id) {
        $order = new WC_PS_Order(wc_get_order($order_id));
        try {
            if ($order->get_total_amount_unpaid() > 0) {
                $order->set_status(self::POST_STATUS_PARTIAL);
                $order->save();
            }
		} catch ( Exception $e ) {
			$logger = wc_get_logger();
			$logger->error(
				sprintf( 'Error updating status for order #%d', $order_id ), array(
					'order' => $order,
					'error' => $e,
				)
			);
			$order->add_order_note( __( 'Update status event failed.', 'woocommerce' ) . ' ' . $e->getMessage() );
			return false;
		}

        return $result;
    }

    function payment_success_order_status($status, WC_order $order) {
        $ps_order = new WC_PS_Order($order);
        if ($ps_order->get_total_amount_unpaid() > 0) {
            return self::POST_STATUS_PARTIAL;
        }
        return $status;
    }

    function payment_success_order_status_msg($status_msg, WC_order $order) {
        $ps_order = new WC_PS_Order($order);
        if ($ps_order->get_total_amount_unpaid() > 0) {
            return __( 'Payment partially completed', self::TEXTDOMAIN );
        }
        return $status_msg;
    }

    /**
     * Allow editing partial orders
     * @hook wc_order_is_editable
     * @param bool $editable
     * @param WC_Order $order
     * @return boolean
     */
    public function order_is_editable($editable,WC_Order $order) {

        $status_partial = substr(self::POST_STATUS_PARTIAL,3); // TODO this is crazy
        $is_partial = $order->has_status($status_partial);
        $editable = $editable || $is_partial;

        return $editable;
    }

    /**
     * Collect and print the payment schedule on the cart page, if applicable
     * @hook woocommerce_cart_totals_after_order_total
     * @hook woocommerce_review_order_after_order_total
     * Summary of after_cart_order_total
     */
    public function after_cart_order_total() {
        $cart_payment_schedule = Payment_Schedule::create_cart_payment_schedule();
        if (count($cart_payment_schedule) > 1 ) {
            wp_enqueue_style('wc-payment-schedule', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/css/wc-payment-schedule.css', [] , WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);
            wc_get_template( 'cart/payment_schedule.php', array('payment_schedule'    => $cart_payment_schedule),'',WC_PAYMENT_SCHEDULE_PLUGIN_PATH . '/templates/' );
        }
    }

    /**
     * Collect and print the order payment schedule
     * @hook woocommerce_thankyou
     * @param int $order_id
     */
    public function after_order_total($order_id){

        $ps_order = new WC_PS_Order(wc_get_order($order_id));

        $order_payment_schedule = $ps_order->create_payment_schedule();

        if (count($order_payment_schedule) > 1 ) {
            wp_enqueue_style('wc-payment-schedule', WC_PAYMENT_SCHEDULE_PLUGIN_URL . '/assets/css/wc-payment-schedule.css', [] , WC_PAYMENT_SCHEDULE_PLUGIN_VERSION);
            wc_get_template( 'order/payment_schedule.php', array('payment_schedule'    => $order_payment_schedule),'',WC_PAYMENT_SCHEDULE_PLUGIN_PATH . '/templates/' );
        }
    }

    /**
     * Insterts the payment schedule in order emails
     * @hook woocommerce_email_after_order_table
     * @param mixed WC_Order $order
     * @param bool $sent_to_admin
     * @param bool  $plain_text
     * @param string $email
     */
    public function email_after_order_table(WC_Order $order, $sent_to_admin, $plain_text, $email ) {

        $ps_order = new WC_PS_Order($order);

        $order_payment_schedule = $ps_order->create_payment_schedule();

        if (count($order_payment_schedule) > 1 ) {
            wc_get_template( 'email/payment_schedule.php', array('payment_schedule'    => $order_payment_schedule),'',WC_PAYMENT_SCHEDULE_PLUGIN_PATH . '/templates/' );
        }
    }
    /**
     * add post status "partial" for partially paid orders
     * @hook woocommerce_register_shop_order_post_statuses
     * @param array $statuses
     * @return array
     */
    public function register_shop_order_post_statuses($statuses){
        $statuses = array_merge($statuses,array(
				self::POST_STATUS_PARTIAL    => array(
					'label'                     => _x( 'Partial payment', 'Order status', self::TEXTDOMAIN ),
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop( 'Partial payment <span class="count">(%s)</span>', 'Partial payment <span class="count">(%s)</span>', self::TEXTDOMAIN ),
				),
            ));
        return $statuses;
    }
    /**
     * Adds 'Partial' order status label
     * @hook wc_order_statuses
     * @param array $statuses
     * @return array
     */
    public function wc_order_statuses($statuses) {
        return array_merge($statuses, array(
            		self::POST_STATUS_PARTIAL     => _x( 'Partial', 'Order status', self::TEXTDOMAIN ),
            ));
    }
    /**
     * Adds 'partial' status to needs_payment() condition
     * @hook woocommerce_valid_order_statuses_for_payment
     * @param array $statuses
     * @param WC_Order $order
     * @return array
     */
    function valid_order_statuses_for_payment($statuses, $order) {
        $statuses = array_merge($statuses, array(self::POST_STATUS_PARTIAL));
        return $statuses;
    }

    /**
     * Adds balance column to shop order admin overview
     * @hook manage_shop_order_posts_columns
     * @param array $columns
     * @return array
     */
    function shop_order_posts_columns($columns) {
        $columns['balance'] =  __('Balance',self::TEXTDOMAIN);
        return $columns;
    }

    /**
     * Renders the balance column in shop order admin overview
     * @hook manage_shop_order_posts_custom_column
     * @param string $column
     * @param int $post_id
     */
    function shop_order_posts_custom_column($column, $post_id) {
        $ps_order = new WC_PS_Order(wc_get_order($post_id));
        switch ($column) {
            case 'balance':
                echo wc_price($ps_order->get_total_amount_unpaid() , array( 'currency' => $ps_order->get_currency() ));
                break;
        }
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