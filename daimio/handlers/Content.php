<?php

/**
 * Content is king
 * 
 * Content items have history. You can manage them with the Content Management Interface. Or here in the terminal. Whichever.
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Content 
{
  
  //
  // Private functions
  //
  
  /**
  * get the array of where params  
  * @return array 
  */ 
  private function get_where_params()
  {
    $params = array('path' => 'ci.path',
                    'type' => 'ci.type',
                    'value' => 'cii.value',
                    'cron' => 'cii.cron',
                    'user' => 'cii.user',
                    'tags' => 'ci.tags',
                    'tag' => 'tag',
                    'id' => 'ci.id');
    return $params;
  }
    
  
  //
  // Non-private functions
  //
  
  
  /**
  * Get a single content item value  
  * @param string A content item handle
  * @return string Version id (defaults to latest)
  * @key __world
  */ 
  static function get_value($handle, $version=NULL)
  {
    if(!$GLOBALS['X']['USER']['wizard'] && strpos($handle, 'bonus:') !== false)
      return ErrorLib::set_error("Invalid handle");
    
    return ContentLib::get_value_from_handle($handle, $version);
  }
  
  
  /**
  * Get an array of selected content handles
  * @param string Limits the results -- see the documentation for information on the where string
  * @return array 
  * @key __world
  */ 
  static function pick($where=NULL)
  {
    $params = Content::get_where_params();
    $where_sql = HandlerLib::where_parser($where, $params);
        
    $item_array = ContentLib::get_content_array($where_sql, '', "concat(type, '/', path) as handle");

    $handles = array();
    foreach($item_array as $item)
      $handles[] = $item['handle'];
    
    return $handles;
  }
  
  /**
  * Get an array of selected content items
  * @param string Limits the results -- see the documentation for information on the where string
  * @param string Dictates the order of items returned
  * @return array 
  * @key __world
  */ 
  static function fetch($where=NULL, $order=NULL)
  {
    $params = Content::get_where_params();
    $where_sql = HandlerLib::where_parser($where, $params);
    $order_sql = preg_match('/\w+( (DESC|ASC))?/', $order) ? "ORDER BY $order" : '';
    
    $item_array = ContentLib::get_content_array($where_sql, $order_sql);
    
    return $item_array;
  }
  
  /** 
  * Search for stuff n things! 
  * @param string Whatever you want to search by 
  * @return array 
  * @key __world
  */ 
  static function search($by)
  {
    $where = "value ~= '%$by%'";
    return Content::fetch($where);
  }
  
  
  /** 
  * Historical veracity for content items
  * @param mixed A list of content handles
  * @param string A date or history id (defaults to beginning of time)
  * @param string A date or history id (defaults to present)
  * @param mixed Accepts :values
  * @return array 
  */ 
  static function get_history($for, $from=NULL, $to=NULL, $include=NULL)
  {
    $handles = HandlerLib::for_parser($for);
    $ids = ContentLib::get_ids_from_handles($handles);
    
    if(!count($ids))
      return ErrorLib::set_error("No valid content handles provided");
      
    // set up $from_sql (date or id)
    if($from && ctype_digit((string) $from))
      $from_sql = "AND version >= " . QueryLib::quote_smart($from);
    elseif($from && ($from_time = date('Y-m-d H:i:s', strtotime($from))))
      $from_sql = "AND ci.cron >= " . QueryLib::quote_smart($from_time);
      
    // set up $to_sql (date or id)
    if($to && ctype_digit((string) $to))
      $to_sql = "AND version <= " . QueryLib::quote_smart($to);
    elseif($to && ($to_time = date('Y-m-d H:i:s', strtotime($to))))
      $to_sql = "AND ci.cron <= " . QueryLib::quote_smart($to_time);
    
    // get values if include included
    $include = (array) $include;
    if(in_array('values', $include))
      $extra_fields = ', value';
    
    // OPT: this inefficiently queries for each item
    foreach($ids as $id)
    {
      $id_safe = QueryLib::quote_smart($id);
      $sql = "
        SELECT ci.id, username, version, ci.cron $extra_fields
        FROM content_instances as ci LEFT JOIN users
        ON users.id = user
        WHERE 
        item_id = $id
        $from_sql
        $to_sql
        ORDER BY ci.cron DESC
      ";
      
      $histories[$id] = QueryLib::make_data_array_from_query($sql);
    }
        
    return $histories;
  }
  
  
  /** 
  * Get the difference between a content item and its past self 
  * @param string Handle of the content item
  * @param string A date or history id (defaults to previous version)
  * @param string A date or history id (defaults to current version)
  * @return string 
  * @key __world
  */ 
  static function diff($handle, $old=NULL, $new=NULL)
  {
    $id = reset(ContentLib::get_ids_from_handles($handle));
    
    if(!$id)
      return ErrorLib::set_error("No valid content handle provided");

    $q_id = QueryLib::quote_smart($id);

    // get the old instance
    if($old === NULL)
      $limit_extra = ', 1';
    elseif($old && ctype_digit((string) $old))
      $old_sql = "AND version = " . QueryLib::quote_smart($old);
    elseif($old && ($old_time = date('Y-m-d H:i:s', strtotime($old))))
      $old_sql = "AND cron >= " . QueryLib::quote_smart($old_time); //ASC
    else
      return ErrorLib::set_error("No appropriate value for param 'old' found");
    
    $sql = "
      SELECT value
      FROM content_instances
      WHERE 
      item_id = $q_id
      $old_sql
      ORDER BY version DESC
      LIMIT 1$limit_extra
    ";
    $old_value = reset(QueryLib::get_column_from_query($sql, 'value'));
    
    
    // and the new one
    if($new === NULL)
      $new_sql = '';
    elseif($new && ctype_digit((string) $new))
      $new_sql = "AND version = " . QueryLib::quote_smart($new);
    elseif($new && ($new_time = date('Y-m-d H:i:s', strtotime($new))))
      $new_sql = "AND cron <= " . QueryLib::quote_smart($new_time);
    else
      return ErrorLib::set_error("No appropriate value for param 'new' found");
      
    $sql = "
      SELECT value
      FROM content_instances
      WHERE 
      item_id = $q_id
      $new_sql
      ORDER BY version DESC
      LIMIT 1
    ";
    
    $new_value = reset(QueryLib::get_column_from_query($sql, 'value'));

    $esc_old = htmlspecialchars(preg_replace("/\n/", " \n", $old_value));
    $esc_new = htmlspecialchars(preg_replace("/\n/", " \n", $new_value));
    return nl2br(ContentLib::diff_master($esc_old, $esc_new, ' '));
  }
  
  
  
  /** 
  * Set content value to an earlier (or later) version 
  * @param mixed A list of content handles
  * @param string Direction to travel if date doesn't match exactly ("forward" or "backward")
  * @param string A date or history id (defaults to current version)
  * @return boolean 
  */ 
  static function rollback($for, $lean=NULL, $from=NULL)
  {
    $handles = HandlerLib::for_parser($for);    
    $ids = ContentLib::get_ids_from_handles($handles);
    
    if(!count($ids))
      return ErrorLib::set_error("No valid content handles provided");
      
    // set up $to_sql (date or id)
    $sign = $lean == 'backward' ? '<' : '>';
    $ordering = $lean == 'backward' ? 'DESC' : 'ASC';
    if($from && ctype_digit((string) $from))
      $from_sql = "AND version $sign= " . QueryLib::quote_smart($from);
    elseif($from && ($from_time = date('Y-m-d H:i:s', strtotime($from))))
      $from_sql = "AND cron $sign= " . QueryLib::quote_smart($from_time);
    
    // query for each item (inefficient!!)
    foreach($ids as $id)
    {
      $id_safe = QueryLib::quote_smart($id);
      $sql = "
        SELECT version
        FROM content_instances
        WHERE 
        item_id = $id
        $from_sql
        ORDER BY cron $ordering
        LIMIT 1
      ";
      
      $version_array = QueryLib::make_data_array_from_query($sql);
      $version = $version_array[0]['version'];
      
      QueryLib::set_single_value('content', 'id', $id, 'current_version', $version);
    }

    return $handles;
  }
  
  
  /**
  * Change the value of the content item
  * @param mixed A list of content handles
  * @param string The new value
  * @return boolean 
  */ 
  static function set_value($for, $to)
  {
    $handles = HandlerLib::for_parser($for);    
    
    if(!count($handles))
      return ErrorLib::set_error("No valid content handles provided");
      
    foreach($handles as $handle) {
      list($type, $path) = ContentLib::split_handle($handle);
      ContentLib::upsert_content($type, $path, $to);
    }
    
    ErrorLib::set_notice('Value changed for: ' . join(', ', $handles));
    return $handles;
  }
  
  /**
  * Change the path of a content item
  * @param mixed A content handle
  * @param string The new path
  * @return boolean 
  */ 
  static function set_path($handle, $path)
  {
    if(!$id = reset(ContentLib::get_ids_from_handles($handle)))
      return ErrorLib::set_error("No matching content found for \"$handle\"");

    // strip fore and aft path slashes
    $path = trim($path, '/'); 

    // valid path check
    if(!PageLib::valid_path($path))
      return ErrorLib::set_error("Invalid content path \"$path\"");

    // get type and new_handle
    list($type, $old_path) = ContentLib::split_handle($handle);
    $new_handle = "$type/$path";
    if(!$type)
      return ErrorLib::set_error("A non-relative handle is required");

    // unique path check
    if(ContentLib::get_id_from_type_and_path($type, $path))
      return ErrorLib::set_error("Duplicate handle $new_handle");

    // do the update
    QueryLib::update_row('content', $id, array('path' => $path));

    ErrorLib::set_notice("Path changed for $new_handle");
    return $new_handle;
  }
  
  /**
  * Add a tag  
  * @param mixed A list of content handles
  * @param string Tags are single words separated by spaces 
  * @return boolean 
  */ 
  static function add_tag($for, $tag)
  {
    $handles = HandlerLib::for_parser($for);    
    $ids = ContentLib::get_ids_from_handles($handles);
    
    $valid_ids = HandlerLib::add_things($ids, $tag, 'content', 'id', 'tags');
    
    return array_values(ContentLib::get_handles_from_ids($valid_ids));
  }
  
  /**
  * Set the tags  
  * @param mixed A list of content handles
  * @param string Tags are single words separated by spaces 
  * @return boolean 
  */ 
  static function set_tags($for, $to)
  {
    $handles = HandlerLib::for_parser($for);    
    $ids = ContentLib::get_ids_from_handles($handles);
    
    $valid_ids = HandlerLib::set_things($ids, $to, 'content', 'id', 'tags');
    
    return array_values(ContentLib::get_handles_from_ids($valid_ids));
  }
  
  /**
  * Remove a tag  
  * @param mixed A list of content handles
  * @param string The tag to remove
  * @return boolean 
  */ 
  static function remove_tag($for, $tag)
  {
    $handles = HandlerLib::for_parser($for);    
    $ids = ContentLib::get_ids_from_handles($handles);
    
    $valid_ids = HandlerLib::remove_things($ids, $tag, 'content', 'id', 'tags');
    
    return array_values(ContentLib::get_handles_from_ids($valid_ids));
  }
  
  
  /**
  * Add a new content item
  * @param string Type of content (accepts page, global, template, lens, exec, or view)
  * @param string Path of the content
  * @param string Value for the new item
  * @param string Tags are single words separated by spaces
  * @return boolean 
  */ 
  static function add($type, $path, $value=NULL, $tags=NULL)
  {    
    if(ContentLib::upsert_content($type, $path, $value, $tags))
      return "$type/$path";
  }
  
  
  /** 
  * When "on" your temp content gets imported with every page load (not for production use!)
  * @param string Acceptable values are "on" and "off"
  * @return boolean 
  */ 
  static function autoload($turn)
  {
    $filename = ".autoload";
    $content_dir = ContentLib::get_content_dir();
    $autoload_state = Content::check_autoload();
    
    if($turn == 'on')
    {
      if($autoload_state == 'on')
        return ErrorLib::set_error("Autoload is already turned on");
        
      FileLib::create_file($filename, $content_dir);
    }
    else
    {
      if($autoload_state == 'off')
        return ErrorLib::set_error("Autoload is already turned off");
        
      FileLib::remove_file($filename, $content_dir);
    }
  }
  
    
  /** 
  * Check the current autoload settings (replies with on or off)
  * @return boolean 
  * @key __world
  */ 
  static function check_autoload()
  {
    $filename = ".autoload";
    $content_dir = ContentLib::get_content_dir();
    
    return file_exists("$content_dir/$filename") ? 'on' : 'off';
  }
  
  
  
  /** 
  * Export all content to daimio/temp/content
  * 
  * Each type of content that is editable will be exported into the daimio/temp/content within its type's subdirectory.
  * The command {content autoload turn :on} enables autoloading, which is great for development (and terrible for production). You can also use the 'and' parameter to enable it here.
  * Note that exporting requires write access to the daimio directory. If the export fails, check your permissions.
  *
  * @param string Accepts :autoload
  * @return boolean 
  */ 
  static function export($and=NULL)
  {
    $content_dir = ContentLib::get_content_dir();

    // NOTE: it'd be nice to allow two-way merging of content, instead of all-or-nothing export/import.
    if(file_exists("$content_dir/.content_lock"))
      return ErrorLib::set_error('Content export already in progress, files are locked. Use the {content import and "remove"} command to re-enable export. (You may also manually remove the /daimio/temp/content/ directory if those files are outdated.)');
    
    // twiddle the daimio/temp/content dir
    ContentLib::remove_temp_content_dir();
    ContentLib::build_temp_content_dir();

    // get all (exportable) content
    $where_sql = "type = 'page' OR type = 'global' OR type = 'template' OR type = 'view' OR type = 'lens' OR type = 'exec'";
    $return_fields = 'ci.type as type, ci.path as path, cii.value as value';
    $content_items = ContentLib::get_content_array($where_sql, '', $return_fields);

    foreach($content_items as $content)
    {
      $filename = basename($content['path']) . '.daml';
      $path = $content_dir . '/' . $content['type'] . '/' . dirname($content['path']);
      FileLib::create_file($filename, $path, $content['value']);
    }
    
    if($and == 'autoload')
    {
      FileLib::create_file(".autoload", $content_dir);
    }
  }


  /** 
  * Put exported content files back in the database
  * @param string Specify a follow-up action (only "remove" is available currently: it removes the entire content directory, allowing another export)
  * @return boolean
  */ 
  static function import($and=NULL)
  {
    $content_dir = ContentLib::get_content_dir();

    // THINK: might be nice to allow two-way merging of content, instead of all-or-nothing export/import.
    if(!file_exists($content_dir))
      return ErrorLib::set_error("Directory $content_dir not found");
    
    // import the content
    ContentLib::import_content_from_dir($content_dir);
    
    // remove the directory (if requested)    
    if($and == 'remove')
    {
      ContentLib::remove_temp_content_dir();
    }
    
    // check metadata for bonus content dirs
    $bonus_dirs = Metadata::get_single_metadata_value_array('daimio', 'daimio', 'bonus_dirs');
    if(!$bonus_dirs) 
      return true;

    $site_dir = $GLOBALS['X']['SETTINGS']['site_directory'];
    
    // import the bonus content
    foreach($bonus_dirs as $dir)
    {
      $dir = "$site_dir/$dir";
      
      if(!file_exists($dir))
        ErrorLib::set_error("Bonus directory $dir not found");

      ContentLib::import_content_from_dir($dir, 'bonus');
    }
  }
  
  
  /** 
  * Purges content instance history from the database (back up db first!!!)
  * @param string Defaults to 7
  * @return boolean 
  */ 
  static function purge($days=NULL)
  {
    $days = ctype_digit((string) $days) ? $days : '7';
    if($days)
    {
      $until_date = date('Y-m-d', strtotime("-$days days"));
      $date_string = "AND cii.cron < '$until_date'";
    }

    // purge old instances for existing items
    $sql = "
      DELETE cii
      FROM content_instances AS cii,
      content AS ci
      WHERE
      cii.item_id = ci.id
      AND cii.version != ci.current_version
      $date_string
    ";    
    DBLib::query($sql);
    
    // purge instances of deleted items
    $sql = "
      DELETE cii 
      FROM content_instances AS cii 
      LEFT JOIN content AS ci 
      ON cii.item_id = ci.id 
      WHERE ci.id IS NULL;
    ";
    DBLib::query($sql);
    
    return true; // THINK: it'd be nice to give an idea of how many rows were purged...
  }
 
  /**
  * Permanently destroy a content item
  * @param string Handle of the content item
  * @return boolean 
  */ 
  static function destroy($handle)
  {
    $handles = HandlerLib::for_parser($handle);
    $content_dir = ContentLib::get_content_dir();
    
    foreach($handles as $handle)
    {
      if(!$id = reset(ContentLib::get_ids_from_handles($handle)))
      {
        ErrorLib::set_error("Content item not found for \"$handle\"");
        continue;
      }
      
      // put a copy of the content in /daimio/temp/destroyed
      $content_value = ContentLib::get_value_from_id($id);
      FileLib::create_destroyed_file($handle, $content_value);
      
      // destroy the content
      QueryLib::delete_from_table('content', 'id', $id);
      // NOTE: we're leaving the instances behind in the db. do this for awhile and see if you ever need them... we might want to remove them here to save space.   
      
      $path = "$content_dir/$handle.daml";
      if(file_exists($path))
        if(!unlink($path)) 
          ErrorLib::set_warning("Can not remove file $path");
          
      Processor::log_command('content');
    }
  }
   
}  

// EOT