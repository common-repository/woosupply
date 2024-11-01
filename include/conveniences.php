<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**	Provided for convenience.
 *	Access to some features without including anything in modules. */
class Conveniences
{
	function getSupplierOrderStatusList()
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';
		return SupplierOrder::statusList();
	}

	/** @return -1 if the first status is lower than the second, 0 if they are equal, and 1 if the second is lower.
	 *	Status are compared relatively to their position in order status list @see getSupplierOrderStatusList().
	 *	@param $strict (bool, default false) when a status does not exist, if strict is true this function return false. Else not found status is considered as the lowest. */
	function cmpOrderStatus($status1, $status2, $strict=false)
	{
		$list = array_keys($this->getSupplierOrderStatusList());
		$p1 = array_search($status1, $list);
		$p2 = array_search($status2, $list);
		if( !$strict )
		{
			if( $p1 === false ) $p1 = -1;
			if( $p2 === false ) $p2 = -1;
		}
		else if( $p1 === false || $p2 === false )
			return false;

		if( $p1 < $p2 ) return -1;
		else if( $p1 > $p2 ) return 1;
		else return 0;
	}

	function formatOrderNumber($orderId)
	{
		if( !isset($this->order_prefix) )
			$this->order_prefix = \get_option('lws_woosupply_supplie_order_id_prefix', '');
		if( !isset($this->nbdigits) )
			$this->nbdigits = \get_option('lws_woosupply_supplie_order_id_digits');

		$num = empty($this->nbdigits) ? $orderId : str_pad($orderId, $this->nbdigits,'0', STR_PAD_LEFT);
		return $this->order_prefix . $num;
	}

	function getSupplierOrder($id, $getOrCreate=false)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierorder.php';
		return (empty($id) && $getOrCreate) ? SupplierOrder::create() : SupplierOrder::get($id);
	}

	function getSupplierProduct($id, $getOrCreate=false)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplierproduct.php';
		return (empty($id) && $getOrCreate) ? SupplierProduct::create() : SupplierProduct::get($id);
	}

	function getSupplier($id, $getOrCreate=false)
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/supplier.php';
		return (empty($id) && $getOrCreate) ? Supplier::create() : Supplier::get($id);
	}

	function getCountryState()
	{
		require_once LWS_WOOSUPPLY_INCLUDES . '/countrystate.php';
		return CountryState::instance();
	}

	/** return a format string to use with  %1$s is city, %2$s is zip */
	function getCityZipFormat()
	{
		return \apply_filters('lws_woosupply_city_zip_format', \get_option('lws_woosupply_pdf_address_city_zip_order', 'zc')=='zc' ? '%2$s %1$s' : '%1$s, %2$s');
	}

	/** @return a new Dompdf instance @see https://github.com/dompdf/dompdf
	 * @param $content (string|false) if a content is given, it is rendered in the pdf that will be ready to be streamed.
	 * @param $paperSize (string) 'letter', 'legal', 'A4', etc.
	 * @param $orientation (string) 'portrait' or 'landscape'.
	 * @param $attributes (array) @see Dompdf::Options::set */
	function getPDF($content=false, $paperSize='A4', $orientation='portrait', $attributes=null)
	{
		if( !isset($this->autoloadDompdf) || !$this->autoloadDompdf )
		{
			require_once LWS_WOOSUPPLY_ASSETS . '/dompdf/src/Autoloader.php';
			\Dompdf\Autoloader::register();
			$this->autoloadDompdf = true;
		}

		$options = new \Dompdf\Options();
		if( empty($attributes) || !is_array($attributes) )
			$attributes = array();
		$attributes = \wp_parse_args($attributes, array(
			'isHtml5ParserEnabled' => true,
			'defaultFont' => 'DejaVu Sans',
			'isRemoteEnabled' => true,
			'isPhpEnabled' => true // allows writing html <script type="text/php">$PAGE_NUM</script> / <script type="text/php">$PAGE_COUNT</script>
		));

		$options->set($attributes);
		$dompdf = new \Dompdf\Dompdf($options);
		$contxt = stream_context_create([
			'ssl' => [
				'verify_peer' => FALSE,
				'verify_peer_name' => FALSE,
				'allow_self_signed'=> TRUE
			]
		]);
		$dompdf->setHttpContext($contxt);

		if($content !== false)
			$dompdf->loadHtml($content);

		$dompdf->setPaper($paperSize, $orientation);
		if($content !== false)
			$dompdf->render();

		return $dompdf;
	}

	function getCurrencySymbol($currency='')
	{
		$symbol = '';
		if( function_exists('\get_woocommerce_currency_symbol') )
			$symbol = \get_woocommerce_currency_symbol($currency);
		return \apply_filters('lws_woosupply_currency_symbol_get', $symbol, $currency);
	}

	function getCurrentCurrency()
	{
		$currency = '';
		if( function_exists('\get_woocommerce_currency') )
			$currency = \get_woocommerce_currency($currency);
		return \apply_filters('lws_woosupply_currency_get', $currency);
	}

	/** @param float @return string */
	function getDisplayQuantity($qty=0.0)
	{
		if( !isset($this->quantityDecimals) )
			$this->quantityDecimals = \get_option('lws_woosupply_quantity_decimals', 0);
		return \apply_filters('lws_woosupply_quantity_format', \number_format_i18n($qty, $this->quantityDecimals), $qty);
	}

	/** @param string @return float */
	function getRawQuantity($displayQty='0.0')
	{
		return \apply_filters('lws_woosupply_quantity_raw', $this->unlocaliseDecimal($displayQty), $displayQty);
	}

	/** @param float @return string */
	function getDisplayPrice($price=0.0)
	{
		if( function_exists('\wc_price') )
			$disp = $this->getWCFormatedPrice($price);
		else
			$disp = \number_format_i18n($price, 2);
		return \apply_filters('lws_woosupply_price_format', $disp, $price);
	}

	/** @param float @return string */
	private function getWCFormatedPrice($price=0.0)
	{
		if( function_exists('\wc_price') )
		{
			$args = $this->getWCArgs();
			$unformatted_price = $price;
			$negative          = $price < 0;
			$price             = apply_filters( 'raw_woocommerce_price', floatval( $negative ? $price * -1 : $price ) );
			$price             = apply_filters( 'formatted_woocommerce_price', number_format( $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] ), $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] );
			if( $negative )
				$price = '-'.$price;
		}
		return $price;
	}

	/** @return WCArgs WooCommerce default args */
	public function getWCArgs()
	{
		if( !isset($this->WCArgs) )
		{
			$this->WCArgs = array(
				'ex_tax_label'       => false,
				'currency'           => '',
				'decimal_separator'  => function_exists('wc_get_price_decimal_separator') ? \wc_get_price_decimal_separator() : '.',
				'thousand_separator' => function_exists('wc_get_price_thousand_separator') ? \wc_get_price_thousand_separator() : '',
				'decimals'           => function_exists('wc_get_price_decimals') ? \wc_get_price_decimals() : '2',
				'price_format'       => function_exists('get_woocommerce_price_format') ? \get_woocommerce_price_format() : '%2$s%1$s',
			);
			$this->WCArgs = apply_filters('wc_price_args', $this->WCArgs);
		}
		return $this->WCArgs;
	}

	/** @param float @return string */
	function getDisplayPriceWithCurrency($price=0.0, $currency='')
	{
		if( function_exists('\wc_price') )
		{
			$args = $this->getWCArgs();
			$negative          = $price < 0;
			$price = $this->getWCFormatedPrice($price);

			if ( apply_filters( 'woocommerce_price_trim_zeros', false ) && $args['decimals'] > 0 ) {
				$price = wc_trim_zeros( $price );
			}

			$disp = ($negative ? '-' : '');
			$disp .= html_entity_decode(sprintf($args['price_format'], get_woocommerce_currency_symbol($args['currency']), $price));
		}
		else
			$disp = \number_format_i18n($price, 2) . $this->getCurrencySymbol();
		return \apply_filters('lws_woosupply_price_format', $disp, $price);
	}

	/** @param string @return float */
	function getRawPrice($displayPrice='0.0')
	{
		return \apply_filters('lws_woosupply_price_raw', $this->unlocaliseDecimal($displayPrice), $displayPrice);
	}

	/** guess a float convertion whatever localisation format.
	 *	Decimal part is sorted out by first dot or comma found from right of the string. */
	function unlocaliseDecimal($number)
	{
		$dot = strrpos($number, '.');
		$comma = strrpos($number, ',');

		if( $dot === false && $comma === false )
		{
			return intval(preg_replace('/[^\d\+\-]/', '', $number));
		}
		else
		{
			if( $dot === false )
				$sep = $comma;
			else if( $comma === false )
				$sep = $dot;
			else
				$sep = max($dot, $comma);

			$int = preg_replace('/[^\d\+\-]/', '', substr($number, 0, $sep));
			$dec = preg_replace('/[^\d]/', '', substr($number, $sep+1));
			return floatval($int.'.'.$dec);
		}
	}

	/** @param $code if set, return only the label if any or the code itself if not found.
	 * @return array of unit. */
	function getQuantityUnits($code = false)
	{
		static $units = false;
		if( false === $units )
		{
			$units = array(
				''  => '',
				'μL'  => __("Microliter", 'woosupply-lite'),
				'MBF' => __("1000 Board Feet", 'woosupply-lite'),
				'MCF' => __("1000 Cubic Feet", 'woosupply-lite'),
				'ACR' => __("Acre", 'woosupply-lite'),
				'A'   => __("Ampere", 'woosupply-lite'),
				'ARP' => __("Arpent", 'woosupply-lite'),
				'BAG' => __("Bag", 'woosupply-lite'),
				'BL'  => __("Bale", 'woosupply-lite'),
				'BBL' => __("Barrel", 'woosupply-lite'),
				'BFT' => __("Board Foot", 'woosupply-lite'),
				'BK'  => __("Book", 'woosupply-lite'),
				'BOT' => __("Bottle", 'woosupply-lite'),
				'BOX' => __("Box", 'woosupply-lite'),
				'BCK' => __("Bucket", 'woosupply-lite'),
				'BE'  => __("Bundle", 'woosupply-lite'),
				'BU'  => __("Bushel", 'woosupply-lite'),
				'CAN' => __("Can", 'woosupply-lite'),
				'CG'  => __("Card", 'woosupply-lite'),
				'CAR' => __("Carton", 'woosupply-lite'),
				'CE'  => __("Case", 'woosupply-lite'),
				'CM'  => __("Centimeter", 'woosupply-lite'),
				'CDS' => __("Cord", 'woosupply-lite'),
				'CRT' => __("Crate", 'woosupply-lite'),
				'CM3' => __("Cubic centimeter", 'woosupply-lite'),
				'DM3' => __("Cubic decimeter", 'woosupply-lite'),
				'FT3' => __("Cubic foot", 'woosupply-lite'),
				'IN3' => __("Cubic inch", 'woosupply-lite'),
				'M3'  => __("Cubic meter", 'woosupply-lite'),
				'YD3' => __("Cubic yard", 'woosupply-lite'),
				'DAY' => __("Day", 'woosupply-lite'),
				'DE'  => __("Deal", 'woosupply-lite'),
				'DM'  => __("Decimeter", 'woosupply-lite'),
				'LE'  => __("Deliverable line item", 'woosupply-lite'),
				'DS'  => __("Display", 'woosupply-lite'),
				'DZN' => __("Dozen", 'woosupply-lite'),
				'EA'  => __("Each", 'woosupply-lite'),
				'FOZ' => __("Fluid Ounce US", 'woosupply-lite'),
				'FT'  => __("Foot", 'woosupply-lite'),
				'G'   => __("Gram", 'woosupply-lite'),
				'GRO' => __("Gross", 'woosupply-lite'),
				'000' => __("Group proportion", 'woosupply-lite'),
				'HA'  => __("Hectare", 'woosupply-lite'),
				'H'   => __("Hour", 'woosupply-lite'),
				'CEN' => __("Hundred", 'woosupply-lite'),
				'IN'  => __("Inch", 'woosupply-lite'),
				'JOB' => __("Job", 'woosupply-lite'),
				'KG'  => __("Kilogram", 'woosupply-lite'),
				'KM'  => __("Kilometer", 'woosupply-lite'),
				'KIT' => __("Kit", 'woosupply-lite'),
				'LF'  => __("Linear Foot", 'woosupply-lite'),
				'L'   => __("Liter", 'woosupply-lite'),
				'LOT' => __("Lot", 'woosupply-lite'),
				'LUG' => __("Lug", 'woosupply-lite'),
				'M'   => __("Meter", 'woosupply-lite'),
				'uM'  => __("Micrometer", 'woosupply-lite'),
				'MI'  => __("Mile", 'woosupply-lite'),
				'MG'  => __("Milligram", 'woosupply-lite'),
				'ML'  => __("Milliliter", 'woosupply-lite'),
				'MM'  => __("Millimeter", 'woosupply-lite'),
				'MIN' => __("Minute", 'woosupply-lite'),
				'MON' => __("Month", 'woosupply-lite'),
				'NAM' => __("Nanometer", 'woosupply-lite'),
				'OZ'  => __("Ounce", 'woosupply-lite'),
				'PAC' => __("Pack", 'woosupply-lite'),
				'PAD' => __("Pad", 'woosupply-lite'),
				'PR'  => __("Pair", 'woosupply-lite'),
				'PAL' => __("Pallet", 'woosupply-lite'),
				'1'   => __("Piece", 'woosupply-lite'),
				'PT'  => _x("Pint", "US liquid", 'woosupply-lite'),
				'LB'  => __("Pound", 'woosupply-lite'),
				'QT'  => _x("Quart", "US liquid", 'woosupply-lite'),
				'RM'  => __("Ream", 'woosupply-lite'),
				'ROL' => __("Roll", 'woosupply-lite'),
				'S'   => __("Second", 'woosupply-lite'),
				'SET' => __("Set", 'woosupply-lite'),
				'ST'  => __("Sheet", 'woosupply-lite'),
				'SQ'  => __("Square", 'woosupply-lite'),
				'CM2' => __("Square centimeter", 'woosupply-lite'),
				'FT2' => __("Square foot", 'woosupply-lite'),
				'IN2' => __("Square inch", 'woosupply-lite'),
				'KM2' => __("Square kilometer", 'woosupply-lite'),
				'M2'  => __("Square meter", 'woosupply-lite'),
				'MI2' => __("Square mile", 'woosupply-lite'),
				'MM2' => __("Square millimeter", 'woosupply-lite'),
				'YD2' => __("Square yard", 'woosupply-lite'),
				'TH'  => __("Thousand", 'woosupply-lite'),
				'TON' => _x("Ton", "short (2000 lb)", 'woosupply-lite'),
				'T'   => _x("Tonne", "metric ton, 1000 kg", 'woosupply-lite'),
				'TU'  => __("Tube", 'woosupply-lite'),
				'GAL' => __("US gallon", 'woosupply-lite'),
				'VIA' => __("Vial", 'woosupply-lite'),
				'W'   => __("Watt", 'woosupply-lite'),
				'WK'  => __("Weeks", 'woosupply-lite'),
				'YD'  => __("Yard", 'woosupply-lite'),
				'YR'  => __("Years", 'woosupply-lite'),
			);
		}

		if( $code !== false )
			return isset($units[$code]) ? $units[$code] : $code;
		else
			return $units;
	}

	/** @param $file as reported by $_FILES. Your form must have the attribute enctype="multipart/form-data" to get $_FILES.
	 * @param $path relative destination directory (will be created if not exists). Base directory is {$wp_content}/uploads/woosupply_uploads.
	 * @param $denyAccess if true, a .htaccess is set @see echoFileAndDie to send it.
	 * @return (string|false) the final location (complete filename with path) of the file on serveur hard drive or false on failure. */
	function uploadFile($file, $path, $filename='', $denyAccess=true)
	{
		if( !\apply_filters('lws_woosupply_file_upload_granted', true, $file, $path, $filename, $denyAccess) )
			return false;

		if( empty($path) && $denyAccess )
			error_log("Attempt to deny access to root woosupply uploads directory. This could affect unexpected features.");

		$upload_dir = wp_upload_dir();
		$dest = \trailingslashit($upload_dir['basedir']) . 'woosupply_uploads/';
		if( !empty($path) )
			$dest .= \trim($path, DIRECTORY_SEPARATOR);

		if( $denyAccess )
		{
			if( !\wp_mkdir_p($dest) )
			{
				error_log("Cannot create uploads directory : " . $dest);
				return false;
			}
			if( !file_put_contents($dest . '.htaccess', "deny from all") )
			{
				error_log("Cannot restrict access to cache dir : " . $dest);
				return false;
			}
		}

		$dest .= \trailingslashit($upload_dir['subdir']);
		if( !\wp_mkdir_p($dest) )
		{
			error_log("Cannot create uploads subdirectory : " . $dest);
			return false;
		}

		if( empty($filename) )
			$filename = $file['name'];
		$index = 0;
		$basename = '-'.$filename;
		while( file_exists($dest . $filename) )
			$filename = ++$index . $basename;

		if( move_uploaded_file($file['tmp_name'], $dest . $filename) )
		{
			return $dest . $filename;
		}
		else
		{
			error_log("Cannot move uploaded file to uploads directory : " . $dest . $filename);
			return false;
		}
	}

	/** Out partial content @see https://github.com/tuxxin/MP4Streaming/blob/master/streamer.php */
	function echoFileAndDie($filepath, $filename='', $mime='')
	{
		if( !\apply_filters('lws_woosupply_file_access_granted', true, $filepath, $filename, $mime) )
		{
			header('HTTP/1.1 403 File access denied');
			exit;
		}

		$fp = @fopen($filepath, 'rb');
		if( !$fp )
		{
			header('HTTP/1.1 503 File unavailable');
			exit;
		}
		if( empty($mime) ) // guess mime (file could be pdf, png, zip...)
		{
			if( function_exists('mime_content_type') )
				$mime = @mime_content_type($filepath);
			else
				$mime = \wp_check_filetype($filepath)['type'];
		}
		if( empty($filename) )
			$filename = basename($filepath);

		$size   = filesize($filepath); // File size
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte
		if( !empty($mime) )
			header("Content-type: {$mime}");
		header("Content-Disposition: attachment; filename={$filename}");
		//header("Accept-Ranges: 0-$length");
		header("Accept-Ranges: bytes");
		if (isset($_SERVER['HTTP_RANGE'])) {
			$c_start = $start;
			$c_end   = $end;
			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if (strpos($range, ',') !== false) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}
			if ($range == '-') {
				$c_start = $size - substr($range, 1);
			}else{
				$range  = explode('-', $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
			$c_end = ($c_end > $end) ? $end : $c_end;
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1;
			fseek($fp, $start);
			header('HTTP/1.1 206 Partial Content');
		}
		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: ".$length);
		$buffer = 1024 * 8;
		while(!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				$buffer = $end - $p + 1;
			}
			set_time_limit(0);
			echo fread($fp, $buffer);
			flush();
		}
		fclose($fp);
		exit();
	}

}

?>
