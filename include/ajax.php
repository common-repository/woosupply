<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class Ajax
{
	public function __construct()
	{
		\add_action( 'wp_ajax_lws_woosupply_order_pdf', array( $this, 'pdf') );
		\add_action( 'wp_ajax_lws_woosupply_order_action_set_received', array( $this, 'setOrderStatusToReceived') );
		\add_action( 'wp_ajax_lws_woosupply_order_action_set_complete', array( $this, 'setOrderStatusToComplete') );
		\add_action( 'wp_ajax_lws_woosupply_order_action_set_paid', array( $this, 'setOrderAsPaid') );

		\add_action( 'wp_ajax_lws_woosupply_format_price', array($this, 'formatPrice') );
		\add_action( 'wp_ajax_lws_woosupply_order_details_format', array($this, 'formatOrderDetailsForm') );

		\add_action( 'wp_ajax_lws_woosupply_supplier_get', array( $this, 'getSupplier') );
		\add_action( 'wp_ajax_lws_woosupply_supplier_list', array( $this, 'getSuppliers') );

		\add_action( 'wp_ajax_lws_woosupply_wc_product_list', array( $this, 'getWCProducts') );
		\add_action( 'wp_ajax_lws_woosupply_supplierproduct_list', array( $this, 'getSupplierProducts') );

		\add_action( 'wp_ajax_lws_woosupply_order_invoice_document_get', array( $this, 'getOrderInvoiceDocument') );
	}

	/** echo pdf instead a usual page.
	 * @param $_GET['supplierorder_id'] the order id */
	public function pdf()
	{
		$ws_order = $this->checkUserCanAndGetOrder('lws_woosupply_order_to_pdf_capability', 'view_purchases');
		if( !empty($ws_order) )
		{
			$base = \get_option('lws_woosupply_pdf_filename');
			$basename = implode('-', array(
				$ws_order->getDate('Ymd'),
				preg_replace('/[^A-Za-z0-9_\-]/', '_', (empty($base) ? 'supplier-order' : $base)),
				intval($ws_order->getId())
			));
			$filename = \apply_filters('lws_woosupply_order_pdf_filename', $basename.'.pdf', $ws_order);
			$content = \apply_filters('lws_woosupply_order_pdf', false, $ws_order);
			if( $content === false )
			{
				ob_start();
				@include(LWS_WOOSUPPLY_INCLUDES . '/templates/order.php');
				$content = ob_get_clean();
			}
			$display = array('Attachment' => (\get_option('lws_woosupply_pdf_display')=='df' ? 1:0));
			try
			{
				\lws_ws()->getPDF($content)->stream($filename, $display);
			}
			catch( \Exception $e )
			{
				error_log('Exception catched during PDF generation :' . $e->getMessage());
				$error = '<p>'.sprintf(__("An error occured during the PDF generation: <b>%s</b>", 'woosupply-lite'), $e->getMessage()).'</p>';
				if( strpos($e->getMessage(), 'PHP GD') !== false )
				{
					$error .= '<p>' . __("This could be due to an image format unsupported conversion.<br/>Note that <b>JPG images can be used in PDF without conversion.</b>", 'woosupply-lite') . '</p>';
				}
				\lws_admin_add_notice_once('lws_woosupply_order_to_pdf', $error, array('level' => 'error'));
				\wp_redirect( \add_query_arg('page', 'lws_woosupply_settings', \admin_url('admin.php')) );
			}
			exit();
		}
		else
		{
			\wp_die(__("Order cannot be found.", 'woosupply-lite'), 404);
			exit;
		}
	}

	/**	@param $_GET['supplierorder_id'] */
	public function getOrderInvoiceDocument()
	{
		$order = $this->checkUserCanAndGetOrder('lws_woosupply_order_invoice_document_get_capability', 'view_purchases');
		if( !empty($order) )
		{
			$filepath = $order->getMeta('invoice_document', true);
			if( !empty($filepath) && file_exists($filepath) )
			{
				\lws_ws()->echoFileAndDie($filepath, $order->getMeta('invoice_document_name', true));
			}
			else
			{
				\wp_die(__("No invoice document found.", 'woosupply-lite'), 404);
				exit;
			}
		}
		else
		{
			\wp_die(__("Order cannot be found.", 'woosupply-lite'), 404);
			exit;
		}
	}

	/** autocomplete/lac compliant.
	 * Search wp_post(wc_product) on id (or name if fromValue is false or missing).
	 * Assume id is prefixed by 'wc_'.
	 * @see hook 'wp_ajax_lws_woosupply_wc_product_list'.
	 * @param $_REQUEST['term'] (string) filter on product name
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getWCProducts()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue, 'wc_');
		$spec = array();

		global $wpdb;
		$sql = "SELECT CONCAT('wc_', ID) as value, post_title as label FROM {$wpdb->posts}";
		if( $fromValue )
		{
			// can only be a SupplierProduct
			$sql .= " WHERE ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$search%");
			}
			$where[] = "post_type='product' AND (post_status='publish' OR post_status='private')";

			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$count = false;
		$offset = 0;
		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}

		\wp_send_json($wpdb->get_results($sql));
	}

	/** autocomplete/lac compliant.
	 * Search SupplierProduct on id if fromValue is true.
	 * Else search for a SupplierProduct or WC_Product.
	 * echo a flat json with SupplierProduct from all supplier unsorted but no WC_Product.
	 * @see hook 'lws_woosupply_supplierproduct_list' to override the original behavior.
	 * @param $_REQUEST['term'] (string) filter on product name
	 * @param $_REQUEST['spec'] (array, json base64 encoded /optional) array('supplier_id' => (int)) the selected order supplier id.
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getSupplierProducts()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);
		$spec = isset($_REQUEST['spec']) ? json_decode(base64_decode($_REQUEST['spec'])) : array();
		if( !is_array($spec) ) $spec = array();

		$count = false;
		$offset = 0;
		$limit = '';
		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$limit = " LIMIT $offset, $count";
		}

		if( !$fromValue ) // make a nearly search
		{
			// For $wpdb->lws_woosupply_supplierproduct term trim, add * only if no +-*()<>"~
			if( 0 === preg_match('/^[\+\-\*\(\)<>"~]/', $term) ) $term = '*'.$term;
			if( 0 === preg_match('/[\+\-\*\(\)<>"~]$/', $term) ) $term .= '*';
		}

		/** let a filter override our behavior.
		 * hook 'lws_woosupply_supplierproduct_list' filter.
		 * @param false : the initial results.
		 * @param $term (string) the search term already decorated for a nearly search.
		 * @param $spec (array) could contains addionnal filter (especially a supplier_id).
		 * @param $offset (int) for paging (sql limit) starting row.
		 * @param $count (int|false) the number of result to return, false means no paging.
		 * @return (false|array) anything other than false will be returned as json, so override the normal behavior
		 * */
		$results = \apply_filters('lws_woosupply_supplierproduct_list', false, $term, $fromValue, $spec, $offset, $count);
		if( false !== $results )
			\wp_send_json($results);

		global $wpdb;
		if( $fromValue )
		{
			// can only be a SupplierProduct
			$sql = "SELECT id as value, name as label FROM {$wpdb->lws_woosupply_supplierproduct}";
			$sql .= " WHERE id IN (" . implode(',', $term) . ")";
			\wp_send_json($wpdb->get_results($sql . $limit));
		}
		else
		{
			$sql = "SELECT id as value, name as label FROM {$wpdb->lws_woosupply_supplierproduct}";
			$sql .= " WHERE " . $wpdb->prepare("MATCH(name) AGAINST(%s IN BOOLEAN MODE)", $term);
			\wp_send_json($wpdb->get_results($sql . $limit));
		}
	}

	/** autocomplete/lac compliant.
	 * Search on id (or name if fromValue is true). */
	public function getSuppliers()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$where = array();
		if( !empty($term) )
		{
			if( $fromValue )
				$where[] = ("id IN (" . implode(',', $term) . ")");
			else
				$where[] = $wpdb->prepare("name LIKE %s", "%$term%");
		}

		$sql = "SELECT id as value, name as label FROM {$wpdb->lws_woosupply_supplier}";
		if( !empty($where) )
			$sql .= " WHERE " . implode(' AND ', $where);

		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}

		\wp_send_json($wpdb->get_results($sql));
	}

	/** @param $_GET['supplier_id'] */
	public function getSupplier()
	{
		$id = 0;
		if( isset($_GET['supplier_id']) )
			$id = intval($_GET['supplier_id']);

		if( !empty($id) )
		{
			require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';
			$supplier = Supplier::get($id);
			if( !empty($supplier) )
			{
				$json = array(
					'name' => '',
					'email' => '',
					'contact_firstname' => '',
					'contact_lastname' => '',
					'address' => '',
					'address_2' => '',
					'address_zipcode' => '',
					'address_city' => '',
					'address_country' => '',
					'address_state' => '',
					'phone_number' => '',
					'fax_number' => '',
					'tax_number' => ''
				);
				foreach( $json as $k => $v )
					$json[$k] = $supplier->getData($k);

				if( isset($json['address_country']) && !empty($json['address_country']) )
				{
					if( isset($json['address_state']) && !empty($json['address_state']) )
						$json['address_state_name'] = \LWS\WOOSUPPLY\CountryState::instance()->getStateByCodes($json['address_country'], $json['address_state']);
					else
						$json['address_state_name'] = '';
					$json['address_country_name'] = \LWS\WOOSUPPLY\CountryState::instance()->getCountryByCode($json['address_country']);
				}

				\wp_send_json($json);
			}
			else
				\wp_send_json(array('error' => __("Unknown supplier.", 'woosupply-lite')));
		}
		else
			\wp_send_json(array('error' => __("No supplier id given or invalid value.", 'woosupply-lite')));
	}

	/** @see setOrderStatus('ws_received') */
	public function setOrderStatusToReceived()
	{
		$this->setOrderStatus('ws_received');
	}

	/** @see setOrderStatus('ws_complete') */
	public function setOrderStatusToComplete()
	{
		$this->setOrderStatus('ws_complete');
	}

	/**
	 *	@param $status the status to set.
	 *	@param $_GET['supplierorder_id'] */
	private function setOrderStatus($status)
	{
		$order = $this->checkUserCanAndGetOrder('lws_woosupply_order_change_status_capability', 'manage_purchases');
		if( !empty($order) )
		{
			$order->setStatus($status);

			$hide = '.lws-woosupply-order-action-received';
			if( \lws_ws()->cmpOrderStatus($order->getStatus(), 'ws_complete') >= 0 )
				$hide .= '|.lws-woosupply-order-action-complete';
			if( !empty($order->getMeta('order_paid', true)) )
				$hide .= '|.lws_woosupply_order_action_paid_set';

			$status_class = 'lws_woosupply_order_status lws-woosupply-order-status lws-woosupply-order-status-' . esc_attr(substr($order->getStatus(), 3));
			$status_label = SupplierOrder::statusLabel($order->getStatus());

			\wp_send_json(array(
				'ok'     => true,
				'status' => $status,
				'html'   => "<div class='$status_class'>{$status_label}</div>",
				'hide'   => $hide
			));
		}
		else
		{
			\wp_send_json(array(
				'ok' => false,
				'error' => __("The supplier order cannot be found.", 'woosupply-lite')
			));
		}
	}

	/**	@param $_GET['supplierorder_id'] */
	function setOrderAsPaid()
	{
		$order = $this->checkUserCanAndGetOrder('lws_woosupply_order_change_paid_capability', 'manage_purchases');
		if( !empty($order) )
		{
			if( empty($order->getMeta('order_paid', true)) )
				$order->updateMeta('order_paid', date('Y-m-d'));

			\wp_send_json(array(
				'ok'     => true,
				'hide'   => '.lws_woosupply_order_action_paid_set'
			));
		}
		else
		{
			\wp_send_json(array(
				'ok' => false,
				'error' => __("The supplier order cannot be found.", 'woosupply-lite')
			));
		}
	}

	/**	@param $defaultCapacity allows testing current_user_can.
	 * @param $filter apply a filter allowing changing the default tested capacity.
	 * @param $_GET['supplierorder_id'] */
	private function checkUserCanAndGetOrder($filter, $defaultCapacity)
	{
		if( !\current_user_can(\apply_filters($filter, $defaultCapacity)) )
		{
			\wp_die(__("Forbidden Order Edition.", 'woosupply-lite'), 403);
			exit;
		}

		if( isset($_GET['supplierorder_id']) && is_numeric($_GET['supplierorder_id']) && $_GET['supplierorder_id'] > 0 )
		{
			$orderId = intval($_GET['supplierorder_id']);
			return lws_ws()->getSupplierOrder($orderId);
		}
		else
		{
			\wp_die(__("Missing or invalid arguments.", 'woosupply-lite'), 400);
			exit;
		}

		return false;
	}

	/** @param $readAsIdsArray (bool) true if term is an array of ID or false if term is a string
	 *	@param $prefix (string) remove this prefix at start of term values.
	 *	@param $_REQUEST['term'] (string) filter on post_title or if $readAsIdsArray (array of int) filter on ID.
	 *	@return an array of int if $readAsIdsArray, else a string. */
	private function getTerm($readAsIdsArray, $prefix='')
	{
		$len = strlen($prefix);
		$term = '';
		if( isset($_REQUEST['term']) )
		{
			if( $readAsIdsArray )
			{
				if( is_array($_REQUEST['term']) )
				{
					$term = array();
					foreach( $_REQUEST['term'] as $t )
					{
						if( $len > 0 && substr($t, 0, $len) == $prefix )
							$t = substr($t, $len);
						$term[] = intval($t);
					}
				}
				else
					$term = array(intval($_REQUEST['term']));
			}
			else
				$term = \sanitize_text_field(trim($_REQUEST['term']));
		}
		return $term;
	}

	public function formatPrice()
	{
		if( !isset($_GET['price']) )
			\wp_die(__("Argument missing", 'woosupply-lite'), 400);
		$price = \lws_ws()->getRawPrice(\sanitize_text_field($_GET['price']));
		\wp_die(\lws_ws()->getDisplayPrice($price));
	}

	/** Get prices and quantites, validate them, apply format, compute a formated row amount.
	 * Compute a total and send all as json {uprices:[], quantities:[], amounts:[],total:0}
	 * @param $_POST['uprices'] array of unit price to be formated
	 * @param $_POST['quantities'] array of quantity to be formated
	 * @param $_POST['timestamp'] to be safe on async
	 **/
	public function formatOrderDetailsForm()
	{
		if( !isset($_POST['uprices']) || !isset($_POST['quantities']) || !isset($_POST['timestamp']) )
		{
			\wp_send_json(array(
				'ok' => false,
				'error' => __("Waited for providing unit price AND amount price.", 'woosupply-lite')
			));
		}
		if( !is_array($_POST['uprices']) || !is_array($_POST['quantities']) )
		{
			\wp_send_json(array(
				'ok' => false,
				'error' => __("Bad data. Expect arrays.", 'woosupply-lite')
			));
		}

		$json = array(
			'quantities' => array(),
			'uprices' => array(),
			'amounts' => array(),
			'total' => 0,
			'timestamp' => \sanitize_text_field($_POST['timestamp'])
		);

		$length = \min(count($_POST['uprices']), count($_POST['quantities']));
		for( $index=0 ; $index<$length ; ++$index )
		{
			$q = \lws_ws()->getRawQuantity(\sanitize_text_field($_POST['quantities'][$index]));
			$p = \lws_ws()->getRawPrice(\sanitize_text_field($_POST['uprices'][$index]));
			$t = $p * $q;

			$json['quantities'][] = \lws_ws()->getDisplayQuantity($q);
			if( $p < 0 )
			{
				$json['uprices'][] = sprintf(_x("Invalid (%s)", "user input negative price", 'woosupply-lite'), $p);
				$json['amounts'][] = _x("NaN", "computed value from negative price", 'woosupply-lite');
			}
			else
			{
				$json['uprices'][] = \lws_ws()->getDisplayPrice($p);
				$json['amounts'][] = \lws_ws()->getDisplayPrice($t);
				$json['total'] += $t;
			}
		}

		$json['total'] = \lws_ws()->getDisplayPrice($json['total']);
		\wp_send_json($json);
	}

}
?>
