<?php

/**
 * Some verb commands
 * 'Verb' is a word to describe the specific contribution of a member to a project.
 * @package daimio
 * @author Cecily Carver
 * @version 1.0
 */

class Verb
{   
  
  // validates the item
  // TODO: move this somewhere else!
  private static function validize($item) {
    $collection = 'verbs';
    $fields = array('role');
    
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
  * Find some verbs 
  * @param string Verb ids
  * @param string Verb role
  * @param string Search a start date range -- accepts (:yesterday :tomorrow) or (1349504624 1349506624)
  * @param string Thing (:collection :id) the subject of the verb
  * @param string Thing (:collection :id) the object of  the verb
  * @param string Thing (:collection :id) -- can be either the subject or object of the verb
	* @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
  * @return string 
  * @key __member
  */ 
  static function find($by_ids=NULL, $by_role=NULL, $by_date_range=NULL, $by_subject=NULL, $by_object=NULL, $by_thing=NULL, $options=NULL)
  {
    if(isset($by_ids)) 
      $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));
    
    if(isset($by_role))
	  	$query['role'] = $by_role;
	
	  if(isset($by_subject)) {
		  if(!$subject = MongoLib::resolveDBRef($by_subject))
        return ErrorLib::set_error("Invalid thing (subject)");	
      
      $query['subject'] = $subject;    
	  }
	
	  if(isset($by_object)) {
		  if(!$object = MongoLib::resolveDBRef($by_object))
        return ErrorLib::set_error("Invalid thing (object)");	
      
      $query['object'] = $object;    
	  }
	  
		if(isset($by_thing)) {
		  if(!$thing = MongoLib::resolveDBRef($by_thing))
        return ErrorLib::set_error("Invalid thing");
		  
		  $query_subj['subject'] = $thing;
		  $query_obj['object'] = $thing;
		  $query['$or'] = array($query_subj, $query_obj);
		}
    
		
    if(isset($by_date_range)) {
      $begin_date = $by_date_range[0];
      $begin_date = ctype_digit((string) $begin_date) ? $begin_date : strtotime($begin_date);
      
      $end_date = $by_date_range[1];
      $end_date = ctype_digit((string) $end_date) ? $end_date : strtotime($end_date);
      
      $query1['start_date']['$gt'] = new MongoDate($begin_date);
      $query1['start_date']['$lt'] = new MongoDate($end_date);

      $query2['end_date']['$gt'] = new MongoDate($begin_date);
      $query2['end_date']['$lt'] = new MongoDate($end_date);

      $query3['start_date']['$lt'] = new MongoDate($begin_date);
      $query3['end_date']['$gt'] = new MongoDate($end_date);

      $query['$or'][0] = $query1;
      $query['$or'][1] = $query2; 
      $query['$or'][2] = $query3;
    }
    
    return MongoLib::find_with_perms('verbs', $query, $options);
  }
  
  /** 
  * Add a verb
	* @param string Thing (:collection :id) that is the subject of the verb
	*	@param string Thing (:collection :id) that is the object of the verb
  * @return string 
  * @key __member
  */ 
  static function add($subject, $object)
  {

    // if(!$member = MongoLib::findOne('members', $member)) {
    //   return ErrorLib::set_error("No such member exists");    
    // }
    // if(!$project = MongoLib::findOne_editable('projects', $project))
    //   return ErrorLib::set_error("That project is not within your domain");    

    // list($collection, $id) = array_values(MongoLib::resolveDBRef($thing));

     if(!$subj_thing = MongoLib::createDBRef($subject[0], $subject[1]))
       return ErrorLib::set_error("Invalid thing: ('$subject[0]', '$subject[1]')");
 
     if(!$obj_thing = MongoLib::createDBRef($object[0], $object[1]))
       return ErrorLib::set_error("Invalid thing: ('$object[0]', '$object[1]')");

		// all clear!

    $verb['role'] = array();
    $verb['begin_date'] = false;
    $verb['end_date'] = false;
		
		$verb['subject'] = $subj_thing;
		$verb['object'] = $obj_thing;
    
		
    $verb['valid'] = false;
    $id = MongoLib::insert('verbs', $verb);
    
    PermLib::grant_user_root_perms('verbs', $id);
    PermLib::grant_permission(array('verbs', $id), "admin:*", 'edit');
    PermLib::grant_permission(array('verbs', $id), "world:*", 'view');

    History::add('verbs', $id, array('action' => 'add'));
    
    return $id;
  }
  
  /** 
  * Set the verb's roles
  * @param string Verb id
  * @param string role
  * @return string 
  * @key __member
  */ 
  static function set_role($id, $value)
  {
    if(!$verb = MongoLib::findOne_editable('verbs', $id))
      return ErrorLib::set_error("That verb is not within your domain");
    
    if($verb['role'] == $value)
      return $id;
    
    // all clear!
    
    $update['role'] = $value;
    MongoLib::set('verbs', $id, $update);

    History::add('verbs', $id, array('action' => 'set_role', 'value' => $value));
    
    $verb['role'] = $value;
    self::validize($verb);
    
    return $id;
  }
  
  /** 
  * Set the verb's start date 
  * @param string verb id
  * @param string New start date
  * @return string 
  * @key __member
  */ 
  static function set_begin_date($id, $value)
  {
    if(!$verb = MongoLib::findOne_editable('verbs', $id))
      return ErrorLib::set_error("That verb is not within your domain");
    
    if(!$value = new MongoDate(ctype_digit((string) $value) ? $value : strtotime($value)))
      return ErrorLib::set_error("That is not a valid date");
    
    if($verb['begin_date'] === $value)
      return $id;
    
    // all clear!
    
    ErrorLib::log_array(array('updating date'));
    
    $update['begin_date'] = $value;
    MongoLib::set('verbs', $id, $update);

    History::add('verbs', $id, array('action' => 'set_begin_date', 'value' => $value));
    
    $verb['begin_date'] = $value;
    self::validize($verb);
    
    return $id;
  }
  
  /** 
  * Set the verb's end date 
  * @param string verb id
  * @param string New end date
  * @return string 
  * @key __member
  */ 
  static function set_end_date($id, $value)
  {
    if(!$verb = MongoLib::findOne_editable('verbs', $id))
      return ErrorLib::set_error("That verb is not within your domain");
    
    if(!$value = new MongoDate(ctype_digit((string) $value) ? $value : strtotime($value)))
      return ErrorLib::set_error("That is not a valid date");
    
    if($verb['end_date'] === $value)
      return $id;
    
    // all clear!
    
    $update['end_date'] = $value;
    MongoLib::set('verbs', $id, $update);

    History::add('verbs', $id, array('action' => 'set_end_date', 'value' => $value));
    
    $verb['end_date'] = $value;
    self::validize($verb);
    
    return $id;
  }
  
  /** 
  * Clone a verb
  * @param string Verb id
  * @return string 
  * @key __member
  */ 
  static function replicate($id)
  {
    if(!$verb = reset(MongoLib::find_with_perms('verbs', $id)))
      return ErrorLib::set_error("That verb is not within your domain");
    
    // all clear!
    $new_subject = array($verb['subject']['$ref'], $verb['subject']['$id']);
    $new_object = array($verb['object']['$ref'], $verb['object']['$id']);
    
    $new_id = self::add($new_subject, $new_object);

    if($verb['role']) 
      self::set_role($new_id, $verb['role']);
    
    if($verb['begin_date']) 
      self::set_key($new_id, $verb['begin_date']);
    
    if($verb['end_date']) 
      self::set_discount($new_id, $verb['end_date']);

    self::validize($verb);

    return $new_id;
  }


  /** 
  * Destroy a verb completely (this will *seriously* mess things up!) 
  * @param string verb id
  * @return string 
  * @key admin
  */ 
  static function destroy($id)
  {
    // check for production status
    if($GLOBALS['X']['SETTINGS']['production'])
      return ErrorLib::set_error("Destruction on production is strictly verboten!");
    
    // get verb
    if(!$verb = MongoLib::findOne_editable('verbs', $id))
      return ErrorLib::set_error("That verb is not within your domain");
    
    // all clear

    // add transaction to history
    History::add('verbs', $id, array('action' => 'destroy', 'was' => $verb));
    
    // destroy the verb
    return MongoLib::removeOne('verbs', $id);
  }

}

// EOT