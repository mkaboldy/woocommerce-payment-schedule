<div class="cart-payment-schedule">
    <h2>Payment Schedule</h2>
    <table cellspacing="0" class="shop_table shop_table_responsive">
        <tbody>
            <?php
            foreach ($payment_schedule as $date => $amount) {?>
            <tr class="cart-term">
                <th><?php echo $date?></th>
                <td data-title="payment"><?php echo wc_price($amount)?></td>
            </tr>                
            <?php } ?>
	    </tbody>
    </table>
</div>