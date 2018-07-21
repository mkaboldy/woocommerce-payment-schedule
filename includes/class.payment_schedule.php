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

    public function add_term($amount, $date) {
        $this->terms[] = [
            self::INDEX_AMOUNT => $amount,
            self::INDEX_DATE => $date,
            ];
    }
    public function get_terms(){
        return $this->terms;
    }
}
