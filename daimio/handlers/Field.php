<?php

/**
 * Fields are to forms as cars are to subways
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Field
{
  
  /** 
  * Display some fields 
  * @param string Form keyword
  * @param mixed A list of field keywords
  * @param int The row id
  * @param string A string for wrapping the fields
  * @param mixed Some options
  * @param string Accepts :data
  * @return mixed 
  * @key __world
  */ 
  static function display($form, $fields=NULL, $row=NULL, $wrapper=NULL, $options=NULL, $return=NULL)
  {
    // row & perms
    if($row)
    {
      if(FormLib::perms_are_bad($form, 'read', $row))
        return ErrorLib::set_error("You haven't permission to display that row");
        
      $row_data = DataLib::fetch($form, "id = $row", $fields, '1');
      $row_data = reset($row_data);
    }
    
    // field keys
    if(!$all_fields = FormLib::get_fields($form))
      return ErrorLib::set_error("No fields found for form $form");
    
    $field_keys = array_keys($all_fields);
    
    if($fields)
    {
      $fields = HandlerLib::for_parser($fields);
      $field_keys = array_intersect($field_keys, $fields);
      if(!$field_keys)
        return ErrorLib::set_error("No fields selected from form $form");
    }
    
    // each field
    foreach($field_keys as $field_keyword)
    {
      $field = $all_fields[$field_keyword];
      
      // use $options to decide on the html_fieldname (for nested forms)
      $html_fieldname = $options['html_fieldname'] ? $options['html_fieldname'] : $field_keyword;

      // valuation decision (can be changed by the field mixin)
      if(array_key_exists($html_fieldname, $_POST))
        $value = $_POST[$html_fieldname]; // last user input
      elseif($row_data[$field_keyword])
        $value = $row_data[$field_keyword]; // row data
      elseif(array_key_exists($html_fieldname, $_GET))
        $value = $_GET[$html_fieldname]; // querystring input
      elseif($field['default_value'])
        $value = $field['default_value']; // default value
      else 
        $value = '';
      
      // decorator
      $decorator = $options['decorator'] ? $options['decorator'] : $field['decorator'];
      if(PageLib::valid_path($decorator))
        if($temp = ContentLib::get_value_from_handle("template:$decorator"))
          $decorator = $temp; // valid content path? get template.
      
      if(!$decorator)
        $decorator = MixMaster::make_the_call('fields', 'get_display_template',  $field['type'], array());
            
      if(!$decorator)
        continue; // no decorator? don't display the field

      // get some data
      $params = array('row_data' => $row_data, 'field' => $field, 'value' => $value, 'html_fieldname' => $html_fieldname, 'form_options' => $options);
      $display_data = MixMaster::make_the_call('fields', 'get_display_data',  $field['type'], $params);      
      $display_data = (array) $display_data + array('fieldname' => $html_fieldname, 'value' => $value) + $field;

      // set some values
      $data[$field_keyword] = $field;
      $data[$field_keyword]['value'] = $display_data['value'] ? $display_data['value'] : $value;
      
      // merge row_data and decorator into input
      $data[$field_keyword]['input_html'] = Processor::merge($decorator, array($display_data));
      
      // merge input into wrapper
      $wrapper = $wrapper ? $wrapper : FormLib::get_default_wrapper_template();
      $data[$field_keyword]['wrapped_html'] = Processor::merge($wrapper, array($data[$field_keyword]));

      // THINK: we need to find a way to cache form creation. and pages in general. this might end up involving a conversion from DAML to raw PHP... then loading a page would just involve grabbing the appropriate data and including the cached PHP file.

      // YAGNI: still need these for the form wrapper template: field options, form default, sitewide default (we have command + daimio default)
    }    
    
    if($return == 'data')
      return $data;
    
    foreach($data as $field)
      $output .= $field['wrapped_html'] . "\n\n";
        
    return $output;
  }
  
  
  /**
  * Add a field to a form
  * @param string Form keyword
  * @param string New field keyword
  * @param string Type of field (varchar, text, all that kinda thing)
  * @param string The name of the field to add
  * @param string Value this field takes by default
  * @param string Field description
  * @param string Field decorator
  * @param int Display order
  * @param array An array of field input_extensions
  * @param array Special options dependent on field type
  * @return boolean 
  */ 
  static function add($form, $keyword, $type, $name=NULL, $default=NULL, $description=NULL, $decorator=NULL, $sort_order=NULL, $input_extensions=NULL, $options=NULL)
  {
    if($keyword != QueryLib::scrub_string($keyword))
      return ErrorLib::set_error("The string \"$keyword\" is not a valid field keyword");
        
    if(!FormLib::confirm_existing_keyword($form))
      return ErrorLib::set_error("Form $form does not exist");
    
    // check for existing field
    if(FormLib::get_field_info($form, $keyword))
      return ErrorLib::set_error("A field with that keyword already exists in form $form");
     
    // name default
    $name = $name ? $name : ucfirst(strtr($keyword, '_', ' '));
    
    $params = array('form' => $form, 'keyword' => $keyword, 'name' => $name, 'options' => $options);
    $field_call = MixMaster::make_the_call('fields', 'add_field', $type, $params);

    if($field_call === false) 
      return false; // in case the field sets an error in the add_field method
      
    $sort_order = $sort_order + 0;
    $options = !$options ? array() : $options;
    $options = is_array($options) ? json_encode($options) : $options;
    QueryLib::add_row("form_fields", 
                      array(
                            'form' => $form,
                            'keyword' => $keyword,
                            'type' => $type,
                            'name' => $name,
                            'default_value' => $default,
                            'description' => $description,
                            'decorator' => $decorator,
                            'sort_order' => $sort_order,
                            'options' => $options
                           ));                             
      
    HandlerLib::set_things($keyword, $input_extensions, 'form_fields', 'keyword', 'input_extensions');
    
    // check field for pre_fetch and post_fetch methods, update form info
    $pre = MixMaster::check_for_method('fields', $type, 'pre_fetch');
    $post = MixMaster::check_for_method('fields', $type, 'post_fetch');
    if($pre)
      HandlerLib::add_things($form, $keyword, 'forms', 'keyword', 'pre_fetch_fields');
    if($post)
      HandlerLib::add_things($form, $keyword, 'forms', 'keyword', 'post_fetch_fields');
    
    Processor::log_command('form_commands');
    
    return $form;
  }
  
  /** 
  * Set field name
  * @param string 
  * @param string 
  * @param string 
  * @return string 
  */ 
  static function set_name($form, $keyword, $to)
  {
    return FormLib::edit_field($form, $keyword, 'name', $to);
  }
  
  
  /** 
   * Set field description
   * @param string 
   * @param string 
   * @param string 
   * @return string 
   */ 
   static function set_description($form, $keyword, $to)
   {
     return FormLib::edit_field($form, $keyword, 'description', $to);
   }
   
  /** 
   * Set field decorator
   * @param string 
   * @param string 
   * @param string 
   * @return string 
   */ 
   static function set_decorator($form, $keyword, $to)
   {
     return FormLib::edit_field($form, $keyword, 'decorator', $to);
   }
   
   
  /** 
   * Set field default_value
   * @param string 
   * @param string 
   * @param string 
   * @return string 
   */ 
   static function set_default_value($form, $keyword, $to)
   {
     return FormLib::edit_field($form, $keyword, 'default_value', $to);
   }
   
   /** 
  * Set field sort order
  * @param string 
  * @param string 
  * @param string 
  * @return string 
  */ 
  static function set_sort_order($form, $keyword, $to)
  {
    if(!ctype_digit((string) $to))
      return ErrorLib::set_error("Sort order must be a positive integer");

    return FormLib::edit_field($form, $keyword, 'sort_order', $to);
  }
  
  /** 
  * Set field options
  * @param string 
  * @param string 
  * @param string 
  * @return string 
  */ 
  static function set_options($form, $keyword, $to)
  {
    $to = is_array($to) ? json_encode($to) : $to;
    
    return FormLib::edit_field($form, $keyword, 'options', $to);
  }
  
  /** 
  * Set field input extensions
  * @param string 
  * @param string 
  * @param string 
  * @return string 
  */ 
  static function set_input_extensions($form, $keyword, $to)
  {
    if($return = FormLib::edit_field($form, $keyword, 'input_extensions', $to))
      HandlerLib::set_things($keyword, $to, 'form_fields', 'keyword', 'input_extensions');
    
    return $return;
  }
  
  /** 
  * Get a list of field types
  * @return array
  */ 
  static function get_types()
  {
    return MixMaster::get_mixins('fields');
  }

  /**
  * Destroy a field
  * @param string Form keyword
  * @param string Field keyword to remove
  * @return boolean 
  */ 
  static function destroy($form, $keyword)
  {
    if(!FormLib::confirm_existing_keyword($form))
      return ErrorLib::set_error("Form $form does not exist");

    FormLib::destroy_field($form, $keyword);
    
    // THINK: back up the field somehow?
    Processor::log_command('form_commands');
    
    return $form;
  }

}

// EOT