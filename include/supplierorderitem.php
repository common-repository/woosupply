<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOSUPPLY_INCLUDES . '/wspost.php';

class SupplierOrderItem extends WSPost
{
  public $order_id = 0; // Order id
  public $supplier_reference = ''; // Product reference from supplier side
  public $item_key = ''; // order local id (mainly to work with before database insertion)
  public $product_id = 0; // supplier product
  public $quantity = 1; // quantity
  public $unit_price = 0; // Unit price
  public $amount = 0; // line amount (= unit price * quantity) provided for convenience or promotional.

	public static function getClassname()
	{
		return get_class();
	}

	/// override abstract WSPost::getAutoloadProperties
	function getAutoloadProperties()
	{
		return \apply_filters('lws_woosupply_'.strtolower(static::getClassname()).'_properties', array(
			'order_id',
			'supplier_reference',
			'product_id',
			'item_key',
			'quantity',
			'unit_price',
			'amount'
		));
	}

	/**	@see getProduct
	 *	@param $product (int|array|object) if array or object, expect a 'id' index/property. */
	public function setProduct($product)
	{
		$this->product_id = 0;
		if( isset($this->product) )
			unset($this->product);

		if( is_numeric($product) )
		{
			$this->product_id = $product;
			return true;
		}
		else if( is_object($product) && isset($product->id) )
		{
			$this->product_id = $product->id;
			if( is_a($product, '\LWS\WOOSUPPLY\SupplierProduct') )
				$this->product = $product;
			return true;
		}
		else if( is_array($product) && isset($product['id']) )
		{
			$this->product_id = $product['id'];
			return true;
		}
		return false;
	}

	/** @param $getOrCreate (bool) Default is false. if true, return an instance anyway.
	 * @return Instance of SupplierProduct or null on error. */
	public function getProduct($getOrCreate=false)
	{
		if( !isset($this->product) )
		{
			require_once LWS_WOOSUPPLY_INCLUDES . '/supplierproduct.php';
			$this->product = SupplierProduct::get($this->product_id);

			if( empty($this->product) && $getOrCreate )
				$this->product = SupplierProduct::create();
		}
		return $this->product;
	}

	public function getProductName()
	{
		$product = $this->getProduct(false);
		if( !empty($product) )
			return $product->name;
		else
			return $this->getMeta('product_name_breach', true);
	}

	/**	Store the order delivered quantity then manage stock.
	 *	@param $quantity the total quantity received for this order.
	 *	It means if the order is partially received, the second receipt should include the first count (again) for the argument $quantity.
	 *
	 *	Then update WC_Product stock (if such a product is linked and manage_stock enabled).
	 *	@see hook 'lws_woosupply_supplierorder_item_stock_move' to prevent this behavior. */
	public function updateOnDelivery($quantity)
	{
		$quantity = floatval($quantity);
		$partial = floatval($this->getMeta('delivered', true));
		$this->updateMeta('delivered', $quantity);
		$diff = $quantity - $partial;

		/** @param (SupplierOrderItem) this item.
		 *	@param (float) moving quantity.
		 *	@note
		 *	postmeta.meta_key='_stock' for wc_product stock quantity.
		 *	@endnote */
		$ret = \apply_filters('lws_woosupply_supplierorder_item_stock_move', false, $this, $diff);

		if( $ret === false )
		{
			// update the WC_Product postmeta.meta_key='_stock'
			$sp = $this->getProduct();
			if( !empty($sp) && !empty($wcProductId = $sp->getMeta('wc_product_id', true)) )
			{
				if( !empty($wcProduct = \wc_get_product($wcProductId)) && $wcProduct->managing_stock() )
				{
					$stock = floatval($wcProduct->get_stock_quantity('edit'));
					$wcProduct->set_stock_quantity($stock + $diff);
					$wcProduct->save();
				}
			}
		}
	}
}
?>
