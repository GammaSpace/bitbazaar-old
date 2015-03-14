<?php

/**
 * This represents a reservation of a product by a member for a specific time. 
 *
 * @package daimio
 * @author Cecily Carver
 * @version 1.0
 */
 
 class Variant {
 
   /** 
   * Checks that the Variant has all necessary fields 
   * @param string variant to validize
   * @return string 
   */ 
   private static function validize($item) {
     $collection = 'variants';
     $fields = array('product', 'square');

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
   * Find variants
   * @param string Variant ids
   * @param string Product id
   * @param string Member id
   * @param string Variant as string
   * @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
   * @return string 
   * @key __member
   */ 
   static function find($by_ids=NULL, $by_product=NULL, $by_member=NULL, $by_square=NULL, $by_product_name=NULL, $options=NULL) {
     if(isset($by_ids)) 
       $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));
     
     if(isset($by_product)) 
       $query['product'] = array('$in' => MongoLib::fix_ids($by_product));
     
     if(isset($by_product_name)) {
       $product = Product::find(NULL, $by_product_name);
       $query['product'] = array('$in' => MongoLib::fix_ids(array_keys($product)));
     }
     
     if(isset($by_member)) 
       $query['member'] = array('$in' => MongoLib::fix_ids($by_member));
     
     if(isset($by_square))
       $query['square'] = new MongoRegex("/$by_square/i");
     else if (!isset($by_ids))
       $query['square']['$not'] = new MongoRegex("/removed/i");
     
     /** ErrorLib::log_array(array('variant find $query', $query));*/
     return MongoLib::find_with_perms('variants', $query, $options);     
   }
 
    /** 
    * Add a variant for a product
    * @param string Product id 
    * @return string 
    * @key admin __exec
    */ 
    static function add($product)
    {
      // check that product exists
      if(!$product = MongoLib::findOne_viewable('products', $product))
        return ErrorLib::set_error("That product is not within your domain");
      
      $variant['product'] = $product['_id'];
      $variant['key'] = false;
      $variant['member'] = false;
      $variant['square'] = 'available';

      $id = MongoLib::insert('variants', $variant);

      PermLib::grant_permission(array('variants', $id), "admin:*", 'root');
      PermLib::grant_permission(array('variants', $id), "user:*", 'view');
      PermLib::grant_permission(array('variants', $id), "user:" . $GLOBALS['X']['USER']['id'], 'edit'); // j
      
      History::add('variants', $id, array('action' => 'add'));

      return $id;     
    }
  
   
   /** 
   * Checks whether it's possible to book the given variant
   * @param string Product id
   * @param string Variant
   * @return string Empty string if variant is ok, error message if not
   * @key __member
   */ 
   static function check($id) {
     
     if(!$id)
       return array('error' => "That is not a valid variant id");
      
     // get variant
     if(!$variant = MongoLib::findOne_viewable('variants', $id))
       return array('error' => "That variant is not within your domain");

     if(!self::validize($variant))
       return array('error' => "That variant is not valid");
       
     // get product
     if(!$product = MongoLib::findOne_viewable('products', $variant['product']))
       return array('error' => "That product is not within your domain");
  
     // get user   
     if(!$user = $GLOBALS['X']['USER'])
       return array('error' => "You must be logged in to book a variant");
 
     // check whether a conflict exists
     if ($variant['square'] != 'available')
      return array('error' => "That variant is not available");
    
     // TODO: check points
   
     // set up local vars: user, event, ttype
     $params['product'] = $variant['product'];
     $params['user'] = $variant['member'];
     $params['variant'] = $variant['_id'];

     // check conditions
     $conditions = $product['conditions'];
     if($error = trim(Processor::process_with_data($conditions, $params)))
       return array('error' => $error); 
   
     return ""; 
   }
   
   /** 
   * Books the given variant for the logged-in user
   * @param string Variant id
   * @return string variant id 
   * @key __member
   */ 
   static function book($id) {

    if($error = self::check($id))
     return ErrorLib::set_error($error['error']);
    
    // get variant
    if(!$variant = MongoLib::findOne_viewable('variants', $id))
     return ErrorLib::set_error("That variant is not within your domain");
      
    // get product
    if(!$product = MongoLib::findOne_viewable('products', $variant['product']))
      return ErrorLib::set_error("That product is not within your domain");
      
    $user_id = $GLOBALS['X']['USER']['id'];

    // update variant
    $update['member'] = $user_id;
    $update['square'] = $product['controller'] ? 'tentative' : 'confirmed';

    MongoLib::set('variants', $id, $update);

    PermLib::grant_permission(array('variants', $id), "admin:*", 'root');
    PermLib::grant_permission(array('variants', $id), "user:*", 'view');
    PermLib::grant_permission(array('variants', $id), "user:" . $user_id, 'edit');

    if ($product['controller'])
     PermLib::grant_permission(array('variants', $id), "user:" . $product['controller'], 'edit');

    History::add('variants', $id, array('action' => 'book'));

    self::validize($variant);

    return $id;
   }

   /** 
   * Confirm the variant with the given id 
   * @param string variant id to confirm
   * @return string 
   * @key __member
   */ 
   static function confirm_booking($id)
   {
      if(!$variant = MongoLib::findOne_editable('variants', $id))
        return ErrorLib::set_error("That variant is not within your domain");

      if(!$product = MongoLib::findOne_editable('products', $variant['product']))
        return ErrorLib::set_error("You must be an admin or the product controller to confirm");     

      if($variant['square'] != 'tentative')
        return ErrorLib::set_error("This action can only be performed on tentative bookings");

      // all clear! 
      $update['square'] = 'confirmed';
      MongoLib::set('variants', $id, $update);

      History::add('variants', $id, array('action' => 'confirm'));

      return $id;
   }
   
   /** 
   * Un-book a variant
   * @param string variant id to cancel 
   * @return string 
   * @key __member
   */ 
   static function cancel_booking($id)
   {
      if(!$variant = MongoLib::findOne_editable('variants', $id))
        return ErrorLib::set_error("That variant is not within your domain");
      
      if ($variant['square'] == 'available')
        return ErrorLib::set_error("No booking exists to cancel");
      
      // all clear! 
      $update['square'] = 'available';
      $update['member'] = false;
      MongoLib::set('variants', $id, $update);

      History::add('variants', $id, array('action' => 'cancel'));

      return $id;
   }
   
   /** 
   * Replicates the given variant (in an un-booked state) 
   * @param string Variant id
   * @return string 
   * @key admin __exec
   */ 
   static function replicate($id)
   {
      if(!$variant = MongoLib::findOne_viewable('variants', $id))
        return ErrorLib::set_error("You do not have permission to view that variant.");  

      // all clear!

      $new_id = self::add($variant['product']);   

      if($variant['start_time'] && $variant['end_time']) 
        self::set_times($new_id, array($variant['start_time']->sec, $variant['end_time']->sec));

      self::validize($variant);

      return $new_id;
   }
   
   /** 
   * Sets the variant's square to "removed"
   * @param string 
   * @return string 
   * @key admin __exec
   */ 
   static function remove($id)
   {
     // get variant
    if(!$variant = MongoLib::findOne_editable('variants', $id))
      return ErrorLib::set_error("That variant is not within your domain");  

    if($variant['square'] == 'removed')
      return $id;

    $update['square'] = 'removed';

    MongoLib::set('variants', $id, $update);

    History::add('variants', $id, array('action' => 'remove'));

    $variant['square'] = 'removed';
    self::validize($variant);

    return $id;   
   }
   
   
 }