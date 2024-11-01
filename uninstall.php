<?php

if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

global $wpdb;
foreach( array('supplier', 'supplierorder', 'supplierorderitem', 'supplierproduct') as  $tablename )
{
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lws_woosupply_{$tablename};");
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lws_woosupply_{$tablename}meta;");
}

\delete_option('lws_woosupply_company_name');
\delete_option('lws_woosupply_company_address');
\delete_option('lws_woosupply_company_address_2');
\delete_option('lws_woosupply_company_zipcode');
\delete_option('lws_woosupply_company_city');
\delete_option('lws_woosupply_company_country');
\delete_option('lws_woosupply_company_state');
\delete_option('lws_woosupply_pdf_address_city_zip_order');
\delete_option('lws_woosupply_company_logo');
\delete_option('lws_woosupply_supplie_order_id_prefix');
\delete_option('lws_woosupply_company_tax_number');
\delete_option('lws_woosupply_pdf_filename');
\delete_option('lws_woosupply_pdf_color_background');
\delete_option('lws_woosupply_pdf_color_foreground');
\delete_option('lws_woosupply_pdf_color_draw');
\delete_option('lws_woosupply_pdf_color_text');
\delete_option('lws_woosupply_pdf_end_of_document');
\delete_option('lws_woosupply_purchases_exclude_tax');

foreach( array('ws_new', 'ws_sent', 'ws_ack', 'ws_received', 'ws_complete') as  $status )
{
	\delete_option('lws_woosupply_paid__order_'.$status);
}

foreach( array('manage_purchases', 'view_purchases') as $cap )
{
	foreach( array('administrator', 'shop_manager') as $slug )
	{
		$role = \get_role($slug);
		if( !empty($role) && $role->has_cap($cap) )
			$role->remove_cap($cap);
	}
}
