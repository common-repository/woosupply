<?php
namespace LWS\WOOSUPPLY;
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';

class SupplierOrderList extends \LWS\Adminpanel\EditList\Source
{
	static public $Id = 'supplierorderlist';

	/** @param $excludeMod is the given status array a blacklist (true) or a whitelist (false). */
	function __construct($excludeMod=true, $statusList=array())
	{
		if( $excludeMod ) // blacklist
		{
			$this->statusList = SupplierOrder::statusList();
			foreach( $statusList as $status )
			{
				if( isset($this->statusList[$status]) )
					unset($this->statusList[$status]);
			}
		}
		else // whitelist
		{
			$this->statusList = array();
			$list = SupplierOrder::statusList();
			foreach( $statusList as $status )
			{
				if( isset($list[$status]) )
					$this->statusList[$status] = $list[$status];
			}
		}

		if( empty($this->statusList) )
		{
			error_log(__CLASS__ . ": statusList cannot be empty. Fallback on full list.");
			$this->statusList = SupplierOrder::statusList();
		}
	}

	function hideColumns($cols)
	{
		if( !is_array($cols) )
			$cols = array($cols);
		$this->hiddenColumns = array_combine($cols, $cols);
	}

	/** @return the status whitelist. */
	function getStatusList()
	{
		return $this->statusList;
	}

	/** Only used for order copy and to enqueue javascript. */
	function input(){
		\wp_enqueue_script('lws-woosupply-orderlist', LWS_WOOSUPPLY_JS.'/supplierorderlist.js', array('jquery'), LWS_WOOSUPPLY_VERSION, true);
		$str = "<input type='hidden' name='id'";

		$str .= "<fieldset class='lws-woosupply-orderlist-override-inputs'>";

		$str .= "<label><span class='lws-editlist-opt-title'>".__("Supplier Contact", 'woosupply-lite')."</span>";
		$str .= "<span class='lws-editlist-opt-input'><input class='lws-input' type='text' autocomplete='off' name='supplier_contact'/></span></label>";

		$str .= "<label><span class='lws-editlist-opt-title'>".__("Supplier Invoice Number", 'woosupply-lite')."</span>";
		$str .= "<span class='lws-editlist-opt-input'><input class='lws-input' type='text' autocomplete='off' name='remote_invoice_id'/></span></label>";

		$str .= "<label><span class='lws-editlist-opt-title'>".__("Supplier Invoice Date", 'woosupply-lite')."</span>";
		$str .= "<span class='lws-editlist-opt-input'><input class='lws-input' type='date' autocomplete='off' name='remote_invoice_date'/></span></label>";

		$str .= "</fieldset>";
		return $str;
	}

	/** Order copy. */
	function write($line)
	{
		if( !(is_array($line) && isset($line['id']) && is_numeric($line['id'])) )
		{
			error_log("Invalid order request.");
			return false;
		}
		if( empty($order = SupplierOrder::get(intval($line['id']))) )
		{
			error_log("Unknown order requested.");
			return false;
		}

		// deep copy except status let to ws_new, delivery to zero
		$copy = SupplierOrder::create();
		$props = array(
			'supplier_id',
			'supplier_name',
			'supplier_contact',
			'supplier_tax_number',
			'supplier_email',
			'supplier_address',
			'supplier_address_2',
			'supplier_address_zipcode',
			'supplier_address_city',
			'supplier_address_country',
			'supplier_address_state',
			'delivery_name',
			'delivery_tax_number',
			'delivery_address',
			'delivery_address_2',
			'delivery_address_zipcode',
			'delivery_address_city',
			'delivery_address_country',
			'delivery_address_state'
		);
		foreach( $props as $prop )
			$copy->$prop = $order->$prop;

		// replace user value
		if( isset($line['supplier_contact']) )
			$copy->$prop = \sanitize_text_field($line['supplier_contact']);

		// copy includes some item meta
		$copy->addManagedItemsMeta(array('comments'));

		// deep item copy
		foreach( $order->getItems() as $item )
		{
			// some meta should be copied too
			foreach( $copy->getManagedItemsMetas() as $meta )
				$item->getMeta($meta, true);
			$copy->pushItem(\apply_filters('lws_woouspply_supplierorder_item_copy', $item->detach(), $order, $copy));
		}

		// finalize and save
		$copy = \apply_filters('lws_woouspply_supplierorder_copy', $copy, $order);
		if( empty($copy) )
		{
			error_log("Cannot achieve the order copy.");
			return false;
		}
		else if( !$copy->update() )
		{
			error_log("Fail to save the order copy.");
			return false;
		}

		// replace user value
		if( isset($line['remote_invoice_date']) )
			$copy->setDate(\sanitize_text_field($line['remote_invoice_date']), 'remote_invoice_date', true, false, true);
		if( isset($line['remote_invoice_id']) )
			$copy->updateMeta('remote_invoice_id', \sanitize_text_field($line['remote_invoice_id']));

		$row = array(
			'id'                  => $copy->getId(),
			'supplierorder_id'    => $copy->getId(),
			'status'              => $copy->getStatus(),
			'order_date'          => $copy->getL18NDate(),
			'order_paid'          => '',
			'remote_invoice_id'   => $copy->getData('remote_invoice_id'),
			'remote_invoice_date' => $copy->getData('remote_invoice_date'),
			'supplier_contact'    => $copy->supplier_contact,
			'supplier_name'       => $copy->supplier_name,
			'amount'              => $copy->getAmount()
		);
		return $this->formatRow($row);
	}

	function erase($line)
	{
		if( !(is_array($line) && isset($line['supplierorder_id']) && is_numeric($line['supplierorder_id'])) )
		{
			error_log("Invalid deletion request.");
			return false;
		}

		$id = intval($line['supplierorder_id']);
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';

		$order = SupplierOrder::get($id);
		if( empty($order) )
		{
			error_log("Cannot found the order.");
			return false;
		}

		if( !empty($filepath = $order->getMeta('invoice_document', true)) && file_exists($filepath) )
		{
			if( !@unlink($filepath) )
				error_log("Failed to remove a file with order '$id' : ".$filepath);
		}

		if( empty($order->delete()) )
		{
			error_log("An error occured during the order deletion.");
			return false;
		}

		return true;
	}

	function labels()
	{
		$labs = array(
			'order_url'     => array(__("Id", 'woosupply-lite'), "5%"),
			'order_date'    => array(__("Date", 'woosupply-lite'), "10%"),
			'supplier_name' => __("Supplier", 'woosupply-lite'),
			'status_label'  => array(__("Status" , 'woosupply-lite'), "10%"),
			'amount'        => array(__("Amount", 'woosupply-lite'), "10%"),
			'pdf'           => array(__("PDF", 'woosupply-lite'), "5%"),
			'_actions'      => __("Actions", 'woosupply-lite')
		);

		if( isset($this->hiddenColumns) && !empty($this->hiddenColumns) )
			$labs = array_diff_key($labs, $this->hiddenColumns);
		return $labs;
	}

	function total()
	{
		global $wpdb;
		return $wpdb->get_var($this->getOrdersQuery(null, true));
	}

	function read($limit)
	{
		global $wpdb;
		if( !empty($orders = $wpdb->get_results($this->getOrdersQuery($limit), ARRAY_A)) )
		{
			foreach($orders as &$order)
			{
				$this->formatRow($order);
			}
		}
		return \apply_filters('lws_woouspply_supplierorder_orderlist_read', $orders);
	}

	protected function formatRow(&$order)
	{
		$status_class = 'lws_woosupply_order_status lws-woosupply-order-status lws-woosupply-order-status-' . esc_attr(substr($order['status'], 3));
		$status_label = SupplierOrder::statusLabel($order['status']);
		$order['status_label'] = "<div class='$status_class'>{$status_label}</div>";

		$order_url = \esc_attr(\add_query_arg(array('page' => 'lws_woosupply_supplierorder', 'supplierorder_id' => $order['supplierorder_id']), \admin_url('admin.php')));
		$ol = \apply_filters('lws_woosupply_supplierorder_label', $order['supplierorder_id']);
		$order['order_url'] = "<a href='$order_url' class='lws-woosupply-order-edit-link'>{$ol}</a>";

		if( !empty($order['supplier_contact']) )
			$order['supplier_name'] .= " &lt;{$order['supplier_contact']}&gt;";

		$order['order_date'] = \mysql2date(\get_option('date_format'), $order['order_date']);
		$order['amount'] = lws_ws()->getDisplayPriceWithCurrency(is_null($order['invoice']) || !strlen($order['invoice']) ? $order['amount'] : $order['invoice']);

		$order['_actions'] = $this->getOrderActions($order['supplierorder_id'], $order['status'], $order);
		$order['pdf'] = $this->getPdfButton($order['supplierorder_id']);
		return $order;
	}

	/// @return the sql request to get the orders
	protected function getOrdersQuery($limit, $countOnly=false)
	{
		global $wpdb;
		$sql = array(
			'select' => '',
			'from'   => '',
			'join'   => '',
			'where'  => '',
			'group'  => '',
			'order'  => ''
		);

		$sql['from'] = "FROM {$wpdb->lws_woosupply_supplierorder} as o";
		$sql['join'] = "LEFT JOIN {$wpdb->lws_woosupply_supplier} as s ON o.supplier_id=s.id";
		$sql['join'] .= "\nLEFT JOIN {$wpdb->lws_woosupply_supplierordermeta} as inv_i ON o.id=inv_i.lws_woosupply_supplierorder_id AND inv_i.meta_key='remote_invoice_id'";

		if( $countOnly )
		{
			$sql['select'] = "SELECT COUNT(o.id)";
		}
		else
		{
			$sql['select'] = <<<EOT
SELECT o.id, o.id as supplierorder_id, o.status, o.order_date, o.supplier_contact, paid.meta_value as order_paid,
inv_i.meta_value as remote_invoice_id, inv_d.meta_value as remote_invoice_date, o.supplier_name,
SUM(i.amount) as amount, TRIM(MAX(a.meta_value)) as invoice
EOT;
			$sql['join'] .= <<<EOT
LEFT JOIN {$wpdb->lws_woosupply_supplierorderitem} as i ON o.id=i.order_id
LEFT JOIN {$wpdb->lws_woosupply_supplierordermeta} as paid ON o.id=paid.lws_woosupply_supplierorder_id AND paid.meta_key='order_paid'
LEFT JOIN {$wpdb->lws_woosupply_supplierordermeta} as inv_d ON o.id=inv_d.lws_woosupply_supplierorder_id AND inv_d.meta_key='remote_invoice_date'
LEFT JOIN {$wpdb->lws_woosupply_supplierordermeta} as a ON o.id=a.lws_woosupply_supplierorder_id AND a.meta_key='remote_invoice_amount'
EOT;
			$sql['group'] = "\nGROUP BY o.id, paid.meta_value";
			$sql['order'] = "\nORDER BY o.id DESC";
		}

		$statusList = array();
		if(isset($_GET['solStatus']) && !empty(trim($_GET['solStatus'])))
		{
			$values = array_intersect(explode('|', $_GET['solStatus']), array_keys($this->getStatusList()));
			foreach( $values as $status )
			{
				if( !empty($status = \sanitize_key($status)) )
					$statusList[] = $wpdb->prepare("%s", $status);
			}
		}
		if( empty($statusList) )
		{
			foreach( $this->getStatusList() as $status => $label )
				$statusList[] = $wpdb->prepare("%s", $status);
		}
		$sql['where'] = "WHERE o.status IN (".implode(',', $statusList).")";

		if(isset($_GET['solSearch']) && !empty(trim($_GET['solSearch'])))
		{
			$fields = array('o.id', 's.name', 's.address_city', 'o.supplier_contact', 'o.supplier_name', 'inv_i.meta_value', 'o.delivery_name');

			foreach( explode(',', $_GET['solSearch']) as $search )
			{
				$search = trim($search);
				$searches = array();
				foreach($fields as $f)
					$searches[] = $wpdb->prepare("$f LIKE %s", "%$search%");
				$searches[] = $wpdb->prepare('%s LIKE CONCAT("%", o.id)', $search);

				$sql['where'] .= " AND (".implode(' OR ', $searches).")";
			}
		}

		$sql = \apply_filters('lws_woosupply_order_list_read_query', $sql, $countOnly);

		if(!is_null($limit))
			$sql[] = $limit->toMysql();
		return implode(' ', $sql);
	}

	private function getOrderActions($orderId, $status, $order)
	{
		$links = array();
		if( !isset($this->ajaxUrl) )
			$this->ajaxUrl = esc_attr(admin_url('/admin-ajax.php'));
		if( !isset($this->canChangeStatus) )
			$this->canChangeStatus = \current_user_can(\apply_filters('lws_woosupply_order_change_status_capability', 'manage_purchases'));
		if( !isset($this->canPay) )
			$this->canPay = \current_user_can(\apply_filters('lws_woosupply_order_change_paid_capability', 'manage_purchases'));

		if( $this->canChangeStatus && \lws_ws()->cmpOrderStatus($status, 'ws_received') < 0 ) // status update quick access (managed by ajax)
		{
				$label = _x("Receive", "Order Quick Action - Set received", 'woosupply-lite');
				$title = esc_attr(_x("Apply 'Received' state on this order. Manage stocks.", "Order Quick Action Title - Set received", 'woosupply-lite'));
			$links[] = "<div data-ajax='{$this->ajaxUrl}' data-action='lws_woosupply_order_action_set_received' data-order='$orderId' title='$title' class='lws_woosupply_order_action lws-woosupply-order-action-received lws-icon-btn' style='--btn-color: #047432'><div class='icon lws-icon lws-icon-delivery'></div><div class='text'>$label</div></div> ";
		}

		if( $this->canPay && empty($order['order_paid']) )
		{
			$label = _x("Payment", "Order Quick Action - Set as paid", 'woosupply-lite');
			$title = esc_attr(_x("Mark this order as paid.", "Order Quick Action Title - Set as paid", 'woosupply-lite'));
			$links[] = "<div data-ajax='{$this->ajaxUrl}' data-action='lws_woosupply_order_action_set_paid' data-order='$orderId' title='$title' class='lws_woosupply_order_action lws_woosupply_order_action_paid_set lws-woosupply-order-action-paid-set lws-icon-btn' style='--btn-color: #777787'><div class='icon lws-icon lws-icon-coins'></div><div class='text'>$label</div></div> ";
		}

		if( $this->canChangeStatus && \lws_ws()->cmpOrderStatus($status, 'ws_complete') < 0 ) // status update quick access (managed by ajax)
		{
			$label = _x("Complete", "Order Quick Action - Set in_progress", 'woosupply-lite');
			$title = esc_attr(_x("Apply 'Complete' state on this order.", "Order Quick Action Title - Set in_progress", 'woosupply-lite'));
			$links[] = "<div data-ajax='{$this->ajaxUrl}' data-action='lws_woosupply_order_action_set_complete' data-order='$orderId' title='$title' class='lws_woosupply_order_action lws-woosupply-order-action-complete lws-icon-btn' style='--btn-color: #016087'><div class='icon lws-icon lws-icon-checkmark'></div><div class='text'>$label</div></div> ";
		}

		$retour = "<div class='lws-woosupply-actions-cell'>".implode('', apply_filters('lws_woosupply_order_actions', $links))."</div>";
		return $retour;
	}

	private function getPdfButton($orderId)
	{
		if( !isset($this->canSeePDF) )
			$this->canSeePDF = \current_user_can(\apply_filters('lws_woosupply_order_to_pdf_capability', 'view_purchases'));

		if( $this->canSeePDF )
		{
			$pdfUrl = esc_attr(add_query_arg(array('action' => 'lws_woosupply_order_pdf', 'supplierorder_id' => $orderId), admin_url('/admin-ajax.php')));
			$pdfLabel = _x("PDF", "Order edition pdf button", 'woosupply-lite');
			return "<a class='lws-icon-btn' href='$pdfUrl' target='_blank'  style='--btn-color:#bb1d25'><div class='icon lws-icon lws-icon-pdf'></div><div class='text'>$pdfLabel</div></a>";
			//return "<a href='$pdfUrl' target='_blank'><div  class='lws-woosupply-order-action-pdf lws-icon lws-icon-pdf'><span class='lws-woosupply-order-pdf-text'>$pdfLabel</span></div></a>";
		}
		return '';
	}
}
?>
