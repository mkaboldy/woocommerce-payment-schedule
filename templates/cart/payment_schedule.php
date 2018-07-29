<tr><td colspan="2" class="payment-schedule-header"><?php echo __('Payment Schedule')?></td></tr>
<?php
foreach ($payment_schedule as $date => $amount) {?>
<tr class="cart-term">
    <th><?php echo $date?></th>
    <td data-title="payment"><?php echo wc_price($amount)?></td>
</tr>                
<?php } ?>
