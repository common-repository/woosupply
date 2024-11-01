<?php
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

$ws_style = '';
$ws_dft_logo_url = '';
$ws_dft_texts = '';
if( isset($ws_order) )
{
	$ws_supplier = \lws_ws()->getSupplier($ws_order->supplier_id, true);
	$ws_style = \apply_filters('stygen_inline_style', '', LWS_WOOSUPPLY_CSS.'/templates/order.css', 'lws_woosupply_pdf_template');
	$ws_style .= "@page {margin-top: 1cm;margin-bottom: 1cm;margin-left: 1cm;margin-right: 1cm;}";
	$ws_style .= "body{margin-top:250px;margin-bottom:100px;font-family:Open Sans, Helvetica, sans-serif;font-size:15px;background-color:#fff;position;relative;}";
	$ws_style .= "div.lwss_selectable.pdf_head{position:fixed !important;top:0px !important;left:0px;right:0px;}";
	$ws_style .= "div.lwss_selectable.pdf_footer{position:fixed !important;bottom:-1cm !important;left:0px;right:0px;}";
}
else if( isset($lws_stygen) )
{
	$ws_dft_logo_url = LWS_WOOSUPPLY_IMG.'/yourlogohere.jpg';
	$ws_dft_texts = __("This is a non mandatory text emplacement", 'woosupply-lite');

	// create a demo order
	$ws_order = \lws_ws()->getSupplierOrder(0, true);
	$ws_order->setData('order_date', \date_create()->format('Y-m-d'));
	$ws_order->setData('id', "66");
	$ws_supplier = \lws_ws()->getSupplier(0, true);
	$ws_supplier->setData('name',__("The Test Supplier", 'woosupply-lite'));
	$ws_order->setData('supplier_address', __("1 test street", 'woosupply-lite'));
	$ws_order->setData('supplier_address_2', __("second line", 'woosupply-lite'));
	$ws_order->setData('supplier_address_zipcode', __("12345", 'woosupply-lite'));
	$ws_order->setData('supplier_address_city', __("TestCity", 'woosupply-lite'));
	$ws_order->setData('supplier_address_country', __("US", 'woosupply-lite'));
	$product = \lws_ws()->getSupplierProduct(0, true);
	$product->setData('name', __("My Test Product 1", 'woosupply-lite'));
	$item = \LWS\WOOSUPPLY\SupplierOrderItem::create();
	$item->setProduct($product);
	$item->setData('supplier_reference', __("MTP1", 'woosupply-lite'));
	$item->setData('quantity', 123);
	$item->setData('unit_price', 10);
	$item->setData('amount', 1230);
	$ws_order->pushItem($item);
	$product = \lws_ws()->getSupplierProduct(0, true);
	$product->setData('name', __("My Test Product 2", 'woosupply-lite'));
	$item = \LWS\WOOSUPPLY\SupplierOrderItem::create();
	$item->setProduct($product);
	$item->setData('supplier_reference', __("MTP2", 'woosupply-lite'));
	$item->setData('quantity', 1);
	$item->setData('unit_price', 120);
	$item->setData('amount', 120);
	$ws_order->pushItem($item);
}
else
{
	error_log("Order PDF template called from nowhere.");
}

$tax_text = !empty(\get_option('lws_woosupply_purchases_exclude_tax', 'on')) ? __("(Excl. Tax)", "PDF Order", 'woosupply-lite') : __("(Incl. Tax)", "PDF Order", 'woosupply-lite');
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<style><?php echo $ws_style; ?></style>
	</head>

	<body>
	<?php if( isset($lws_stygen) ) echo '<div class="lws-dompdf-viewport">'; ?>

		<script type="text/php">
			if( isset($pdf) ) {
				$pageScript = implode("\n", array(
					'$txt = "{$PAGE_NUM} / {$PAGE_COUNT}";',
					'$fonts = implode(", ", \apply_filters("lws_woosupply_pdf_font_list", array("Open Sans", "Helvetica", "sans-serif"), "order", $PAGE_NUM));',
					'$fm = $fontMetrics->get_font($fonts, "normal");',
					'$fs = 6;',
					'$x = ($pdf->get_width()*.5) - ($pdf->get_text_width($txt, $fm, $fs)*.5);',
					'$pdf->text($x, $pdf->get_height()-$fs-12, $txt, $fm, $fs);',
				));
				$pdf->page_script($pageScript);
			}
		</script>

		<div class="lwss_selectable pdf_head" data-type="Header">
			<div class="lwss_selectable company_logo" data-type="Logo">
			<?php
				$ws_logo_id = \get_option('lws_woosupply_company_logo');
				$ws_logo_url = (isset($lws_stygen) ? \wp_get_attachment_url($ws_logo_id) : \get_attached_file($ws_logo_id));
				if( empty($ws_logo_url) )
					$ws_logo_url = $ws_dft_logo_url;
				if( !empty($ws_logo_url) )
					echo('<img src="'.$ws_logo_url.'"/>');
			?>
			</div>
			<div class="lwss_selectable order_info" data-type="Order Information">
			<?php
				echo(__("Order", "PDF Header", 'woosupply-lite')." #");
				$order_num = \lws_ws()->formatOrderNumber($ws_order->getData('id'));
				$order_date = \date_create($ws_order->getData("order_date"))->format('Y-m-d');
				echo($order_num." | ".$order_date);
			?>
			</div>
			<div class="lwss_selectable company_info" data-type="Company Information">
				<?php
					$delivery_a = $ws_order->getDefaultDeliveryAddress();
					$delivery_str[] = htmlspecialchars($delivery_a->delivery_name);
					$delivery_str[] = htmlspecialchars($delivery_a->delivery_address);
					if( !empty($delivery_a->delivery_address_2) )
						$delivery_str[] = htmlspecialchars($delivery_a->delivery_address_2);

					$city = sprintf(\lws_ws()->getCityZipFormat(), htmlspecialchars($delivery_a->delivery_address_city), htmlspecialchars($delivery_a->delivery_address_zipcode));
					$country = $delivery_a->delivery_address_country;
					$state = $delivery_a->delivery_address_state;
					if( !empty($state) )
						$city .= ', ' . htmlentities(\lws_ws()->getCountryState()->getStateByCodes($country, $state));
					$delivery_str[] = $city;
					$delivery_str[] = '<b>'.htmlentities(\lws_ws()->getCountryState()->getCountryByCode($country)).'</b>';

					if( !empty($delivery_a->delivery_email) )
						$delivery_str[] = '<br/><b>'.htmlspecialchars(__("email :", 'woosupply-lite')).'</b> <a href="'.esc_attr($delivery_a->delivery_email).'">'.htmlspecialchars($delivery_a->delivery_email).'</a>';
					$delivery_str[] = '';
					echo implode('<br/>', $delivery_str);
				?>
			</div>
			<div class="lwss_selectable supplier_info" data-type="Supplier Address">
			<?php
					$supplier_str[] = "<b>".htmlspecialchars($ws_supplier->getData("name"))."</b>" ;
					$supplier_str[] = htmlspecialchars($ws_order->getData("supplier_address"));
					if( !empty($ws_order->getData("supplier_address_2")) )
						$supplier_str[] = htmlspecialchars($ws_order->getData("supplier_address_2"));

					$city = sprintf(\lws_ws()->getCityZipFormat(), htmlspecialchars($ws_order->getData("supplier_address_city")), htmlspecialchars($ws_order->getData("supplier_address_zipcode")));
					$country = $ws_order->getData("supplier_address_country");
					$state = $ws_order->getData("supplier_address_state");
					if( !empty($state) )
						$city .= ', ' . htmlentities(\lws_ws()->getCountryState()->getStateByCodes($country, $state));
					$supplier_str[] = $city;
					$supplier_str[] = '<b>'.htmlentities(\lws_ws()->getCountryState()->getCountryByCode($country)).'</b>';

					$supplier_str[] = '';
					echo implode('<br/>', $supplier_str);
				?>
			</div>
		</div>

		<div class="lwss_selectable pdf_footer" data-type="Footer">
			<div class="lwss_selectable lwss_modify pdf_text_footer" data-type="Footer text" data-id='lws_woosupply_pdf_end_of_document'>
				<div class="lwss_modify_content"><?php echo \lws_get_option('lws_woosupply_pdf_end_of_document', $ws_dft_texts); ?></div>
			</div>
		</div>

		<div class="lwss_selectable pdf_body" data-type="Body">
			<div class="lwss_selectable lwss_modify pdf_text_above_body" data-type="Optional text" data-id='lws_woosupply_pdf_header_text'>
				<div class="lwss_modify_content"><?php echo \lws_get_option('lws_woosupply_pdf_header_text', $ws_dft_texts); ?></div>
			</div>
			<table cellspacing="0" cellpadding="0" class="lwss_selectable pdf_body_table" data-type="Table">
				<thead class="lwss_selectable pdf_body_table_head" data-type="Table Head">
					<tr>
					<th class="lwss_selectable pdf_body_table_head_supref" data-type="Supplier Ref Header"><?php echo __("Product Ref.", "PDF Order", 'woosupply-lite'); ?></th>
						<th class="lwss_selectable pdf_body_table_head_prod" data-type="Product Header"><?php echo __("Product / Comments", "PDF Order", 'woosupply-lite'); ?></th>
						<th class="lwss_selectable pdf_body_table_head_qty" data-type="Quantity Header"><?php echo __("Quantity", "PDF Order", 'woosupply-lite'); ?></th>
						<th class="lwss_selectable pdf_body_table_head_upr" data-type="Unit Price Header"><?php echo sprintf(__("Unit Price (%s)", 'woosupply-lite'), \lws_ws()->getCurrencySymbol()); ?></th>
						<th class="lwss_selectable pdf_body_table_head_tot" data-type="Total Header"><?php echo sprintf(__("Total Amount (%s)", 'woosupply-lite'), lws_ws()->getCurrencySymbol()); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
						foreach( $ws_order->getItems() as $item )
						{
							$pname = $item->getProductName();
							?>
					<tr>
						<td class="lwss_selectable pdf_body_table_lines_supref" data-type="Supplier Ref Lines"><?php echo htmlspecialchars($item->supplier_reference); ?></td>
						<td class="lwss_selectable pdf_body_table_lines_prod" data-type="Product Lines"><?php echo htmlspecialchars($pname); ?></td>
						<td class="lwss_selectable pdf_body_table_lines_qty" data-type="Quantity Lines"><?php echo htmlspecialchars($item->quantity); ?></td>
						<td class="lwss_selectable pdf_body_table_lines_upr" data-type="Unit Price Lines"><?php echo htmlspecialchars(\lws_ws()->getDisplayPrice($item->unit_price)); ?></td>
						<td class="lwss_selectable pdf_body_table_lines_tot" data-type="Total Lines"><?php echo htmlspecialchars(\lws_ws()->getDisplayPrice($item->amount)); ?></td>
					</tr>
					<?php
						}
					?>
				</tbody>
				<tfoot>
					<tr>
						<td class="lwss_selectable pdf_body_table_footer_label" data-type="Footer Label" colspan="4"><?php echo __("Total", "PDF Order", 'woosupply-lite')." ".$tax_text; ?></td>
						<td  class="lwss_selectable pdf_body_table_footer_total" data-type="Footer Number"><?php echo htmlspecialchars(\lws_ws()->getDisplayPrice($ws_order->amount)); ?></td>
					</tr>
				</tfoot>
			</table>
		</div>

	<?php if( isset($lws_stygen) ) echo '</div>'; ?>
	</body>
</html>
