<?php
namespace LWS\WOOSUPPLY;
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';

class SupplierList extends \LWS\Adminpanel\EditList\Source
{
	static public $Id = 'supplierlist';

	function __construct()
	{
		add_filter('lws_ap_editlist_item_actions_'.sanitize_key(self::$Id), array($this, 'rowButtons'), 10, 3);
	}

	/** Remove trash link if any order belong to a supplier. */
	function rowButtons($btns, $id, $row)
	{
		global $wpdb;
		if( 0 < ($oc = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->lws_woosupply_supplierorder} WHERE supplier_id=%d", $id))) )
		{
			$info = sprintf(_n("%d order", "%d orders", $oc, 'woosupply-lite'), $oc);
			$btns['del'] = "<span class='lws-editlist-woosupply-order-count'>{$info}</span>";
		}
		return $btns;
	}

	/** no quick edit. */
	function input()
	{
		return '';
	}

	function labels()
	{
		return array(
			'label'           => array(__("Name", 'woosupply-lite'), "25%"),
			'email'          => array(__("Email", 'woosupply-lite') , "20%"),
			'address_city'           => array(__("City", 'woosupply-lite'), "20%"),
			"global-contact" => __("Contact" , 'woosupply-lite')
		);
	}

	function read($limit)
	{
		global $wpdb;
		$sql = $this->suppliers($limit);
		$suppliers = $wpdb->get_results($sql, ARRAY_A);

		foreach($suppliers as &$supplier)
		{
			$url = add_query_arg(array('page' => 'lws_woosupply_supplier', 'supplier_id' => $supplier['supplier_id']), admin_url('admin.php'));
			$supplier['label'] = "<a href='$url'>{$supplier['name']}</a>";
			$supplier['global-contact'] = implode(' ', array($supplier['contact_firstname'], $supplier['contact_lastname']));
		}

		return $suppliers;
	}

	/// @return the sql request to get the users
	protected function suppliers($limit, $countOnly=false)
	{
		global $wpdb;
		$sql = "";
		if( $countOnly )
			$sql = "SELECT COUNT(ID) FROM {$wpdb->lws_woosupply_supplier} as u";
		else
			$sql = "SELECT id as supplier_id, name, email, address_city, contact_firstname, contact_lastname FROM {$wpdb->lws_woosupply_supplier}";

		if(isset($_GET['slSearch']) && !empty(trim($_GET['slSearch'])))
		{
			$search = trim($_GET['slSearch']);
			$like = "%$search%";
			$searches = array();
			foreach( array('name', 'email', 'address_city', 'contact_firstname', 'contact_lastname') as $f )
				$searches[] = $wpdb->prepare("$f LIKE %s", $like);
			$sql .= " WHERE (".implode(' OR ', $searches).") ";
		}
		if( !is_null($limit) )
			$sql .= $limit->toMysql();
		return $sql;
	}

	function total()
	{
		global $wpdb;
		$sql = $this->suppliers(null, true);
		$c = $wpdb->get_var($sql);
		return (is_null($c) ? -1 : $c);
	}

	function erase($line)
	{
		if( !(is_array($line) && isset($line['supplier_id']) && is_numeric($line['supplier_id'])) )
		{
			error_log("Invalid deletion request.");
			return false;
		}

		$id = intval($line['supplier_id']);
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';

		$supplier = Supplier::get($id);
		if( empty($supplier) )
		{
			error_log("Cannot found the supplier.");
			return false;
		}

		global $wpdb;
		if( 0 < ($oc = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->lws_woosupply_supplierorder} WHERE supplier_id=%d", $id))) )
		{
			error_log("Cannot delete the supplier since orders are linked");
			return false;
		}

		if( empty($supplier->delete()) )
		{
			error_log("An error occured during the supplier deletion.");
			return false;
		}

		return true;
	}

	function write($line)
	{
		return false;
	}

}
?>
