<?php

/**
 * These are things you can use points to reserve.
 *
 * @package daimio
 * @author Cecily Carver
 * @version 1.0
 */
 
 class Product {
   
   /** 
   * Checks that the product has all necessary fields 
   * @param string 
   * @return string 
   */ 
   private static function validize($item) {
     $collection = 'products';
     $fields = array('name','type','key');

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
   * Find a product 
   * @param string Product id
   * @param string Product name
   * @param string Product key -- only returns exact matches
   * @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
   * @return string The products found 
   * @key __member
   */ 
   static function find($by_ids=NULL, $by_name=NULL, $by_key=NULL, $options=NULL)
   {
     if(isset($by_ids)) 
       $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));
     
     if(isset($by_key))
 			$query['key'] = new MongoRegex("/^$by_key/i");
     
     if(isset($by_name))
       $query['name'] = new MongoRegex("/$by_name/i");

     return MongoLib::find_with_perms('products', $query, $options);     
   }
   
   /** 
   * Add a product to the system  
   * @return string Product id 
   * @key admin __exec
   */ 
   static function add()
   {
     $product['name'] = false;
     $product['conditions'] = false;
     $product['controller'] = false;
     $product['type'] = false;
     $product['key'] = false;
     
     $id = MongoLib::insert('products', $product);

     PermLib::grant_permission(array('products', $id), "admin:*", 'root');
     PermLib::grant_permission(array('products', $id), "user:*", 'view');
     PermLib::grant_permission(array('products', $id), "user:" . $GLOBALS['X']['USER']['id'], 'edit'); // j
     
     History::add('products', $id, array('action' => 'add'));

     return $id;
   }
   
   /** 
   * Sets the name of the product 
   * @param string Product id
   * @param string Product name
   * @return string Product id 
   * @key admin __exec
   */ 
   static function set_name($id, $value)
   {
     if(!$product = MongoLib::findOne_editable('products', $id))
       return ErrorLib::set_error("That product is not within your domain");

     $value = Processor::sanitize($value);

     if(!$value || strlen($value) < 3 || strlen($value) > 200)
       return ErrorLib::set_error("Invalid product name");

     if($product['name'] == $value)
       return $id;

     // all clear!

     $update['name'] = $value;
     MongoLib::set('products', $id, $update);

     History::add('products', $id, array('action' => 'set_name', 'value' => $value));

     $product['name'] = $value;
     self::validize($product);

     return $id;     
   }

 	/** 
 	* Adds a URL token for the product 
 	* @param string Product id
 	* @param string Value of the token
 	* @return string 
 	* @key __member
 	*/ 
 	static function set_key($id, $value)
 	{     
 		if(!$product = MongoLib::findOne_editable('products', $id))
       return ErrorLib::set_error("That product is not within your domain");

     if(!$value)
       return ErrorLib::set_error("This key has no value");

     if($product['key'] === $value)
       return $id;

     if(MongoLib::check('products', array('key' => $value)))
 	    return ErrorLib::set_error("A product with this key already exists");
     if($value != QueryLib::scrub_string($value, '_', '_.-'))
       return ErrorLib::set_error("Token is not URL-safe");

     // all clear!

     $update['key'] = $value;
     MongoLib::set('products', $id, $update);

     History::add('products', $id, array('action' => 'set_key', 'value' => $value));

     $product['key'] = $value;
     self::validize($product);

     return $id;	  	
 	}

   /** 
   * Sets the name of the product 
   * @param string Product id
   * @param string Product type, either 'time' or 'inventory'
   * @return string Product id 
   * @key admin __exec
   */ 
   static function set_type($id, $value)
   {
     if(!$product = MongoLib::findOne_editable('products', $id))
       return ErrorLib::set_error("That product is not within your domain");

     if($product['type'] == $value)
       return $id;
    
     $types = array("time", "inventory");
      
     if(!in_array($value, $types))
      return ErrorLib::set_error("That is not a valid type");

     // all clear!

     $update['type'] = $value;
     MongoLib::set('products', $id, $update);

     History::add('products', $id, array('action' => 'set_type', 'value' => $value));

     $product['type'] = $value;
     self::validize($product);

     return $id;     
   }

   
   /** 
   * Sets the number of units consumed per inventory item  
   * @param string Product id
   * @param string Number of units (e.g., dollars) consumed per inventory item
   * @return string Product id
   * @key admin __exec
   */ 
   static function set_rate($id, $value)
   {
     if(!$product = MongoLib::findOne_editable('products', $id))
       return ErrorLib::set_error("That product is not within your domain");

     if($product['rate'] == $value)
       return $id;

     if(!is_numeric($value) || $value < 1 || $value != round($value))
       return ErrorLib::set_error("rate must be a positive integer");

     // all clear!

     $update['rate'] = $value;
     MongoLib::set('products', $id, $update);

     History::add('products', $id, array('action' => 'set_rate', 'value' => $value));

     $product['rate'] = $value;
     self::validize($product);

     return $id;
   }
   
   
      
   /** 
   * Defines a daml string to evaluate when a booking is attempted. Use to check for conflicts, plus any other conditions this product might hold
   * @param string Product id
   * @param string Product conditions
   * @return string Product id
   * @key admin __exec
   */ 
   static function set_conditions($id, $value)
   {
     if(!$product = MongoLib::findOne_editable('products', $id))
       return ErrorLib::set_error("That product is not within your domain");

     if($product['conditions'] == $value)
       return $id;

     // all clear!

     $update['conditions'] = $value;
     MongoLib::set('products', $id, $update);

     History::add('products', $id, array('action' => 'set_conditions', 'value' => $value));

     $product['conditions'] = $value;
     self::validize($product);

     return $id;
   }  
   
   /** 
   * Defines a 'controller' id who must approve bookings for this product    
   * @param string Product id    
   * @param string Resouce controller member id
   * @return string Product id
   * @key admin __exec
   */ 
   static function set_controller($id, $value)
   {
     if(!$product = MongoLib::findOne_editable('products', $id))
       return ErrorLib::set_error("That product is not within your domain");

     if(!$value)
       return ErrorLib::set_error("That is not a valid value");

     if($product['controller'] == $value)
       return $id;

     // make sure proposed controller is a valid member
     if(!$member = MongoLib::findOne('members', $value)) 
       return ErrorLib::set_error("No such member exists");  
         
     // all clear!

     $update['controller'] = $value;
     MongoLib::set('products', $id, $update);

     History::add('products', $id, array('action' => 'set_controller', 'value' => $value));

     // grant edit permissions to controller
     PermLib::grant_permission(array('products', $id), "user:" . $value, 'edit');

     $product['controller'] = $value;
     self::validize($product);

     return $id;   
   }
    
   /** 
   * Destroy a product completely (this will *seriously* mess things up!) 
   * @param string Product id
   * @return string 
   */ 
   static function destroy($id)
   {
     // check for production status
     if($GLOBALS['X']['SETTINGS']['production'])
       return ErrorLib::set_error("Destruction on production is strictly verboten!");

     // get event
     if(!$product = MongoLib::findOne('products', $id))
       return ErrorLib::set_error("No such event exists");

     // all clear

     // add transaction to history
     History::add('products', $id, array('action' => 'destroy', 'was' => $product));

     // destroy the event
     return MongoLib::removeOne('products', $id);
   }
 }