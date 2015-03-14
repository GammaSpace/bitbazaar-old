<?php

/**
 * 680 milliseconds is nearly an eternity for a positronic brain
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Data 
{ 
  
  /**
  * How many items are there?
  * 
  * @param mixed Form keyword
  * @param string Limits the results -- see the documentation for information on the where string
  * @return int 
  * @key __world
  */ 
  static function count($form, $where=NULL)
  {    
    if(FormLib::perms_are_bad($form, 'read'))
      return false;

    return DataLib::count($form, $where);
  }
  
  
  /**
  * Fetch data from a form
  * 
  * @example {data fetch form :profiles where "name = 'Tom'"}
  * 
  * @param string Form keyword
  * @param string Limits the results -- see the documentation for information on the where string
  * @param string A list of fields to return
  * @param string How many results to return (defaults to 1000)
  * @param string Starting row
  * @param string A field keyword followed by ASC or DESC, like "id ASC"
  * @param string Various nicks and nacks
  * @return array 
  * @key __world
  */ 
  static function fetch($form, $where=NULL, $fields=NULL, $limit=NULL, $offset=NULL, $order=NULL, $options=NULL)
  {
    if(FormLib::perms_are_bad($form, 'read'))
      return false;
  
    return DataLib::fetch($form, $where, $fields, $limit, $offset, $order, $options);
  }
  
  
  /**
  * Add a new row or edit an old one  
  * 
  * @param mixed Form keyword
  * @param array A hash of fieldname->value pairs
  * @param int The id of the row to edit (leave blank for a new row)
  * @return int(s) 
  * @key __world
  */ 
  static function input($form, $values=NULL, $row=NULL)
  {
    if($row)
    {
      if(FormLib::perms_are_bad($form, 'edit', $row))
        return false;
    }
    else
    {
      if(FormLib::perms_are_bad($form, 'add'))
        return false;
    }
    
    return DataLib::input($form, $values, $row);
  }
  
  
  /**
  * Permanently delete a row
  * 
  * @param string Form keyword
  * @param mixed The ids of the rows to delete
  * @return boolean 
  * @key __world
  */ 
  static function destroy($form, $rows)
  {
    $rows = HandlerLib::for_parser($rows);
    
    if(!count($rows))
      return ErrorLib::set_error("No rows found");
            
    foreach($rows as $row)
    {
      if(FormLib::perms_are_bad($form, 'delete', $row))
        continue;

      DataLib::destroy($form, $row);
      $count++;
    }
    
    ErrorLib::set_notice("$count rows successfully destroyed");
    
    return $form;
  }  
}

//EOT