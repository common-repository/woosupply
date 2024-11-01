<?php
namespace LWS\WOOSUPPLY;
if( !defined( 'ABSPATH' ) ) exit();

/** Set an address country/state couple
 * options ids are {$this->m_Id}_country and {$this->m_Id}_state
 * $extra accepts country and state keys, else get from get_option.
 */
class CountryStateField extends \LWS\Adminpanel\Pages\Field
{
	/** @return field html. */
	public static function compose($id, $extra=null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function register($page)
	{
		$this->ownerPage = $page;
		if( !$this->isGizmo() )
		{
			\register_setting( $page, $this->id().'_country' );
			\register_setting( $page, $this->id().'_state' );
		}
		return $this;
	}

	public function input()
	{
		echo $this->html();
	}

	private function html()
	{
		$id = esc_attr($this->id());
		$cname = $id.'_country';
		$sname = $id.'_state';
		$args = array(
			'country_name'    => $cname,
			'country_value'   => $this->hasExtra('country') ? $this->getExtraValue('country') : \get_option($cname),
			'state_name'      => $sname,
			'state_value'     => $this->hasExtra('state') ? $this->getExtraValue('state') : \get_option($sname),
			'required'		  => false,
			'state_before'    => $this->hasExtra('state_before') ? $this->getExtraValue('state_before') : '',
			'state_after'     => $this->hasExtra('state_after') ? $this->getExtraValue('state_after') : '',
			'country_after'   => $this->hasExtra('country_after') ? $this->getExtraValue('country_after') : '',
			'country_before'  => $this->hasExtra('country_before') ? $this->getExtraValue('country_before') : ''
		);
		return static::getInputs($args);
	}

	/**	@param $options (array) with keys:
	 * * country_name, state_name the input name attribute (also used as id)
	 * * country_value, state_value the input value attribute
	 * * country_class, state_class css classes appended to the input
	 * * country_before, country_after, state_before, state_after (string) respectively wrapped around country and state input.
	 * * state_dom_id use state_name by default. If state_before and state_after wrap the state input into a any DOM, set this id to hide it totaly instead state input only.
	 * * require (bool) add class and attributes meaning input is require.
	 * * enqueue_scripts (bool) enqueue the javascripts at the same.
	 *	@return (string) HTML inputs code */
	static function getInputs($options=array())
	{
		$options = \wp_parse_args($options, array(
			'country_name'    => '',
			'country_value'   => '',
			'country_class'   => 'lws-input',
			'state_name'      => '',
			'state_value'     => '',
			'state_class'     => 'lws-input',
			'country_before'  => '',
			'country_after'   => '',
			'state_before'    => '',
			'state_after'     => '',
			'state_dom_id'		=> '',
			'required'        => true,
			'enqueue_scripts' => true,
			'disabled'        => false
		));

		$cattr = '';
		$cdata = '';
		if( $options['required'] )
		{
			$cattr .= ' required';
			$cdata = " data-required='1'";
		}

		require_once LWS_WOOSUPPLY_INCLUDES . '/countrystate.php';

		$source = '';
		if( !empty(CountryState::instance()->getList()) )
		{
			$options['country_class'] .= ' lws_woosupply_country_select lac_select';
			$source = " data-source='" . \esc_attr(base64_encode(json_encode(CountryState::instance()->getList()))) . "'";
		}

		$options['country_value'] = \esc_attr($options['country_value']);
		$options['state_value'] = \esc_attr($options['state_value']);

		if( empty($options['state_dom_id']) )
			$options['state_dom_id'] = $options['state_name'];

		if( $options['enqueue_scripts'] )
			static::enqueueScript();

		$icountry = "<input$cattr$cdata$source class='{$options['country_class']}' type='text' id='{$options['country_name']}' name='{$options['country_name']}' value='{$options['country_value']}' data-stateid='{$options['state_dom_id']}' />";
		$istate = "<input class='{$options['state_class']}' type='text' id='{$options['state_name']}' name='{$options['state_name']}' value='{$options['state_value']}' />";
		return $options['country_before'] . $icountry . $options['country_after'] . $options['state_before'] . $istate . $options['state_after'];
	}

	static function enqueueScript()
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/countrystate.php';
		if( !empty(CountryState::instance()->getList()) )
		{
			\wp_enqueue_script('jquery');
			\wp_enqueue_script('lws-base64');
			\wp_enqueue_script('lws-lac-select');
			\wp_enqueue_script('lws-state-select', LWS_WOOSUPPLY_JS.'/stateselect.js', array('jquery', 'lws-base64', 'lws-lac-select'), LWS_WOOSUPPLY_VERSION, true);
		}
	}
}

?>
