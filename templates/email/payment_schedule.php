<?php
/**
 * Order payment schedule table shown in emails.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text_align = is_rtl() ? 'right' : 'left';
?>
<h2>Payment Schedule</h2>

<div style="margin-bottom: 40px;">
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <tbody>
            <?php foreach ($payment_schedule as $date => $amount) { ?>
            <tr>
                <th><?php echo $date?></th>
                <td><?php echo $amount?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
