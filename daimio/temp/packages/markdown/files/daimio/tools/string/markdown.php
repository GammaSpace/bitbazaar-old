
/**
* Apply textile to a string
* @param string The string
* @return string 
* @key __world
*/ 
static function markdown($on)
{
  return MarkdownExtra::defaultTransform($on);
}
