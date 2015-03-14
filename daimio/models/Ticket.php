<?php

/**
 * Ticket ticket ticket, I bought it on the train
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
  * Retrieves the count of all tickets sold for a given event and/or type
  * @return string
  */
  private static function count_tickets($by_events, $by_types=NULL)
  {
    $query['thing.$id'] = array('$in' => MongoLib::fix_ids($by_events));
    $query['square'] = "active";

    if(isset($by_types))
      $query['type'] = array('$in' => MongoLib::fix_ids($by_types));

    $tickets = MongoLib::find('tickets', $query, $options);

    return count($tickets);
  }



  /**
  * Find some tickets
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

    return MongoLib::find_with_perms('tickets', $query, $options);
  }

  /**
  * Checks whether a ticket can be purchased by the current user. Returns an empty string if the ticket is ok
  * @param string Event id
  * @param string Ticket type
  * @param string Quantity of tickets to buy
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

    // get ticket type
    if(!$ttype = MongoLib::findOne_viewable('ttypes', $ttype))
      return array('error' => "That ticket type was not found");

    // get ttype info
    if(!$ttype_info = $event['ttypes'][$ttype['key']])
      return array('error' => "That event has no such ticket type");

    // check event cap
    $current_tickets = self::count_tickets($event['_id']);
    if($current_tickets + $quantity > $event['capacity'])
      return array('error' => "That event does not have enough capacity");

    // check ticket type inventory
    $current_tickets = self::count_tickets($event['_id'], $ttype['_id']);
    if(($current_tickets + $quantity) > $ttype_info['capacity']) // TODO: race condition
      return array('error' => "Not enough tickets of that type available");

    if(!$user = $GLOBALS['X']['USER'])
      return array('error' => "You must be logged in to buy a ticket");

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
  * Buy a ticket!
  * @param string Event id
  * @param string Ticket type
  * @param string Purchase token
  * @param string Quantity of tickets to buy
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

    // get ticket type
    if(!$ttype = MongoLib::findOne_viewable('ttypes', $ttype))
      return ErrorLib::set_error("That ticket type was not found");

    // get ttype info
    if(!$ttype_info = $event['ttypes'][$ttype['key']])
      return ErrorLib::set_error("That event has no such ticket type");

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

    // make ticket
    $user_id = $GLOBALS['X']['USER']['id'];
    $ids = '';

    $ticket['token'] = $token;
    $ticket['user'] = $user_id;
    $ticket['type'] = $ttype['_id'];
    $ticket['coupon'] = $coupon;
    $ticket['price'] = $price;
    $ticket['square'] = 'active';
    $ticket['thing'] = MongoLib::createDBRef('events', $event['_id']);

    for ($i=0; $i < $quantity; $i++) {
      unset($ticket['_id']);

      $id = MongoLib::insert('tickets', $ticket);

      PermLib::grant_permission(array('tickets', $id), "admin:*", 'root');
      PermLib::grant_permission(array('tickets', $id), "user:$user_id", 'view');

      History::add('tickets', $id, array('action' => 'add'));

      $ids[] = $id;

    }

    return $ids;
  }

  /**
  * Cancel a ticket
  * Note that this does not return the ticket to inventory -- that needs to be done with a separate command.
  * @param string
  * @return string
  * @key admin __exec
  */
  static function cancel($id)
  {
    if(!$ticket = MongoLib::findOne_editable('tickets', $id))
      return ErrorLib::set_error("That ticket is not within your domain");

    if($ticket['square'] == 'cancelled')
      return $id;

    // all clear!

    // set square to 'cancelled'
    $update['square'] = 'cancelled';
    MongoLib::set('tickets', $id, $update);

    History::add('tickets', $id, array('action' => 'cancel'));

    return $id;
  }



  /**
  * Find some ticket types
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
      $query['key'] = array('$in' => MongoLib::fix_ids($by_key));
      // $query['key'] = new MongoRegex("/$by_key/i");

    return MongoLib::find_with_perms('ttypes', $query, $options);
  }

  /**
  * Add a new ticket type
  * @param string The ticket type's unique single word key
  * @return string
  * @key admin __exec
  */
  static function add_type($key)
  {
    if(!$key || $key != QueryLib::scrub_string($key))
      return ErrorLib::set_error("Invalid ticket type key");

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
  * Set the ticket type's name
  * @param string Ticket type id
  * @param string New name
  * @return string
  * @key admin __exec
  */
  static function set_type_name($id, $value)
  {
    if(!$ttype = MongoLib::findOne_editable('ttypes', $id))
      return ErrorLib::set_error("That ticket type is not within your domain");

    if(!$value || strlen($value) < 3 || strlen($value) > 200)
      return ErrorLib::set_error("Invalid ticket type name");

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
  * Set the ticket type's conditions
  * @param string Ticket type id
  * @param string If this DAML chunk returns a string, it's considered an error and cancels purchase
  * @return string
  * @key admin __exec
  */
  static function set_type_conditions($id, $value)
  {
    if(!$ttype = MongoLib::findOne_editable('ttypes', $id))
      return ErrorLib::set_error("That ticket type is not within your domain");

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

	/**
	* Adds a URL token for the ticket
	* @param string ticket id
	* @param string Value of the token
	* @return string
	* @key __member
	*/
	static function set_type_key($id, $value)
	{
    if(!$ttype = MongoLib::findOne_viewable('ttypes', $id))
      return array('error' => "That ticket type was not found");

    if(!$value)
      return ErrorLib::set_error("This key has no value");

    if($ttype['key'] === $value)
      return $id;

    ErrorLib::set_error($ttype);

    if(MongoLib::check('ttypes', array('key' => $value)))
	    return ErrorLib::set_error("A ticket type with this key already exists");
    if($value != QueryLib::scrub_string($value, '_', '_.-'))
      return ErrorLib::set_error("Token is not URL-safe");

    // all clear!

    $update['key'] = $value;
    MongoLib::set('ttypes', $id, $update);

    History::add('ttypes', $id, array('action' => 'set_key', 'value' => $value));

    $ttype['key'] = $value;
    self::validize($ttype);

    return $id;
	}



}

// EOT
