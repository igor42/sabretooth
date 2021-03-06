<?php
/**
 * sample_list.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package sabretooth\ui
 * @filesource
 */

namespace sabretooth\ui;

/**
 * widget sample list
 * 
 * @package sabretooth\ui
 */
class sample_list extends base_list_widget
{
  /**
   * Constructor
   * 
   * Defines all variables required by the sample list.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args An associative array of arguments to be processed by the widget
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'sample', $args );
    
    $session = \sabretooth\session::self();

    $this->add_column( 'name', 'Name', true );
    $this->add_column( 'participants', 'Participants', false );
    $this->add_column( 'qnaires', 'Questionnaires', false );
  }
  
  /**
   * Set the rows array needed by the template.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function finish()
  {
    parent::finish();
    
    foreach( $this->get_record_list() as $record )
    {
      // assemble the row for this record
      $this->add_row( $record->id,
        array( 'name' => $record->name,
               'participants' => $record->get_participant_count(),
               'qnaires' => $record->get_qnaire_count() ) );
    }

    $this->finish_setting_rows();
  }
}
?>
