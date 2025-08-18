<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<?php do_action( 'wpo_wcpdf_before_document', $this->get_type(), $this->order ); 

 $pdf_setting = get_option('wpo_wcpdf_settings_general');

?>



<table class="head container">
	<tr>
		<td class="header" class="text-center">
			<?php if ( $this->has_header_logo() ) : ?>
				<?php do_action( 'wpo_wcpdf_before_shop_logo', $this->get_type(), $this->order ); ?>
				<?php $this->header_logo(); ?>
				<?php do_action( 'wpo_wcpdf_after_shop_logo', $this->get_type(), $this->order ); ?>
			<?php else : ?>
				<?php $this->title(); ?>
			<?php endif; ?>
		</td>

	</tr>
</table>

<?php do_action( 'wpo_wcpdf_before_document_label', $this->get_type(), $this->order ); ?>

<?php if ( $this->has_header_logo() ) : ?>
	<h1 class="document-type-label"><?php $this->title(); ?></h1>
<?php endif; ?>

<?php do_action( 'wpo_wcpdf_after_document_label', $this->get_type(), $this->order ); ?>

<table class="order-data-addresses">
	<tr>
		<td class="address billing-address">
			<?php do_action( 'wpo_wcpdf_before_billing_address', $this->get_type(), $this->order ); ?>
			<!-- <p><?php $this->billing_address(); ?></p> -->
			<?php do_action( 'wpo_wcpdf_after_billing_address', $this->get_type(), $this->order ); ?>
			<?php if ( isset( $this->settings['display_email'] ) ) : ?>
				<div class="billing-email"><?php $this->billing_email(); ?></div>
			<?php endif; ?>
			<?php if ( isset( $this->settings['display_phone'] ) ) : ?>
				<div class="billing-phone"><?php $this->billing_phone(); ?></div>
			<?php endif; ?>

			<div><?= $pdf_setting['shop_email_address']['default'] ?></div>
			<div><?= $pdf_setting['shop_phone_number']['default'] ?></div>
		</td>

		<td class="order-data">
			<table>
				<?php do_action( 'wpo_wcpdf_before_order_data', $this->get_type(), $this->order ); ?>
				<?php if ( isset( $this->settings['display_number'] ) ) : ?>
					<tr class="invoice-number">
						<th><?php $this->number_title(); ?></th>
						<td><?php $this->number( $this->get_type() ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( isset( $this->settings['display_date'] ) ) : ?>
					<tr class="invoice-date">
						<th><?php $this->date_title(); ?></th>
						<td><?php $this->date( $this->get_type() ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $this->show_due_date() ) : ?>
					<tr class="due-date">
						<th><?php $this->due_date_title(); ?></th>
						<td><?php $this->due_date(); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="order-number">
					<th><?php $this->order_number_title(); ?></th>
					<td><?php $this->order_number(); ?></td>
				</tr>
				<tr class="order-date">
					<th><?php $this->order_date_title(); ?></th>
					<td><?php $this->order_date(); ?></td>
				</tr>


				<tr>
					<th>Payment Status:</th>
					<td><b> <?php echo urm_get_payment_status($this->get_order_number() ); ?></b> </td>
				</tr>

				<?php do_action( 'wpo_wcpdf_after_order_data', $this->get_type(), $this->order ); ?>
			</table>
		</td>
	</tr>
</table>

<?php do_action( 'wpo_wcpdf_before_order_details', $this->get_type(), $this->order ); ?>



<h1>Billed To</h1>
<div>
	<div><?= $this->order->get_billing_first_name().' '.$this->order->get_billing_last_name() ?> </div>
	<div><?php $this->billing_email(); ?></div>
	<div><?php $this->billing_phone(); ?></div>
</div>
<br>

<hr>
	<h1>Appoinment Summary</h1>
<hr>


<table style="width:100%" class="appoinment_summary_table">
	<?php foreach ( $this->get_order_items() as $item_id => $item ) : ?>

	<tr>
		<th> Session Topic</th>
		<td> <?= $item['name'] ?> </td>
	</tr>
		
	<tr>
		<th> Appoinment Date & time</th>
		<td> <?= urm_date_format( wc_get_order_item_meta( $item_id, 'session_date_time', true ) ); ?> </td>
	</tr>

	<tr>
		<th> Duration </th>
		<td> <?=  wc_get_order_item_meta( $item_id, 'appointment_duration', true ); ?> Mins</td>
	</tr>

	<tr>
		<th> Mentor </th>
		<td> <?= urm_get_username( wc_get_order_item_meta( $item_id, 'mentor_id', true ) ); ?> </td>
	</tr>

	<tr>
		<th> Child </th>
		<td> <?= urm_get_username( wc_get_order_item_meta( $item_id, 'child_id', true ) ); ?> </td>
	</tr>

	<tr>
		<th> Appoinment Status </th>
		<td> <?=  ucfirst( wc_get_order_item_meta( $item_id, 'appointment_status', true ) ) ?> </td>
	</tr>

	<?php endforeach; ?>
</table>

<br>
<hr>
<h1>Payment Summary</h1>
<hr>

<table style="width:100%;" class="payment_summary_table">
	<tr>
		<th >Total Fee </th>
		<?php 
		foreach ( $this->get_woocommerce_totals() as $key => $total ) : 
			if($key != "order_total") { continue; }
		?>
			
		<td>
			<span class="totals-price">
				<?php echo esc_html( wc_price($total['value'] )); ?>
			</span>
		</td>
			
		<?php endforeach; ?>
	</tr>
	<tr>
		<th>Payment Method </th>
		<td><?php $this->payment_method(); ?></td>
	</tr>
</table>



<?php do_action( 'wpo_wcpdf_after_order_details', $this->get_type(), $this->order ); ?>

<div class="bottom-spacer"></div>

<?php if ( $this->get_footer() ) : ?>
	<htmlpagefooter name="docFooter"><!-- required for mPDF engine -->
		<div id="footer">
			<!-- hook available: wpo_wcpdf_before_footer -->
			<?php $this->footer(); ?>
			<!-- hook available: wpo_wcpdf_after_footer -->
		</div>
	</htmlpagefooter><!-- required for mPDF engine -->
<?php endif; ?>

<?php do_action( 'wpo_wcpdf_after_document', $this->get_type(), $this->order ); ?>