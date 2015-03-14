
  /**
  * Apply textile to a string
  * @param string The string
  * @return string 
  * @key __world
  */ 
  static function textile($on)
  {
    $textile = new TextileLib();
    return $textile->TextileThis($on);
  }
