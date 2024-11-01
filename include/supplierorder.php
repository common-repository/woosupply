<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOSUPPLY_INCLUDES . '/wspost.php';
require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorderitem.php';

class SupplierOrder extends WSPost
{
	public $supplier_id = 0; // we have to keep supplier in our DB to be used
	public $status = 'ws_new'; // Order status
	public $order_date = '';

	// delivery address bloc
	public $delivery_name = ''; /// Delivery Society Name (we can order but let us deliver somewhere else)
	public $delivery_tax_number = '';
	public $delivery_address = '';
	public $delivery_address_2 = '';
	public $delivery_address_zipcode = '';
	public $delivery_address_city = '';
	public $delivery_address_country = '';
	public $delivery_address_state = '';

	// legacy supplier address copy
	public $supplier_name = '';
	public $supplier_contact = '';
	public $supplier_tax_number = '';
	public $supplier_email = '';
	public $supplier_address = '';
	public $supplier_address_2 = '';
	public $supplier_address_zipcode = '';
	public $supplier_address_city = '';
	public $supplier_address_country = '';
	public $supplier_address_state = '';

	// Order detail
	public $amount = 0;
	protected $items = array(); // array of SupplierOrderItem instances
	private $managedItemsMeta = array();

	public static function getClassname()
	{
		return get_class();
	}

	/// override abstract WSPost::getAutoloadProperties
	function getAutoloadProperties()
	{
		return \apply_filters('lws_woosupply_'.strtolower(static::getClassname()).'_properties', array(
			'supplier_id',
			'status',
			'order_date',
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
			'delivery_address_state',
		));
	}

	static function getDefaultDeliveryAddress()
	{
		$countryState = explode(':', \get_option('woocommerce_default_country', ''));
		$values = array(
			'delivery_name'               => \get_option('lws_woosupply_company_name', \get_bloginfo('name')),
			'delivery_tax_number'         => \get_option('lws_woosupply_company_tax_number'),
			'delivery_address'            => \get_option('lws_woosupply_company_address', \get_option('woocommerce_store_address')),
			'delivery_address_2'          => \get_option('lws_woosupply_company_address_2', \get_option('woocommerce_store_address_2')),
			'delivery_address_zipcode'    => \get_option('lws_woosupply_company_zipcode', \get_option('woocommerce_store_postcode')),
			'delivery_address_city'       => \get_option('lws_woosupply_company_city', \get_option('woocommerce_store_city')),
			'delivery_address_country'    => \get_option('lws_woosupply_company_country', count($countryState)>0 ? $countryState[0] : ''),
			'delivery_address_state'      => \get_option('lws_woosupply_company_state', count($countryState)>1 ? $countryState[1] : ''),
			'delivery_email'              => \get_option('lws_woosupply_company_email', \get_option('woocommerce_email_from_address'))
		);
		return \apply_filters('lws_woosupply_supplierorder_default_delivery_address_get', (object)$values);
	}

	/** @param $force (false) if true and $date is not a DateTime or a valid date format, fonce date to now().
	 * If $date is not a date or empty, nothting is changed.
	 * @param $forceNow (true) if $date is not valid or empty, force now as value. */
	public function setDate($date, $meta_key='order_date', $force=false, $forceNow=true, $isMeta=false)
	{
		$dt = \is_a($date, 'DateTime') ? $date : (empty($date) ? null : \date_create($date));

		if( empty($dt) && $forceNow )
			$dt = \date_create();

		if( !empty($dt) )
		{
			if( $isMeta && !empty($this->getId()) )
				$this->updateMeta($meta_key, $dt->format('Y-m-d'));
			else
				$this->setData($meta_key, $dt->format('Y-m-d'), true);
		}
		else if( $force )
		{
			if( $isMeta && !empty($this->getId()) )
				$this->deleteMeta($meta_key);
			else
				$this->setData($meta_key, null, true);
		}
	}

	public function getDate($format='Y-m-d', $meta_key='order_date', $getNowOnEmpty=true)
	{
		if( isset($this->$meta_key) )
			$value = $this->$meta_key;
		else
			$value = $this->getMeta($meta_key, true);

		$dt = !empty($value) ? \date_create($value) : null;
		if( empty($dt) && $getNowOnEmpty )
			$dt = \date_create();

		return empty($dt) ? '' : $dt->format($format);
	}

	public function getL18NDate($meta_key='order_date', $getNowOnEmpty=true)
	{
		$dt = $this->getDate('Y-m-d', $meta_key, $getNowOnEmpty);
		return empty($dt) ? '' : \mysql2date(\get_option('date_format'), $dt);
	}

	/** @return a public id. */
	public function getLabel()
	{
		return LWS_WSORDER_ID_PREFIX . \lws_ws()->formatOrderNumber($this->getId());
	}

	static public function getLabelPrefix()
	{
		static $prefix = false;
		if( false === $prefix )
			$prefix = \get_option('lws_woosupply_supplie_order_id_prefix', '');
		return LWS_WSORDER_ID_PREFIX.$prefix;
	}

	/** @return (array) all available status as key => label. */
	static public function statusList()
	{
		static $status = null;
		if( is_null($status) )
		{
			/** If new status are inserted by this hook, please try to respect a usual cinematic order. */
			$status = \apply_filters('lws_woosupply_supplierorder_status_list', array(
				'ws_new'      => _x("New", "Supplier Order Status", 'woosupply-lite'),
				'ws_sent'     => _x("Sent", "Supplier Order Status", 'woosupply-lite'),
				'ws_ack'      => _x("Accepted", "Supplier Order Status", 'woosupply-lite'),
				'ws_received' => _x("Received", "Supplier Order Status", 'woosupply-lite'),
				'ws_complete' => _x("Completed", "Supplier Order Status", 'woosupply-lite')
			));
		}
		return $status;
	}

	/** List of order status meaning that order reach an end, then most modifications become deprecated. */
	static public function lockStatusList()
	{
		return \apply_filters('lws_woosupply_order_lock_status', array('ws_complete'));
	}

	/**	@param $status (string) status code.
	 *	@return (string) Human readable status (or try to compute one). */
	static public function statusLabel($status)
	{
		$list = self::statusList();
		return isset($list[$status]) ? $list[$status] : __(ucfirst(substr($status, 3)));
	}

	/**	Loads this order and order items.
	 * @param $id supplier order id, mandatory
	 * @param $withItems (bool) default true, load order items too.
	 * @return bool true if loaded, false if record not found */
	public function load($id, $withItems=true)
	{
		$loaded = parent::load($id);
		if($loaded)
			$this->loadItems();
		return $loaded;
	}

	/** Update or create order in database.
	 * @param $updateMetaKeys (array|false) update/add/delete meta with given keys too.
	 * @return bool false if not saved / true if saved */
	public function update()
	{
		if( isset($this->supplier) && !empty($this->supplier) )
		{
			if( empty($this->supplier->getId()) )
				$this->supplier->update();
			$this->supplier_id = $this->supplier->getId();
		}

		$updated = parent::update();
		if($updated)
			$this->updateItems();
		return $updated;
	}

	/** Delete order and items. */
	public function delete()
	{
		if( $ok = parent::delete() )
			$this->deleteItems();
		return $ok;
	}

	public function setStatus($status, $save=true)
	{
		$oldStatus = $this->status;
		$this->status = $status;
		if( $save )
			parent::update();
		\do_action('lws_woosupply_supplierorder_status_changed', $this->getId(), $this, $oldStatus);
	}

	public function getStatus()
	{
		return $this->status;
	}

	/** @param $context default='', for computing, could be 'view' for display and so on. */
	public function getAmount()
	{
		return \apply_filters('lws_woosupply_supplierorder_amount_get', $this->amount);
	}

	/**	@see getSupplier
	 *	@param $supplier (int|array|object) if array or object, expect a 'id' index/property. */
	public function setSupplier($supplier)
	{
		$this->supplier_id = 0;
		if( isset($this->supplier) )
			unset($this->supplier);

		if( is_numeric($supplier) )
		{
			$this->supplier_id = $supplier;
			return true;
		}
		else if( is_object($supplier) && isset($supplier->id) )
		{
			$this->supplier_id = $supplier->id;
			if( is_a($supplier, '\LWS\WOOSUPPLY\Supplier') )
				$this->supplier = $supplier;
			return true;
		}
		else if( is_array($supplier) && isset($supplier['id']) )
		{
			$this->supplier_id = $supplier['id'];
			return true;
		}
		return false;
	}

	/** @param $getOrCreate (bool) Default is false. if true, return an instance anyway.
	 * @return Instance of Supplier or null on error. */
	public function getSupplier($getOrCreate=false)
	{
		if( !isset($this->supplier) )
		{
			require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';
			$this->supplier = Supplier::get($this->supplier_id);

			if( empty($this->supplier) && $getOrCreate )
				$this->supplier = Supplier::create();
		}
		return $this->supplier;
	}

	/** Managed meta will be updated with the wspost at update.
	 * @param $meta_key (string|array) */
	public function addManagedItemsMeta($meta_key)
	{
		if( is_array($meta_key) )
		{
			foreach( $meta_key as $k )
				$this->managedItemsMeta[$k] = true;
		}
		else
			$this->managedItemsMeta[$meta_key] = true;
	}

	/** Managed meta will be updated with the wspost at update.
	 * @param $meta_key (string|array) */
	public function setManagedItemsMeta($meta_key)
	{
		$this->managedItemsMeta = array();
		$this->addManagedItemsMeta($meta_key);
	}

	/** Managed meta will be updated with the wspost at update. */
	public function getManagedItemsMetas()
	{
		return array_keys($this->managedItemsMeta);
	}

	/** Managed meta will be updated with the wspost at update. */
	public function isManagedItemsMetas($meta_key)
	{
		return isset($this->managedItemsMeta[$meta_key]);
	}

	/** Managed meta will be updated with the wspost at update. */
	public function unmanagedItemsMetas($meta_key)
	{
		if( $meta_key === true )
			$this->managedItemsMeta = array();
		if( isset($this->managedItemsMeta[$meta_key]) )
			unset($this->managedItemsMeta[$meta_key]);
	}

	/** Add an item to the order
	 *	@param $item (SupplierOrderItem)
	 * @return (SupplierOrderItem) the updated $item. */
	public function pushItem($item)
	{
		$item->order_id = $this->getId();
		$this->amount += $item->amount;
		if( empty($item->item_key) || isset($this->items[$item->item_key]) )
		{
			$key = 10 * count($this->items);
			while( isset($this->items['#'.$key]) )
				$key += 10;
			$item->item_key = '#'.$key;
		}
		return ($this->items[$item->item_key] = $item);
	}

	public function getItems()
	{
		return $this->items;
	}

	/** The given item from this order.
	 *	@param $item (SupplierOrderItem|string|int) instance of or id or key.
	 * @return (SupplierOrderItem|null) */
	public function getItem($item)
	{
		if( is_a($item, '\LWS\WOOSUPPLY\SupplierOrderItem') )
		{
			if( isset($this->items[$item->item_key]) )
				return $this->items[$item->item_key];
		}
		else if( is_numeric($item) )
		{
			foreach($this->items as $key => $value)
			{
				if( $value->getId() == $item )
					return $value;
			}
		}
		else if( is_string($item) )
		{
			if( isset($this->items[$item]) )
				return $this->items[$item];
		}

		return null;
	}

	/** The given item exists in this order.
	 *	@param $item (SupplierOrderItem|string|int) instance of or id or key.
	 * @return (bool) */
	public function hasItem($item)
	{
		if( is_a($item, '\LWS\WOOSUPPLY\SupplierOrderItem') )
		{
			if( isset($this->items[$item->item_key]) )
				return true;
		}
		else if( is_numeric($item) )
		{
			foreach($this->items as $key => $value)
			{
				if( $value->getId() == $item )
					return true;
			}
		}
		else if( is_string($item) )
		{
			if( isset($this->items[$item]) )
				return true;
		}

		return false;
	}

	/** Remove the given item from the order (do not delete it in database)
	 *	@param $item (SupplierOrderItem|string|int) instance of or id or key.
	 * @return the removed item. */
	public function popItem($item)
	{
		$removed = null;
		if( is_a($item, '\LWS\WOOSUPPLY\SupplierOrderItem') )
		{
			if( isset($this->items[$item->item_key]) )
			{
				$removed = $item;
				unset($this->items[$item->item_key]);
			}
		}
		else if( is_numeric($item) )
		{
			foreach($this->items as $key => $value)
			{
				if( $value->getId() == $item )
				{
					$removed = $value;
					unset($this->items[$removed->item_key]);
					break;
				}
			}
		}
		else if( is_string($item) )
		{
			if( isset($this->items[$item]) )
			{
				$removed = $this->items[$item];
				unset($this->items[$item]);
			}
		}

		if( !empty($removed) )
			$this->amount -= $removed->amount;
		return $removed;
	}

	/** Load order items from database. */
	protected function loadItems()
	{
		global $wpdb;
		$results = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->lws_woosupply_supplierorderitem} WHERE order_id=%d ORDER BY id ASC", $this->getId()));

		$this->items = array();
		$this->amount = 0;

		foreach($results as $itemId)
		{
			$item = SupplierOrderItem::get($itemId);
			$this->items[$item->item_key] = $item;
			$this->amount += $item->amount;
		}
	}

	/** Saves local items in database. */
	protected function updateItems()
	{
		foreach($this->items as &$item)
		{
			$item->order_id = $this->getId();
			$item->setManagedMeta($this->getManagedItemsMetas());
			$item->update();
		}
	}

	/** Delete local items in database. */
	protected function deleteItems()
	{
		foreach($this->items as &$item)
			$item->delete();
	}

  protected function __construct()
  {
		parent::__construct();
		$this->order_date = \date_create()->format('Y-m-d');
	}
}
?>
