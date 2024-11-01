<?php
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

if( !function_exists('lws_admin_has_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_has_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		return isset($notices[$key]);
	}
}

if( !function_exists('lws_admin_delete_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_delete_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		if( isset($notices[$key]) )
		{
			unset($notices[$key]);
			\update_site_option('lws_adminpanel_notices', $notices);
		}
	}
}

if( !function_exists('lws_admin_add_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_add_notice($key, $message, $options=array())
	{
		$options['message'] = $message;
		\update_site_option('lws_adminpanel_notices', array_merge(get_site_option('lws_adminpanel_notices', array()), array($key => $options)));
	}
}

if( !function_exists('lws_admin_add_notice_once') )
{
	/** @see lws_admin_add_notice */
	function lws_admin_add_notice_once($key, $message, $options=array())
	{
		$options['once'] = true;
		lws_admin_add_notice($key, $message, $options);
	}
}

if( !function_exists('lws_get_value') )
{
	/** @return $value if not empty, else return $default. */
	function lws_get_value($value, $default='')
	{
		return empty($value) ? $default : $value;
	}
}

if( !function_exists('lws_get_option') )
{
	/** @return \get_option($option) if not empty, else return $default. */
	function lws_get_option($option, $default='')
	{
		return \lws_get_value(\get_option($option), $default);
	}
}

if( !function_exists('lws_get_tooltips_html') )
{
	/** @return \get_option($option) if not empty, else return $default. */
	function lws_get_tooltips_html($content, $cssClass='', $id='')
	{
		if( !empty($cssClass) )
			$cssClass = (' ' . $cssClass);

		$attr = '';
		if( !empty($id) )
			$attr = " id='" . \esc_attr($id) . "'";

		$retour = "<div class='lws_tooltips_button$cssClass lws-icon lws-icon-question'$attr>";
		$retour .= "<div class='lws_tooltips_wrapper' style='display:none'>";
		$retour .= "<div class='lws_tooltips_arrow'><div class='lws_tooltips_arrow_inner'></div></div>";
		$retour .= "<div class='lws_tooltips_content'>$content</div></div></div>";
		return $retour;
	}
}

if (!function_exists('color_luminance')) {
	/**
	 * Lightens/darkens a given colour (hex format), returning the altered colour in hex format.7
	 * @param str $hex Colour as hexadecimal (with or without hash);
	 * @percent float $percent Decimal ( 0.2 = lighten by 20%(), -0.4 = darken by 40%() )
	 * @return str Lightened/Darkend colour as hexadecimal (with hash);
	 */
	function color_luminance($hex, $percent)
	{

		// validate hex string

		$hex = preg_replace('/[^0-9a-f]/i', '', $hex);
		$new_hex = '#';

		if (strlen($hex) < 6) {
			$hex = $hex[0] + $hex[0] + $hex[1] + $hex[1] + $hex[2] + $hex[2];
		}

		// convert to decimal and change luminosity
		for ($i = 0; $i < 3; $i++) {
			$dec = hexdec(substr($hex, $i * 2, 2));
			$dec = min(max(0, $dec + $dec * $percent), 255);
			$new_hex .= str_pad(dechex($dec), 2, 0, STR_PAD_LEFT);
		}

		return $new_hex;
	}
}

if (!function_exists('get_theme_colors')) {
	/** Returns a string containing 5 colors which are variations of the given color */
	function get_theme_colors($name, $color = '')
	{

		if (empty($color)) {
			$colorstring = $name . ':#999999;';
			$colorstring .= $name . '-light:#bbbbbb;';
			$colorstring .= $name . '-lighter:#dddddd;';
			$colorstring .= $name . '-alpha:#dddddd40;';
			$colorstring .= $name . '-dark:#666666;';
			$colorstring .= $name . '-darker:#333333;';
		} else {
			$colorstring = $name . ':' . $color . ';';
			$colorstring .= $name . '-light:' . color_luminance($color, 0.25) . ';';
			$colorstring .= $name . '-lighter:' . color_luminance($color, 0.80) . ';';
			$colorstring .= $name . '-alpha:' . $color . '40;';
			$colorstring .= $name . '-dark:' . color_luminance($color, -0.25) . ';';
			$colorstring .= $name . '-darker:' . color_luminance($color, -0.5) . ';';
		}

		return $colorstring;
	}
}