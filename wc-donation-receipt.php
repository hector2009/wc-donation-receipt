<?php
/*
 * Plugin Name:       WC Donation Receipt
 * Plugin URI:        https://wagroup.io
 * Description:       Generates a donation receipt for WooCommerce orders.
 * Version:           1.0
 * Author:            Sergey Stepnev
 * Author URI:        https://wagroup.io
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-donation-receipt
 * Domain Path:       /languages
 * Requires Plugins:  wc-donation-platform
 */

include_once __DIR__ . '/vendor/autoload.php';

/**
 * WC_Donation_Receipt class
 *
 * This class handles the generation of donation receipts for WooCommerce orders.
 */
class WC_Donation_Receipt {

	public function __construct() {
		add_action( 'woocommerce_thankyou', [ $this, 'generate_receipt' ], 10, 1 );
		add_action( 'woocommerce_email_attachments', [ $this, 'email_attachments' ], 10, 3 );
	}

	/**
	 * Get the URL for the order receipt PDF file.
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function get_order_receipt_url( $order_id ) {
		$upload_dir = wp_upload_dir();
		$order      = wc_get_order( $order_id );

		$full_name_arr   = [];
		$full_name_arr[] = $order->get_billing_first_name();
		$full_name_arr[] = $order->get_billing_last_name();

		$full_name      = implode( ' ', array_filter( $full_name_arr ) );

		$pdf_title_parts   = [ 'tax-receipt' ];
		$pdf_title_parts[] = $order_id;
		$pdf_title_parts[] = sanitize_title( $full_name );
		$pdf_title_parts[] = sanitize_title( wc_format_datetime( $order->get_date_created(), 'Y-m-d' ) );
		$pdf_title         = implode( '-', $pdf_title_parts );

		return $upload_dir['basedir'] . "/receipts/{$pdf_title}.pdf";
	}

	/**
	 * Generate a donation receipt PDF for the given order ID.
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function generate_receipt( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		if ( file_exists( $this->get_order_receipt_url( $order_id ) ) ) {
			return; // PDF already generated
		}

		// Load order data
		$order             = wc_get_order( $order_id );
		$order_total_price = $order->get_formatted_order_total();
		$order_date        = wc_format_datetime( $order->get_date_created() );
		$payment_method    = wp_kses_post( $order->get_payment_method_title() );

		$dompdf = new Dompdf\Dompdf();

		// Build PDF content
		$html = '<h2 style="text-align:center;">NEWPORT BEACH FIRST RESPONDERS</h2>';
		$html .= '<div style="text-align:center;font-size: 18px;"><i>Supporting Those Who Come First</i></div>';
		$html .= '<hr style="margin: 50px 0;" />';
		$html .= '<h3 style="text-align:center;margin-bottom:50px;text-decoration: underline;">INFORMATION TO DONORS FOR TAX DEDUCTIBLE DONATIONS</h3>';
		$html .= '<div style="font-size:18px;">
	Dear Donor:<br><br>
On behalf of NEWPORT BEACH FIRST RESPONDERS, we extend our heartfelt gratitude for
your generous contribution. Your support directly advances our charitable mission to promote the
welfare and safety of first responders and the local communities they serve.<br><br>
In compliance with IRS requirements, we confirm that no goods or services were provided in
exchange for your contribution. Accordingly, your donation is fully deductible to the extent
permitted by law. NEWPORT BEACH FIRST RESPONDERS has requested recognition as a tax-
exempt public charity under Section 501(c)(3) of the Internal Revenue Code. Our Federal Tax
Identification Number (EIN) is 39-3582726.  <br><br>
Your generosity enables us to provide essential resources and programs that honor and assist those
who serve our local communities every day. We are deeply grateful for your partnership in this
vital work.<br><br>
With appreciation,     <br>
NEWPORT BEACH FIRST RESPONDERS
	</div>';
		$html .= '<div style="margin-top: 150px;border: 1px solid #000; padding: 10px 20px;">';
		$html .= '<h4 style="text-decoration: underline;text-align: center;margin-bottom: 10px;margin-top:0;"><i>For Office Use Only</i></h4>';
		$html .= '<table width="100%" cellpadding="10" cellspacing="0">';
		$html .= '<tr>';
		$html .= "<td>Tax Deductible Donation: {$order_total_price}</td>";
		$html .= "<td>Manner of Payment: {$payment_method}</td>";
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td>Authorized by: Here Sign</td>';
		$html .= "<td>Date: {$order_date}</td>";
		$html .= '</tr>';
		$html .= '</table>';
		$html .= '</div>';
		$html .= '<hr style="margin-top: 50px;" />';

		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		// Save PDF to uploads folder
		$upload_dir = wp_upload_dir();
		if ( ! file_exists( $upload_dir['basedir'] . '/receipts' ) ) {
			wp_mkdir_p( $upload_dir['basedir'] . '/receipts' );
		}
		file_put_contents( $this->get_order_receipt_url( $order_id ), $dompdf->output() );
	}

	/**
	 * Add the donation receipt PDF as an attachment to the customer completed order email.
	 *
	 * @param $attachments
	 * @param $email_id
	 * @param $order
	 *
	 * @return mixed
	 */
	public function email_attachments( $attachments, $email_id, $order ) {
		if ( $email_id === 'customer_completed_order' ) {
			$attachments[] = $this->get_order_receipt_url( $order->get_id() );
		}

		return $attachments;
	}
}

// Initialize the WC_Donation_Receipt class
new WC_Donation_Receipt();
