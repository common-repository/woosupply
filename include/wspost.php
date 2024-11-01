<?php
namespace LWS\WOOSUPPLY;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**	WSPost abstract class need to know a few about who extends it
 *	Since it build database table name, key, etc. on first inheritent class name.
 *	We do not use abstract static in WSPost directly to keep PHP 5.2 compatiblity. */
interface IWSPost
{
	/** The basename used by WSPost to build any key, database tablename, etc.
	 * A simple implementation could be
	 * @code
	 *	public static getClassname()
	 *	{
	 *		return array_pop(explode('\\', get_class()));
	 *	}
	 * @endcode
	 */
	public static function getClassname();
}

/** Base class for any post like class.
 * Manage database actions and meta support.
 * Assume:
 *  * $_GET key build as "{$lower_classname_without_namespace}_id".
 *  * Relative databate table named "{$wpdb->prefix}lws_woosupply_{$lower_classname_without_namespace}".
 * Note: for a $classname_without_namespace @see IWSPost::getClassname
 **/
abstract class WSPost implements IWSPost
{
  protected $id = 0; // id of current record
  private $managedMeta = array();
  public $metas = array(); // if more fields provided from plugins, they can be added in this array with their keys

	/** @return (array of string) database table field names (same as class properties) */
  abstract function getAutoloadProperties();
  /** @return (array of striny{%d|%s}) An array of formats to be mapped to each of the getAutoloadProperties. If string, that format will be used for all of the getAutoloadProperties. */
  function getAutoloadPropertiesFormat(){ return null; }

	/**	@return (int) Id of the related database record. */
  public function getId()
  {
    return $this->id;
  }

  /** Reset Id */
  public function detach()
  {
    $this->id = 0;
    return $this;
  }

  /** compute the class relative key use in $_GET.
   *	Expect built as "{$lower_classname_without_namespace}_id" */
  static function getBasename($toLower=false)
  {
		$fullname = explode('\\', static::getClassname());
		$basename = array_pop($fullname);
		if( $toLower )
			$basename = strtolower($basename);
		return $basename;
	}

  /** compute the class relative key use in $_GET.
   *	Expect built as "{$lower_classname_without_namespace}_id" */
  static function getKey()
  {
		return static::getBasename(true).'_id';
	}

  /** compute the database table name relative to this class.
   *	Expect built as "{$wpdb->prefix}lws_woosupply_{$lower_classname_without_namespace}" */
  static function getTableName()
  {
		global $wpdb;
		return $wpdb->prefix . 'lws_woosupply_' . static::getBasename(true);
	}

  /** compute the database meta table name relative to this class.
   *	Expect built as "lws_woosupply_{$lower_classname_without_namespace}" */
  static function getMetaType()
  {
		return 'lws_woosupply_' . static::getBasename(true);
	}

  /** compute the database meta table name relative to this class.
   *	Expect built as "{$wpdb->prefix}lws_woosupply_{$lower_classname_without_namespace}" */
  static function getMetaTableName()
  {
		global $wpdb;
		return $wpdb->prefix . 'lws_woosupply_' . static::getBasename(true) . 'meta';
	}

  protected function __construct()
  {
		$this->id = 0;
		$this->metas = array();
	}

	/**	Get an instance of object and try to load it.
	*	If $id is omitted, look at $_REQUEST[$key] for an id.
	* @param (int) $id The object id to load.
	* @param (string) $key Optionnal, if not set, use static::getKey(). If no $id provided, check $_REQUEST[$key]
	* @return Instance of className, null if no id or false on error.
	*/
	static function get($id='', $key=false, $managedMetaKeys=false)
	{
		static $requested = array();
		$instance = null;
		if( !$key)
			$key = static::getKey();
		$basename = static::getBasename(true);

		if( empty($id) && isset($_REQUEST[$key]) )
			$id = sanitize_key($_REQUEST[$key]);

		if( isset($requested[$basename]) && ($requested[$basename]->getId() == $id || (empty($requested[$basename]->getId()) && empty($id))) )
			$instance = $requested[$basename];

		if( empty($instance) && !empty($id) )
		{
			$instance = static::create();
			if( !$instance->load($id) )
				return false;

			$instance = apply_filters('lws_woosupply_'.$basename.'_get', $instance, $id);
			if( isset($_REQUEST[$key]) && sanitize_key($_REQUEST[$key]) == $id )
				$requested[$basename] = $instance;
		}

		if( $managedMetaKeys !== false )
			$inst->addManagedMeta($managedMetaKeys);
		return $instance;
	}

	/**
	* @param string $className original class name
	* @return class instance empty
	*/
	static function create($managedMetaKeys=false)
	{
		$classname = apply_filters('lws_woosupply_class_name_' . static::getBasename(true), static::getClassname());
		$inst = new $classname();
		if( $managedMetaKeys !== false )
			$inst->addManagedMeta($managedMetaKeys);
		return $inst;
	}

	/**	Loads values from tableName
	* Only load declared properties.
	* @param string $tableName where to load row
	* @param string $id record id to load
	* @return bool true if id found, false if not
	**/
	public function load($id)
	{
		global $wpdb;
		$tableName = static::getTableName();
		$result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tableName WHERE id=%d", $id), ARRAY_A);

		if( !is_null($result) )
		{
			foreach( $result as $k => $v )
			{
				if( isset($this->$k) )
					$this->$k = $v;
				else
					error_log("Missmatch database - class / $tableName - ".get_class($this)." missing or uninitialized property $k.");
			}

			$this->metas = array();
			return true;
		}

		return false;
	}

	/**
	* set record into DB in $tableName
	* @param string $tableName where to add/update row
	* @param array $data all datas to add/update
	* @param array $format specify the format of each datas
	* @return bool true if values added, false in case of error
	*/
  public function update()
  {
		$ok = false;
		$tableName = static::getTableName();
		$format = $this->getAutoloadPropertiesFormat();

		\do_action('lws_woosupply_' . static::getBasename(true) . '_about_to_update', $this);

		$data = array();
		foreach( $this->getAutoloadProperties() as $prop )
			$data[$prop] = $this->getData($prop);

		global $wpdb;
		if( empty($this->id) )
		{
			if( $ok = $wpdb->insert($tableName, $data, $format) )
				$this->id = $wpdb->insert_id;
		}
		else
			$ok = (false !== $wpdb->update($tableName, $data, array('id'=>$this->id), $format, array('%d')));

		if( $ok && !empty($this->managedMeta) )
		{
			foreach( $this->managedMeta as $metaKey => $v )
			{
				if( isset($this->metas[$metaKey]) )
					$this->updateMeta($metaKey, $this->metas[$metaKey]);
				else
					$this->deleteMeta($metaKey);
			}
		}

		if( $ok )
			\do_action('lws_woosupply_' . static::getBasename(true) . '_updated', $this);
    return $ok;
	}

	/**	Delete data in database.
	 *	@return (int|false) count of deleted rows (main table + meta) or false on error. */
	public function delete()
	{
		$d = static::deleteById($this->id);
		$this->id = 0;
		return $d;
	}

	/** Provided for convenience. @see delete. */
	static public function deleteById($id)
	{
		if( empty($id) )
			return 0;

		\do_action('lws_woosupply_' . static::getBasename(true) . '_about_to_delete', $id);
		global $wpdb;
		if( false === ($m = $wpdb->delete(static::getMetaTableName(), array(static::getMetaType().'_id' => $id))) )
			return false;
		if( false === ($t = $wpdb->delete(static::getTableName(), array('id' => $id))) )
			return false;
		\do_action('lws_woosupply_' . static::getBasename(true) . '_deleted', $id);
		return $m + $t;
	}

	/**	Determine if a data property or local meta is set for this object.
	 *	@param (string) $meta_key Metadata key.
	 *	@return (bool) True of the key is set, false if not. */
	public function hasData($name)
	{
		if( isset($this->$name) )
			return true;
		else if( isset($this->metas[$name]) )
			return true;
		return false;
	}

	/**	This function read local data, it never looks at database.
	 *	@param $name name of the property or meta.
	 *	@param $dft the returned value if data is not found.
	 *	@param $escAttr (bool) if true, apply esc_attr before returning the value (default false).
	 *	@return local data value, direct property or from meta array. */
	public function getData($name, $dft=null, $tryLoadMeta=true, $escAttr=false)
	{
		$value = $dft;
		if( isset($this->$name) )
			$value = $this->$name;
		else if( isset($this->metas[$name]) )
			$value = $this->metas[$name];
		else if( $tryLoadMeta && $this->hasMeta($name) )
			$value = $this->getMeta($name, true);
		return $escAttr ? \esc_attr($value) : $value;
	}

	/**	Set a value as property if exists or as meta.
	 *	This function set data locally, it never writes in database.
	 *	@param $name name of the property or meta.
	 *	@param $value (mixed).
	 *	@param $unique (bool) Default is false, if data is set as meta only, it defines if value is appended or replace.
	 *	@param $removeMetaIfEmpty if $value is empty, $unique meta is removed.
	 *	@return this. */
	public function setData($name, $value, $unique=false, $removeMetaIfEmpty=true)
	{
		if( isset($this->$name) )
			$this->$name = $value;
		else if( $unique )
		{
			if( $removeMetaIfEmpty && (is_string($value)||is_numeric($value) ? !strlen($value) : empty($value)) )
				$this->removeData($name);
			else
				$this->metas[$name] = $value;
		}
		else
			$this->metas[$name][] = $value;
		return $this;
	}

	/** Locally remove a meta data.
	 * Do nothing if $name is a class property.
	 * @return (bool) if a data is really removed. */
	public function removeData($name)
	{
		if( isset($this->metas[$name]) )
		{
			unset($this->metas[$name]);
			return true;
		}
		return false;
	}

	/** Remove meta cache.
	 * @param $meta_keys (string|array) the keys of meta to clean. If empty, all meta cache is cleaned. */
	public function cleanMeta($meta_keys=false)
	{
		if( $meta_keys !== false && !empty($meta_keys) )
		{
			if( is_string($meta_keys) )
				$meta_keys = array($meta_keys);
			if( is_array($meta_keys) )
			{
				foreach( $meta_keys as $k )
				{
					if( isset($this->metas[$k]) )
						unset($this->metas[$k]);
				}
			}
		}
		else
			$this->metas = array();
	}

	/** Managed meta will be updated with the wspost at update.
	 * @param $meta_key (string|array) */
	public function addManagedMeta($meta_key)
	{
		if( is_array($meta_key) )
		{
			foreach( $meta_key as $k )
				$this->managedMeta[$k] = true;
		}
		else
			$this->managedMeta[$meta_key] = true;
	}

	/** Managed meta will be updated with the wspost at update.
	 * @param $meta_key (string|array) */
	public function setManagedMeta($meta_key)
	{
		$this->managedMeta = array();
		$this->addManagedMeta($meta_key);
	}

	/** Managed meta will be updated with the wspost at update. */
	public function getManagedMetas()
	{
		return array_keys($this->managedMeta);
	}

	/** Managed meta will be updated with the wspost at update. */
	public function isManagedMetas($meta_key)
	{
		return isset($this->managedMeta[$meta_key]);
	}

	/** Managed meta will be updated with the wspost at update. */
	public function unmanagedMetas($meta_key)
	{
		if( $meta_key === true )
			$this->managedMeta = array();
		if( isset($this->managedMeta[$meta_key]) )
			unset($this->managedMeta[$meta_key]);
	}

	/**	Determine if a meta key is set for a given object.
	 *	@param (string) $meta_key Metadata key.
	 *	@return (bool) True of the key is set, false if not. */
	public function hasMeta($meta_key)
	{
		return \metadata_exists(static::getMetaType(), $this->id, $meta_key);
	}

	/**	Get meta to relative table.
	 *	@param (string) $meta_key Metadata key. If not specified, retrieve all metadata for the specified object.
	 *	@param (bool)   $single If true, return only the first value of the specified meta_key. This parameter has no effect if meta_key is not specified.
	 *	@return Single metadata value, or array of values. If the $this->id parameters are invalid, false is returned. If the meta value isn't set, an empty string or array is returned, respectively. */
	public function getMeta($meta_key='', $single=false)
	{
		$meta_value = \get_metadata(static::getMetaType(), $this->id, $meta_key, $single);
		if( $single )
			$this->metas[$meta_key] = $meta_value;
		else
			$this->metas[$meta_key][] = $meta_value;
		return $meta_value;
	}

	/**	Add meta to relative table.
	 *	@param (string) $meta_key   Metadata key
	 *	@param (mixed)  $meta_value Metadata value. Must be serializable if non-scalar.
	 *	@param (bool)   $unique     Optional, default is false.
	 *		This determines whether the specified key can have multiple entries for the specified object id.
	 *		If false, add_meta() will add duplicate keys to the object. If true, nothing will be added if the specified key already exists for the specified id.
	 *	@return int|false Returns false on failure. On success, returns the ID of the inserted row.
	 */
	public function addMeta($meta_key, $meta_value, $unique = false)
	{
		if( $unique )
			$this->metas[$meta_key] = $meta_value;
		else
			$this->metas[$meta_key][] = $meta_value;
		// add slash since wp strip them
		return \add_metadata(static::getMetaType(), $this->id, $meta_key, is_string($meta_value)?addslashes($meta_value):$meta_value, $unique);
	}

	/**	Update meta to this table. If no value already exists for this object and metadata key, the metadata will be added.
	 *	@param $prev_value  (mixed) (Optional) If specified, only update existing metadata entries with the specified value. Otherwise, update all entries.
	 *	@return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public function updateMeta($meta_key, $meta_value, $prev_value = '')
	{
		if( empty($prev_value) || (isset($this->metas[$meta_key]) && $this->metas[$meta_key] == $prev_value) )
			$this->metas[$meta_key] = $meta_value;
		// add slash since wp strip them
		return \update_metadata(static::getMetaType(), $this->id, $meta_key, is_string($meta_value)?addslashes($meta_value):$meta_value, $prev_value);
	}

	/**
	 * delete meta from this table
	 * @param string $meta_key   Metadata key
	 * @param mixed  $meta_value Optional. Metadata value. Must be serializable if non-scalar. If specified, only delete
	 *                           metadata entries with this value. Otherwise, delete all entries with the specified meta_key.
	 *                           Pass `null, `false`, or an empty string to skip this check. (For backward compatibility,
	 *                           it is not possible to pass an empty string to delete those entries with an empty string
	 *                           for a value.)
	 * @param bool   $delete_all Optional, default is false. If true, delete matching metadata entries for all objects,
	 *                           ignoring the specified object_id. Otherwise, only delete matching metadata entries for
	 *                           the specified object_id.
	 * @return bool True on successful delete, false on failure.
	 */
	public function deleteMeta($meta_key, $meta_value = '', $delete_all = false)
	{
		if( empty($meta_value) || (isset($this->metas[$meta_key]) && $this->metas[$meta_key] == $meta_value) )
			unset($this->metas[$meta_key]);
		return \delete_metadata(static::getMetaType(), $this->id, $meta_key, $meta_value, $delete_all);
	}



	static public function postHasMeta($id, $meta_key)
	{
		return \metadata_exists(static::getMetaType(), $id, $meta_key);
	}

	static public function postGetMeta($id, $meta_key='', $single=false)
	{
		return \get_metadata(static::getMetaType(), $id, $meta_key, $single);
	}

	static public function postAddMeta($id, $meta_key, $meta_value, $unique = false)
	{
		// add slash since wp strip them
		return \add_metadata(static::getMetaType(), $id, $meta_key, is_string($meta_value)?addslashes($meta_value):$meta_value, $unique);
	}

	static public function postUpdateMeta($id, $meta_key, $meta_value, $prev_value = '')
	{
		// add slash since wp strip them
		return \update_metadata(static::getMetaType(), $id, $meta_key, is_string($meta_value)?addslashes($meta_value):$meta_value, $prev_value);
	}

	static public function postDeleteMeta($id, $meta_key, $meta_value = '', $delete_all = false)
	{
		return \delete_metadata(static::getMetaType(), $id, $meta_key, $meta_value, $delete_all);
	}

}
?>
