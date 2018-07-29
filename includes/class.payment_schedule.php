<?php

defined( 'ABSPATH' ) || die;

/**
 *
 */
class Payment_Schedule {
    private $terms;
    const INDEX_AMOUNT = 'amount';
    const INDEX_DATE = 'date';

    public function __construct() {
        $this->terms = [];
    }

    public function create_line_payment_schedule($line_total, $delivery_timestamp) {
        $first_term = round($line_total * 0.5);

        $this->add_term($first_term, date('m/d/Y'));
        $this->add_term($line_total - $first_term, date('m/d/Y',strtotime('-60 days',$delivery_timestamp)));

    }

    public static function create_cart_payment_schedule() {
        $cart_payment_schedule = array();
        foreach ( WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item[WC_Payment_Schedule::CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE])) {
                $item_payment_terms = $cart_item[WC_Payment_Schedule::CART_ITEM_DATA_INDEX_PAYMENT_SCHEDULE]->get_terms();
            } else {
                $item_payment_schedule = new Payment_Schedule();
                $item_payment_schedule->add_term( $cart_item['line_total'], date('m/d/Y'));
                $item_payment_terms =  $item_payment_schedule->get_terms();
            }
            foreach ($item_payment_terms as $item_payment_term) {
                if (isset($cart_payment_schedule[$item_payment_term['date']])){
                    $cart_payment_schedule[$item_payment_term['date']] += $item_payment_term['amount'];
                } else {
                    $cart_payment_schedule[$item_payment_term['date']]  = $item_payment_term['amount'];
                }
            }
        }
        return $cart_payment_schedule;
    }

    public function add_term($amount, $date = null) {
        if (null == $date) {
            $date = date('m/d/Y');
        }
        $this->terms[] = [
            self::INDEX_AMOUNT => $amount,
            self::INDEX_DATE => $date,
            ];
    }

    public function get_first_amount() {
        return $this->terms[0][self::INDEX_AMOUNT];
    }
    public function get_terms(){
        return $this->terms;
    }
}
