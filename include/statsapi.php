<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**	Provides ajax entries to get business statistics. */
class StatsAPI
{

	function __construct()
	{
		$prefix = 'wp_ajax_lws_woosupply_statistics_';
		\add_action( $prefix.'pending_order', array( $this, 'pendingOrderCount') );
		\add_action( $prefix.'this_month_spending', array( $this, 'currentMonthSpendAmount') );

		\add_action( $prefix.'resume', array( $this, 'resume') );
		\add_action( $prefix.'balance', array( $this, 'balance') );
	}

	private function userCanOrDie($from='')
	{
		if( !\current_user_can(\apply_filters('lws_woosupply_api_statistics_capacity', 'view_purchases', $from)) )
			\wp_die();
	}

	function balance()
	{
		$this->userCanOrDie(__FUNCTION__);

		$dmin = \date_create($_REQUEST['min']);
		$dmax = \date_create($_REQUEST['max']);
		if( !empty($dmin) && !empty($dmax) )
		{
			$vmin = $dmin->format('Y-m-d');
			$vmax = $dmax->format('Y-m-d');

			$result = array(
				'spent' => $this->getBalanceSpent($vmin, $vmax),
				'gain' => $this->getBalanceGain($vmin, $vmax),
				'balance' => 'eq',
				'margin' => __("n/c", 'woosupply-lite')
			);

			if( is_numeric($result['spent']) && is_numeric($result['gain']) )
			{
				$result['margin'] = $result['gain'] - $result['spent'];
				$result['unformatted'] = array(
					'spent' => $result['spent'],
					'gain' => $result['gain'],
					'margin' => $result['margin']
				);
			}
			if( is_numeric($result['spent']) )
				$result['spent'] = lws_ws()->getDisplayPriceWithCurrency($result['spent']);
			if( is_numeric($result['gain']) )
				$result['gain'] = lws_ws()->getDisplayPriceWithCurrency($result['gain']);
			if( is_numeric($result['margin']) )
			{
				if( abs($result['margin']) > \get_option('lws_woosupply_price_cmp_epsilon', 0.001) )
					$result['balance'] = $result['margin'] > 0.0 ? 'gt' : 'lt';
				$result['margin'] = lws_ws()->getDisplayPriceWithCurrency($result['margin']);
			}

			\wp_send_json($result);
		}
		else
			\wp_die();
	}

	/**	Get sale total.
	 *	Get order tax from meta '_order_tax'.
	 *	Get order total with tax from meta '_order_total'.
	 *	@param $dateMin (string) mysql formatted date.
	 *	@param $dateMin (string) mysql formatted date. */
	private function getBalanceGain($dateMin, $dateMax)
	{
		$ht = !empty(\get_option('lws_woosupply_purchases_exclude_tax', 'on'));
		$total_sales = "SUM(meta.meta_value)-SUM(ship.meta_value)";
		if( $ht )
			$total_sales .= "-SUM(tax.meta_value)-SUM(stax.meta_value)";

		global $wpdb;
		$sql = "SELECT ({$total_sales}) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts";
		$sql .= " LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id AND meta.meta_key = '_order_total'";
		$sql .= " LEFT JOIN {$wpdb->postmeta} AS ship ON posts.ID = ship.post_id AND ship.meta_key = '_order_shipping'";
		if( $ht )
		{
			$sql .= " LEFT JOIN {$wpdb->postmeta} AS tax ON posts.ID = tax.post_id AND tax.meta_key = '_order_tax'";
			$sql .= " LEFT JOIN {$wpdb->postmeta} AS stax ON posts.ID = stax.post_id AND stax.meta_key = '_order_shipping_tax'";
		}
		$sql .= " WHERE posts.post_type = 'shop_order'";
		$sql .= " AND posts.post_status IN ('" . implode( "','", array( 'wc-completed', 'wc-processing', 'wc-on-hold' ) ) . "')";
		$sql .= " AND DATE('$dateMin') <= DATE(posts.post_date) AND DATE(posts.post_date) < DATE('$dateMax')";

		$values = apply_filters('woocommerce_reports_sales_overview_order_totals', $wpdb->get_row($sql));
		return is_null($values) ? 'error' : floatval($values->total_sales);
	}

	/** @param $dateMin (string) mysql formatted date.
	 *	@param $dateMin (string) mysql formatted date. */
	private function getBalanceSpent($dateMin, $dateMax)
	{
		global $wpdb;
		$sql = <<<EOT
SELECT o.id, SUM(i.amount) as spent, TRIM(MAX(a.meta_value)) as invoice FROM {$wpdb->lws_woosupply_supplierorder} as o
INNER JOIN {$wpdb->lws_woosupply_supplierorderitem} as i ON o.id=i.order_id
INNER JOIN {$wpdb->lws_woosupply_supplierordermeta} as m ON o.id=m.lws_woosupply_supplierorder_id AND meta_key='order_paid'
LEFT JOIN {$wpdb->lws_woosupply_supplierordermeta} as a ON o.id=a.lws_woosupply_supplierorder_id AND a.meta_key='remote_invoice_amount'
WHERE DATE('$dateMin') <= DATE(m.meta_value) AND DATE(m.meta_value) < DATE('$dateMax')
GROUP BY o.id
EOT;

		$values = apply_filters('lws_woosupply_reports_purchase_overview_order_totals', $wpdb->get_results($sql));
		if( is_null($values) )
			return 'error';
		$value = 0;
		foreach( $values as $row )
			$value += is_numeric($row->invoice) ? $row->invoice : $row->spent;
		return floatval($value);
	}

	/**	Report of supplier order and customer order.
	 *	Columns are: order_id, order_date, amount, order_url, order_type(supplier|customer), name (supplier or customer name)
	 *	@return json table */
	function resume()
	{
		$this->userCanOrDie(__FUNCTION__);

		$dmin = \date_create($_REQUEST['min']);
		$dmax = \date_create($_REQUEST['max']);
		if( !empty($dmin) && !empty($dmax) )
		{
			$rs = $this->getSupplierOrders($dmin, $dmax);
			$rc = $this->getCustomerOrders($dmin, $dmax);
			$results = array_merge($rs, $rc);
			\wp_send_json(\apply_filters('lws_woosupply_statistics_resume_table', $results, $dmin, $dmax));
		}
		else
			\wp_die();
	}

	private function getSupplierOrders($dmin, $dmax)
	{
		$vmin = $dmin->format('Y-m-d');
		$vmax = $dmax->format('Y-m-d');
		global $wpdb;
		$sql = "SELECT o.id, DATE_FORMAT(m.meta_value, '%Y-%m-%d') as order_date, SUM(i.amount) as total, TRIM(a.meta_value) as invoice, supplier_name as name";
		$sql .= " FROM {$wpdb->lws_woosupply_supplierorder} as o";
		$sql .= " INNER JOIN {$wpdb->lws_woosupply_supplierorderitem} as i ON o.id=i.order_id";
		$sql .= " INNER JOIN {$wpdb->lws_woosupply_supplierordermeta} as m ON o.id=m.lws_woosupply_supplierorder_id AND m.meta_key='order_paid'";
		$sql .= " LEFT JOIN {$wpdb->lws_woosupply_supplierordermeta} as a ON o.id=a.lws_woosupply_supplierorder_id AND a.meta_key='remote_invoice_amount'";
		$sql .= " WHERE DATE('$vmin') <= DATE(m.meta_value) AND DATE(m.meta_value) < DATE('$vmax')";
		$sql .= " GROUP BY o.id";

		$results = $wpdb->get_results($sql);
		foreach( $results as &$result )
		{
			$result->order_id   = LWS_WSORDER_ID_PREFIX . \lws_ws()->formatOrderNumber($result->id);
			$result->total_spent= is_null($result->invoice) ? $result->total : $result->invoice;
			$result->amount     = lws_ws()->getDisplayPriceWithCurrency($result->total_spent);
			$result->order_url  = \add_query_arg(array('page' => 'lws_woosupply_supplierorder', 'supplierorder_id' => $result->id), \admin_url('admin.php'));
			$result->order_type = 'supplier';
		}
		return $results;
	}

	private function getCustomerOrders($dmin, $dmax)
	{
		$vmin = $dmin->format('Y-m-d');
		$vmax = $dmax->format('Y-m-d');
		$ht = !empty(\get_option('lws_woosupply_purchases_exclude_tax', 'on'));
		$total_sales = "SUM(meta.meta_value)-SUM(ship.meta_value)";
		if( $ht )
			$total_sales .= "-SUM(tax.meta_value)-SUM(stax.meta_value)";

		global $wpdb;
		$sql = "SELECT ({$total_sales}) AS total_sales, posts.ID AS order_id, DATE_FORMAT(posts.post_date, '%Y-%m-%d') as order_date, user.user_login, user.display_name";
		$sql .= " FROM {$wpdb->posts} AS posts";
		$sql .= " LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id AND meta.meta_key = '_order_total'";
		$sql .= " LEFT JOIN {$wpdb->postmeta} AS ship ON posts.ID = ship.post_id AND ship.meta_key = '_order_shipping'";
		if( $ht )
		{
			$sql .= " LEFT JOIN {$wpdb->postmeta} AS tax ON posts.ID = tax.post_id AND tax.meta_key = '_order_tax'";
			$sql .= " LEFT JOIN {$wpdb->postmeta} AS stax ON posts.ID = stax.post_id AND stax.meta_key = '_order_shipping_tax'";
		}
		$sql .= " LEFT JOIN {$wpdb->postmeta} AS mu ON posts.ID = mu.post_id AND mu.meta_key = '_customer_user'";
		$sql .= " LEFT JOIN {$wpdb->users} AS user ON user.ID = mu.meta_value";
		$sql .= " WHERE posts.post_type = 'shop_order'";
		$sql .= " AND posts.post_status IN ('" . implode( "','", array( 'wc-completed', 'wc-processing', 'wc-on-hold' ) ) . "')";
		$sql .= " AND DATE('$vmin') <= DATE(posts.post_date) AND DATE(posts.post_date) < DATE('$vmax')";
		$sql .= " GROUP BY posts.ID";

		$results = $wpdb->get_results($sql);
		foreach( $results as &$result )
		{
			$result->amount     = lws_ws()->getDisplayPriceWithCurrency($result->total_sales);
			$result->order_url  = \get_edit_post_link($result->order_id, '');
			$result->order_type = 'customer';
			$result->name       = empty($result->display_name) ? $result->user_login : $result->display_name;
		}
		return $results;
	}

	/** output the number of ws_sent or ws_ack supplier order. */
	function pendingOrderCount()
	{
		$this->userCanOrDie(__FUNCTION__);
		global $wpdb;
		$value = $wpdb->get_var("SELECT COUNT(o.id) FROM {$wpdb->lws_woosupply_supplierorder} as o WHERE o.status IN ('ws_sent', 'ws_ack')");
		\wp_die($value);
	}

	/** amount of order ws_ack, ws_received or ws_complete this month */
	function currentMonthSpendAmount()
	{
		$this->userCanOrDie(__FUNCTION__);
		$startDate = \date_create();
		$startDate->setDate($startDate->format('Y'), $startDate->format('m'), max(1, intval(\get_option('lws_woosupply_day_start_of_month', 1))));
		$d = $startDate->format('Y-m-d');

		global $wpdb;
		$sql = <<<EOT
SELECT o.id, SUM(i.amount) as spent, TRIM(MAX(a.meta_value)) as invoice FROM {$wpdb->lws_woosupply_supplierorder} as o
INNER JOIN {$wpdb->lws_woosupply_supplierorderitem} as i ON o.id=i.order_id
INNER JOIN {$wpdb->lws_woosupply_supplierordermeta} as m ON o.id=m.lws_woosupply_supplierorder_id AND meta_key='order_paid'
LEFT JOIN {$wpdb->lws_woosupply_supplierordermeta} as a ON o.id=a.lws_woosupply_supplierorder_id AND a.meta_key='remote_invoice_amount'
WHERE o.status IN ('ws_ack', 'ws_received', 'ws_complete')
AND DATE(m.meta_value) >= date('$d')
GROUP BY o.id
EOT;
		$values = $wpdb->get_results($sql);
		$value = 0;
		foreach( $values as $row )
			$value += is_numeric($row->invoice) ? $row->invoice : $row->spent;

		\wp_die(lws_ws()->getDisplayPriceWithCurrency(floatval($value)));
	}

}

?>
