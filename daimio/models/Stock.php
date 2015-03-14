<?php

/**
 * Ticket stockitem stockitem, I bought it on the train
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Ticket
{
  
  // validates the item
  // TODO: move this somewhere else!
  private static function validize($item) {
    $collection = 'ttypes';
    $fields = array('name', 'conditions');
    
    if(!$item) return false;
    if($item['valid']) return true;
    
    foreach($fields as $key)
      if($item[$key] === false)
        return false;
    
    // all clear!
    
    $update['valid'] = true;
    MongoLib::set($collection, $item['_id'], $update);
    
    return true;
  }
  
  /** 
  * Retrieves the count of all stockitems sold for a given event and/or type
  * @return string 
  */ 
  private static function count_stockitems($by_events, $by_types=NULL)
  {
    $query['thing.$id'] = array('$in' => MongoLib::fix_ids($by_events));
    $query['square'] = "active";

    if(isset($by_types))
      $query['type'] = array('$in' => MongoLib::fix_ids($by_types));

    $stockitems = MongoLib::find('stockitems', $query, $options);
    
    return count($stockitems);
  }
  
  
  
  /** 
  * Find some stockitems 
  * @param string Ticket ids
  * @param string Event ids
  * @param string Type ids
  * @param string User ids 
  * @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
  * @return string 
  * @key __member
  */ 
  static function find($by_ids=NULL, $by_events=NULL, $by_types=NULL, $by_user=NULL, $options=NULL)
  {
    if(isset($by_ids))
      $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));
    
    if(isset($by_events)) {
      $query['thing.$id'] = array('$in' => MongoLib::fix_ids($by_events));
      $query['square'] = "active";
    }
    
    if(isset($by_user)) {
      $query['user'] = array('$in' => MongoLib::fix_ids($by_user));
      $query['square'] = "active";
    }
    
    if(isset($by_types)) {
      $query['type'] = array('$in' => MongoLib::fix_ids($by_types));
      $query['square'] = "active";
    }
      
    // TODO: add by_valid or something similar
    
    return MongoLib::find_with_perms('stockitems', $query, $options);
  }
  
  /** 
  * Checks whether a stockitem can be purchased by the current user. Returns an empty string if the stockitem is ok  
  * @param string Event id
  * @param string Ticket type
  * @param string Quantity of stockitems to buy
  * @return string 
  * @key __world
  */ 
  static function check($event, $ttype, $quantity)
  {
    // get event
    if(!$event = MongoLib::findOne_viewable('events', $event))
      return array('error' => "That event is not within your domain");
    
    if(!$event['valid'])
      return array('error' => "Invalid event");
    
    // get stockitem type
    if(!$ttype = MongoLib::findOne_viewable('ttypes', $ttype))
      return array('error' => "That stockitem type was not found");
    
    // get ttype info
    if(!$ttype_info = $event['ttypes'][$ttype['key']])
      return array('error' => "That event has no such stockitem type");
    
    // check event cap
    $current_stockitems = self::count_stockitems($event['_id']);
    if($current_stockitems + $quantity > $event['capacity'])     
      return array('error' => "That event does not have enough capacity");
    
    // check stockitem type inventory
    $current_stockitems = self::count_stockitems($event['_id'], $ttype['_id']);
    if(($current_stockitems + $quantity) > $ttype_info['capacity']) // TODO: race condition
      return array('error' => "Not enough stockitems of that type available");
    
    if(!$user = $GLOBALS['X']['USER'])
      return array('error' => "You must be logged in to buy a stockitem");
      
    // set up local vars: user, event, ttype
    $params['user'] = $user;
    $params['event'] = $event;
    $params['ttype'] = $ttype;
    $params['quantity'] = $quantity;

    // check conditions
    $conditions = $ttype['conditions'];
    if($error = trim(Processor::process_with_data($conditions, $params)))
      return array('error' => $error);
      
    return "";
  }
  
  /** 
  * Buy a stockitem!
  * @param string Event id
  * @param string Ticket type
  * @param string Purchase token
  * @param string Quantity of stockitems to buy
  * @param string Coupon code
  * @return string 
  * @key admin __exec
  */ 
  static function buy($event, $ttype, $token, $quantity, $coupon=NULL)
  {
    if($error = self::check($event, $ttype, $quantity))
      return ErrorLib::set_error($error['error']);
    
    // get event
    if(!$event = MongoLib::findOne_viewable('events', $event))
      return ErrorLib::set_error("That event is not within your domain");
    
    // get stockitem type
    if(!$ttype = MongoLib::findOne_viewable('ttypes', $ttype))
      return ErrorLib::set_error("That stockitem type was not found");
    
    // get ttype info
    if(!$ttype_info = $event['ttypes'][$ttype['key']])
      return ErrorLib::set_error("That event has no such stockitem type");
    
    // fiddle with price
    $price = $ttype_info['price'];
    
    // THINK: check this against stripe api?
    if(!$token) 
      return ErrorLib::set_error("Invalid token");
    
    // try coupon
    if($coupon) {     
      
      // get coupon
      if(!$coupon_db = reset(Coupon::find(NULL,NULL,$coupon)))
        return ErrorLib::set_error("No such coupon");
      $coupon_id = $coupon_db['_id'];
      
      $test = Coupon::apply($coupon_id, $event['_id'], $ttype['_id']);
      if($test['price'])
        $price = $test['price'];
      else
        return $test;
    }
    
    // all clear!
    
    // reduce ttype quantity
    //Event::mod_ttype_quantity($event, $ttype, -1 * $quantity);
    
    // make stockitem
    $user_id = $GLOBALS['X']['USER']['id'];
    $ids = '';
  
    $stockitem['token'] = $token;
    $stockitem['user'] = $user_id;
    $stockitem['type'] = $ttype['_id'];
    $stockitem['coupon'] = $coupon;
    $stockitem['price'] = $price;
    $stockitem['square'] = 'active';
    $stockitem['thing'] = MongoLib::createDBRef('events', $event['_id']);
    
    for ($i=0; $i < $quantity; $i++) {     
      unset($stockitem['_id']);
      
      $id = MongoLib::insert('stockitems', $stockitem);

      PermLib::grant_permission(array('stockitems', $id), "admin:*", 'root');
      PermLib::grant_permission(array('stockitems', $id), "user:$user_id", 'view');

      History::add('stockitems', $id, array('action' => 'add'));      
      
      $ids[] = $id;
      
    }
       
    return $ids;
  }
  
  /** 
  * Cancel a stockitem 
  * Note that this does not return the stockitem to inventory -- that needs to be done with a separate command.
  * @param string 
  * @return string 
  * @key admin __exec
  */ 
  static function cancel($id)
  {
    if(!$stockitem = MongoLib::findOne_editable('stockitems', $id))
      return ErrorLib::set_error("That stockitem is not within your domain");
    
    if($stockitem['square'] == 'cancelled')
      return $id;
    
    // all clear!
    
    // set square to 'cancelled'
    $update['square'] = 'cancelled';
    MongoLib::set('stockitems', $id, $update);
    
    History::add('stockitems', $id, array('action' => 'cancel'));
    
    return $id;   
  }
  
  

  /** 
  * Find some stockitem types
  * @param string Ticket type ids
  * @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
  * @return string 
  * @key __world
  */ 
  static function find_types($by_ids=NULL, $by_key=NULL, $options=NULL)
  {
    if(isset($by_ids))
      $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));
    
    if(isset($by_key))
      $query['key'] = new MongoRegex("/$by_key/i");
      
    return MongoLib::find_with_perms('ttypes', $query, $options);
  }
  
  /** 
  * Add a new stockitem type  
  * @param string The stockitem type's unique single word key 
  * @return string 
  * @key admin __exec
  */ 
  static function add_type($key)
  {
    if(!$key || $key != QueryLib::scrub_string($key))
      return ErrorLib::set_error("Invalid stockitem type key");
    
    // all clear!
    
    $ttype['key'] = $key;
    $ttype['name'] = false;
    $ttype['conditions'] = false;
    $ttype['per_user_limit'] = 0;
    $ttype['valid'] = false;
    $id = MongoLib::insert('ttypes', $ttype);
    
    PermLib::grant_permission(array('ttypes', $id), "admin:*", 'root');
    PermLib::grant_permission(array('ttypes', $id), "user:" . $GLOBALS['X']['USER']['id'], 'edit');
    PermLib::grant_permission(array('ttypes', $id), "user:*", 'view');
    PermLib::grant_permission(array('ttypes', $id), "world:*", 'view');
    
    History::add('ttypes', $id, array('action' => 'add'));
    
    return $id;
  }
  
  /** 
  * Set the stockitem type's name 
  * @param string Ticket type id
  * @param string New name
  * @return string 
  * @key admin __exec
  */ 
  static function set_type_name($id, $value)
  {
    if(!$ttype = MongoLib::findOne_editable('ttypes', $id))
      return ErrorLib::set_error("That stockitem type is not within your domain");
    
    if(!$value || strlen($value) < 3 || strlen($value) > 200)
      return ErrorLib::set_error("Invalid stockitem type name");
    
    if($ttype['name'] == $value)
      return $id;
    
    // all clear!
    
    $update['name'] = $value;
    MongoLib::set('ttypes', $id, $update);

    History::add('ttypes', $id, array('action' => 'set_type_name', 'value' => $value));
    
    $ttype['name'] = $value;
    self::validize($ttype);
    
    return $id;
  }
    
  /** 
  * Set the stockitem type's conditions 
  * @param string Ticket type id
  * @param string If this DAML chunk returns a string, it's considered an error and cancels purchase
  * @return string 
  * @key admin __exec
  */ 
  static function set_type_conditions($id, $value)
  {
    if(!$ttype = MongoLib::findOne_editable('ttypes', $id))
      return ErrorLib::set_error("That stockitem type is not within your domain");
    
    if($ttype['conditions'] === $value)
      return $id;
    
    // all clear!
    
    $update['conditions'] = $value;
    MongoLib::set('ttypes', $id, $update);

    History::add('ttypes', $id, array('action' => 'set_type_conditions', 'value' => $value));
    
    $ttype['conditions'] = $value;
    self::validize($ttype);
    
    return $id;
  }
}

// EOT