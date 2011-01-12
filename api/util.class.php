<?php
/**
 * util.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package sabretooth
 */

namespace sabretooth;

/**
 * util: utility class of static methods
 *
 * This class is where all utility functions belong.  The class is final so that it cannot be
 * instantiated nor extended (and it shouldn't be!).  All methods within the class are static.
 * NOTE: only functions which do not logically belong in any class should be included here.
 * @package sabretooth
 */
final class util
{
  /**
   * Constructor (or not)
   * 
   * This method is kept private so that no one ever tries to instantiate it.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access private
   */
  private function __construct() {}

  /**
   * A replacement for print_r which is html-aware.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param mixed $data The data to display.
   * @static
   * @access public
   */
  public static function var_dump_html( $data )
  {
    // get the var_dump string
    ob_start();
    var_dump( $data );
    $output = "\n".ob_get_clean();

    // make strings magenta
    $output = preg_replace( '/("[^"]*")/', '<font color="magenta">${1}</font>', $output );

    // make types yellow and type braces red
    $output = preg_replace(
      '/\n( *)(bool|int|float|string|array)\(([^)]*)\)/',
      "\n".'${1}<font color="yellow">${2}</font>'.
      '<font color="red">(</font>'.
      '<font color="white"> ${3} </font>'.
      '<font color="red">)</font>',
      $output );
    
    // replace => with html arrows
    $output = str_replace( '=>', ' &#8658;', $output );
    
    // output as a pre
    echo '<pre style="font-weight: bold; color: #B0B0B0; background: black">'.$output.'</pre>';
  }
}
?>