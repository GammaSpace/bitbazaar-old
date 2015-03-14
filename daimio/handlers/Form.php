<?php

/**
 * Create and alter form sets
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Form 
{
  
  /**
  * get the array of where params  
  * @return array 
  */ 
  private function get_where_params()
  {
    $params = array('keyword', 'delete_perms', 'edit_perms', 'add_perms', 'read_perms', 'input_extensions');
    return $params;
  }
  
  
  
  /**
  * Return an array of selected form keywords  
  * @param string Limits the results -- see the documentation for information on the where string
  * @return string 
  */ 
  static function pick($where=NULL)
  {
    // THINK: a lot of this pick & fetch functionality is genericizable. look in to that.
    $params = Form::get_where_params();
    $where_sql = HandlerLib::where_parser($where, $params);
    
    $item_array = FormLib::get_form_array($where_sql, 'ORDER BY keyword ASC', 'keyword');

    $keywords = array();
    foreach($item_array as $item)
      $keywords[] = $item['keyword'];
      
    return $keywords;
  }
  
  /**
  * Return an array of selected forms
  * @param string Limits the results -- see the documentation for information on the where string
  * @return string 
  */ 
  static function fetch($where=NULL)
  {
    $params = Form::get_where_params();
    $where_sql = HandlerLib::where_parser($where, $params);
    
    $form_array = FormLib::get_form_array($where_sql);
    foreach($form_array as $form)
    {
      // OPT: it would be quicker to get all the fields at once...
      $form['fields'] = FormLib::get_fields($form['keyword']);
      $forms[$form['keyword']] = $form;
    }
    
    return array_values($forms);
  }
  
    
  /**
  * Display an html form
  * @param mixed A list of form keywords
  * @param string The id of the row to display (leave blank for a new row)
  * @param string Template for the main form layout
  * @param string Template for wrapping the individual fields
  * @param string A list of fields to include
  * @param string A list of fields to exclude
  * @param string A list of all fields to be returned
  * @param string Takes: 'data' to return an array
  * @param string Display options for the form and fields
  * @return string 
  * @key __world
  */ 
  static function display($keyword, $row=NULL, $layout=NULL, $wrapper=NULL, $include=NULL, $exclude=NULL, $fields=NULL, $return=NULL, $options=NULL)
  {    
    if($row)
    {
      if(FormLib::perms_are_bad($keyword, 'read', $row))
        return ErrorLib::set_error("You haven't permission to display that row");
    }
    
    // OPT: it might be possible to remove this get_fields call, since we do it in Field::display too
    $all_fields = FormLib::get_fields($keyword);
    $fields_keys = count($all_fields) ? array_keys($all_fields) : array();

    // THINK: i'm not entirely sure about using the for-style processor for these (or even for for for that matter), but we'll do it for now and sort out this syntax hurdle later.
    $just_these_fields = HandlerLib::for_parser($fields);
    $include_fields = HandlerLib::for_parser($include); 
    $exclude_fields = HandlerLib::for_parser($exclude);
      
    if($just_these_fields)
      $fetch_these = $just_these_fields;
    else
      $fetch_these = array_diff($fields_keys, $exclude_fields) + $include_fields;
    
    // get the fields n' stuff
    $form_data['fields'] = Field::display($keyword, $fetch_these, $row, $wrapper, $options, 'data');
    $form_data['row_id'] = $row;
    $form_data['form_keyword'] = $keyword;
        
    if(!$layout)
    {
      // YAGNI: allow form / site level layout templates also
      $layout = FormLib::get_default_layout_template();
    }
    
    if($return == 'data')
      return $form_data;
    else
      return Processor::merge($layout, array($form_data));
  }
  
  
  /**
  * Create a new form  
  * @param string Keyword of the new form
  * @return boolean 
  */ 
  static function add($keyword)
  {
    // valid keyword?
    if($keyword != QueryLib::scrub_string($keyword))
      return ErrorLib::set_error("The string \"$keyword\" is not a valid keyword");

    // unique keyword?
    if(FormLib::confirm_existing_keyword($keyword))
      return ErrorLib::set_error("A form with keyword \"$keyword\" already exists");
     
    // set default permissions
    $delete_perms = ' __owner ';
    $edit_perms = ' __owner ';
    $add_perms = ' __member ';
    $read_perms = ' __world ';
    
    $field_array[] = array('name' => 'user_id', 'type' => 'int(11)');
    $index_array = array('KEY user_id (user_id)');
    
    DBLib::create_table("form__$keyword", $field_array, $index_array);
    
    QueryLib::add_row('forms',
                      array(
                            'keyword' => $keyword,
                            'delete_perms' => $delete_perms,
                            'edit_perms' => $edit_perms,
                            'add_perms' => $add_perms,
                            'read_perms' => $read_perms
                           ));
                           
    Processor::log_command('form_commands');
    return $keyword;
  }
  
  
  /**
  * Set the permission levels for a form
  * 
  * You can use the standard magic keys __world and __member here, which allow world access and authenticated user access, respectively. You can also use the special __owner key, which grants access to the originator of a row, and the __lens key for use with the Lens handler.
  * If you're accessing data through models and lenses, you'll want to lock your forms up tight. Set the delete, edit, and add perms to '', and read perms to '__lens'.
  * 
  * @param mixed A list of form keywords
  * @param string Accepts delete, edit, add, read
  * @param string Keyholes are single words separated by spaces
  * @return boolean 
  */ 
  static function set_perms($for, $type, $keyholes)
  {
    $form_keywords = HandlerLib::for_parser($for);    

    if(!count($form_keywords))
      return ErrorLib::set_error("A valid form keyword was not supplied");

    if(!in_array($type, array('delete', 'edit', 'add', 'read')))
      return ErrorLib::set_error("Permission type \"$type\" is not one of :delete, :edit, :add, or :read");

    if($keyholes)
    {
      $keyholes = HandlerLib::for_parser($keyholes);
      $keyhole_string = ' ' . implode(' ', $keyholes) . ' ';      
    }

    foreach($form_keywords as $form_keyword)
    {
      QueryLib::set_single_value('forms', 'keyword', $form_keyword, $type . '_perms', $keyhole_string);
    }
    
    Processor::log_command('form_commands');
    return $form_keywords;
  }
  
  /**
  * Set the input_extensions for this form
  * @param mixed A list of form keywords
  * @param string Space-delimited string of input_extensions
  * @return boolean 
  */ 
  static function set_input_extensions($for, $to)
  {
    Processor::log_command('form_commands');
    return HandlerLib::set_things($for, $to, 'forms', 'keyword', 'input_extensions');
  }
  

  /**
  * Permanently delete a form  
  * @param mixed A form keyword
  * @return boolean
  */ 
  static function destroy($keyword)
  {    
    if(!FormLib::confirm_existing_keyword($keyword))
      return ErrorLib::set_warning("There is no form with keyword \"$keyword\"");
    
    $fields = FormLib::get_fields($keyword);

    foreach($fields as $field)
      FormLib::destroy_field($keyword, $field['keyword']);
    
    // THINK: back up the data in some fashion?
    
    DBLib::drop_table("form__$keyword");
  
    QueryLib::delete_from_table('forms', 'keyword', $keyword);
    QueryLib::delete_from_table('form_fields', 'form', $keyword);                           
    
    Processor::log_command('form_commands');
  }
}

//EOT