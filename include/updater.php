<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage plugin activation and update.
 * Provide the action hook 'lws_woosupply_version_changed' to helps modules updates. */
class Updater
{

	/**	Call this function when plugin version is about to change.
	 *	@param $woosupply LWS_WooSupply instance.
	 *	@param $oldVersion plugin version before update. */
	public static function update($woosupply, $oldVersion='0')
	{
		// update tables and set default values
		self::activation();

		// option init
		$countryState = explode(':', \get_option('woocommerce_default_country', ''));
		\add_option('lws_woosupply_company_name',      \get_bloginfo('name'));
		\add_option('lws_woosupply_company_address',   \get_option('woocommerce_store_address'));
		\add_option('lws_woosupply_company_address_2', \get_option('woocommerce_store_address_2'));
		\add_option('lws_woosupply_company_zipcode',   \get_option('woocommerce_store_postcode'));
		\add_option('lws_woosupply_company_city',      \get_option('woocommerce_store_city'));
		\add_option('lws_woosupply_company_country',   count($countryState)>0 ? $countryState[0] : '');
		\add_option('lws_woosupply_company_state',     count($countryState)>1 ? $countryState[1] : '');

		\do_action('lws_woosupply_version_changed', $woosupply, $oldVersion);
		\wp_schedule_single_event(time()+1, 'flush_rewrite_rules');

		if( empty($oldVersion) || version_compare($oldVersion, '1.0.0', '<') )
			self::addCapacity();
	}

	public static function log($msg)
	{
		if( !empty($msg) )
			error_log($msg);
	}

	/** Add 'manage_purchases' capacity to 'administrator' and 'shop_manager'. */
	private static function addCapacity()
	{
		foreach( array('manage_purchases', 'view_purchases') as $cap )
		{
			foreach( array('administrator', 'shop_manager') as $slug )
			{
				$role = \get_role($slug);
				if( !empty($role) && !$role->has_cap($cap) )
				{
					$role->add_cap($cap);
				}
			}
		}
	}

	/**	Create tables.
	 *	Add relevent default values. Values are NOT overwriten if already exist. */
	public static function activation()
	{
		ob_start(array(get_class(), 'log')); // dbDelta could write on standard output

		// create new tables
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Supplier
		$sql = "CREATE TABLE {$wpdb->lws_woosupply_supplier} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL COMMENT 'Supplier Company Name',
			email varchar(255) NOT NULL COMMENT 'Supplier email',
			contact_firstname varchar(255) COMMENT 'Supplier contact first name',
			contact_lastname varchar(255) COMMENT 'Supplier contact last name',
			address varchar(255) COMMENT 'Supplier address line 1',
			address_2 varchar(255) COMMENT 'Supplier address line 2',
			address_zipcode varchar(25) COMMENT 'Supplier address zip code',
			address_city varchar(255) COMMENT 'Supplier address city',
			address_country varchar(100) COMMENT 'Supplier address country',
			address_state varchar(100) COMMENT 'Supplier address state',
			phone_number varchar(50) COMMENT 'Supplier phone number',
			fax_number varchar(50) COMMENT 'Supplier fax number if needed',
			tax_number varchar(25) COMMENT 'Supplier tax number',
			PRIMARY KEY id (id)
		) $charset_collate;";
		dbDelta( $sql );
		self::createTableMeta($wpdb->lws_woosupply_supplier, $charset_collate);

		// Order
		$sql = "CREATE TABLE {$wpdb->lws_woosupply_supplierorder} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) NOT NULL COMMENT 'Supplier id',
			status varchar(25) COMMENT 'Order status',
			order_date datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Official order date',
			delivery_name varchar(255) NOT NULL COMMENT 'Delivery Company Name',
			delivery_tax_number varchar(25) COMMENT 'Own tax number',
			delivery_address varchar(255) COMMENT 'Delivery address line 1',
			delivery_address_2 varchar(255) COMMENT 'Delivery address line 2',
			delivery_address_zipcode varchar(25) COMMENT 'Delivery address zip code',
			delivery_address_city varchar(255) COMMENT 'Delivery address city',
			delivery_address_country varchar(100) COMMENT 'Delivery address country',
			delivery_address_state varchar(100) COMMENT 'Delivery address state',
			supplier_name varchar(255) NOT NULL COMMENT 'Supplier Company Name',
			supplier_contact varchar(513) NULL COMMENT 'Supplier contact used for this order',
			supplier_email varchar(255) NOT NULL COMMENT 'Supplier email',
			supplier_tax_number varchar(25) COMMENT 'Supplier tax number legal copy',
			supplier_address varchar(255) COMMENT 'Supplier address line 1 legal copy',
			supplier_address_2 varchar(255) COMMENT 'Supplier address line 2 legal copy',
			supplier_address_zipcode varchar(25) COMMENT 'Supplier address zip code legal copy',
			supplier_address_city varchar(255) COMMENT 'Supplier address city legal copy',
			supplier_address_country varchar(100) COMMENT 'Supplier address country legal copy',
			supplier_address_state varchar(100) COMMENT 'Supplier address state legal copy',
			PRIMARY KEY id (id),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );
		self::createTableMeta($wpdb->lws_woosupply_supplierorder, $charset_collate);

		// Order Item
		$sql = "CREATE TABLE {$wpdb->lws_woosupply_supplierorderitem} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) NOT NULL COMMENT 'Local Supplier Order id',
			product_id bigint(20) NOT NULL COMMENT 'Local Supplier Product id',
			supplier_reference varchar(255) NOT NULL COMMENT 'Product reference from supplier side',
			item_key varchar(25) NOT NULL COMMENT 'A order local id of the line',
			quantity float NOT NULL DEFAULT 0 COMMENT 'Ordered product count',
			unit_price float NOT NULL DEFAULT 0 COMMENT 'price for one product',
			amount float NOT NULL DEFAULT 0 COMMENT 'total price of the line (with any discount)',
			PRIMARY KEY id (id),
			KEY order_id (order_id)
		) $charset_collate;";
		dbDelta( $sql );
		self::createTableMeta($wpdb->lws_woosupply_supplierorderitem, $charset_collate);

		// Product
		$product_collate = 'ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
		$sql = "CREATE TABLE {$wpdb->lws_woosupply_supplierproduct} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL COMMENT 'Denomination',
			PRIMARY KEY id (id),
			FULLTEXT INDEX name (name)
		) $product_collate;";
		dbDelta( $sql );
		self::createTableMeta($wpdb->lws_woosupply_supplierproduct, $charset_collate);

		ob_end_flush();
	}

	private static function createTableMeta($foreignTable, $charset_collate='')
	{
		global $wpdb;
		$parent_id = substr($foreignTable, strlen($wpdb->prefix));

		$sql = "CREATE TABLE {$foreignTable}meta (
			meta_id bigint(20) NOT NULL AUTO_INCREMENT,
			{$parent_id}_id bigint(20) NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext NULL DEFAULT NULL,
			PRIMARY KEY meta_id (meta_id),
			KEY {$parent_id}_id ({$parent_id}_id),
			KEY meta_key (meta_key)
		) $charset_collate;";
		dbDelta( $sql );
	}

}
?>
