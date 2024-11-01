<?php

namespace LWS\WOOSUPPLY;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class SupplierEdition
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

	/**	Checks if required fields are filled and valid.
	 * @return bool true if ok / false if some required field empty. */
	private function isValidPost()
	{
		$formats = array();
		$defaults = array();
		foreach ($this->getProperties() as $prop)
		{
			$index = 'supplier_' . $prop;
			$formats[$index] = 't';
			$defaults[$index] = '';
		}

		$args = \apply_filters('lws_adminpanel_post_parse', array(
			'format'   => $formats,
			'required' => array(
				'supplier_name' => true
			),
			'defaults' => $defaults,
			'labels'   => array(
				'supplier_name' => __("Supplier name", 'woosupply-lite')
			)
		));

		if (!$args['valid'])
		{
			\lws_admin_add_notice_once('singular_edit', $args['error'], array('level' => 'error'));
			return false;
		}
		else
			$this->_post = $args['values'];
		return apply_filters('lws_woosupply_supplier_form_is_valid', true);
	}

	private function getProperties()
	{
		return array('name', 'email', 'contact_firstname', 'contact_lastname', 'address', 'address_2', 'address_zipcode', 'address_city', 'address_country', 'address_state', 'phone_number', 'fax_number', 'tax_number');
	}

	/**
	 * saves supplier datas using $id if provided, new if not
	 * @param string $id the record id of supplier
	 * @return string saved id if all saved, false if something went wrong
	 */
	public function save($id)
	{
		if (!$this->isValidPost())
			return 0;

		require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';
		$supplier = empty($id) ? Supplier::create() : Supplier::get($id);
		if (empty($supplier))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Cannot found the supplier <b>%s</b> to update it.", 'woosupply-lite'), $id), array('level' => 'error'));
			return 0;
		}

		foreach ($this->getProperties() as $prop)
		{
			$index = 'supplier_' . $prop;
			$supplier->$prop = $this->_post[$index];
		}

		$supplier = \apply_filters('lws_woosupply_supplier_form_before_update', $supplier);
		if (!$supplier->update())
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Error occured during the supplier #%s update.", 'woosupply-lite'), $id), array('level' => 'error'));
			return 0;
		}

		return $supplier->getId();
	}

	/**
	 * asks to delete a record
	 * @param string $id supplier record id
	 * @return bool true if deleted, false if not
	 */
	public function delete($id)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';
		$supplier = Supplier::get($id);
		if (empty($supplier))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Cannot found the supplier <b>%s</b>.", 'woosupply-lite'), $id), array('level' => 'warning'));
			return false;
		}

		global $wpdb;
		if (0 < ($oc = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->lws_woosupply_supplierorder} WHERE supplier_id=%d", $id))))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__('Cannot delete the supplier <b>%1$s</b> since %2$d orders are linked.', 'woosupply-lite'), $supplier->name, $oc), array('level' => 'warning'));
			return false;
		}

		if (empty($supplier->delete()))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("An error occured during the supplier <b>%s</b> deletion.", 'woosupply-lite'), $id), array('level' => 'error'));
			return false;
		}
		return true;
	}

	/**
	 * Show HTML bloc with datas if $id exists
	 * @param string $id supplier record id
	 * @return bool true if $id found, false if not
	 */
	public function show($id = 0)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';
		$supplier = empty($id) ? Supplier::create() : Supplier::get($id);
		if (empty($supplier))
		{
			\lws_admin_add_notice_once('singular_edit', sprintf(__("Cannot load the supplier <b>%s</b>.", 'woosupply-lite'), $id), array('level' => 'warning'));
			return false;
		}

		$this->echoForm($supplier);
		return true;
	}

	/**
	 * echoes the form with supplier datas
	 * @param Supplier object, empty or not, depends on previous load
	 */
	private function echoForm($supplier)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/countrystatefield.php';
		\LWS\WOOSUPPLY\CountryStateField::enqueueScript();

		$title = empty($supplier->getId()) ? __("New supplier", 'woosupply-lite') : sprintf(__("SUPPLIER - %s", 'woosupply-lite'), $supplier->name);
		$str = "<h1 class='lws-woosupply-title'>$title</h1>";

		$labels = array(
			'title'     => __("Supplier", 'woosupply-lite'),
			'name'      => __("Name", 'woosupply-lite'),
			'email'     => __("EMail", 'woosupply-lite'),
			'fname' 	=> __("Contact firstname", 'woosupply-lite'),
			'lname' 	=> __("Contact lastname", 'woosupply-lite'),
			'addr1'     => __("Address line 1", 'woosupply-lite'),
			'addr2'     => __("Address line 2", 'woosupply-lite'),
			'city'      => __("City", 'woosupply-lite'),
			'country'     => __("Country", 'woosupply-lite'),
			'state'     => __("State", 'woosupply-lite'),
			'zip'       => __("Postcode / ZIP", 'woosupply-lite'),
			'phone'     => __("Phone", 'woosupply-lite'),
			'fax'       => __("Fax", 'woosupply-lite'),
			'tax'       => _x("<a target='_blank' href='https://en.wikipedia.org/wiki/VAT_identification_number'>VAT number</a>", "https://fr.wikipedia.org/wiki/Code_Insee#Num%C3%A9ro_de_TVA_intracommunautaire", 'woosupply-lite'),
			'info'     	=> __("Supplier Information", 'woosupply-lite'),
			'addr'     	=> __("Supplier Address", 'woosupply-lite'),
			'other'    	=> __("Other Informations", 'woosupply-lite'),
		);
		/*
		$inputs = \LWS\WOOSUPPLY\CountryStateField::getInputs(array(
			'country_name'    => 'supplier_address_country',
			'country_value'   => $supplier->address_country,
			'state_name'      => 'supplier_address_state',
			'state_value'     => $supplier->address_state,
			'enqueue_scripts' => false,
			'required'        => false
		));
		*/
		$inputs = \LWS\WOOSUPPLY\CountryStateField::getInputs(array(
			'country_name'    => 'supplier_address_country',
			'country_value'   => $supplier->address_country,
			'country_after'    => '</div>',
			'state_before'    => '<div class="lws-woosupply-label">' . $labels['state'] . '</div><div class="lws-woosupply-input lws_state_line_removable">',
			'state_name'      => 'supplier_address_state',
			'state_value'     => $supplier->address_state,
			'state_after'    => '</div>',
			'enqueue_scripts' => false,
			'disabled'        => false
		));

?>
		<div class='singular-edit-grid'>
			<div class='grid-item'>
				<div class='grid-item-title'><?= $labels['info'] ?></div>
				<div class='lws-woosupply-label'><?= $labels['name'] ?></div>
				<div class='lws-woosupply-input'>
					<input type='text' class='lws-input lws-required' id='supplier_name' name='supplier_name' value='<?= \esc_attr($supplier->name) ?>' required />
				</div>
				<div class='lws-woosupply-label'><?= $labels['email'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='email' name='supplier_email' value='<?= \esc_attr($supplier->email) ?>' />
				</div>
				<div class='lws-woosupply-label'><?= $labels['fname'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='text' name='supplier_contact_firstname' value='<?= \esc_attr($supplier->contact_firstname) ?>' />
				</div>
				<div class='lws-woosupply-label'><?= $labels['lname'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='text' name='supplier_contact_lastname' value='<?= \esc_attr($supplier->contact_lastname) ?>' />
				</div>
				<div class='lws-woosupply-label'><?= $labels['phone'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='tel' name='supplier_phone_number' value='<?= \esc_attr($supplier->phone_number) ?>' />
				</div>
				<div class='lws-woosupply-label'><?= $labels['fax'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='tel' name='supplier_fax_number' value='<?= \esc_attr($supplier->fax_number) ?>' />
				</div>
			</div>

			<div class='grid-item'>
				<div class='grid-item-title'><?= $labels['addr'] ?></div>
				<div class='lws-woosupply-label'><?= $labels['addr1'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='text' name='supplier_address' value='<?= \esc_attr($supplier->address) ?>' />
				</div>
				<div class='lws-woosupply-label'><?= $labels['addr2'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='text' name='supplier_address_2' value='<?= \esc_attr($supplier->address_2) ?>' />
				</div>
				<div class='lws-woosupply-label'><?= $labels['city'] ?></div>
				<div class='lws-woosupply-input'>
					<input class='lws-input' type='text' name='supplier_address_city' value='<?= \esc_attr($supplier->address_city) ?>' />
				</div>
				<div class='lws-woosupply-label'><?= $labels['country'] ?></div>
				<div class='lws-woosupply-input'>
					<?= $inputs ?>
					<div class='lws-woosupply-label'><?= $labels['zip'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='supplier_address_zipcode' value='<?= \esc_attr($supplier->address_zipcode) ?>' />
					</div>
				</div>

				<div class='grid-item'>
					<div class='grid-item-title'><?= $labels['other'] ?></div>
					<div class='lws-woosupply-label'><?= $labels['tax'] ?></div>
					<div class='lws-woosupply-input'>
						<input class='lws-input' type='text' name='supplier_tax_number' value='<?= \esc_attr($supplier->tax_number) ?>' />
					</div>
				</div>
			</div>
	<?php
	}
}

?>