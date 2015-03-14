<?php

/**
 * Assign enhanced functionality to innocuous sounding words
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Exec
{
  
  // This is an auto-generated file! DO NOT EDIT!!
  // Edits will be destroyed by the next {daimio freshen_commands} call.
  
  static $type = 'exec';
  static $table = 'execs';
  
  
  /** 
  * Find some things 
  * @param string 
  * @return array 
  */ 
  static function pick($where=NULL)
  {
    $where_sql = HandlerLib::where_parser($where);

    if($where_sql)
      $where_sql = "WHERE $where_sql";

    $sql = "
            SELECT keyword
            FROM execs
            $where_sql
           ";

    $item_array = QueryLib::make_data_array_from_query($sql);

    $keywords = array();
    foreach($item_array as $item)
      $keywords[] = $item['keyword'];
      
    return $keywords;
  }
  
  
  /** 
  * Use this is things get out of alignment  
  * @return boolean 
  */ 
  static function reconsolidate()
  {
    FileLib::create_lenslike_handler(self::$type, self::$table, 'Assign enhanced functionality to innocuous sounding words');
  }
  
  
  /** 
  * Add a new exec
  * 
  * @param string The keyword is also the method name
  * @param string Keyholes are single words separated by spaces
  * @param string A param, like :id, or a list of params, like (:from :to), or a hash of params, like {* (:keyword "A keyword" :params "A hash")}
  * @param string An integer N indicating that the first N params are required
  * @param string Short description
  * @param string Detailed help
  * @param string Tags are single words separated by spaces 
  * @return boolean
  */ 
  static function add($keyword, $keyholes, $params=NULL, $required=NULL, $apropos=NULL, $description=NULL, $tags=NULL)
  {
    // valid keyword?
    if(!$keyword || $keyword != QueryLib::scrub_string($keyword))
      return ErrorLib::set_error("Invalid keyword '$keyword'");
    
    // nice keyword?
    if(in_array($keyword, array('add', 'set_tags', 'add_tag', 'remove_tag', 'set_keyholes', 'add_keyhole', 'remove_keyhole')))
      return ErrorLib::set_error("Keyword can not be '$keyword'");
    
    // unique keyword?
    if(self::pick("keyword = $keyword"))
      return ErrorLib::set_error("That keyword is already in use");
    
    // valid params?
    if($params)
    {
      if(!is_array($params))
        $params = array($params);
        
      foreach($params as $param => $help)
      {
        if(ctype_digit((string) $param)) {
          $param = $help;
          $help = '';
        }
        
        if($param != QueryLib::scrub_string($param))
        {
          ErrorLib::set_error("Invalid param '$param'");
          $param_error = true;
        }
        else {
          $good_params[$param] = $help;
        }
      }
      
      $params = $good_params;
      
      if($param_error)
        return false;
    }

    // add conditions content
    $path = "$keyword/conditions";
    $content_dir = ContentLib::get_content_dir();
    if(!ContentLib::get_ids_from_handles($path))
      if(!ContentLib::upsert_content(self::$type, $path, '', self::$type)) 
        return ErrorLib::set_error("Could not create content");
    
    // if autoloading, add the conditions content file
    if(file_exists($content_dir))
      if(!file_exists("$content_dir/exec/$keyword/conditions.daml"))
        FileLib::create_file("conditions.daml", "$content_dir/exec/$keyword");     
    
    // add actions content
    $path = "$keyword/actions";
    if(!ContentLib::get_ids_from_handles($path))
      if(!ContentLib::upsert_content(self::$type, $path, '', self::$type)) 
        return ErrorLib::set_error("Could not create content");

    // if autoloading, add the actions content file
    if(file_exists($content_dir))
      if(!file_exists("$content_dir/exec/$keyword/actions.daml"))
        FileLib::create_file("actions.daml", "$content_dir/exec/$keyword");
      
    // scrub tags and keyholes
    $tags = HandlerLib::scrub_tags($tags);    
    $keyholes = HandlerLib::scrub_tags($keyholes);    

    // add thing to database
    QueryLib::add_row(self::$table,
                      array(
                            'keyword' => $keyword,
                            'keyholes' => $keyholes,
                            'params' => json_encode($params),
                            'required' => $required,
                            'description' => $description,
                            'apropos' => $apropos,
                            'tags' => $tags
                           ));
    
    // perform file and database updates
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
  
  /** 
  * Set the keyword 
  * @param string A keyword
  * @param string The new keyword will also be the method name
  * @return string 
  */ 
  static function set_keyword($old, $new)
  {
    // get thing
    if(!$thing = QueryLib::get_row(self::$table, 'keyword', $old))
      return ErrorLib::set_error("Invalid keyword");
    
    // valid keyword?
    if(!$new || $new != QueryLib::scrub_string($new))
      return ErrorLib::set_error("Invalid keyword '$new'");
    
    // nice keyword?
    if(in_array($new, array('add', 'set_tags', 'add_tag', 'remove_tag', 'set_keyholes', 'add_keyhole', 'remove_keyhole')))
      return ErrorLib::set_error("Keyword can not be '$new'");
    
    // unique keyword?
    if(self::pick("keyword = $new"))
      return ErrorLib::set_error("That keyword is already in use");
    
    // set keyword
    QueryLib::set_single_value(self::$table, 'keyword', $old, 'keyword', $new);
    
    // move the content
    $content_dir = ContentLib::get_content_dir();
    $path = "$content_dir/exec";
    if(file_exists("$path/$old"))
      rename("$path/$old", "$path/$new");
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $new;
  }
  
  
  /** 
  * Set the params 
  * @param string A keyword
  * @param string A param, like :id, or a list of params, like (:from :to), or a hash of params, like {* (:keyword "A keyword" :params "A hash")}
  * @param string An integer N indicating that the first N params are required
  * @return string 
  */ 
  static function set_params($keyword, $params, $required)
  {
    // get thing
    if(!$thing = QueryLib::get_row(self::$table, 'keyword', $keyword))
      return ErrorLib::set_error("Invalid keyword");
    
    // valid params?
    if($params)
    {
      if(!is_array($params))
        $params = array($params);
        
      foreach($params as $param => $help)
      {
        if(ctype_digit((string) $param)) {
          $param = $help;
          $help = '';
        }
        
        if($param != QueryLib::scrub_string($param))
        {
          ErrorLib::set_error("Invalid param '$param'");
          $param_error = true;
        }
        else {
          $good_params[$param] = $help;
        }
      }
      
      if($param_error)
        return false;
    }

    // set params and required
    QueryLib::set_single_value(self::$table, 'keyword', $keyword, 'params', json_encode($good_params));
    QueryLib::set_single_value(self::$table, 'keyword', $keyword, 'required', $required);
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
  
  /** 
  * Set the keyword 
  * @param string A keyword
  * @param string Short description
  * @param string Detailed help
  * @return string 
  */ 
  static function set_help($keyword, $apropos, $description)
  {
    // get thing
    if(!$thing = QueryLib::get_row(self::$table, 'keyword', $keyword))
      return ErrorLib::set_error("Invalid keyword");
    
    // set apropos and description
    QueryLib::set_single_value(self::$table, 'keyword', $keyword, 'apropos', $apropos);
    QueryLib::set_single_value(self::$table, 'keyword', $keyword, 'description', $description);
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
  
  /**
  * Set the tags  
  * @param mixed A list of keywords
  * @param string Tags are single words separated by spaces 
  * @return boolean 
  */ 
  static function set_tags($for, $to)
  {
    HandlerLib::set_things($for, $to, self::$table, 'keyword', 'tags');
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
  /**
  * Add a tag  
  * @param mixed A list of keywords
  * @param string Tags are single words separated by spaces
  * @return array 
  */ 
  static function add_tag($for, $tag)
  {
    HandlerLib::add_things($for, $tag, self::$table, 'keyword', 'tags');
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
  /**
  * Remove a tag  
  * @param mixed A list of keywords
  * @param string The tag to remove
  * @return array 
  */ 
  static function remove_tag($for, $tag)
  {
    HandlerLib::remove_things($for, $tag, self::$table, 'keyword', 'tags');
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
    
  /**
  * Set the keyholes
  * @param mixed A list of keywords
  * @param string Keyholes are single words separated by spaces 
  * @return boolean 
  */ 
  static function set_keyholes($for, $to)
  {
    HandlerLib::set_things($for, $to, self::$table, 'keyword', 'keyholes');
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
  /**
  * Add a keyhole
  * @param mixed A list of keywords
  * @param string The keyhole to add
  * @return boolean 
  */ 
  static function add_keyhole($for, $keyhole)
  {
    HandlerLib::add_things($for, $keyhole, self::$table, 'keyword', 'keyholes');
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  
  /**
  * Remove a keyhole
  * @param mixed A list of keywords
  * @param string The keyhole to remove
  * @return boolean 
  */ 
  static function remove_keyhole($for, $keyhole)
  {
    HandlerLib::remove_things($for, $keyhole, self::$table, 'keyword', 'keyholes');
    
    self::reconsolidate();
    Processor::log_command(self::$type);
    
    return $keyword;
  }
  

  // NOTE: keep this destroy function here at the bottom
  
  /**
  * Permanent, irrevocable destruction
  * @param string A keyword
  * @return boolean 
  */ 
  static function destroy($keyword)
  {
    if(!$thing = QueryLib::get_row(self::$table, 'keyword', $keyword))
      return ErrorLib::set_error("Invalid keyword '$keyword'");

    // remove from db
    QueryLib::delete_from_table(self::$table, 'keyword', $keyword);
    
    // remove content
    Content::destroy("exec/$keyword/conditions");
    Content::destroy("exec/$keyword/actions");
    
    Processor::log_command(self::$type);
    self::reconsolidate();
  }
  
  
  // THIS COMMENT MARKS THE DELETION DEMARKATION LINE


  /**
  * 
  * 
  * 
  * 
  * @param string Subject line
  * @param string Body of email
  * 
  * @return array
  * @key __world
  */
  static function admin_send_email($subject='', $body='')
  {
    // load params into local variables
    $params['subject'] = $subject;
    $params['body'] = $body;


    // process conditions
    $conditions = ContentLib::get_value_from_type_and_path(self::$type, "admin_send_email/conditions");
    if($error = trim(Processor::process_with_enhancements($conditions, '__' . self::$type, $params)))
      return ErrorLib::set_error($error);
    
    // process actions
    $actions = ContentLib::get_value_from_type_and_path(self::$type, "admin_send_email/actions");
    return Processor::process_with_enhancements($actions, '__' . self::$type, $params, 'arrayable');
  }


  /**
  * 
  * 
  * 
  * 
  * @param string Subject line of the email
  * @param string Body of the email
  * 
  * @return array
  * @key __world
  */
  static function send_member_email($subject, $body)
  {
    // load params into local variables
    $params['subject'] = $subject;
    $params['body'] = $body;


    // process conditions
    $conditions = ContentLib::get_value_from_type_and_path(self::$type, "send_member_email/conditions");
    if($error = trim(Processor::process_with_enhancements($conditions, '__' . self::$type, $params)))
      return ErrorLib::set_error($error);
    
    // process actions
    $actions = ContentLib::get_value_from_type_and_path(self::$type, "send_member_email/actions");
    return Processor::process_with_enhancements($actions, '__' . self::$type, $params, 'arrayable');
  }


  /**
  * Set your email address
  * 
  * 
  * 
  * @param string 
  * 
  * @return array
  * @key __world
  */
  static function set_email($to)
  {
    // load params into local variables
    $params['to'] = $to;


    // process conditions
    $conditions = ContentLib::get_value_from_type_and_path(self::$type, "set_email/conditions");
    if($error = trim(Processor::process_with_enhancements($conditions, '__' . self::$type, $params)))
      return ErrorLib::set_error($error);
    
    // process actions
    $actions = ContentLib::get_value_from_type_and_path(self::$type, "set_email/actions");
    return Processor::process_with_enhancements($actions, '__' . self::$type, $params, 'arrayable');
  }

}

//EOT