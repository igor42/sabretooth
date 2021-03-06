<?php
/**
 * base_delete.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package sabretooth\ui
 * @filesource
 */

namespace sabretooth\ui;

/**
 * Base class for all record "delete" actions.
 * 
 * @package sabretooth\ui
 */
abstract class base_delete extends base_record_action
{
  /**
   * Constructor.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $subject The widget's subject.
   * @param array $args Action arguments
   * @throws exception\argument
   * @access public
   */
  public function __construct( $subject, $args )
  {
    parent::__construct( $subject, 'delete', $args );

    // make sure we have an id (we don't actually need to use it since the parent does)
    $this->get_argument( 'id' );
  }
  
  /**
   * Executes the action.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function execute()
  {
    try
    {
      $this->get_record()->delete();
    }
    catch( \sabretooth\exception\database $e )
    { // help describe exceptions to the user
      if( $e->is_constrained() )
      {
        throw new \sabretooth\exception\notice(
          'Unable to delete the '.$this->get_subject().
          ' because it is being referenced by the database.', __METHOD__, $e );
      }

      throw $e;
    }
  }
}
?>
