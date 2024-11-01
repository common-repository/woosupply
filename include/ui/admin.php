<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class Admin
{
	public function __construct()
	{
		lws_register_pages($this->pages());
		\add_action('admin_enqueue_scripts', array($this, 'scripts'));
	}

	public function scripts($hook)
	{
		if( false != strpos($hook, 'lws_woosupply') )
		{
			\wp_enqueue_style('dashicons');
			\wp_enqueue_style('lws-woosupply-css', LWS_WOOSUPPLY_CSS."/style.css", array(), LWS_WOOSUPPLY_VERSION);
			\wp_enqueue_style('lws-woosupply-pdf-stygen', LWS_WOOSUPPLY_CSS."/templates/order-stygenonly.css", array(), LWS_WOOSUPPLY_VERSION);

			\wp_enqueue_script('lws-switch');
			\wp_enqueue_style('lws-switch');
			\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));
		}
	}

	protected function pages()
	{
		return array(
			$this->pageOrders(    LWS_WOOSUPPLY_PAGE.'_supplierorder'),
			$this->pageSuppliers( LWS_WOOSUPPLY_PAGE.'_supplier'),
			$this->pageStatistics(LWS_WOOSUPPLY_PAGE.'_statistics'),
			$this->pageSettings(  LWS_WOOSUPPLY_PAGE.'_settings')
		);
	}

	private function formatSingularAddButton($pageId, $key, $label, $restrictedTab=array())
	{
		if( !empty($restrictedTab) && !is_array($restrictedTab) )
			$restrictedTab = array($restrictedTab);
		$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : '';

		if( empty($restrictedTab) || in_array($tab, $restrictedTab) )
		{
			$urlAdd = add_query_arg(array('page' => $pageId, $key => ''), admin_url('admin.php'));
			return "<a class='lws-adm-btn singular-add-btn' href='$urlAdd'>$label</a>";
		}
		else
			return '';
	}

	protected function pageOrders($pageId)
	{
		$key = 'supplierorder_id';

		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/supplierorderedition.php';
		$singular_edit = array(
			'form' => array(SupplierOrderEdition::instance(), 'show'),
			'save' => array(SupplierOrderEdition::instance(), 'save'),
			'delete' => array(SupplierOrderEdition::instance(), 'delete'),
			'key' => $key
		);

		return array(
			'id' => $pageId,
			'dashicons' => LWS_WOOSUPPLY_IMG.'/woosupply-icon.png',
			'title' => __("WooSupply", 'woosupply-lite'),
			'rights' => 'manage_purchases',
			'index'    => '56',
			'subtitle' => __("Orders", 'woosupply-lite'),
			'singular_edit' => $singular_edit,
			'text' => $this->formatSingularAddButton($pageId, $key, __("New Order", 'woosupply-lite'), array('', $pageId, '_pending', '_completed')),
			'toc'      => false,
			'tabs'     => array(
				'pending' => array(
					'id' => '_pending',
					'title' => __("Pending Orders", 'woosupply-lite'),
					'icon' => 'lws-icon-check-list',
					'groups' => array(
						array(
							'icon' => 'lws-icon-check-list',
							'title' => __("Pending Order List", 'woosupply-lite'),
							'editlist' => $this->createOrderEditList($key, true, array('ws_complete'))
						)
					)
				),
				'completed' => array(
					'id' => '_completed',
					'title' => __("Completed Orders", 'woosupply-lite'),
					'icon' => 'lws-icon-check-all',
					'groups' => array(
						array(
							'icon' => 'lws-icon-check-all',
							'title' => __("Completed Order List", 'woosupply-lite'),
							'editlist' => $this->createOrderEditList($key, false, array('ws_complete'), '_actions')
						)
					)
				)
			)
		);
	}

	/** @param $excludeMod is the given status array a blacklist (true) or a whitelist (false). */
	protected function createOrderEditList($key, $excludeMod=true, $statusList=array(), $hiddenColumns=array())
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/supplierorderlist.php';

		$filters = array(new \LWS\Adminpanel\EditList\FilterSimpleField('solSearch', __('Search...', 'woosupply-lite')));

		$listSource = new SupplierOrderList($excludeMod, $statusList);
		$links = array(''=>array('solStatus' => ''));
		foreach( $listSource->getStatusList() as $k => $s )
			$links[$k] = array('solStatus' => $k);

		if( !empty($hiddenColumns) )
			$listSource->hideColumns($hiddenColumns);

		if( count($links) > 2 )
			array_splice($filters, 0, 0, array(new \LWS\Adminpanel\EditList\FilterSimpleLinks($links, array(), '', array_merge(array(''=>__("All", 'woosupply-lite')), SupplierOrder::statusList()), 'solStatus')));

		$editlist = lws_editlist(
			SupplierOrderList::$Id,
			$key,
			$listSource,
			\LWS\Adminpanel\EditList::DEL | \LWS\Adminpanel\EditList::DUP,
			$filters
		);

		return $editlist;
	}

	protected function pageSuppliers($pageId)
	{
		$key = 'supplier_id';

		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/supplierlist.php';
		$editlist = lws_editlist(
			SupplierList::$Id,
			$key,
			new SupplierList(),
			\LWS\Adminpanel\EditList::DEL,
			array(
				new \LWS\Adminpanel\EditList\FilterSimpleField('slSearch', __('Search...', 'woosupply-lite'))
			)
		);

		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/supplieredition.php';
		$singular_edit = array(
			'form' => array(SupplierEdition::instance(), 'show'),
			'save' => array(SupplierEdition::instance(), 'save'),
			'delete' => array(SupplierEdition::instance(), 'delete'),
			'key' => $key
		);

		return array(
			'id' => $pageId,
			'title' => __("Suppliers", 'woosupply-lite'),
			'rights' => 'manage_purchases',
			'singular_edit' => $singular_edit,
			'text' => $this->formatSingularAddButton($pageId, $key, __("New Supplier", 'woosupply-lite'), array('', $pageId)),
			'toc'      => false,
			'tabs'     => array(
				array(
					'id' => $pageId,
					'title' => __("Suppliers", 'woosupply-lite'),
					'groups' => array(
						array(
							'title' => 'Supplier List',
							'editlist' => $editlist,
							'icon' => 'lws-icon-users-wm'
						)
					)
				)
			)
		);
	}

	protected function pageSettings($pageId)
	{
		return array(
			'id'       => $pageId,
			'title'    => __("WooSupply", 'woosupply-lite'),
			'rights'   => 'manage_purchases', // acces restriction to visit the page
			'subtitle' => __("Settings", 'woosupply-lite'),
			'tabs'     => array(
				'gensettings' => array(
					'id' => 'gensettings',
					'title'	=> __("General Settings", 'woosupply-lite'),
					'icon'	=> 'lws-icon-setup-preferences',
					'groups' => $this->settings()
				),
				'pdf'    => array(
					'id' => 'pdf',
					'title'  => __("PDF", 'woosupply-lite'),
					'icon'	=> 'lws-icon-pdf',
					'groups' => array(
						'pdf'     => $this->settingsPdf()
					)
				),/*
				'statistics'    => array(
					'id' => 'statistics',
					'title'  => __("Statistics", 'woosupply-lite'),
					'groups' => array(
// ...
					)
				)*/
			)
		);
	}

	protected function settings()
	{
		return array(
			'company' => $this->settingsCompany(),
			'documents' => $this->settingsDocuments(),
		);
	}

	protected function settingsPdf()
	{
		return array(
			'title' => 'PDF Settings',
			'icon'	=> 'lws-icon-pdf',
			'fields' => array(
				array(
					'id' => 'lws_woosupply_pdf_filename',
					'title' => __("PDF Filename", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array(
						'placeholder' => 'supplier-order'
					)
				),
				array(
					'id' => 'lws_woosupply_pdf_display',
					'title' => __("PDF Display", 'woosupply-lite'),
					'type' => 'radio',
					'extra' => array(
						'options' => array(
							'df' => __("Download PDF", 'woosupply-lite'),
							'nt' => __("Open PDF in a new browser tab", 'woosupply-lite')
						),
						'default' => 'nt',
					)
				),
				array(
					'id' => 'lws_woosupply_pdf_address_city_zip_order',
					'title' => __("Address Format", 'woosupply-lite'),
					'type' => 'radio',
					'extra' => array(
						'options' => array(
							'zc' => __("Zip code - City", 'woosupply-lite'),
							'cz' => __("City - Zip code", 'woosupply-lite')
						),
						'default' => 'zc',
						'help' => __("How address is displayed", 'woosupply-lite')
					)
				),
				array(
					'id' => 'lws_woosupply_pdf_template',
					'type' => 'stygen',
					'extra' => array(
						'html'=>LWS_WOOSUPPLY_PATH.'/include/templates/order.php',
						'css'=>LWS_WOOSUPPLY_URL.'/css/templates/order.css',
						'help' => __("Here you can customize the look of your PDF orders", 'woosupply-lite'),
						'subids' => array(
							'lws_woosupply_pdf_header_text',
							'lws_woosupply_pdf_end_of_document'
						)
					)
				),
			)
		);
	}



	protected function settingsCompany()
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';
		$placeholder = SupplierOrder::getDefaultDeliveryAddress();
		$labels = array(
			'country'   => __("Country", 'woosupply-lite'),
			'state'     => __("State", 'woosupply-lite'),
		);

		return array(
			'title' => __("Your company", 'woosupply-lite'),
			'icon' => 'lws-icon-office',
			'text' => __("This information is needed for all exchanges between you and your suppliers", 'woosupply-lite'),
			'function' => function(){\wp_enqueue_script('woosupply-lite'.'_admin', LWS_WOOSUPPLY_JS . '/admin.js', array('jquery'), LWS_WOOSUPPLY_VERSION, true);},
			'fields' => array(
				array(
					'id' => 'lws_woosupply_company_logo',
					'title' => __("Your logo", 'woosupply-lite'),
					'type' => 'media',
					'extra' => array('type' => 'image')
				),
				array(
					'id' => 'lws_woosupply_company_name',
					'title' => __("Company Name", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array(
						'placeholder' => $placeholder->delivery_name,
						'size' => '50'
					)
				),
				array(
					'id' => 'lws_woosupply_company_address',
					'title' => __("Address line 1", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array(
						'placeholder' => $placeholder->delivery_address,
						'size' => '50'
					)
				),
				array(
					'id' => 'lws_woosupply_company_address_2',
					'title' => __("Address line 2", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array(
						'placeholder' => $placeholder->delivery_address_2,
						'size' => '50'
					)
				),
				array(
					'id' => 'lws_woosupply_company_city',
					'title' => __("City", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array(
						'placeholder' => $placeholder->delivery_address_city,
						'size' => '50'
					)
				),
				array(
					'id' => 'lws_woosupply_company_zipcode',
					'title' => __("Post Code", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array('placeholder' => $placeholder->delivery_address_zipcode)
				),
				array(
					'id' => 'lws_woosupply_company', // 'lws_woosupply_company_country' / 'lws_woosupply_company_state'
					'title' => $labels['country'],
					'type' => 'countrystate',
					'extra' => array(
						'country_after' => '</td></tr>',
						'state_before' => '<tr class="lws_state_line_removable"><th scope="row"><label for="lws_woosupply_company_state"><div class="lws-field-label">'.$labels['state'].'</div></label></th><td>',
						'state_after' => '</td></tr>'
					)
				),
				array(
					'id' => 'lws_woosupply_company_tax_number',
					'title' => __("Tax number", 'woosupply-lite'),
					'type' => 'text'
				),
				array(
					'id' => 'lws_woosupply_company_email',
					'title' => __("Email address", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array(
						'help' => 'Email address used to send and receive documents',
						'placeholder' => $placeholder->delivery_email,
						'size' => '50'
					)
				)
			)
		);
	}

	protected function settingsDocuments()
	{
		return array(
			'title' => __("Documents", 'woosupply-lite'),
			'text' => __("Set of rules which will apply in your exchanges between you and our suppliers", 'woosupply-lite'),
			'icon' => 'lws-icon-recipe',
			'fields' => array(
				array(
					'id' => 'lws_woosupply_purchases_exclude_tax',
					'title' => __("B2B (Purchases prices exclude tax)", 'woosupply-lite'),
					'type' => 'box',
					'extra' => array('default'=>true)
				),
				array(
					'id' => 'lws_woosupply_supplie_order_id_prefix',
					'title' => __("Order number prefix", 'woosupply-lite'),
					'type' => 'text'
				),
				array(
					'id' => 'lws_woosupply_supplie_order_id_digits',
					'title' => __("Order number padding width", 'woosupply-lite'),
					'type' => 'text',
					'extra' => array(
						'type' => 'number'
					)
				)
			)
		);
	}

	protected function pageStatistics($pageId)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/ui/standardstats.php';

		return array(
			'id' => $pageId,
			'title' => __("Statistics", 'woosupply-lite'),
			'rights' => 'view_purchases',
			'function' => array(StandardStats::instance(), 'enqueueStandardStatsScripts'),
			'toc' => false,
			'tabs' => array(
				'ws_std_stat'  => array(
					'id' => 'ws_std_stat',
					'title'  => __("WooSupply Standard Statistics", 'woosupply-lite'),
					'groups' => array(
						array(
							'id'	=> 'show_stats',
							'title' => 'Statistics',
							'icon'	=> 'lws-icon-trend-up',
							'function' => array(StandardStats::instance(), 'showStats'),

						)
					)
				)
			)
		);
	}

}

?>
