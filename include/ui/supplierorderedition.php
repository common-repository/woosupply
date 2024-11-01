<?php

namespace LWS\WOOSUPPLY;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class SupplierOrderEdition
{
	public static function instance()
	{
		static $instance = false;
		if (!$instance)
		{
			$instance = new self();
		}
		return $instance;
	}

	function __construct()
	{
		\add_filter('lws_woosupply_supplierorder_form_product_id_get', array($this, 'saveProduct'), 1000, 4);
		\add_filter('lws_woosupply_supplierorder_form_supplier_id_get', array($this, 'saveSupplier'), 1000, 2);
		\add_action('lws_adminpanel_singular_buttons_lws_woosupply_supplierorder', array($this, 'buttonPDF'), 100, 2);
		\add_action('lws_adminpanel_singular_form_lws_woosupply_supplierorder', array($this, 'addProductsBlock'), 10, 1);
		\add_filter('lws_adminpanel_singular_form_attributes_lws_woosupply_supplierorder', function ($attrs)
		{
			$attrs['enctype'] = 'multipart/form-data';
			return $attrs;
		});
		\add_filter('lws_adminpanel_singular_boxes_lws_woosupply_supplierorder', array($this, 'addSupplierOrderBoxes'), 10, 2);
		\add_filter('lws_adminpanel_singular_box_content_lws_woosupply_supplierorder_box_invoice', array($this, 'addSupplierOrderInvoiceBox'), 1000, 2);
	}

	/** @return string a pdf download button to order edit page. */
	public function buttonPDF($content, $orderId)
	{
		if (!empty($orderId) && \current_user_can(\apply_filters('lws_woosupply_order_to_pdf_capability', 'manage_purchases')))
		{
			$pdfUrl = esc_attr(add_query_arg(array('action' => 'lws_woosupply_order_pdf', 'supplierorder_id' => $orderId), admin_url('/admin-ajax.php')));
			$pdfLabel = _x("Order PDF", "Order edition pdf button", 'woosupply-lite');
			$content .= "<br/><a class='lws-icon-btn' href='$pdfUrl' target='_blank'  style='--btn-color:#bb1d25'><div class='icon lws-icon lws-icon-pdf'></div><div class='text'>$pdfLabel</div></a>";
		}
		return $content;
	}

	private function getValidProperties($locked)
	{
		if (!isset($this->validProperties))
		{
			$this->validProperties =  array(
				'order_remote_invoice_id'        => array('default' => '', 'label' => __('Supplier invoice id', 'woosupply-lite'), 'format' => 't'),
				'order_remote_invoice_date'      => array('default' => '', 'label' => __('Supplier invoice date', 'woosupply-lite'), 'format' => 't'),
				'order_remote_invoice_amount'    => array('default' => '', 'label' => __('Supplier invoice amount', 'woosupply-lite'), 'format' => 't'),
				'order_remote_invoice_paid'      => array('default' => '', 'label' => __('Is Order paid', 'woosupply-lite'), 'format' => '/(on)?/'),
				'order_remote_invoice_paid_date' => array('default' => '', 'label' => __('Order payment date', 'woosupply-lite'), 'format' => 't'),
			);

			if (!$locked)
			{
				$this->validProperties = array_merge(
					$this->validProperties,
					array(
						'order_status'                   => array('required' => true, 'label' => __('Status', 'woosupply-lite'), 'format' => 'k'),
						'order_supplier_id'              => array('required' => true, 'label' => __('Supplier', 'woosupply-lite'), 'format' => 'k'),
						'order_supplier_name'            => array('required' => true, 'label' => __('Supplier name', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_name'            => array('required' => true, 'label' => __('Delivery name', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_address'         => array('required' => true, 'label' => __('Delivery address', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_address_2'       => array('default' => '', 'label' => __('Delivery address second line', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_address_city'    => array('required' => true, 'label' => __('Delivery city', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_address_country' => array('required' => true, 'label' => __('Delivery country', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_address_state'   => array('default' => '', 'label' => __('Delivery state', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_address_zipcode' => array('required' => true, 'label' => __('Delivery zipcode', 'woosupply-lite'), 'format' => 't'),
						'order_delivery_tax_number'      => array('default' => '', 'label' => __('Delivery tax number', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_contact'         => array('default' => '', 'label' => __('Supplier contact', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_tax_number'      => array('default' => '', 'label' => __('Supplier tax number', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_email'           => array('default' => '', 'label' => __('Supplier email', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_address'         => array('default' => '', 'label' => __('Supplier address', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_address_2'       => array('default' => '', 'label' => __('Supplier address second line', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_address_zipcode' => array('default' => '', 'label' => __('Supplier zipcode', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_address_city'    => array('default' => '', 'label' => __('Supplier city', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_address_country' => array('default' => '', 'label' => __('Supplier country', 'woosupply-lite'), 'format' => 't'),
						'order_supplier_address_state'   => array('default' => '', 'label' => __('Supplier state', 'woosupply-lite'), 'format' => 't'),
						'order_date'                     => array('default' => '', 'label' => __('Date created', 'woosupply-lite'), 'format' => 't')
					)
				);
			}
		}
		return $this->validProperties;
	}

	/**	Checks if required fields are filled and valid.
	 * @return bool true if ok / false if some required field empty. */
	private function isValidPost($order)
	{
		if (!isset($_POST['order_status']) || !array_key_exists(sanitize_key($_POST['order_status']), SupplierOrder::statusList()))
		{
			\lws_admin_add_notice_once('singular_edit', __("Invalid order status.", 'woosupply-lite'), array('level' => 'error'));
			return false;
		}

		$locked = in_array($order->getStatus(), SupplierOrder::lockStatusList());
		$args = \apply_filters('lws_adminpanel_post_parse_opt', array(), $this->getValidProperties($locked));
		if (!$args['valid'])
		{
			\lws_admin_add_notice_once('singular_edit', $args['error'], array('level' => 'error'));
			return false;
		}
		else
			$this->_post = $args['values'];

		if (!$locked)
		{
			if (isset($_POST['order_remote_invoice_date']) && !empty($_POST['order_remote_invoice_date']) && empty(date_create($_POST['order_remote_invoice_date'])))
			{
				// not required, warn only
				\lws_admin_add_notice_once('supplierorder_warning', __("Invalid invoice date format.", 'woosupply-lite'), array('level' => 'warning'));
			}

			if (isset($_POST['order_items_key']))
			{
				if (!is_array($_POST['order_items_key']))
				{
					\lws_admin_add_notice_once('singular_edit', __("Invalid form.", 'woosupply-lite'), array('level' => 'error'));
					return false;
				}

				$minc = count($_POST['order_items_key']);
				foreach (array('product', 'comments', 'supplierRef', 'quantity', 'unitPrice', 'amount') as $k)
				{
					$index = 'order_items_' . $k;
					if (!(isset($_POST[$index]) && is_array($_POST[$index]) && $minc <= count($_POST[$index])))
					{
						\lws_admin_add_notice_once('singular_edit', __("Invalid Data Format.", 'woosupply-lite'), array('level' => 'error'));
						return false;
					}
				}
			}
		}
		return apply_filters('lws_woosupply_supplierorder_form_is_valid', true, $order);
	}

	/**	Create or update supplier
	 *	@return (int|Supplier) supplier id or Supplier instance. */
	public function saveSupplier($supplierId, $order = null)
	{
		if (empty($supplierId))
		{
			// create a new supplier
			require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';
			$supplier = Supplier::create();

			$supplier->name = $this->_post['order_supplier_name'];
			foreach (array('email', 'contact', 'address', 'address_2', 'address_zipcode', 'address_city', 'address_country', 'address_state', 'phone_number', 'fax_number', 'tax_number') as $prop)
			{
				if ($prop == 'contact') // split in firstname/lastname
				{
					$contact = explode(






















						' ',
						$this->_post['order_supplier_' . $prop],
						2
					);
					if (count($contact) > 1
					)
					{
						$supplier->contact_firstname = $contact[0];
						$supplier->contact_lastname = $contact[1];
					}
					else
						$supplier->contact_lastname = $contact[0];
				}
				else
					$supplier->$prop = $this->_post['order_supplier_' . $prop];
			}

			$supplier = \apply_filters('lws_woosupply_supplierorder_form_before_supplier_update', $supplier);
			$supplier->update();
			return $supplier;
		}
		return $supplierId;
	}

	/**	Create or update supplier
	 * @param $supplierProductId,
	 * @param $orderItemProductId (string) request id, could be a SupplierProduct.id or a WP_Post.ID prefixed by 'wc_'.
	 * @param $item (SupplierOrderItem)
	 * @param $order (SupplierOrder)
	 * @return (int|SupplierProduct) supplier id. */
	public function saveProduct($supplierProductId, $orderItemProductId, $item, $order)
	{
		if (empty($supplierProductId))
		{
			if (substr($orderItemProductId, 0, 3) == 'wc_')
			{
				$post_id = intval(substr($orderItemProductId, 3));
				// do we have a SupplierProduct, whatever the supplier (no link in this version anyway)?
				global $wpdb;
				$sp_id = $wpdb->get_var($wpdb->prepare("SELECT lws_woosupply_supplierproduct_id FROM {$wpdb->lws_woosupply_supplierproductmeta} WHERE meta_key='wc_product_id' AND meta_value=%s", $post_id));
				if (!empty($sp_id))
				{
					$supplierProductId = intval($sp_id);
				}
				else // creation
				{
					require_once LWS_WOOSUPPLY_INCLUDES . '/supplierproduct.php';
					$product = SupplierProduct::create();
					$product->name = \get_the_title($post_id);
					$product = \apply_filters('lws_woosupply_supplierorder_form_before_product_update', $product, $post_id, $item, $order);
					$product->update();
					$product->updateMeta('wc_product_id', $post_id);
					return $product;
				}
			}
			else if (is_numeric($orderItemProductId))
				$supplierProductId = intval($orderItemProductId);
		}
		return $supplierProductId;
	}

	/**
	 * saves supplier datas using $id if provided, new if not
	 * @param string $id the record id of supplier
	 * @return string saved id if all saved, false if something went wrong
	 */
	public function save($id)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';
		$order = empty($id) ? SupplierOrder::create() : SupplierOrder::get($id);
		if (empty($order))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Cannot found the supplier order <b>%s</b> to update it.", 'woosupply-lite'), $id), array('level' => 'error'));
			return 0;
		}

		if (!$this->isValidPost($order))
			return 0;

		if (!in_array($order->getStatus(), SupplierOrder::lockStatusList()))
			$this->saveUnlocked($order, $id);

		$this->saveAnyway($order, $id);

		return $order->getId();
	}

	private function saveUnlocked(&$order, $id)
	{
		// order values
		$order->setDate($this->_post['order_date']);

		// delivery
		foreach (array('delivery_name', 'delivery_tax_number', 'delivery_address', 'delivery_address_2', 'delivery_address_city', 'delivery_address_country', 'delivery_address_state', 'delivery_address_zipcode') as $prop)
		{
			$index = 'order_' . $prop;
			$order->$prop = $this->_post[$index];
		}

		// order supplier info
		foreach (array('supplier_name', 'supplier_email', 'supplier_contact', 'supplier_tax_number', 'supplier_address', 'supplier_address_2', 'supplier_address_zipcode', 'supplier_address_city', 'supplier_address_country', 'supplier_address_state') as $prop)
		{
			$index = 'order_' . $prop;
			$order->$prop = $this->_post[$index];
		}
		/** let a filter get or create Supplier and return the id or instance.
		 * If a Supplier creation is requested, look at $_POST content for data.
		 * @return a valid Supplier.id or Supplier instance.
		 * @param Supplier.id (default is 0)  or Supplier instance.
		 * @param SupplierOrder instance. */
		$order->setSupplier(\apply_filters('lws_woosupply_supplierorder_form_supplier_id_get', intval($this->_post['order_supplier_id']), $order));

		// del items
		$itemKeys = isset($_POST['order_items_key']) ? $_POST['order_items_key'] : array();
		foreach ($order->getItems() as $item)
		{
			if (!in_array($item->item_key, $itemKeys))
				$order->popItem($item->item_key)->delete();
		}

		// add/update items
		for ($i = 0; $i < count($itemKeys); ++$i)
		{
			if (\apply_filters('lws_woosupply_supplierorder_form_valid_item_row', !empty(trim($_POST['order_items_product'][$i]))))
			{
				$itemKeys[$i] = $this->saveItem($i, $itemKeys[$i], $order);
			}
		}

		/** at this time, we cannot ensure order exists in database and have an id. */
		$order = \apply_filters('lws_woosupply_supplierorder_form_before_unlocked_update', $order);
		if (!$order->update())
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Error occured during the supplier order #%s update.", 'woosupply-lite'), $id), array('level' => 'error'));
			return 0;
		}

		// set item meta
		for ($i = 0; $i < count($itemKeys); ++$i)
		{
			if (!empty($item = $order->getItem($itemKeys[$i])))
			{
				$item->updateMeta('comments', stripslashes(trim($_POST['order_items_comments'][$i])));

				if ($item->hasData('product_name_breach'))
					$item->updateMeta('product_name_breach', $item->getData('product_name_breach'));
				else
					$item->deleteMeta('product_name_breach');
				if ($item->hasData('product_id_breach'))
					$item->updateMeta('product_id_breach', $item->getData('product_id_breach'));
				else
					$item->deleteMeta('product_id_breach');
			}
		}

		$order->addMeta('order_currency', lws_ws()->getCurrentCurrency(), true);
	}

	/** @return the item key. */
	private function saveItem($index, $key, &$order)
	{
		if (empty($item = $order->popItem($key)))
			$item = SupplierOrderItem::create();

		$item->product_id = 0;
		$item->supplier_reference = \sanitize_text_field(stripslashes($_POST['order_items_supplierRef'][$index]));
		$item->quantity = lws_ws()->getRawQuantity($_POST['order_items_quantity'][$index]);
		$item->unit_price = lws_ws()->getRawPrice($_POST['order_items_unitPrice'][$index]);
		$item->amount = $item->quantity * $item->unit_price; // not read from form but recomptued to be sure.

		if ($_POST['order_items_product'][$index] == 'shipping')
		{
			$item->setData('product_name_breach', _x("Shipping", "shipping item denomination", 'woosupply-lite'), true);
			$item->setData('product_id_breach', 'shipping', true);
		}
		else if ($item->hasData('product_name_breach'))
		{
			$item->setData('product_name_breach_backup', $item->getData('product_name_breach'), true);
			$item->setData('product_id_breach_backup', $item->getData('product_id_breach'), true);
			$item->removeData('product_name_breach');
			$item->removeData('product_id_breach');
		}

		/** let a filter get or create SupplierProduct and return the id.
		 * @return a valid SupplierProduct.id.
		 * @param SupplierProduct.id (default is 0).
		 * @param (string) Required id, could reference a SupplierProduct or a WP_Post (eq. WC_Product). In second case, id is prefixed by 'wc_'
		 * @param SupplierOrderItem instance (could have a null id if new)
		 * @param SupplierOrder instance  (could have a null id if new)
		 * Supplier is already saved, then can be get from order @see SupplierOrder::getSupplier(). */
		$item->setProduct(\apply_filters('lws_woosupply_supplierorder_form_product_id_get', 0, \sanitize_text_field($_POST['order_items_product'][$index]), $item, $order));

		return $order->pushItem($item)->item_key;
	}

	/**	now order exists in db for sure. */
	private function saveAnyway(&$order, $id)
	{
		$order->addManagedMeta('remote_invoice_id');
		$order->setData('remote_invoice_id', $this->_post['order_remote_invoice_id'], true, true);

		$order->addManagedMeta('remote_invoice_amount');
		$value = $this->_post['order_remote_invoice_amount'];
		$order->setData('remote_invoice_amount', strlen($value) ? \lws_ws()->getRawPrice($value) : '', true, true);

		$order->addManagedMeta('remote_invoice_date');
		$order->setDate($this->_post['order_remote_invoice_date'], 'remote_invoice_date', true, false, true);

		// set invoice payement date
		$order->addManagedMeta('order_paid');
		if ($this->_post['order_remote_invoice_paid'] == 'on')
		{
			$date = \date_create($this->_post['order_remote_invoice_paid_date']); // override
			if (empty($date))
			{
				$date = \date_create($order->getMeta('order_paid', true)); // last value
				if (empty($date))
					$date = \date_create(); // default: today
			}
			$order->setData('order_paid', $date->format('Y-m-d'), true);
		}
		else
			$order->deleteMeta('order_paid');

		// save invoice doc
		$fkey = 'order_remote_invoice_attachement';
		if (isset($_FILES[$fkey]) && !empty($_FILES[$fkey]) && !empty($_FILES[$fkey]['tmp_name']))
		{
			$filename = $_FILES[$fkey]['name'];
			if (empty($_FILES[$fkey]['error']))
			{
				$order->addManagedMeta(array('invoice_document', 'invoice_document_name'));
				$this->removeOldFile($order, 'invoice_document');
				if (!empty($filepath = \lws_ws()->uploadFile($_FILES[$fkey], 'invoices', $filename, true)))
				{
					$order->setData('invoice_document', $filepath, true);
					$order->setData('invoice_document_name', $filename, true);
					\lws_admin_add_notice_once('invoice_attachment', sprintf(__("An invoice document (%s) has been uploaded.", 'woosupply-lite'), $filename), array('level' => 'info'));
				}
				else
					\lws_admin_add_notice_once('invoice_attachment', sprintf(__("The invoice document (%s) cannot be saved on the server.", 'woosupply-lite'), $filename), array('level' => 'error'));
			}
			else
				\lws_admin_add_notice_once('invoice_attachment', sprintf(__("The invoice document (%s) cannot be uploaded.", 'woosupply-lite'), $filename), array('level' => 'error'));
		}

		// finally, update the status
		$status = \sanitize_key($_POST['order_status']);
		if ($status != ($lastStatus = $order->getStatus()))
			$order->setStatus($status);

		$order = \apply_filters('lws_woosupply_supplierorder_form_before_update', $order, $lastStatus);
		if (!$order->update())
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Error occured during the supplier order #%s update.", 'woosupply-lite'), $id), array('level' => 'error'));
			return 0;
		}
	}

	private function removeOldFile(&$order, $meta_key)
	{
		$old = $order->getMeta($meta_key, true);
		if (!empty($old))
		{
			$upload_dir = wp_upload_dir();
			$prefix = \trailingslashit($upload_dir['basedir']) . 'woosupply_uploads/';

			if (file_exists($old) && substr($old, 0, strlen($prefix)) == $prefix)
			{
				global $wpdb;
				$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(meta_id) FROM {$wpdb->lws_woosupply_supplierorder}meta WHERE meta_key='invoice_document' AND meta_value=%s", $old));
				if ($exists <= 1)
				{
					if (!unlink($old))
						error_log("Cannot free old file : " . $old);
				}
			}
			$order->deleteMeta('invoice_document');
		}
	}

	/**
	 * asks to delete a record
	 * @param string $id order record id
	 * @return bool true if deleted, false if not
	 */
	public function delete($id)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';
		$order = SupplierOrder::get($id);
		if (empty($order))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Cannot found the supplier order <b>%s</b>.", 'woosupply-lite'), $id), array('level' => 'warning'));
			return false;
		}
		if (empty($order->delete()))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("An error occured during the supplier order <b>%s</b> deletion.", 'woosupply-lite'), $id), array('level' => 'error'));
			return false;
		}
		return true;
	}

	/**
	 * Show either order form or notice depending on $id
	 * @param string $id supplier record id
	 * @return bool true if $id found, false if not
	 */
	public function show($id = 0)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';
		$order = empty($id) ? SupplierOrder::create() : SupplierOrder::get($id);
		$this->order = $order;
		if (empty($order))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Cannot load the supplier order <b>%s</b>.", 'woosupply-lite'), $id), array('level' => 'warning'));
			return false;
		}

		$this->displayLocked = in_array($order->getStatus(), SupplierOrder::lockStatusList());
		$this->inputLocked = ($this->displayLocked) ? 'disabled="disabled" ' : '';
		$this->iconHidden = ($this->displayLocked) ? "style='display: none'" : '';

		$this->echoForm($order);
		\wp_register_script('lws-woosupply-supplierorder-edit', LWS_WOOSUPPLY_JS . '/supplierorder.js', array('jquery', 'lws-base64', 'lws-tools'), LWS_WOOSUPPLY_VERSION, true);
		\wp_localize_script('lws-woosupply-supplierorder-edit', 'lws_woosupply_price_format', \lws_ws()->getWCArgs());
		\wp_enqueue_script('lws-woosupply-supplierorder-edit');
		\do_action('lws_adminpanel_enqueue_lac_scripts', array('select', 'input'));
		return true;
	}

	/**
	 * echoes the form with supplier order datas
	 * @param SupplierOrder object, empty or not, depends on previous load
	 */
	private function echoForm($order)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/countrystatefield.php';
		\LWS\WOOSUPPLY\CountryStateField::enqueueScript();

		$title = empty($order->getId()) ? __("NEW ORDER", 'woosupply-lite') : sprintf(__("ORDER %s", 'woosupply-lite'), $order->getLabel());
		$str = "<h1 class='lws-woosupply-title'>$title</h1>";

		$str .= "<div class='singular-edit-grid'>";
		$str .= $this->orderInfo($order);
		$str .= $this->supplierInfo($order);
		$str .= $this->deliveryInfo($order);
		$str .= "</div>";
		echo $str;
	}

	public function addProductsBlock($id)
	{
		$order = empty($id) ? SupplierOrder::create() : SupplierOrder::get($id);
		$str = "<div class='singular-box'>";
		$str .= $this->orderDetails($order);
		$str .= "</div>";
		echo $str;
	}

	private function orderInfo($order)
	{
		$labels = array(
			'general' => __("General", 'woosupply-lite'),
			'date' => __("Date created", 'woosupply-lite'),
			'status' => __("Status", 'woosupply-lite'),
			'supplier' => __("Supplier", 'woosupply-lite')
		);

		$str = "<div class='grid-item'>";
		$str .= "<div class='grid-item-title'>{$labels['general']}</div>";

		$date = $order->getDate();
		$str .= "<div class='lws-woosupply-label'>{$labels['date']}</div><div class='lws-woosupply-input'>";
		$str .= "<input class='lws-input' type='date' name='order_date' value='" . esc_attr($date) . "' {$this->inputLocked}/>";
		$str .= "</div>";

		$str .= "<div class='lws-woosupply-label bold-label'>{$labels['status']}</div>";
		$str .= "<div class='lws-woosupply-input'><select name='order_status' id='order_status' class='lac_select' data-mode='select'>";
		foreach (SupplierOrder::statusList() as $value => $text)
		{
			$selected = ($value == $order->getStatus() ? " selected='selected'" : '');
			$str .= "<option$selected value='$value'>$text</option>";
		}
		$str .= "</select></div>";

		$str .= "<div class='grid-item-title'>{$labels['supplier']}</div>";

		$supplier = $order->getSupplier(true);
		$supId = '';
		$source = '';
		if (!empty($supplier->getId()))
		{
			$supId = esc_attr($supplier->getId());
			$source = array($supplier->getId() => array('value' => $supplier->getId(), 'label' => $supplier->name));
			$source = \esc_attr(base64_encode(json_encode($source)));
		}
		//$ajax = esc_attr(admin_url('/admin-ajax.php'));
		$str .= "<div class='lws-woosupply-label bold-label'>{$labels['supplier']}</div>";
		$str .= "<div class='lws-woosupply-input'><input class='lac_select' data-name='order_supplier_name' data-source='$source' data-ajax='lws_woosupply_supplier_list' id='order_supplier_id' name='order_supplier_id' value='{$supId}' required {$this->inputLocked}/>";
		$str .= "</div>";

		$str .= "</div>";
		return $str;
	}

	/** @return (string) HTML code for supplier address inputs. */
	private function supplierInfo($order)
	{
		$labels = array(
			'title'     => __("Supplier Info", 'woosupply-lite'),
			'addrtitle' => __("Supplier Address", 'woosupply-lite'),
			'new'       => __("New", 'woosupply-lite'),
			'edit'      => __("Edit", 'woosupply-lite'),
			'name'      => __("Name", 'woosupply-lite'),
			'email'     => __("Email", 'woosupply-lite'),
			'contact'   => __("Contact", 'woosupply-lite'),
			'addr1'     => __("Address line 1", 'woosupply-lite'),
			'addr2'     => __("Address line 2", 'woosupply-lite'),
			'city'      => __("City", 'woosupply-lite'),
			'country'   => __("Country", 'woosupply-lite'),
			'state'     => __("State", 'woosupply-lite'),
			'zip'       => __("Postcode / ZIP", 'woosupply-lite'),
			'phone'     => __("Phone", 'woosupply-lite'),
			'fax'       => __("Fax", 'woosupply-lite'),
			'tax'       => _x("VAT Number", "https://fr.wikipedia.org/wiki/Code_Insee#Num%C3%A9ro_de_TVA_intracommunautaire", 'woosupply-lite')
		);
		$url = array(
			'new'  => \esc_attr(add_query_arg(array('page' => 'lws_woosupply_supplier', 'supplier_id' => ''), admin_url('admin.php'))),
			'edit' => \esc_attr(add_query_arg(array('page' => 'lws_woosupply_supplier', 'supplier_id' => empty($order->supplierId) ? '0' : intval($order->supplierId)), admin_url('admin.php')))
		);

		$supplier = $order->getSupplier(true);
		$source = array($supplier->getId() => array('value' => $supplier->getId(), 'label' => $supplier->name));
		$source = \esc_attr(base64_encode(json_encode($source)));
		$service = esc_attr(\add_query_arg('action', 'lws_woosupply_order_details_format', admin_url('/admin-ajax.php')));

		// Details
		$inputs = \LWS\WOOSUPPLY\CountryStateField::getInputs(array(
			'country_name'    => 'order_supplier_address_country',
			'country_value'   => $order->supplier_address_country,
			'country_after'    => '</div>',
			'state_before'    => '<div class="lws-woosupply-label">' . $labels['state'] . '</div><div class="lws-woosupply-input lws_state_line_removable">',
			'state_name'      => 'order_supplier_address_state',
			'state_value'     => $order->supplier_address_state,
			'state_after'    => '</div>',
			'enqueue_scripts' => false,
			'required'        => false,
			'disabled'        => $this->displayLocked
		));

		ob_start();
?>
		<div class='grid-item'>
			<div class='grid-item-title'><?= $labels['title'] ?>
				<div id='supplier_edit' class='grid-item-iconbtn lws-icon lws-icon-pencil' <?= $this->iconHidden ?>></div>
			</div>

			<div id='supplier-info-div' class='lws-woosupply-showform info-div'>
				<div class='lws-woosupply-showform-line lws-bold' data-name='order_supplier_name'><?= $order->supplier_name ?></div>
				<div class='lws-woosupply-showform-line' data-name='order_supplier_contact'><?= $order->supplier_contact ?></div>
				<div class='lws-woosupply-showform-line' data-name='order_supplier_address'><?= $order->supplier_address ?></div>
				<div class='lws-woosupply-showform-line' data-name='order_supplier_address_2'><?= $order->supplier_address_2 ?></div>
				<span class='lws-woosupply-showform-line' data-name='order_supplier_address_zipcode'><?= $order->supplier_address_zipcode ?></span> <span class='lws-woosupply-showform-line' data-name='order_supplier_address_city'><?= $order->supplier_address_city ?></span>
				<br />
				<span class='lws-woosupply-showform-line' data-name='order_supplier_address_country_name'><?= \lws_ws()->getCountryState()->getCountryByCode($order->supplier_address_country) ?></span>
				<span class='lws-woosupply-showform-line' data-name='order_supplier_address_state_name'><?= \lws_ws()->getCountryState()->getStateByCodes($order->supplier_address_country, $order->supplier_address_state) ?></span>
				<br /><br />
				<div class='lws-woosupply-showform-title'><?= $labels['email'] ?> :</div>
				<div class='lws-woosupply-showform-line' data-name='order_supplier_email'><?= $order->supplier_email ?></div>
				<br />
				<div class='lws-woosupply-showform-title'><?= $labels['tax'] ?> :</div>
				<div class='lws-woosupply-showform-line' data-name='order_supplier_tax_number'><?= $order->supplier_tax_number ?></div>
			</div>

			<!-- fieldset with ajax data loading from supplierorder.js -->
			<fieldset id='lws_woosupply_supplier_details' class='fieldset-grid' data-service='<?= $service ?>'>
					<div class='lws-woosupply-label'><?= $labels['name'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input lws-required' type='text' name='order_supplier_name' value='<?= esc_attr($order->supplier_name) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['contact'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_supplier_contact' value='<?= esc_attr($order->supplier_contact) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['email'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='email' name='order_supplier_email' value='<?= esc_attr($order->supplier_email) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['tax'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_supplier_tax_number' value='<?= esc_attr($order->supplier_tax_number) ?>' <?= $this->inputLocked ?> />
					</div>

					<div class='grid-item-title'><?= $labels['addrtitle'] ?></div>
					<div class='lws-woosupply-label'><?= $labels['addr1'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_supplier_address' value='<?= esc_attr($order->supplier_address) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['addr2'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_supplier_address_2' value='<?= esc_attr($order->supplier_address_2) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['city'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_supplier_address_city' value='<?= esc_attr($order->supplier_address_city) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['country'] ?></div>
					<div class='lws-woosupply-input'>
						<?= $inputs ?>
					<div class='lws-woosupply-label'><?= $labels['zip'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_supplier_address_zipcode' value='<?= esc_attr($order->supplier_address_zipcode) ?>' <?= $this->inputLocked ?> />
					</div>
			</fieldset>
		</div>
	<?php
		return ob_get_clean();
	}

	/** @return (string) HTML code for delivery address inputs. */
	private function deliveryInfo($order)
	{
		$address = empty($order->getId()) ? SupplierOrder::getDefaultDeliveryAddress() : $order;
		$labels = array(
			'title' => __("Delivery Address", 'woosupply-lite'),
			'name'  => __("Company name", 'woosupply-lite'),
			'addr1' => __("Address line 1", 'woosupply-lite'),
			'addr2' => __("Address line 2", 'woosupply-lite'),
			'city'  => __("City", 'woosupply-lite'),
			'country'   => __("Country", 'woosupply-lite'),
			'state'     => __("State", 'woosupply-lite'),
			'zip'   => __("Postcode / ZIP", 'woosupply-lite'),
			'tax'   => _x("Tax Number", "https://fr.wikipedia.org/wiki/Code_Insee#Num%C3%A9ro_de_TVA_intracommunautaire", 'woosupply-lite')
		);
		$inputs = \LWS\WOOSUPPLY\CountryStateField::getInputs(array(
			'country_name'    => 'order_delivery_address_country',
			'country_value'   => $address->delivery_address_country,
			'country_after'    => '</div>',
			'state_before'    => '<div class="lws-woosupply-label">' . $labels['state'] . '</div><div class="lws-woosupply-input lws_state_line_removable">',
			'state_name'      => 'order_delivery_address_state',
			'state_value'     => $address->delivery_address_state,
			'state_after'    => '</div>',
			'enqueue_scripts' => false,
			'disabled'        => $this->displayLocked
		));

		ob_start();
	?>
		<div class='grid-item'>
			<div class='grid-item-title'><?= $labels['title'] ?>
				<div id='delivery_edit' class='grid-item-iconbtn lws-icon lws-icon-pencil' <?= $this->iconHidden ?>></div>
			</div>

			<div id='delivery-info-div' class='lws-woosupply-showform info-div'>
				<div class='lws-woosupply-showform-line lws-bold' data-name='order_delivery_name'><?= $address->delivery_name ?></div>
				<div class='lws-woosupply-showform-line' data-name='order_delivery_address'><?= $address->delivery_address ?></div>
				<div class='lws-woosupply-showform-line' data-name='order_delivery_address_2'><?= $address->delivery_address_2 ?></div>
				<span class='lws-woosupply-showform-line' data-name='order_delivery_address_zipcode'><?= $address->delivery_address_zipcode ?></span> <span class='lws-woosupply-showform-line' data-name='order_delivery_address_city'><?= $address->delivery_address_city ?></span>
				<br />
				<span class='lws-woosupply-showform-line' data-name='order_delivery_address_country_name'><?= \lws_ws()->getCountryState()->getCountryByCode($address->delivery_address_country) ?></span>
				<span class='lws-woosupply-showform-line' data-name='order_delivery_address_state_name'><?= \lws_ws()->getCountryState()->getStateByCodes($address->delivery_address_country, $address->delivery_address_state) ?></span>
				<br /><br />
				<div class='lws-woosupply-showform-title'><?= $labels['tax'] ?> :</div>
				<div class='lws-woosupply-showform-line' data-name='order_delivery_tax_number'><?= $address->delivery_tax_number ?></div>
			</div>

			<!-- fieldset with ajax data loading from supplierorder.js -->
			<fieldset id='lws_woosupply_delivery_details' class='fieldset-grid'>
					<div class='lws-woosupply-label'><?= $labels['name'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input lws-required' type='text' name='order_delivery_name' value='<?= esc_attr($address->delivery_name) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['addr1'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input lws-required' type='text' name='order_delivery_address' value='<?= esc_attr($address->delivery_address) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['addr2'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_delivery_address_2' value='<?= esc_attr($address->delivery_address_2) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['city'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input lws-required' type='text' name='order_delivery_address_city' value='<?= esc_attr($address->delivery_address_city) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['country'] ?></div>
					<div class='lws-woosupply-input'>
					<?= $inputs ?>
					<div class='lws-woosupply-label'><?= $labels['zip'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input lws-required' type='text' name='order_delivery_address_zipcode' value='<?= esc_attr($address->delivery_address_zipcode) ?>' <?= $this->inputLocked ?> />
					</div>
					<div class='lws-woosupply-label'><?= $labels['tax'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='order_delivery_tax_number' value='<?= esc_attr($address->delivery_tax_number) ?>' <?= $this->inputLocked ?> />
					</div>
			</fieldset>
		</div>
<?php
		return ob_get_clean();
	}

	/** @return (string) order lines HTML table. */
	private function orderDetails($order)
	{
		$thead = $this->tableHead($order);
		$tbody = '';
		foreach ($order->getItems() as $item)
		{
			$tbody .= $this->tableRow($order, $item);
		}
		$tfoot = $this->tableFoot($order);
		$template = \esc_attr(base64_encode($this->tableRow($order, null)));
		$title = __("Products", 'woosupply-lite');

		return <<<EOT
<h1 class='lws-woosupply-title'>{$title}</h1>
<table id='lws_woosupply_supplierorder_items' class='lws-woosupply-singular-table' cellpadding='0' cellspacing='0' data-template='{$template}'>
	<thead id='lws_woosupply_supplierorder_items_head' class='lws-woosupply-singular-table-head'>
		<tr>{$thead}</tr>
	</thead>
	<tbody id='lws_woosupply_supplierorder_items_body' class='lws-woosupply-singular-table-body'>
		{$tbody}
	</tbody>
	<tfoot id='lws_woosupply_supplierorder_items_foot'>
		<tr class='lws-woosupply-supplierorder-items-foot lws-woosupply-supplierorder-items-row'>{$tfoot}</tr>
	</tfoot>
</table>
EOT;
	}

	/** @return (string) HTML code. order line table <tfoot> content. */
	private function tableFoot($order)
	{
		$str = '';
		$columns = \apply_filters('lws_woosupply_supplierorder_items_form_foots', array(
			'total_label' => __("Total", 'woosupply-lite'),
			'total_value' => lws_ws()->getDisplayPrice($order->getAmount())
		), $order);

		$size = count($this->heads);
		$miss = $size - count($columns);
		$colspan = $miss > 1 ? " colspan='$miss'" : '';
		foreach ($columns as $k => $text)
		{
			$str .= \apply_filters('lws_woosupply_supplierorder_items_form_foot_' . $k, "<td$colspan class='lws-woosupply-supplierorder-items-cell lws-woosupply-supplierorder-item-column-{$k}' data-column='{$k}'>{$text}</td>", $text, $order);
			$colspan = '';
		}

		for ($i = ($miss + 1); $i < $size; ++$i)
		{
			$str .= "<td class='lws-woosupply-supplierorder-items-cell lws-woosupply-supplierorder-item-cell-hidden'></td>";
		}
		return $str;
	}

	/** @return (string) HTML code. order line table <thead> content. */
	private function tableHead($order)
	{
		$str = '';
		$this->heads = array(
			'product'     => __("Product", 'woosupply-lite'),
			'comments'    => __("Comments", 'woosupply-lite'),
			'supplierRef' => __("Supplier Reference", 'woosupply-lite'),
			'quantity'    => __("Quantity", 'woosupply-lite'),
			'unitPrice'   => sprintf(__("Unit Price (%s)", 'woosupply-lite'), lws_ws()->getCurrencySymbol()),
			'amount'      => sprintf(__("Total Amount (%s)", 'woosupply-lite'), lws_ws()->getCurrencySymbol()),
			'actions'     => ''
		);

		$this->heads = \apply_filters('lws_woosupply_supplierorder_items_form_heads', $this->heads, $order);
		foreach ($this->heads as $k => $label)
		{
			$str .= \apply_filters('lws_woosupply_supplierorder_items_form_head_' . $k, "<th data-column='{$k}'>{$label}</th>", $label, $order);
		}
		return $str;
	}

	/**	To add a column, use filter 'lws_woosupply_supplierorder_items_form_cells' complete the array.
	 *	Then filter 'lws_woosupply_supplierorder_items_form_cell_'.$column to return the html code of the cell.
	 *	@return (string) HTML code. order line table tbody > tr content. */
	private function tableRow($order, $item)
	{
		$columns = array_keys($this->heads);
		$itemKey = empty($item) ? '' : $item->item_key;

		$str = "<tr class='lws-woosupply-supplierorder-items-row order_items_row'>";
		$str .= "<input name='order_items_key[]' class='lws_woosupply_supplierorder_items_key' value='" . esc_attr($itemKey) . "' type='hidden' style='display:none;'>";

		$values = empty($item) ? SupplierOrderItem::create() : $item;
		foreach ($columns as $column)
		{
			$cell = \apply_filters('lws_woosupply_supplierorder_items_form_cell_' . $column, '', $values, $order);
			if (empty($cell))
			{
				switch ($column)
				{
					case 'product':
						$selected = $values->product_id;
						if (!empty($wcid = intval(\get_metadata('lws_woosupply_supplierproduct', $values->product_id, 'wc_product_id', true))))
							$selected = ('wc_' . $wcid);
						else if ('shipping' == $values->getMeta('product_id_breach', true))
							$selected = 'shipping';
						if (empty($selected))
							$selected = '';
						$shipping = esc_attr(base64_encode(json_encode(array('shipping' => array('value' => 'shipping', 'label' => __("[Shipping]", 'woosupply-lite'))))));
						$cell = "<td class='lws-text-input lws-large' data-column='product'>";
						$cell .= "<input data-source='$shipping' data-ajax='lws_woosupply_wc_product_list' data-mode='research' name='order_items_{$column}[]' class='lac_select $column' value='" . \esc_attr($selected) . "' {$this->inputLocked}/>";
						break;
					case 'comments':
						$cell = "<td class='lws-text lws-xlarge' data-column='comments'>";
						$cell .= "<textarea rows='1' type='text' name='order_items_{$column}[]' class='$column' {$this->inputLocked}>" . \esc_html($values->getMeta('comments', true)) . "</textarea>";
						break;
					case 'supplierRef':
						$cell = "<td class='lws-text-input lws-large' data-column='supplierRef'>";
						$cell .= "<input type='text' name='order_items_{$column}[]' class='$column' value='" . \esc_attr($values->supplier_reference) . "' {$this->inputLocked}/>";
						break;
					case 'quantity':
						$readonly = \lws_ws()->cmpOrderStatus($order->getStatus(), 'ws_received') < 0 ? '' : ' readonly';
						$cell = "<td class='lws-number-input lws-normal' data-column='quantity'>";
						$cell .= "<input type='text'$readonly name='order_items_{$column}[]' class='$column' value='" . \esc_attr(\lws_ws()->getDisplayQuantity($values->quantity)) . "' {$this->inputLocked}/>";
						break;
					case 'unitPrice':
						$cell = "<td class='lws-number-input lws-normal' data-column='unitPrice'>";
						$cell .= "<input type='text' pattern='\\s*\\d+.*' name='order_items_{$column}[]' class='$column' value='" . \esc_attr(\lws_ws()->getDisplayPrice($values->unit_price)) . "' {$this->inputLocked}/>";
						break;
					case 'amount':
						$cell = "<td class='lws-number-input lws-normal' data-column='amount'>";
						$cell .= "<input type='text' readonly name='order_items_{$column}[]' class='$column' value='" . \esc_attr(\lws_ws()->getDisplayPrice($values->amount)) . "' {$this->inputLocked}/>";
						break;
					case 'actions':
						$cell = "<td class='lws-action' data-column='actions'>";
						$cell .= "<div class='lws-woosupply-supplierorder-item-delete lws-icon lws-icon-bin' {$this->iconHidden}></div>";
						break;
					default:
						break;
				}
				$cell .= "</td>";
			}
			$str .= $cell;
		}

		$str .= "</tr>";
		return $str;
	}
	public function addSupplierOrderBoxes($emptyArray, $singularId)
	{
		return array(
			'box_invoice' => array('title' => __('Supplier Invoice', 'woosupply-lite'))
		);
	}

	public function addSupplierOrderInvoiceBox($content, $id)
	{
		$order = empty($id) ? SupplierOrder::create() : SupplierOrder::get($id);

		$labels = array(
			'remote_id' => __("Invoice number", 'woosupply-lite'),
			'remote_date' => __("Invoice Date", 'woosupply-lite'),
			'remote_amount' => sprintf(__("Invoice amount(%s)", 'woosupply-lite'), lws_ws()->getCurrencySymbol()),
			'payment' => __("Payment Status", 'woosupply-lite'),
			'attachement' => __("Invoice Document", 'woosupply-lite'),
			'view' => __("See the Document", 'woosupply-lite'),
			'upload' => __("Upload PDF Invoice", 'woosupply-lite'),
			'file_selected' => __("File selected", 'woosupply-lite')
		);

		$str = "";
		$str .= "<div class='lws-supplierorder-invoice-title'>{$labels['remote_id']}</div>";
		$str .= "<input class='lws-input' type='text' name='order_remote_invoice_id' value='" . $order->getData('remote_invoice_id', '', true, true) . "'/>";

		$invdate = $order->getDate('Y-m-d', $meta_key = 'remote_invoice_date', false);
		$str .= "<div class='lws-supplierorder-invoice-title'>{$labels['remote_date']}</div>";
		$str .= "<input class='lws-input' type='date' name='order_remote_invoice_date' value='" . esc_attr($invdate) . "'/>";

		$str .= "<div class='lws-supplierorder-invoice-title lws-bold'>{$labels['remote_amount']}</div>";
		$str .= "<input class='lws-input' type='text' id='order_remote_invoice_amount' name='order_remote_invoice_amount' value='" . $order->getData('remote_invoice_amount', '', true, true) . "'/>";

		$paid = $order->getData('order_paid');
		$str .= "<div class='lws-supplierorder-invoice-title'>{$labels['payment']}</div>";
		$str .= "<div style='height: 20px;margin: 10px 0 20px 0;'><input type='checkbox' class='lws_switch' id='order_remote_invoice_paid' name='order_remote_invoice_paid' data-left='Not Paid' data-right='Paid' " . (empty($paid) ? '' : "checked='checked'") . "/></div>";
		$str .= "<input class='lws-input' id='order_remote_invoice_paid_date' type='date' name='order_remote_invoice_paid_date' value='" . esc_attr(empty($paid) ? date('Y-m-d') : $paid) . "'/>";

		$str .= "<div class='lws-supplierorder-invoice-title'>{$labels['attachement']}</div>";
		$str .= "<div class='lws-icon-btn' style='--btn-color:#bb1d25'>";
		$str .= "<div class='icon lws-icon lws-icon-pdf'></div>";

		$upload_max_size = min($this->textAsBytes(\ini_get('upload_max_filesize')), $this->textAsBytes(\ini_get('post_max_size')));
		// sould we keep a margin for the rest of form?
		$warn = \esc_attr(sprintf(__("File too big (max %s)", 'woosupply-lite'), $this->bytesAsText($upload_max_size)));
		$str .= "<input type='hidden' id='upload_max_size' value='$upload_max_size' data-warn='$warn'>";

		if (!empty($order->getData('invoice_document')))
		{
			$href = \esc_attr(add_query_arg(array('action' => 'lws_woosupply_order_invoice_document_get', 'supplierorder_id' => $order->getId()), admin_url('/admin-ajax.php')));
			$str .= "<div class='text'>";
			$str .= "<a href='$href' target='_blank' class='lws-woosupply-attachement-url'>{$labels['view']}</a>";
			$str .= "</div>";
			$str .= "<label for='order_remote_invoice_attachement' class='end-line-btn lws-icon lws-icon-refresh' ></label><input type='file' id='order_remote_invoice_attachement' name='order_remote_invoice_attachement' style='display: none;'>";
		}
		else
		{
			$str .= "<div class='text'><label for='order_remote_invoice_attachement'>{$labels['upload']}</label><input type='file' id='order_remote_invoice_attachement' name='order_remote_invoice_attachement' style='display: none;'></div>";
		}
		$str .= "</div>";
		$str .= "<div class='lws-file-selected'>{$labels['file_selected']}</div>";

		return $str;
	}

	/** @param $val value as set in MySql global.
	 * @return int value, $val converted to bytes. */
	private function textAsBytes($val)
	{
		$val = trim($val);
		$last = strtolower(substr($val, -1));
		$newVal = substr($val, 0, strlen($val) - 1);
		switch ($last)
		{
				// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$newVal *= 1024;
			case 'm':
				$newVal *= 1024;
			case 'k':
				$newVal *= 1024;
				break;
			default:
				$newVal = $val;
		}
		return $newVal;
	}

	/** @param $val int bytes.
	 * @return display text, converted to the bigger size unit. */
	private function bytesAsText($val)
	{
		$units = array(
			_x("o", "file size unit - octet", 'woosupply-lite'),
			_x("Mo", "file size unit - mega octet", 'woosupply-lite'),
			_x("Go", "file size unit - giga octet", 'woosupply-lite')
		);
		$index = 1;
		while ($val >= 1024 && $index < count($units))
		{
			++$index;
			$val /= 1024.0;
		}
		return \number_format_i18n($val, 1) . $units[$index - 1];
	}
}

?>