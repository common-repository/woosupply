<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();


/** A simple text field with a button.
 * Look for $_GET[$name] in your EditListSource::read implemention. */
class FilterSimpleField extends Filter
{
	/** @param $name you will get the filter value in $_GET[$name]. */
	function __construct($name, $placeholder, $buttonLabel='')
	{
		parent::__construct();
		$this->_class = "lws-editlist-filter-search lws-editlist-filter-" . strtolower($name);
		$this->name = $name;
		$this->placeholder = \esc_attr($placeholder);
		$this->buttonLabel = (empty($buttonLabel) ? __('Search', 'lws-adminpanel') : $buttonLabel);
	}

	function input($above=true)
	{
		$search = '';
		if( isset($_GET[$this->name]) && !empty(trim($_GET[$this->name])) )
			$search = trim(esc_attr(\sanitize_text_field($_GET[$this->name])));

		$filterlabel = __('Narrow your search', 'lws-adminpanel');

		$retour = "<div class='lws-editlist-filter-box end'><div class='lws-editlist-filter-box-title'>{$filterlabel}</div>";
		$retour .= "<div class='lws-editlist-filter-box-content'>";
		$retour .= "<label><input type='text' placeholder='{$this->placeholder}' name='{$this->name}' value='$search' class='lws-input lws-input-enter-submit lws-ignore-confirm'>";
		$retour .= "<button class='lws-adm-btn lws-editlist-filter-btn'>{$this->buttonLabel}</button></label>";
		$retour .= "</div></div>";
		return $retour;

		/*
		$str = "<div class='lws-editlistfilter-simplefiaaeld'>";
		$str .= "<label><input type='text' placeholder='{$this->placeholder}' name='{$this->name}' value='$search' class='lws-input-enter-submit lws-ignore-confirm'>";
		$str .= "<button class='lws-adm-btn aaa'>{$this->buttonLabel}</button></label>";
		$str .= "</div>";
		return $str;
		*/
	}
}
