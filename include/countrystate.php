<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage a list of country and states.
 * array keys are country or state code, values are array with value (code) and label (full name). */
class CountryState
{
	public static function instance()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
		}
		return $instance;
	}

	/** @return (string) country name */
	function getCountryByCode($countryCode)
	{
		if( isset($this->getList()[$countryCode]) )
			return $this->getList()[$countryCode]['label'];
		return false;
	}

	/** @return (string|false) state name */
	function getStateByCodes($countryCode,$stateCode)
	{
		$states = $this->getStates($countryCode);
		if( !empty($states) )
		{
			if( isset($states[$stateCode]) )
				return $states[$stateCode]['label'];
		}
		return false;
	}

	/** @return (array|false) state array */
	function getStates($countryCode)
	{
		if( isset($this->getList()[$countryCode]) )
		{
			if( isset($this->getList()[$countryCode]['states']) )
				return $this->getList()[$countryCode]['states'];
			return array();
		}
		return false;
	}

	/** @return array( code => array( 'name' => translated_name, state => array( code => translated_name ) ) ) */
	function getList()
	{
		if( !isset($this->countryState) )
		{
			$this->countryState = \apply_filters('lws_woosupply_countrystates_load', array());

			if( empty($this->countryState) && function_exists('wc') && isset(\wc()->countries) )
			{
				$this->countryState = array();
				foreach( \wc()->countries->get_countries() as $value => $label )
				{
					$this->countryState[$value] = array(
						'value'  => $value,
						'label'  => \html_entity_decode($label)
					);

					$wcStates = \wc()->countries->get_states($value);
					if( !empty($wcStates) )
					{
						$states = array();
						foreach( $wcStates as $code => $name )
						{
							$states[$code] = array(
								'value'  => $code,
								'label'  => \html_entity_decode($name)
							);
						}
						$this->countryState[$value]['states'] = $states;
					}
				}
			}
		}
		return $this->countryState;
	}

	/** @return (string) HTML inputs code */
	function getInputs($options=array())
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/countrystatefield.php';
		return CountryStateField::getInputs($options);
	}

	function enqueueScript()
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/countrystatefield.php';
		CountryStateField::enqueueScript();
	}

}
?>
