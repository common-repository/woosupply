<?php
/**
 * Plugin Name: WooSupply
 * Description: A toolbox helping you to manage your supplies. Can be used with WooCommerce or standalone.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 1.2.2
 * License: Copyright LongWatchStudio 2020
 * Text Domain: woosupply-lite
 * Domain Path: /languages/
 * WC requires at least: 3.4.0
 * WC tested up to: 4.8
 *
 * Copyright (c) 2020 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**
 * @class LWS_WooSupply The class that holds the entire plugin
 */
final class LWS_WooSupply
{

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action( 'lws_adminpanel_register', array($instance, 'register') );
			add_action( 'lws_adminpanel_plugins', array($instance, 'plugin') );

			add_filter( 'plugin_row_meta', array($instance, 'addLicenceLink'), 10, 4 );
			add_filter( 'lws_adminpanel_purchase_url_woosupply', array($instance, 'addPurchaseUrl') );
			add_filter( 'lws_adminpanel_plugin_version_woosupply', array($instance, 'addPluginVersion') );
			add_filter( 'lws_adminpanel_documentation_url_woosupply', array($instance, 'addDocUrl') );

			register_activation_hook( __FILE__, 'LWS_WooSupply::activate' );
			spl_autoload_register(array($instance, 'autoload'));

			if( version_compare(($oldV=get_option('lws_woosupply_version', '0')), $instance->v(), '<') )
			{
				require_once LWS_WOOSUPPLY_INCLUDES . '/updater.php';
				\LWS\WOOSUPPLY\Updater::update($instance, $oldV);
				update_option('lws_woosupply_version', $instance->v());
			}
		}
		return $instance;
	}

	/** autoload Supplier* classes and WSPost. */
	public function autoload($class)
	{
		if( substr($class, 0, 14) == 'LWS\WOOSUPPLY\\' )
		{
			$basename = strtolower(substr($class, 14));
			if( in_array(substr($basename, 0, 3), array('sup', 'wsp', 'cou')) && file_exists(LWS_WOOSUPPLY_INCLUDES . '/' . $basename . '.php') )
			{
				@include_once LWS_WOOSUPPLY_INCLUDES . '/' . $basename . '.php';
				return true;
			}
		}
	}

	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	/** Load translation file
	 * If called via a hook like this
	 * @code
	 * add_action( 'plugins_loaded', array($instance,'load_plugin_textdomain'), 1 );
	 * @endcode
	 * Take care no text is translated before. */
	function load_plugin_textdomain() {
		load_plugin_textdomain( 'woosupply-lite', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_WOOSUPPLY_VERSION', self::v() );
		define( 'LWS_WOOSUPPLY_FILE', __FILE__ );

		define( 'LWS_WOOSUPPLY_PATH', dirname( LWS_WOOSUPPLY_FILE ) );
		define( 'LWS_WOOSUPPLY_INCLUDES', LWS_WOOSUPPLY_PATH . '/include' );
		define( 'LWS_WOOSUPPLY_SNIPPETS', LWS_WOOSUPPLY_PATH . '/snippets' );
		define( 'LWS_WOOSUPPLY_ASSETS', LWS_WOOSUPPLY_PATH . '/assets' );

		define( 'LWS_WOOSUPPLY_URL', plugins_url( '', LWS_WOOSUPPLY_FILE ) );
		define( 'LWS_WOOSUPPLY_JS', plugins_url( '/js', LWS_WOOSUPPLY_FILE ) );
		define( 'LWS_WOOSUPPLY_CSS', plugins_url( '/css', LWS_WOOSUPPLY_FILE ) );
		define( 'LWS_WOOSUPPLY_IMG', plugins_url( '/img', LWS_WOOSUPPLY_FILE ) );

		define( 'LWS_WOOSUPPLY_DOMAIN', 'woosupply-lite');
		define( 'LWS_WOOSUPPLY_PAGE', 'lws_woosupply');

		define( 'LWS_WSORDER_ID_PREFIX', '#' );

		// define table names
		global $wpdb;
		foreach( array('supplier', 'supplierorder', 'supplierorderitem', 'supplierproduct') as $suffix )
		{
			$prop = 'lws_woosupply_' . $suffix;
			$propmeta = $prop . 'meta';
			$wpdb->$prop = $wpdb->prefix . $prop;
			$wpdb->$propmeta = $wpdb->prefix . $propmeta;
		}
	}

	public function addLicenceLink($links, $file, $data, $status)
	{
		if( (!defined('LWS_WOOSUPPLY_ACTIVATED') || !LWS_WOOSUPPLY_ACTIVATED) && plugin_basename(__FILE__)==$file)
		{
			$label = __("Add Licence Key", 'woosupply-lite');
			$url = add_query_arg(array('page'=>LWS_WOOSUPPLY_PAGE.'_settings', 'tab'=>'license'), admin_url('admin.php'));
			$links[] = "<a href='$url'>$label</a>";
		}
		return $links;
	}

	/** Add link to the plugin row in Installed Extension screen. */
	function extensionListActions($links, $file)
	{
		$label = __('Settings'); // use standart wp sentence, no text domain
		$url = add_query_arg(array('page'=>LWS_WOOSUPPLY_PAGE.'_settings'), admin_url('admin.php'));
		array_unshift($links, "<a href='$url'>$label</a>");
		$label = __('Help'); // use standart wp sentence, no text domain
		$url = esc_attr($this->addDocUrl(''));
// ...		$links[] = "<a href='$url'>$label</a>";
		return $links;
	}

	public function addDocUrl($url)
	{
		// return __("https://plugins.longwatchstudio.com/en/documentation-en/woosupply/", 'woosupply-lite');
	}

	public function addPurchaseUrl($url)
	{
		// return __("https://plugins.longwatchstudio.com/en/product/woosupply-en/", 'woosupply-lite');
	}

	public function addPluginVersion($url)
	{
		return $this->v();
	}

	private function install()
	{
		\add_filter('plugin_action_links_'.plugin_basename( __FILE__ ), array($this, 'extensionListActions'), 10, 2);
		\add_action('lws_woosupply_supplierorder_status_changed', array($this, 'applyOrderDelivery'), 10, 3);
		\add_action('lws_woosupply_supplierorder_status_changed', array($this, 'applyOrderPaid'), 10, 3);
		\add_action('lws_woosupply_supplierorder_status_changed', array($this, 'keepStatusDate'), 10, 3);

		if( defined('DOING_AJAX') && DOING_AJAX )
		{
			require_once LWS_WOOSUPPLY_INCLUDES . '/ajax.php';
			new \LWS\WOOSUPPLY\Ajax();
			require_once LWS_WOOSUPPLY_INCLUDES . '/statsapi.php';
			new \LWS\WOOSUPPLY\StatsAPI();
		}
		else
		{
			require_once LWS_WOOSUPPLY_INCLUDES . '/ui/standardstats.php';
			\LWS\WOOSUPPLY\StandardStats::instance();

			add_filter('lws_adminpanel_field_types', function($types){
				require_once LWS_WOOSUPPLY_INCLUDES . '/ui/countrystatefield.php';
				$types['countrystate'] = array('\LWS\WOOSUPPLY\CountryStateField', LWS_WOOSUPPLY_INCLUDES . '/ui/countrystatefield.php');
				return $types;
			});

			add_action('admin_enqueue_scripts', array($this, 'registerScripts'));
		}

		// convenience filters
		\add_filter('lws_woosupply_supplierorder_status_label', function($status){
			require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';
			return \LWS\WOOSUPPLY\SupplierOrder::statusLabel($status);
		});
		\add_filter('lws_woosupply_supplierorder_label', function($orderId){
			return LWS_WSORDER_ID_PREFIX . \lws_ws()->formatOrderNumber($orderId);
		});
	}

	public function registerScripts()
	{
		// since we register here, we allows anyone enqueue them later (espacially any woosupply addon)
		\wp_register_script('woosupply-lite'.'_statistics_script', LWS_WOOSUPPLY_JS . '/statactions.js', array('jquery', 'lws-base64', 'lws-tools'), LWS_WOOSUPPLY_VERSION, true);
		\wp_register_style('woosupply-lite'.'_statistics_style', LWS_WOOSUPPLY_CSS . '/statactions.css', array(), LWS_WOOSUPPLY_VERSION);
	}

	/** First time order reach a given status, we keep that date for history. */
	public function keepStatusDate($orderId, $order, $oldStatus)
	{
		if( $order->getStatus() != $oldStatus )
		{
			$key = 'status_date_' . $order->getStatus();
			if( empty($order->getMeta($key, true)) )
			{
				$order->updateMeta($key, date(DATE_W3C));
			}
		}
	}

	/** Since order status pass 'ws_received', we considere a full delivery.
	 *	For each item, set ordered quantity as delivered quantity.
	 *	@see hook 'lws_woosupply_supplierorder_received' to prevent this behavior.
	 *	That method manage the revert too (reset delivered quantity to 0).
	 */
	public function applyOrderDelivery($orderId, $order, $oldStatus)
	{
		if( $order->getStatus() == $oldStatus )
			return;
		$cmp = lws_ws()->cmpOrderStatus($order->getStatus(), 'ws_received');

		if( $cmp >= 0 && empty($order->getMeta('receipt_date', true)) )
		{
			$order->updateMeta('receipt_date', \date(DATE_W3C));

			/** Hook 'lws_woosupply_supplierorder_received'
			 * @param 1 (bool, default=false) if true, delivery process considered as already done.
			 * @return (bool) is the delivery process already done.
			 * If this filter return other than false, we do nothing more. */
			$ret = \apply_filters('lws_woosupply_supplierorder_received', false, $order);

			if( $ret === false )
			{
				foreach( $order->getItems() as $item )
				{
					$item->updateOnDelivery($item->quantity);
				}
			}
		}

		if( $cmp < 0 && !empty($order->getMeta('receipt_date', true)) )
		{
			$order->deleteMeta('receipt_date');

			/** @see Hook 'lws_woosupply_supplierorder_received */
			$ret = \apply_filters('lws_woosupply_supplierorder_received_revert', false, $order);
			if( $ret === false )
			{
				foreach( $order->getItems() as $item )
				{
					$item->updateOnDelivery(0);
				}
			}
		}
	}

	/** Since order status pass 'ws_complete', we considere it as paid. */
	public function applyOrderPaid($orderId, $order, $oldStatus)
	{
		if( $order->getStatus() != $oldStatus && lws_ws()->cmpOrderStatus($order->getStatus(), 'ws_complete') >= 0 )
		{
			if( empty($order->getMeta('order_paid', true)) )
			{
				$order->updateMeta('order_paid', \date('Y-m-d'));
			}
		}
	}

	public function register()
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/admin.php';
		new \LWS\WOOSUPPLY\Admin();
	}

	public function plugin()
	{
		$activated = false;
//		lws_register_update(__FILE__, null, md5(\get_class() . __FUNCTION__));
//		$activated = lws_require_activation(__FILE__, null, array('lws_woosupply_supplierorder', 'lws_woosupply_supplier', 'lws_woosupply_statistics','lws_woosupply_settings'), md5(\get_class() . __FUNCTION__));
//		lws_extension_showcase(__FILE__);
		define( 'LWS_WOOSUPPLY_ACTIVATED', $activated );
		$this->install();
	}

	/** add relevent default values. Values are NOT overwriten if already exist. */
	public static function activate()
	{
		require_once dirname(__FILE__) . '/include/updater.php';
		\LWS\WOOSUPPLY\Updater::activation();
	}

}

/** access to some features without including anything. @see Conveniences */
function lws_ws()
{
	static $conveniences = null;
	if( empty($conveniences) )
	{
		require_once dirname(__FILE__) . '/include/conveniences.php';
		$conveniences = new \LWS\WOOSUPPLY\Conveniences();
	}
	return $conveniences;
}

LWS_WooSupply::init();
{
	if( \file_exists($asset = (dirname(__FILE__) . '/assets/lws-adminpanel/lws-adminpanel.php')) )
		include_once $asset;
	if( \file_exists($asset = (dirname(__FILE__) . '/modules/woosupply-pro/woosupply-pro.php')) )
		include_once $asset;
}
