<?php

/**
 * You can put stuff in stuff.
 *
 * @package daimio
 * @author Cecily Carver
 * @version 1.0
 */

class Stuff
{
  // validates the item
  private static function validize($item) {
    $collection = 'stuff';
    $fields = array('type');

    if(!$item) return false;

    if($item['valid']) return true;

    foreach($fields as $key)
      if($item[$key] === false) return false;

    // all clear!

    $update['valid'] = true;
    MongoLib::set($collection, $item['_id'], $update);

    return true;
  }

  /**
  * Find your stuff.
  * @param string Id of the stuff to find
  * @param string Type of stuff to find (will only return exact matches)
  * @param string Key of stuff to find (will only return exact matches)
  * @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
  * @return string
  * @key __world
  */
  static function find($by_ids=NULL, $by_type=NULL, $by_key=NULL, $options=NULL)
  {
    if(isset($by_ids))
      $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));

    if(isset($by_type))
      $query['type'] = new MongoRegex("/^$by_type/i");

		if(isset($by_key))
			$query['key'] = new MongoRegex("/^$by_key/i");

    return MongoLib::find_with_perms('stuff', $query, $options);
  }

  /**
  * Add something to Stuff.
  * @param string Type of stuff to add
  * @return string
  * @key __member
  */
  static function add($type)
  {
    $stuff['type'] = $type;
    $stuff['valid'] = false;
    $stuff['user'] = $GLOBALS['X']['USER']['id'];
    $stuff['cron'] = date('Y-m-d H:i:s');

    $id = MongoLib::insert('stuff', $stuff);

    PermLib::grant_permission(array('stuff', $id), "admin:*", 'root');
    PermLib::grant_permission(array('stuff', $id), "user:" . $GLOBALS['X']['USER']['id'], 'edit');
    PermLib::grant_permission(array('stuff', $id), "user:*", 'view');

    self::validize($stuff);

    History::add('stuff', $id, array('action' => 'add'));

    return $id;
  }

  /**
  * Destroy a stuff completely (this may *seriously* mess things up!)
  * @param string stuff id
  * @return string
  */
  static function destroy($id)
  {
    // check for production status
    if($GLOBALS['X']['SETTINGS']['production'])
      return ErrorLib::set_error("Destruction on production is strictly verboten!");

    // get event
    if(!$stuff = MongoLib::findOne('stuff', $id))
      return ErrorLib::set_error("No such stuff exists");

    // all clear

    // add transaction to history
    History::add('stuff', $id, array('action' => 'destroy', 'was' => $stuff));

    // destroy the event
    return MongoLib::removeOne('stuff', $id);
  }
  /**
  * Adds a URL token for the stuff
  * @param string Stuff id
  * @param string Value of the key
  * @return string
  * @key admin
  */
  static function set_key($id, $value)
  {
  	if(!$stuff = MongoLib::findOne_editable('stuff', $id))
      return ErrorLib::set_error("That stuff is not within your domain");

    if(!$value)
      return ErrorLib::set_error("This key has no value");

    if($stuff['key'] === $value)
      return $id;

    if(MongoLib::check('stuff', array('key' => $value)))
      return ErrorLib::set_error("An stuff with this key already exists");
    if($value != QueryLib::scrub_string($value, '_', '_.-'))
      return ErrorLib::set_error("Token is not URL-safe");

    // all clear!

    $update['key'] = $value;
    MongoLib::set('stuff', $id, $update);

    History::add('stuff', $id, array('action' => 'set_key', 'value' => $value));

    $stuff['key'] = $value;
    self::validize($stuff);

    return $id;
  }
}
