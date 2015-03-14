<?php

/**
 * Lineups contain an ordered set of items
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 * @requires MongoLib
 */

class Lineup
{

  /** 
  * Get a set of lineups
  * @param string Array of lineup ids
  * @param string Array of lineup types
  * @param string Part of a name
  * @param string A particular item
  * @param string If true, return the query instead of the results
  * @param string Supports sort, limit, skip, fields, nofields, count, i_can and attrs: {* (:limit 5 :skip 30 :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)} or {* (:i_can :edit)}
  * @return id 
  * @key __lens __exec
  */ 
  static function find($by_ids=NULL, $by_types=NULL, $by_name=NULL, $by_item=NULL, $return_query=NULL, $options=NULL)
  {
    if(isset($by_ids)) {
       $by_ids = MongoLib::fix_ids($by_ids);
       $query['_id'] = array('$in' => $by_ids);
    }
    
    if(isset($by_types)) {
      if(is_array($by_types))
        $query['type'] = array('$in' => $by_types);
      else
        $query['type'] = $by_types;
    }
    
    if(isset($by_name)) {
      $query['name'] = $by_name;
    }
    
    if(isset($by_item)) {
      $query['items'] = $by_item;
    }
    
    if($return_query)
      return $query ? $query : array();
    
    return MongoLib::find_with_perms('lineups', $query, $options);
  }

  
  /** 
  * Add a new lineup 
  * @param string Lineup name
  * @param string Lineup type (like 'stack', or 'favorites')
  * @return id 
  * @key __exec
  */ 
  static function add($name, $type)
  {
    if(!$name || !$type)
      return ErrorLib::set_error("Name and type are required");
    
    $lineup['name'] = $name;
    $lineup['type'] = $type;
    
    return MongoLib::insert('lineups', $lineup);
  }
  
  
  /** 
  * Add an item to the end of a lineup 
  * @param string Lineup id
  * @param string Item
  * @return boolean
  * @key __exec
  */ 
  static function add_item($id, $item)
  {
    return MongoLib::addToSet('lineups', $id, 'items', $item);
  }
  
  /** 
  * Remove an item from a lineup 
  * @param string Lineup id
  * @param string Item index
  * @return booleam
  * @key __exec
  */ 
  static function remove_item($id, $index)
  {
    if(!$lineup = reset(Lineup::find($id)))
      return ErrorLib::set_error("Invalid lineup id");
    
    if(count($lineup['items']) <= $index)
      return ErrorLib::set_error("That is not a valid index");
    
    unset($lineup['items'][$index]);
    $lineup['items'] = array_values($lineup['items']);
    
    $update = array('items' => $lineup['items']);
    return MongoLib::set('lineups', $id, $update);
  }
  
  /** 
  * Move an item to a new index 
  * @param string Lineup id
  * @param string Old index
  * @param string New index
  * @return int 
  * @key __exec
  */ 
  static function reorder($id, $old, $new)
  {
    if(!$lineup = reset(Lineup::find($id)))
      return ErrorLib::set_error("Invalid lineup id");

    if(count($lineup['items']) <= $old)
      return ErrorLib::set_error("That is not a valid index");
    
    $item = $lineup['items'][$old];
    unset($lineup['items'][$old]);
    array_splice($lineup['items'], $new, 0, array($item));
    
    $update = array('items' => $lineup['items']);
    return MongoLib::set('lineups', $id, $update);
  }
  
  
  /** 
  * Destroy a lineup completely
  * @param string Lineup id
  * @return boolean 
  * @key __exec
  */ 
  static function destroy($id)
  {
    if(!MongoLib::check('lineups', $id))
      return ErrorLib::set_error("No such lineup exists");    
    
    return MongoLib::removeOne('lineups', $id);
  }

}

// EOT