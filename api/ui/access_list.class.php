<?php
/**
 * access_list.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package sabretooth\ui
 * @filesource
 */

namespace sabretooth\ui;

/**
 * widget access list
 * 
 * @package sabretooth\ui
 */
class access_list extends base_list_widget
{
  /**
   * Constructor
   * 
   * Defines all variables required by the access list.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args An associative array of arguments to be processed by the widget
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'access', $args );
    
    $session = \sabretooth\session::self();
    
    $this->add_column( 'user.name', 'User', true );
    $this->add_column( 'role.name', 'Role', true );
    $this->add_column( 'site.name', 'Site', true );
    $this->add_column( 'date', 'Granted', true );
  }

  /**
   * Finish setting the variables in the list widget, including filling in the rows.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function finish()
  {
    parent::finish();
    
    foreach( $this->get_record_list() as $record )
    {
      $this->add_row( $record->id,
        array( 'user.name' => $record->get_user()->name,
               'role.name' => $record->get_role()->name,
               'site.name' => $record->get_site()->name,
               'date' => \sabretooth\util::get_fuzzy_time_ago( $record->date ) ) );
    }

    $this->finish_setting_rows();
  }
}
?>
