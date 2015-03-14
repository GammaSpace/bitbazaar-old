<?php

/**
 * Helper methods for mongo stuff
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Mongous
{
  
  /** 
  * Get a thing from mongo 
  * @param string A thing or a (:collection :id) pair
  * @return array 
  * @key __lens __exec __trigger
  */ 
  static function get_thing($from)
  {
    return MongoLib::getDBRef($from);
  }

  
  /** 
  * Extract seconds since the epoch from a MongoId object or MongoDate
  * @param string MongoId or MongoDate
  * @return int 
  * @key __world
  */ 
  static function extract_time($from)
  {
    return MongoLib::extract_time($from);
  }
  
  /** 
  * Change a string into a mongoid 
  * @param string A string representation of a mongoid 
  * @return mongoid 
  * @key __world
  */ 
  static function fix_id($id)
  {
    return MongoLib::fix_id($id);
  }
  
  
}

// EOT