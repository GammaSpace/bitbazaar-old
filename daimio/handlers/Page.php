<?php

/**
 * I'm responsible for page-related stuff
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Page 
{  
  
  /**
  * Get an array of selected page paths  
  * @param string Limits the results -- see the documentation for information on the where string
  * @return array 
  * @key __world
  */ 
  static function pick($where=NULL)
  {
    $where_sql = HandlerLib::where_parser($where);
    
    $item_array = PageLib::get_page_array($where_sql, 'ORDER BY path ASC', 'path');

    $paths = array();
    foreach($item_array as $item)
      $paths[] = $item['path'];
      
    return $paths;
  }
  
  /**
  * Get an array of selected pages   
  * @param string Limits the results -- see the documentation for information on the where string
  * @param string Dictates the order of items returned 
  * @return array 
  * @key __world
  */ 
  static function fetch($where=NULL, $order=NULL)
  {
    $where_sql = HandlerLib::where_parser($where);
    $order_sql = preg_match('/\w+( (DESC|ASC))?/', $order) ? "ORDER BY $order" : '';
    
    $item_array = PageLib::get_page_array($where_sql, $order_sql);
    
    return $item_array;
  }

  
  /**
  * Add a new page  
  * 
  * Any path segment prefixed with '__' is a considered a wildcard. Whatever it matches will be assigned to a request variable of the same name, so for a page path like blog/__article_id/comments/__comment_id and the url foo.com/blog/10/comments/12 the param #article_id would be 10 and #comment_id would be 12.
  * 
  * @param string The URL path (like blog/__article_id/comments/__comment_id)
  * @param string Keyholes are single words separated by spaces
  * @param string Tags are single words separated by spaces
  * @return string 
  */ 
  static function add($path, $keyholes=NULL, $tags=NULL)
  {
    // strip fore and aft path slashes
    $path = trim($path, '/'); 

    // empty path
    if(!$path)
      return ErrorLib::set_error("Empty path is not allowed. To add a catch-all page use a wildcard path like __home");
    
    // ensure valid path check
    if(!PageLib::valid_path($path))
      return ErrorLib::set_error("Invalid page path \"$path\"");
    
    // all clear!
    
    // defaults and cleaning
    $tags = HandlerLib::scrub_tags($tags);    
    $keyholes = HandlerLib::scrub_tags($keyholes);    

    // unique path check
    if($conflicting_path = PageLib::existing_path_like($path))
      return ErrorLib::set_error("A page with matching path '$conflicting_path' exists");
    
    // so we can match a page to a url
    $path_like = PageLib::make_path_like($path);

    QueryLib::add_row('pages',
                      array(
                            'path' => $path,
                            'tags' => $tags,
                            'keyholes' => $keyholes,
                            'path_like' => $path_like
                           ));

    Processor::log_command('pages');
    return $path;
  }
  
 
  /**
  * Change the page path (careful!)
  * 
  * If you change this you will break URLs and you'll need to reconnect any content items associated with this page by updating their paths.
  * 
  * Any path segment prefixed with '__' is a considered a wildcard. Whatever it matches will be assigned to a request variable of the same name, so for a page path like blog/__article_id/comments/__comment_id and the url foo.com/blog/10/comments/12 the param #article_id would be 10 and #comment_id would be 12.
  * 
  * @param mixed Old page path
  * @param string New page path
  * @return array 
  */ 
  static function set_path($old, $new)
  {
    $old = trim($old, '/');
    $new = trim($new, '/');
    
    // page exists?
    if(!PageLib::get_page($old))
      return ErrorLib::set_error("Page '$old' does not exist");
    
    // valid new path?
    if(!PageLib::valid_path($new))
      return ErrorLib::set_error("Invalid page path '$new'");
    
    // unique new path?
    if($conflicting_path = PageLib::existing_path_like($new) && $conflicting_path != $old)
      return ErrorLib::set_error("A page with matching path '$conflicting_path' exists");

    QueryLib::set_single_value('pages', 'path', $old, 'path', $new);

    // so we can match a page to the new url
    $path_like = PageLib::make_path_like($new);
    QueryLib::set_single_value('pages', 'path', $new, 'path_like', $path_like);

    Processor::log_command('pages');
    return $new;
  }
  
  /**
  * Set the tags  
  * @param mixed A list of paths
  * @param string Tags are single words separated by spaces 
  * @return boolean 
  */ 
  static function set_tags($for, $to)
  {
    Processor::log_command('pages');
    return HandlerLib::set_things($for, $to, 'pages', 'path', 'tags');
  }
  
  /**
  * Add a tag  
  * @param mixed A list of paths
  * @param string Tags are single words separated by spaces
  * @return array 
  */ 
  static function add_tag($for, $tag)
  {
    Processor::log_command('pages');
    return HandlerLib::add_things($for, $tag, 'pages', 'path', 'tags');
  }
  
  /**
  * Remove a tag  
  * @param mixed A list of paths
  * @param string The tag to remove
  * @return array 
  */ 
  static function remove_tag($for, $tag)
  {
    Processor::log_command('pages');
    return HandlerLib::remove_things($for, $tag, 'pages', 'path', 'tags');
  }
  
  /**
  * Set the keyholes  
  * @param mixed A list of paths
  * @param string Keyholes are single words separated by spaces 
  * @return boolean 
  */ 
  static function set_keyholes($for, $to)
  {
    Processor::log_command('pages');
    return HandlerLib::set_things($for, $to, 'pages', 'path', 'keyholes');
  }
  
  /**
  * Add a keyhole to some pages
  * @param mixed A list of paths
  * @param string The keyhole to add
  * @return boolean 
  */ 
  static function add_keyhole($for, $keyhole)
  {
    Processor::log_command('pages');
    return HandlerLib::add_things($for, $keyhole, 'pages', 'path', 'keyholes');
  }
  
  /**
  * Remove a keyhole from some pages
  * @param mixed A list of paths
  * @param string The keyhole to remove
  * @return boolean 
  */ 
  static function remove_keyhole($for, $keyhole)
  {
    Processor::log_command('pages');
    return HandlerLib::remove_things($for, $keyhole, 'pages', 'path', 'keyholes');
  }
   
  
  /**
  * Get the full page content
  * @param string A url like 'item/12/notes' (protocol, domain, and subdirectory are unnecessary)
  * @return string 
  * @key __world
  */ 
  static function display($url)
  {
    // THINK: we're grabbing this page in unescaped format -- for big data loads we might want to allow a 'mode' param that could pass 'echo' through PageLib
    return PageLib::display($url, '');
  }
  
  
  /** 
  * Redirect to a page or url 
  * @param string Page path or raw URL
  * @param string Querystring to set on redirect. Accepts a string or a hash.
  * @return boolean 
  * @key __world
  */ 
  static function redirect($to, $set=NULL)
  {
    $url = $to;
    
    if(strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0)
    {
      $url = strpos($url, '/') === 0 ? substr($url, 1) : $url;
      $url = "{$GLOBALS['X']['VARS']['SITE']['path']}/$url";      
    }
    
    if(is_array($set))
    {
      foreach($set as $key => $value)
        $set_array[] .= urlencode($key) . '=' . urlencode($value);
      $set = join('&', $set_array);
    }
    
    if($set)
      $url .= "?$set";
      
    header("Location: " . $url . "\r\n");
    exit;
  }
  
  
  /**
  * Permanently destroy a page
  * @param string Path of the page to delete  
  * @return boolean 
  */ 
  static function destroy($for)
  {
    $paths = HandlerLib::for_parser($for);  

    if(!count($paths))
      return ErrorLib::set_error("No valid page paths were found");

    // TODO: check for dir in /content and autoload -- it won't destroy if it's there, so remove that too
    // TODO: add this to the temp/destroyed (a page export command?)
    // TODO: add notices for items linked to this destroyed page (content, etc)
    foreach($paths as $path)
      QueryLib::delete_from_table('pages', 'path', trim($path, '/'));
    
    Processor::log_command('pages');
  }
  
}


// EOT