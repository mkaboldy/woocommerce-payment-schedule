<?php

defined( 'ABSPATH' ) || die;

/**
 *
 */
class Payment_History {
    private $payments;
    const INDEX_AMOUNT = 'amount';
    const INDEX_DATE = 'date';

    public function __construct() {
        $this->payments = [];
    }

    public function add_payment($amount, $date) {
        $this->payments[] = [
            self::INDEX_AMOUNT => $amount,
            self::INDEX_DATE => $date,
            ];
    }
    public function get_payments(){
        return $this->payments;
    }
}
