<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOSUPPLY_INCLUDES . '/wspost.php';

class Supplier extends WSPost
{
	public $name = ''; //Company Name
	public $email = ''; //Email
	public $contact_firstname = ''; //Contact First Name
	public $contact_lastname = ''; //Contact Last Name
	public $address = ''; //Address first line
	public $address_2 = ''; //Address second line
	public $address_zipcode = ''; //Address zip code
	public $address_city = ''; //Address City
	public $address_country = ''; //Address Country
	public $address_state = ''; //Address State
	public $phone_number = ''; //Phone number
	public $fax_number = ''; //Fax number if needed
	public $tax_number = ''; //Tax Id

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
			'email',
			'contact_firstname',
			'contact_lastname',
			'address',
			'address_2',
			'address_zipcode',
			'address_city',
			'address_country',
			'address_state',
			'phone_number',
			'fax_number',
			'tax_number'
		));
	}

	/// override abstract WSPost::getAutoloadPropertiesFormat
	function getAutoloadPropertiesFormat()
	{
		return \apply_filters('lws_woosupply_'.strtolower(static::getClassname()).'_properties_format', array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s'
		));
	}

}

?>
