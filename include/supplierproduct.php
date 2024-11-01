<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOSUPPLY_INCLUDES . '/wspost.php';

class SupplierProduct extends WSPost
{
	public $name = ''; // Denomination

	/// implements IWSPost::getClassname
	public static function getClassname()
	{
		return get_class();
	}

	/// override abstract WSPost::getAutoloadProperties
	function getAutoloadProperties()
	{
		return \apply_filters('lws_woosupply_'.strtolower(static::getClassname()).'_properties', array(
			'name',
		));
	}

	/// override abstract WSPost::getAutoloadPropertiesFormat
	function getAutoloadPropertiesFormat()
	{
		return \apply_filters('lws_woosupply_'.strtolower(static::getClassname()).'_properties_format', array(
			'%s',
		));
	}

}

?>
