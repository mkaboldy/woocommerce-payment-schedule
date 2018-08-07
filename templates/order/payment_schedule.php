
<section class="woocommerce-payment-schedule">
    <h2 class="woocommerce-column__title"><?php echo __('Payment Schedule')?></h2>
    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
        <?php foreach ($payment_schedule as $date => $amount) { ?>
        <tr class="cart-term">
            <th>
                <?php echo $date ?>
            </th>
            <td data-title="payment">
                <?php echo wc_price($amount) ?>
            </td>
        </tr>
        <?php } ?>
    </table>        
</section>
