<?php

/**
* Some coupon commands
*
* @package daimio
* @author dann toliver
* @version 1.0
*/

class Coupon
{

  // validates the item
  // TODO: move this somewhere else!
  private static function validize($item) {
    $collection = 'coupons';
    $fields = array('name', 'key', 'conditions', 'actions');

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
  * Find some coupons
  * @param string Coupon ids
  * @param string Coupon name
  * @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
  * @return string
  * @key __member
  */
  static function find($by_ids=NULL, $by_name=NULL, $by_key=NULL, $options=NULL)
  {
    if(isset($by_ids))
    $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));

    if(isset($by_name))
    $query['name'] = new MongoRegex("/$by_name/i");

    if(isset($by_key))
    $query['key'] = new MongoRegex("/$by_key/i");

    return MongoLib::find_with_perms('coupons', $query, $options);
  }


  /**
  * Apply a coupon to a ticket -- returns either {* (:error "xyzzy")} if it fails or {* (:price 12.34)} if it passes
  * This is also checked in the {ticket buy} command -- if it passes when run directly it'll pass there too.
  * @param string Coupon id
  * @param string Event id
  * @param string Ticket type id (not checked here for validity or inventory)
  * @return string
  * @key __member
  */
  static function apply($id, $event, $ttype)
  {
    if(!$coupon = MongoLib::findOne_viewable('coupons', $id))
    return ErrorLib::set_error("That coupon is not within your domain");

    if(!$event = MongoLib::findOne_viewable('events', $event))
    return ErrorLib::set_error("That event is not within your domain");

    if(!$ttype = MongoLib::findOne_viewable('ttypes', $ttype))
    return ErrorLib::set_error("That ticket type is not within your domain");

    if(!$user = $GLOBALS['X']['USER'])
    return ErrorLib::set_error("You must be logged in to apply a coupon");

    // all clear!

    // set up local vars: user, event, ttype, price
    $params['user'] = $user;
    $params['event'] = $event;
    $params['ttype'] = $ttype;
    $params['price'] = $event['ttypes'][$ttype['key']]['price'];

    // check conditions
    $conditions = $coupon['conditions'];
    if($error = trim(Processor::process_with_data($conditions, $params)))
    return array('error' => $error);

    // run actions
    $actions = $coupon['actions'];
    $price = Processor::process_with_data($actions, $params);

    // validate price
    $price = trim($price);
    if($price === '0' || $price === 0)
    return array('price' => 0);

    if(!$price || !is_numeric($price))
    $price = $event['ttypes'][$ttype['key']]['price'];

    return array('price' => $price);
  }



  /**
  * Add a coupon
  * @return string
  * @key admin __exec
  */
  static function add()
  {
    $coupon['name'] = false;
    $coupon['conditions'] = false;
    $coupon['actions'] = false;
    $coupon['key'] = false;
    $coupon['valid'] = false;
    $id = MongoLib::insert('coupons', $coupon);

    PermLib::grant_user_root_perms('coupons', $id);
    PermLib::grant_permission(array('coupons', $id), "admin:*", 'edit');
    // PermLib::grant_permission(array('coupons', $id), "world:*", 'view');

    History::add('coupons', $id, array('action' => 'add'));

    return $id;
  }

  /**
  * Set the coupon's name
  * @param string Coupon id
  * @param string New name
  * @return string
  * @key admin __exec
  */
  static function set_name($id, $value)
  {
    if(!$coupon = MongoLib::findOne_editable('coupons', $id))
    return ErrorLib::set_error("That coupon is not within your domain");

    if($coupon['name'] == $value)
    return $id;

    // all clear!

    $update['name'] = $value;
    MongoLib::set('coupons', $id, $update);

    History::add('coupons', $id, array('action' => 'set_name', 'value' => $value));

    $coupon['name'] = $value;
    self::validize($coupon);

    return $id;
  }

  /**
  * Set the coupon's key
  * @param string Coupon id
  * @param string Coupon key (URL-safe)
  * @return string
  * @key admin __exec
  */
  static function set_key($id, $value)
  {
    if(!$coupon = MongoLib::findOne_editable('coupons', $id))
    return ErrorLib::set_error("That coupon is not within your domain");

    if($coupon['key'] == $value)
    return $id;

    if(MongoLib::check('coupons', array('key' => $value)))
    return ErrorLib::set_error("A coupon with this key already exists");

    // all clear!

    $update['key'] = $value;
    MongoLib::set('coupons', $id, $update);

    History::add('coupons', $id, array('action' => 'set_key', 'value' => $value));

    $coupon['key'] = $value;
    self::validize($coupon);

    return $id;
  }


  /**
  * Conditions are always run before applying actions, and either return an error string or pass by returning nothing
  * @param string Coupon id
  * @param string A DAML chunk -- user, event, and ttype are provided as variables
  * @return string
  * @key admin __exec
  */
  static function set_conditions($id, $value)
  {
    if(!$coupon = MongoLib::findOne_editable('coupons', $id))
    return ErrorLib::set_error("That coupon is not within your domain");

    if($coupon['conditions'] === $value)
    return $id;

    // all clear!

    $update['conditions'] = $value;
    MongoLib::set('coupons', $id, $update);

    History::add('coupons', $id, array('action' => 'set_conditions', 'value' => $value));

    $coupon['conditions'] = $value;
    self::validize($coupon);

    return $id;
  }

  /**
  * The coupon's actions always return a price value -- no value means 'full price'
  * @param string Coupon id
  * @param string A DAML chunk -- user, event, and ttype are provided as variables
  * @return string
  * @key admin __exec
  */
  static function set_actions($id, $value)
  {
    if(!$coupon = MongoLib::findOne_editable('coupons', $id))
    return ErrorLib::set_error("That coupon is not within your domain");

    if($coupon['actions'] === $value)
    return $id;

    // all clear!

    $update['actions'] = $value;
    MongoLib::set('coupons', $id, $update);

    History::add('coupons', $id, array('action' => 'set_actions', 'value' => $value));

    $coupon['actions'] = $value;
    self::validize($coupon);

    return $id;
  }

  /**
  * Clone a coupon
  * @param string Coupon id
  * @return string
  * @key admin __exec
  */
  static function replicate($id)
  {
    if(!$coupon = MongoLib::findOne_editable('coupons', $id))
    return ErrorLib::set_error("That coupon is not within your domain");

    // all clear!

    $new_id = self::add();

    if($coupon['name'])
    self::set_name($new_id, $coupon['name'] . ' _copy_');

    if($coupon['conditions'])
    self::set_conditions($new_id, $coupon['conditions']);

    if($coupon['actions'])
    self::set_actions($new_id, $coupon['actions']);

    if($coupon['key'])
    self::set_key($new_id, $coupon['key']);

    self::validize($coupon);

    return $new_id;
  }


  /**
  * Destroy a coupon completely (this will *seriously* mess things up!)
  * @param string Coupon id
  * @return string
  */
  static function destroy($id)
  {
    // check for production status
    if($GLOBALS['X']['SETTINGS']['production'])
    return ErrorLib::set_error("Destruction on production is strictly verboten!");

    // get coupon
    if(!$coupon = MongoLib::findOne('coupons', $id))
    return ErrorLib::set_error("No such coupon exists");

    // all clear

    // add transaction to history
    History::add('coupons', $id, array('action' => 'destroy', 'was' => $coupon));

    // destroy the coupon
    return MongoLib::removeOne('coupons', $id);
  }


}

// EOT
