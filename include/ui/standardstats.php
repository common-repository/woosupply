<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**	Provides several widgets to present business statistics. */
class StandardStats
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

	/** Output the standard statistics window */
	function showStats()
	{
		$html = "<div class='lws-woosupply-stat-container'><div class='lws-woosupply-stat-cont-left'>";
		$html .= $this->getSettingsPanel();
		$html .= $this->getOverviewPanel();
		//$html .= $this->getGoProPanel();
		$html .= "</div><div class='lws-woosupply-stat-cont-right'>";
		$html .= $this->getChartPanel();
		$html .= $this->getTablePanel();
		$html .= "</div>";
		$html .= "</div>";
		echo $html;
	}

	// Show the statistics settings panel
	function getSettingsPanel()
	{
		$labels = array(
			'settings' => _x("Statistics Settings", "statistics", 'woosupply-lite'),
			'supord' => _x("Supplier orders", "statistics", 'woosupply-lite'),
			'allord' => _x("All orders", "statistics", 'woosupply-lite'),
			'ordmar' => _x("All orders and margin", "statistics", 'woosupply-lite'),
			'start' => _x("Start Date", "statistics", 'woosupply-lite'),
			'end' => _x("End Date", "statistics", 'woosupply-lite')
		);
		$today = \date_create();
		$min = $today->format('Y-m-01');
		$max = \date_create($min)->add(new \DateInterval('P1M'))->sub(new \DateInterval('P1D'))->format('Y-m-d');

		$html = "<div class='lws-woosupply-stat-box lws-whitebg'><div class='lws-woosupply-subform'>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='grid-item-title'>{$labels['settings']}</div></div>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='lws-woosupply-label'>{$labels['start']}</div>";
		$html .= "<div class='lws-woosupply-input'><input class='lws-input lws-ignore-confirm' id='{$this->resumeRangeIds['min']}' value='{$min}' type='date' disabled></div></div>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='lws-woosupply-label'>{$labels['end']}</div>";
		$html .= "<div class='lws-woosupply-input'><input class='lws-input lws-ignore-confirm' id='{$this->resumeRangeIds['max']}' value='{$max}' type='date' disabled></div></div>";
		$html .= "<div class='lws-woosupply-subform-line'>";
		$html .= "<div class='lws-woosupply-stat-button mgr5 lws-icon lws-icon-previous-page lws_woosupply_stats_period_previous' data-max-id='{$this->resumeRangeIds['max']}' data-min-id='{$this->resumeRangeIds['min']}'></div>";
		$html .= "<div class='lws-woosupply-stat-button lws-icon lws-icon-refresh'></div>";
		$html .= "<div class='lws-woosupply-stat-button mgl5 lws-icon lws-icon-next-page lws_woosupply_stats_period_next' data-max-id='{$this->resumeRangeIds['max']}' data-min-id='{$this->resumeRangeIds['min']}'></div>";
		$html .= "</div></div></div>";
		return $html;
	}

	// Show the statistics overview panel
	function getOverviewPanel()
	{
		$labels = array(
			'overview' => _x("Statistics Overview", "statistics", 'woosupply-lite'),
			'buy' => _x("Net buy", "statistics", 'woosupply-lite'),
			'sale' => _x("Net sales", "statistics", 'woosupply-lite'),
			'margin' => _x("Margin", "statistics", 'woosupply-lite'),
		);

		$html = "<div id='lws_woosupply_statistics_balance_resume' data-ajax='lws_woosupply_statistics_balance' class='lws-woosupply-stat-box lws-whitebg' data-max-id='{$this->resumeRangeIds['max']}' data-min-id='{$this->resumeRangeIds['min']}'><div class='lws-woosupply-subform'>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='grid-item-title'>{$labels['overview']}</div></div>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='lws-woosupply-stat-sum-icon lws-icon lws-icon-arrow-down lws-stat-purple'></div>";
		$html .= "<div class='lws-woosupply-stat-sum-cont'><div id='lws_woosupply_statistics_balance_spent' class='lws-woosupply-stat-sum-amount lws-stat-purple'>0</div>";
		$html .= "<div class='lws-woosupply-stat-sum-text'>{$labels['buy']}</div></div></div>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='lws-woosupply-stat-sum-icon lws-icon lws-icon-arrow-up lws-stat-blue'></div>";
		$html .= "<div class='lws-woosupply-stat-sum-cont'><div id='lws_woosupply_statistics_balance_gain' class='lws-woosupply-stat-sum-amount lws-stat-blue'>0</div>";
		$html .= "<div class='lws-woosupply-stat-sum-text'>{$labels['sale']}</div></div></div>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='lws-woosupply-stat-sum-icon lws-icon lws-icon-trend-up lws-stat-orange'></div>";
		$html .= "<div class='lws-woosupply-stat-sum-cont'><div id='lws_woosupply_statistics_balance_margin' class='lws-woosupply-stat-sum-amount lws-stat-orange'>0</div>";
		$html .= "<div class='lws-woosupply-stat-sum-text'>{$labels['margin']}</div></div></div>";
		$html .= "</div></div>";
		return $html;
	}

	// Show the statistics settings panel
	function getGoProPanel()
	{
		$texts = array(
			'gopro' => _x("Upgrade to pro version", "statistics", 'woosupply-lite'),
			'1' => _x("Upgrade to Pro Version to get access to many other statistics and informations :", "statistics", 'woosupply-lite'),
			'2' => _x("Products statistics", "statistics", 'woosupply-lite'),
			'3' => _x("Stock variations", "statistics", 'woosupply-lite'),
			'4' => _x("Enhanced Dashboard", "statistics", 'woosupply-lite'),
			'5' => _x("Custom Periods", "statistics", 'woosupply-lite'),
			'6' => _x("…", "statistics", 'woosupply-lite')
		);

		$html = "<div class='lws-woosupply-stat-box lws-darkgrey'><div class='lws-woosupply-subform'>";
		$html .= "<div class='lws-woosupply-subform-line'><div class='grid-item-title'><b>{$texts['gopro']}</b></div></div>";
		$html .= "<div class='lws-woosupply-subform-line'>{$texts['1']}</div>";
		$html .= "<div class='lws-woosupply-subform-line'><ul>";
		$html .= "<li>{$texts['2']}</li>";
		$html .= "<li>{$texts['3']}</li>";
		$html .= "<li>{$texts['4']}</li>";
		$html .= "<li>{$texts['5']}</li>";
		$html .= "<li>{$texts['6']}</li>";
		$html .= "</div>";
		$html .= "</div></div>";
		return $html;
	}

	// Show the statistics overview panel
	function getChartPanel()
	{
		$labels = array(
			'overview' => _x("Statistics Overview", "statistics", 'woosupply-lite'),
			'buy' => _x("Net purchases", "statistics", 'woosupply-lite'),
			'sale' => _x("Net sales", "statistics", 'woosupply-lite'),
			'margin' => _x("Margin", "statistics", 'woosupply-lite'),
		);

		$html = "<div id='lws_woosupply_statistics_chart' class='lws-woosupply-stat-box lws-whitebg'>";
		$html .= "<canvas id='lws-wsstat-chart'></canvas>";
		$html .= "</div>";
		return $html;
	}

	// Show the statistics overview panel
	function getTablePanel()
	{
		$headers = array(
			'order_id' => array(_x("Order Id.", "Statistics Table", 'woosupply-lite'), 'str'),
			'order_date' => array(_x("Date", "Statistics Table", 'woosupply-lite'), 'str'),
			'name' => array(_x('Supplier/Customer Name', 'Statistics Table', 'woosupply-lite'), 'str'),
			'total_spent' => array(_x("Purchases Amount", "Statistics Table", 'woosupply-lite'), 'price'),
			'total_sales' => array(_x("Sales Amount", "Statistics Table", 'woosupply-lite'), 'price'),
		);
		$action = 'lws_woosupply_statistics_resume';
		$classes = 'lws-woosupply-stat-content-table lws_woosupply_statistics_ajax_source lws_woosupply_statistics_table_sortable';
		$currencySymbol = \lws_ws()->getCurrencySymbol();

		$html = "<div id='lws_woosupply_statistics_table' data-ajax='lws_woosupply_statistics_resume' class='lws-woosupply-stat-box lws-whitebg' data-max-id='{$this->resumeRangeIds['max']}' data-min-id='{$this->resumeRangeIds['min']}'>";
		$html .= "<div class='lws-woosupply-stats-content'>";
		$html .= "<table class='{$classes}' data-currency='{$currencySymbol}' data-ajax='{$action}' data-chart-id='{$this->resumeRangeIds['chart']}' data-max-id='{$this->resumeRangeIds['max']}' data-min-id='{$this->resumeRangeIds['min']}' cellspacing='0' cellpadding='0'><thead><tr>";
		foreach( $headers as $column => $value ){
			$html .= "<th data-column='$column' data-sorttype='{$value[1]}'>";
			$html .= "<div class='lws-woosupply-stat-th-container'><div class='lws-woosupply-stat-th-text'>{$value[0]}</div><div class='lws-woosupply-stat-th-sort'></div></div>";
			$html .= "</th>";
		}
		$html .= "</tr></thead><tbody></tbody>";
		$html .= "</table></div>";
		$html .= "</div>";
		return $html;
	}


	function enqueueStandardStatsScripts()
	{
		\wp_enqueue_script('jquery');
		\wp_enqueue_script('lws-base64');
		\wp_enqueue_script('lws-tools');
		\wp_enqueue_script('woosupply-lite'.'_statistics_script');
		\wp_enqueue_style('dashicons');
		\wp_enqueue_style('woosupply-lite'.'_statistics_style');
		\wp_enqueue_script('lws-chart-js');
		\wp_enqueue_style('lws-chart-js');

		\wp_enqueue_script('woosupply-lite'.'_balance_script', LWS_WOOSUPPLY_JS . '/balance.js', array('woosupply-lite'.'_statistics_script'), LWS_WOOSUPPLY_VERSION, true);
		\wp_enqueue_style('woosupply-lite'.'_balance_style', LWS_WOOSUPPLY_CSS . '/balance.css', array('woosupply-lite'.'_statistics_style'), LWS_WOOSUPPLY_VERSION);
	}

	protected function __construct()
	{
		if( is_admin() )
		{
			$this->dashboardLiteId = 'woosupply-lite'.'_statistics_lite';
			$this->resumeRangeIds = array(
				'min' => 'lws_woosupply_stats_period_min',
				'max' => 'lws_woosupply_stats_period_max',
				'chart' => 'lws_woosupply_statistics_chart'
			);

			\add_action( 'wp_dashboard_setup', array($this, 'registerDashboard') );
			\add_action( 'admin_enqueue_scripts', array($this, 'dashboardEnqueueScripts'), 10, 1 );
		}
	}

	function registerDashboard()
	{
		if( \current_user_can(\apply_filters('lws_woosupply_dashboard_statistics_capacity', 'view_purchases')) )
		{
			// optional args allow use to add a config ui if needed.
			\wp_add_dashboard_widget($this->dashboardLiteId, _x("WooSupply status", "Dashboard widget title", 'woosupply-lite'), array($this, 'dashboardLite'), array($this, 'dashboardLiteConfig') );
		}
	}

	function dashboardEnqueueScripts($hook)
	{
		$screen = \get_current_screen();
		if( isset($screen->id) && 'dashboard' === $screen->id )
		{
			\wp_enqueue_script('lws-base64');
			\wp_enqueue_script('lws-tools');
			\wp_enqueue_style('dashicons');
			\wp_enqueue_script($this->dashboardLiteId.'_script', LWS_WOOSUPPLY_JS . '/dashboard.js', array('jquery', 'lws-base64', 'lws-tools'), LWS_WOOSUPPLY_VERSION, true);
			\wp_enqueue_style($this->dashboardLiteId.'_style', LWS_WOOSUPPLY_CSS . '/dashboard.css', array(), LWS_WOOSUPPLY_VERSION);
		}
	}

	/** Option form for lws_woosupply_statistics_lite widget.
	 * Manage more options with hook 'lws_woosupply_dashboard_status_list_config'. */
	function dashboardLiteConfig()
	{
		if( isset($_POST['submit']) )
		{
			if( isset($_POST['lws_woosupply_day_start_of_month']) && intval($_POST['lws_woosupply_day_start_of_month']) > 0 && intval($_POST['lws_woosupply_day_start_of_month']) <= 31 )
			{
				$d = intval($_POST['lws_woosupply_day_start_of_month']);
				if( 0 < $d && $d <= 31 )
					\update_option( 'lws_woosupply_day_start_of_month', $d );
			}
		}

		$startday = esc_attr(max(1, intval(\get_option('lws_woosupply_day_start_of_month', 1))));
		$label = __("Month starts at day", 'woosupply-lite');
		echo "<p><label>$label<input type='number' min='1' max='31' name='lws_woosupply_day_start_of_month' value='$startday' /></label></p>";

		\do_action('lws_woosupply_dashboard_status_list_config');
	}

	/**	Display a dashboard widget with few supply order status.
	 *
	 * Use 'lws_woosupply_dashboard_status_list' hook to add item in the status list.
	 * This filter use an array(dom_id => dom_html_content).
	 * In list items use a <span class='lws_woosupply_statistic_value' data-statistic='something' data-source='dashboard'>XXX</span>
	 * where 'something' has a relative 'wp_ajax_lws_woosupply_statistics_something' ajax entry. */
	function dashboardLite()
	{
		$pendingUrl = \esc_attr(\add_query_arg(array('page'=>'lws_woosupply_supplierorder', 'solStatus'=>'ws_ack|ws_sent'), \admin_url('admin.php')));

		$ul = array(
			'this-month-spending' => sprintf(_x("<strong>%s</strong> bought this month", "dashboard", 'woosupply-lite'), "<span class='lws_woosupply_statistic_value' data-statistic='this_month_spending' data-source='dashboard'>&nbsp;</span>"),
			'pending-order' => "<a href='$pendingUrl'>".sprintf(_x("<strong>%s supply orders</strong> awaiting supplier feedback", "dashboard", 'woosupply-lite'), "<span class='lws_woosupply_statistic_value' data-statistic='pending_order' data-source='dashboard'>&nbsp;</span>")."</a>"
		);

		echo "<ul class='lws-woosupply-status-list'>";
		foreach( \apply_filters('lws_woosupply_dashboard_status_list', $ul) as $id => $li )
		{
			$class = \esc_attr($id) . ' lws_woosupply_statistic_cell';
			echo "<li class='$class'><div class='lws-woosupply-status-list-item'>{$li}</div></li>";
		}
		echo "</ul>";
	}

}

?>
