<?php
/**
 * active_record.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package sabretooth\database
 * @filesource
 */

namespace sabretooth\database;

/**
 * active_record: abstract database table object
 *
 * The active_record class represents tables in the database.  Each table has its own class which
 * extends this class.  Furthermore, each table must have a single 'id' column as its primary key.
 * @package sabretooth\database
 */
abstract class active_record extends \sabretooth\base_object
{
  /**
   * Constructor
   * 
   * The constructor either creates a new object which can then be insert into the database by
   * calling the {@link save} method, or, if an primary key is provided then the row with the
   * requested primary id will be loaded.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param integer $id The primary key for this object.
   * @throws exception\runtime
   * @access public
   */
  public function __construct( $id = NULL )
  {
    // determine the columns for this table
    $db = \sabretooth\session::self()->get_db();
    $columns = $db->MetaColumnNames( static::get_table_name() );

    if( !is_array( $columns ) || 0 == count( $columns ) )
      throw new \sabretooth\exception\runtime(
        "Meta column names return no columns for table ".static::get_table_name(), __METHOD__ );

    foreach( $columns as $name ) $this->columns[ $name ] = NULL;
    
    if( NULL != $id )
    {
      // make sure this table has an id column as the primary key
      $primary_key_names = $db->MetaPrimaryKeys( static::get_table_name() );
      if( 1 != count( $primary_key_names ) || static::$primary_key_name != $primary_key_names[0] )
      {
        throw new \sabretooth\exception\runtime(
          'Unable to create record, single-column primary key "'.
          static::$primary_key_name.'" does not exist.', __METHOD__ );
      }
      $this->columns[static::$primary_key_name] = intval( $id );
    }
    
    // now load the data from the database
    // (this gets skipped if a primary key has not been set)
    $this->load();
  }
  
  /**
   * Destructor
   * 
   * The destructor will save the record to the database if auto-saving is on and the record
   * already exists in the database (new records must explicitely be saved to be added to the
   * database).
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function __destruct()
  {
    // save to the database if auto-saving is on
    if( self::$auto_save && isset( $this->columns[static::$primary_key_name] ) ) $this->save();
  }
  
  /**
   * Loads the active record from the database.
   * 
   * If this is a new record then this method does nothing, if the record's primary key is set then
   * the data from the corresponding row is loaded.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @throws exception\runtime
   * @access public
   */
  public function load()
  {
    if( isset( $this->columns[static::$primary_key_name] ) )
    {
      // not using a modifier here is ok since we're forcing id to be an integer
      $sql = sprintf( 'SELECT * FROM %s WHERE %s = %d',
                      static::get_table_name(),
                      static::$primary_key_name,
                      $this->columns[static::$primary_key_name] );

      $row = self::get_row( $sql );

      if( 0 == count( $row ) )
        throw new \sabretooth\exception\runtime(
          sprintf( 'Load failed to find record for %s with %s = %d.',
                   static::get_table_name(),
                   static::$primary_key_name,
                   $this->columns[static::$primary_key_name] ),
          __METHOD__ );

      $this->columns = $row;
    }
  }
  
  /**
   * Saves the active record to the database.
   * 
   * If this is a new record then a new row will be inserted, if not then the row with the
   * corresponding id will be updated.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function save()
  {
    if( $this->read_only ) return;

    // building the SET list since it is identical for inserts and updates
    $sets = '';
    $first = true;
    foreach( $this->columns as $key => $val )
    {
      if( static::$primary_key_name != $key )
      {
        // make sure to html-escape values for text-columns
        if( 'text' == static::get_column_type( $key ) ) $val = htmlentities( $val );

        $sets .= sprintf( '%s %s = %s',
                          $first ? '' : ',',
                          $key,
                          self::format_string( $val ) );
        $first = false;
      }
    }
    
    // either insert or update the row based on whether the primary key is set
    $sql = sprintf( is_null( $this->columns[static::$primary_key_name] )
                    ? 'INSERT INTO %s SET %s'
                    : 'UPDATE %s SET %s WHERE %s = %d',
                    static::get_table_name(),
                    $sets,
                    static::$primary_key_name,
                    $this->columns[static::$primary_key_name] );
    self::execute( $sql );
    
    // get the new new primary key
    if( is_null( $this->columns[static::$primary_key_name] ) ) $this->columns[static::$primary_key_name] = self::insert_id();
  }
  
  /**
   * Deletes the record.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function delete()
  {
    // warn if we are in read-only mode
    if( $this->read_only )
    {
      \sabretooth\log::warning( 'Tried to delete read-only record.' );
      return;
    }

    if( is_null( $this->columns[static::$primary_key_name] ) )
    {
      \sabretooth\log::warning( 'Tried to delete record with no id.' );
      return;
    }
    
    // not using a modifier here is ok since we're forcing id to be an integer
    $sql = sprintf( 'DELETE FROM %s WHERE %s = %d',
                    static::get_table_name(),
                    static::$primary_key_name,
                    $this->columns[static::$primary_key_name] );
    self::execute( $sql );
  }

  /**
   * Count the total number of rows in the table.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the count.
   * @return int
   * @static
   * @access public
   */
  public static function count( $modifier )
  {
    return self::get_one(
      sprintf( 'SELECT COUNT(*) FROM %s %s',
               static::get_table_name(),
               is_null( $modifier ) ? '' : $modifier->get_sql() ) );
  }

  /**
   * Magic get method.
   *
   * Magic get method which returns the column value from the record's table
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $column_name The name of the column or table being fetched from the database
   * @return mixed
   * @throws exception\argument
   * @access public
   */
  public function __get( $column_name )
  {
    // make sure the column exists
    if( !$this->has_column_name( $column_name ) )
      throw new \sabretooth\exception\argument( 'column_name', $column_name, __METHOD__ );
    
    return isset( $this->columns[ $column_name ] ) ? $this->columns[ $column_name ] : NULL;
  }

  /**
   * Magic set method.
   *
   * Magic set method which sets the column value to a record's table.
   * For this change to be writen to the database see the {@link save} method
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $column_name The name of the column
   * @param mixed $value The value to set the contents of a column to
   * @throws exception\argument
   * @access public
   */
  public function __set( $column_name, $value )
  {
    // make sure the column exists
    if( !$this->has_column_name( $column_name ) )
      throw new \sabretooth\exception\argument( 'column_name', $column_name, __METHOD__ );
    
    $this->columns[ $column_name ] = $value;
  }
  
  /**
   * Magic call method.
   * 
   * Magic call method which allows for several methods which get information about records in
   * tables linked to by this table by either a foreign key or joining table.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $name The name of the called function (should be get_<record>,
   *                     get_<record>_count() or get_<record>_list(), where <record> is the name
   *                     of an active record class related to this record.
   * @param array $args The arguments passed to the called function.  This can either be null or
   *                    a modifier to be applied to the magic methods.
   * @throws exception\runtime, exception\argument
   * @return mixed
   * @access public
   * @method array get_<record>() Returns the record with foreign keys referencing the <record>
   *               table.  For instance, if a record has a foreign key "other_id", then
   *               get_other() will return the "other" record with the id equal to other_id.
   * @method array get_record_list() Returns an array of records from the joining <record> table
   *               given the provided modifier.  If a record has a joining "has" table then
   *               calling get_other_list() will return an array of "other" records which are
   *               linked in the joining table, and get_other_count() will return the number of
   *               "other" recrods found in the joining table.
   * @method array get_record_list_inverted() This is the same as the non-inverted method but it
   *               returns all items which are NOT linked to joining table.
   * @method int get_<record>_count() Returns the number of records in the joining <record> table
   *             given the provided modifier.
   * @method int get_<record>_count_inverted() This is the same as the non-inverted method but it
   *             returns the number of records NOT in the joining table.
   * @method null add_<record>() Given an array of ids, this method adds associations between the
   *              current and foreign <record> by adding rows into the joining "has" table.
   * @method null remove_<record>() Given an id, this method removes the association between the
                  current and foreign <record> by removing the corresponding row from the joining
                  "has" table.
   */
  public function __call( $name, $args )
  {
    // create an exception which will be thrown if anything bad happens
    $exception = new \sabretooth\exception\runtime(
      sprintf( 'Call to undefined function: %s::%s().',
               get_called_class(),
               $name ), __METHOD__ );

    $return_value = NULL;

    // parse the function call name
    $name_parts = explode( '_', $name );
    
    // must have between 2 and 4 parts
    if( 2 > count( $name_parts ) || 4 < count( $name_parts ) ) throw $exception;
    
    // Based on the name of the called function, define what action we're taking using the
    // $name_parts array
    $action = $name_parts[0];
    $foreign_table_name = $name_parts[1];
    $sub_action = NULL;
    $inverted = false;

    // make sure action is valid
    if( 'get' != $action && 'add' != $action && 'remove' != $action ) throw $exception;

    // make sure the foreign table exists
    if( !self::table_exists( $foreign_table_name ) ) throw $exception;
    
    // define some handy strings
    $current_table_name = static::get_table_name();
    $current_key_name = $current_table_name.'.id';
    $current_key_value = $this->columns[ static::$primary_key_name ];
    $foreign_class_name = '\\sabretooth\\database\\'.$foreign_table_name;
    $foreign_key_name = $foreign_table_name.'.id';
    
    // add and remove require a joining table and have a sub-action
    $search_for_joining_table = 'add' == $action || 'remove' == $action;
    
    if( 3 <= count( $name_parts ) )
    {
      // make sure sub action is valid
      $sub_action = $name_parts[2];
      if( 'list' != $sub_action && 'count' != $sub_action ) throw $exception;
      
      $search_for_joining_table = true;
      
      // define the modifier
      $modifier = 1 == count( $args ) &&
                  'sabretooth\\database\\modifier' == get_class( $args[0] )
                ? $args[0]
                : new modifier();
    }
     
    // determine the joining table, if necessary
    if( $search_for_joining_table )
    {
      // the joining table may be <table>_has_<foreign_table> or <foreign>_has_<table>
      $forward_joining_table_name = $current_table_name.'_has_'.$foreign_table_name;
      $reverse_joining_table_name = $foreign_table_name.'_has_'.$current_table_name;
      
      if( $this->table_exists( $forward_joining_table_name ) )
      {
        $joining_table_name = $forward_joining_table_name;
      }
      else if( $this->table_exists( $reverse_joining_table_name ) )
      {
        $joining_table_name = $reverse_joining_table_name;
      }
      else
      { // joining table not found
        throw $exception;
      }
      
      // define a few more handy strings
      $joining_current_key_name = $joining_table_name.'.'.$current_table_name.'_id';
      $joining_foreign_key_name = $joining_table_name.'.'.$foreign_table_name.'_id';
    }

    if( 4 == count( $name_parts ) )
    {
      if( 'inverted' == $name_parts[3] ) $inverted = true;
      else throw $exception;
    }

    // now that we're relatively sure the method name is valid, make sure we have a valid record
    if( is_null( $current_key_value ) )
    { 
      \sabretooth\log::warning( 'Tried to query record with no id.' );
      return 0;
    }

    // once we get here we know for sure that the function name is valid
    if( 'add' == $action )
    { // calling: add_<record>( $ids )
      // warn if we are in read-only mode
      if( $this->read_only )
      {
        \sabretooth\log::warning(
          'Tried to add '.$foreign_table_name.' records to read-only record.' );
        return;
      }

      // make sure the first argument is a non-empty array of ids
      if( 1 != count( $args ) || !is_array( $args[0] ) || 0 == count( $args[0] ) )
        throw new \sabretooth\exception\argument( 'args', $args, __METHOD__ );
      $foreign_id_list = $args[0];
      
      $values = '';
      $first = true;
      foreach( $foreign_id_list as $foreign_key_value )
      {
        if( !$first ) $values .= ', ';
        $values .= sprintf( '(%s, %s)',
                         active_record::format_string( $current_key_value ),
                         active_record::format_string( $foreign_key_value ) );
        $first = false;
      }

      self::execute(
        sprintf( 'INSERT INTO %s (%s, %s) VALUES %s',
                 $joining_table_name,
                 $joining_current_key_name,
                 $joining_foreign_key_name,
                 $values ) );
    }
    else if( 'remove' == $action )
    { // calling: remove_<record>( $ids )
      // warn if we are in read-only mode
      if( $this->read_only )
      {
        \sabretooth\log::warning(
          'Tried to remove '.$foreign_table_name.' records to read-only record.' );
        return;
      }

      // make sure the first argument is a non-empty array of ids
      if( 1 != count( $args ) || 0 >= $args[0] )
        throw new \sabretooth\exception\argument( 'args', $args, __METHOD__ );
      $foreign_key_value = $args[0];
      
      $modifier = new modifier();
      $modifier->where( $joining_current_key_name, $current_key_value );
      $modifier->where( $joining_foreign_key_name, $foreign_key_value );

      self::execute(
        sprintf( 'DELETE FROM %s %s',
                 $joining_table_name,
                 $modifier->get_sql() ) );
    }
    else if( 'get' == $action && is_null( $sub_action ) )
    { // calling: get_<record>()
      // make sure this table has the correct foreign key
      if( !$this->has_column_name( $foreign_table_name.'_id' ) ) throw $exception;

      // create the record using the foreign key
      $return_value = new $foreign_class_name( $this->columns[ $foreign_table_name.'_id' ] );
    }
    else if( 'get' == $action && !is_null( $sub_action ) )
    { // calling one of: get_<record>_list( $modifier = NULL )
      //                 get_<record>_list_inverted( $modifier = NULL )
      //                 get_<record>_count( $modifier = NULL )
      //                 get_<record>_count_inverted( $modifier = NULL )
      if( $inverted )
      { // we need to invert the list
        // first create SQL to match all records in the joining table
        $sub_modifier = new modifier();
        $sub_modifier->where( $foreign_key_name, $joining_foreign_key_name, false );
        $sub_modifier->where( $joining_current_key_name, $current_key_name, false );
        $sub_modifier->where( $current_key_name, $current_key_value );
        $sub_select_sql =
          sprintf( 'SELECT %s FROM %s, %s, %s %s',
                   $joining_foreign_key_name,
                   $foreign_table_name,
                   $joining_table_name,
                   $current_table_name,
                   $sub_modifier->get_sql() );

        // now create SQL that gets all primary ids that are NOT in that list
        $modifier->where_not_in( $foreign_key_name, $sub_select_sql, false );
        $sql = sprintf( 'SELECT %s FROM %s %s',
                        'list' == $sub_action
                          ? $foreign_key_name
                          : 'COUNT( '.$foreign_key_name.' )',
                        $foreign_table_name,
                        $modifier->get_sql() );
      }
      else
      { // no inversion, just select the records from the joining table
        $modifier->where( $foreign_key_name, $joining_foreign_key_name, false );
        $modifier->where( $joining_current_key_name, $current_key_name, false );
        $modifier->where( $current_key_name, $current_key_value );
        $sql = sprintf( 'SELECT %s FROM %s, %s, %s %s',
                        'list' == $sub_action
                          ? $joining_foreign_key_name
                          : 'COUNT( '.$joining_foreign_key_name.' )',
                        $foreign_table_name,
                        $joining_table_name,
                        $current_table_name,
                        $modifier->get_sql() );
      }

      if( 'list' == $sub_action )
      {
        $ids = self::get_col( $sql );
        $return_value = array();
        foreach( $ids as $id ) array_push( $return_value, new $foreign_class_name( $id ) );
      }
      else // 'count' == $sub_action
      {
        $return_value = self::get_one( $sql );
      }
    }

    return $return_value;
  }

  /**
   * Select a number of records.
   * 
   * This method returns an array of records.
   * The modifier may include any columns from tables which this record has a foreign key
   * relationship with.  To sort by such columns make sure to include the table name along with
   * the column name (for instance 'table.column') as the sort column value.
   * Be careful when calling this method.  Based on the modifier object a record is created for
   * every row being selected, so selecting a very large number of rows (100+) isn't a good idea.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the selection.
   * @return array( active_record )
   * @static
   * @access public
   */
  public static function select( $modifier = NULL )
  {
    $records = array();
    $this_table = static::get_table_name();
    
    // check to see if the modifier is sorting a value in a foreign table
    $table_list = array( $this_table );
    if( !is_null( $modifier ) )
    {
      foreach( $modifier->get_order_columns() as $order )
      {
        $table = strstr( $order, '.', true );
        if( $table && 0 < strlen( $table ) && $table != $this_table )
        {
          // check to see if we have a foreign key for this table
          $temp_record = new static();
          $foreign_key_name = $table.'_id';
          if( $temp_record->has_column_name( $foreign_key_name ) )
          {
            // add the table to the list to select and join it in the modifier
            array_push( $table_list, $table );
            $modifier->where(
              $this_table.'.'.$foreign_key_name,
              $table.'.'.static::$primary_key_name, false );
          }
        }
      }
    }
    
    // build the table list
    $select_tables = '';
    $first = true;
    foreach( $table_list as $table )
    {
      $select_tables .= sprintf( '%s %s',
                                 $first ? '' : ',',
                                 $table );
      $first = false;
    }

    $id_list = self::get_col(
      sprintf( 'SELECT %s.%s FROM %s %s',
               $this_table,
               static::$primary_key_name,
               $select_tables,
               is_null( $modifier ) ? '' : $modifier->get_sql() ) );

    foreach( $id_list as $id ) array_push( $records, new static( $id ) );

    return $records;
  }

  /**
   * Get record using unique key.
   * 
   * This method returns an instance of the active record using the name and value of a unique key.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $column a column with the unique key property
   * @param string $value the value of the column to match
   * @return database\active_record
   * @static
   * @access public
   */
  public static function get_unique_record( $column, $value )
  {
    $record = NULL;
    $database = \sabretooth\session::self()->get_setting( 'db', 'database' );

    // determine the unique key(s)
    $modifier = new modifier();
    $modifier->where( 'TABLE_SCHEMA', $database );
    $modifier->where( 'TABLE_NAME', static::get_table_name() );
    $modifier->where( 'COLUMN_KEY', 'UNI' );

    $unique_keys = self::get_col( 
      sprintf( 'SELECT COLUMN_NAME FROM information_schema.COLUMNS %s',
               $modifier->get_sql() ) );
    
    // make sure the column is unique
    if( in_array( $column, $unique_keys ) )
    {
      // this returns null if no records are found
      $modifier = new modifier();
      $modifier->where( $column, $value );

      $id = self::get_one(
        sprintf( 'SELECT %s FROM %s %s',
                 static::$primary_key_name,
                 static::get_table_name(),
                 $modifier->get_sql() ) );

      if( !is_null( $id ) ) $record = new static( $id );
    }
    return $record;
  }

  /**
   * Returns the string formatted for database queries.
   * 
   * The returned value will be put in double quotes unless the input is null in which case NULL
   * is returned.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $string The string to format for use in a query.
   * @return string
   * @static
   * @access public
   */
  public static function format_string( $string )
  {
    // NULL values are returns as a MySQL NULL value
    if( is_null( $string ) ) return 'NULL';
    
    // trim whitespace from the begining and end of the string
    if( is_string( $string ) ) $string = trim( $string );
    
    return 0 == strlen( $string ) ? 'NULL' : '"'.mysql_real_escape_string( $string ).'"';
  }

  /**
   * Returns the name of the table associated with this active record.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return string
   * @access protected
   */
  protected static function get_table_name()
  {
    // table and class names (without namespaces) should always be identical
    return substr( strrchr( get_called_class(), '\\' ), 1 );
  }
  
  /**
   * Determines whether a particular table exists.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $name The name of the table to check for.
   * @return boolean
   * @access protected
   */
  protected static function table_exists( $name )
  {
    $database = \sabretooth\session::self()->get_setting( 'db', 'database' );
    $modifier = new modifier();
    $modifier->where( 'TABLE_SCHEMA', $database );
    $modifier->where( 'TABLE_NAME', $name );

    $count = self::get_one(
      sprintf( 'SELECT COUNT(*) FROM information_schema.TABLES %s',
               $modifier->get_sql() ) );

    return 0 < $count;
  }

  /**
   * Returns a column's data type.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $name A column name in the active record's corresponding table.
   * @return string
   * @access public
   */
  public static function get_column_type( $column_name )
  {
    $database = \sabretooth\session::self()->get_setting( 'db', 'database' );
    $modifier = new modifier();
    $modifier->where( 'TABLE_SCHEMA', $database );
    $modifier->where( 'TABLE_NAME', static::get_table_name() );
    $modifier->where( 'COLUMN_NAME', $column_name );

    $type = self::get_one(
      sprintf( 'SELECT DATA_TYPE FROM information_schema.COLUMNS %s',
               $modifier->get_sql() ) );

    if( NULL == $type )
      throw new \sabretooth\exception\argument( 'column_name', $column_name, __METHOD__ );

    return $type;
  }

  /**
   * Returns an array of all enum values for a particular column.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $name A column name in the active record's corresponding table.
   * @return array( string )
   * @access public
   */
  public static function get_enum_values( $column_name )
  {
    $database = \sabretooth\session::self()->get_setting( 'db', 'database' );
    $modifier = new modifier();
    $modifier->where( 'TABLE_SCHEMA', $database );
    $modifier->where( 'TABLE_NAME', static::get_table_name() );
    $modifier->where( 'COLUMN_NAME', $column_name );
    $modifier->where( 'DATA_TYPE', 'enum' );

    $type = self::get_one(
      sprintf( 'SELECT COLUMN_TYPE FROM information_schema.COLUMNS %s',
               $modifier->get_sql() ) );

    if( NULL == $type )
      throw new \sabretooth\exception\argument( 'column_name', $column_name, __METHOD__ );

    
    // match all strings in single quotes, then cut out the quotes from the match and return them
    preg_match_all( "/'[^']+'/", $type, $matches );
    $values = array();
    foreach( current( $matches ) as $match )
    {
      array_push( $values, substr( $match, 1, -1 ) );
    }
    return $values;
  }

  /**
   * Returns whether the record's associated table has a specific column name.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $name A column name
   * @return boolean
   * @access public
   */
  public function has_column_name( $column_name )
  {
    return array_key_exists( $column_name, $this->columns );
  }
  
  /**
   * Database convenience method.
   * 
   * Execute SQL statement $sql and return derived class of ADORecordSet if successful. Note that a
   * record set is always returned on success, even if we are executing an insert or update
   * statement.
   * Note: This is a convenience wrapper for ADOdb::Execute()
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $sql SQL statement
   * @param array $input_array binding variables to parameters
   * @return ADORecordSet
   * @throws exception\database
   * @static
   * @access protected
   */
  protected static function execute( $sql, $input_array = false )
  {
    $db = \sabretooth\session::self()->get_db();
    $result = $db->Execute( $sql, $input_array );
    if( false === $result )
    {
      // pass the db error code instead of a class error code
      throw new \sabretooth\exception\database( $db->ErrorMsg(), $sql, $db->ErrorNo() );
    }

    return $result;
  }
  
  /**
   * Database convenience method.
   * 
   * Executes the SQL and returns the first field of the first row.
   * Note: This is a convenience wrapper for ADOdb::GetOne()
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $sql SQL statement
   * @param array $input_array binding variables to parameters
   * @return native or NULL if no records were found.
   * @throws exception\database
   * @static
   * @access protected
   */
  protected static function get_one( $sql, $input_array = false )
  {
    $db = \sabretooth\session::self()->get_db();
    $result = $db->GetOne( $sql, $input_array );
    if( false === $result )
    {
      // pass the db error code instead of a class error code
      throw new \sabretooth\exception\database( $db->ErrorMsg(), $sql, $db->ErrorNo() );
    }

    return $result;
  }
  
  /**
   * Database convenience method.
   * 
   * Executes the SQL and returns the first row as an array.
   * Note: This is a convenience wrapper for ADOdb::GetRow()
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $sql SQL statement
   * @param array $input_array binding variables to parameters
   * @return array (empty if no records are found)
   * @throws exception\database
   * @static
   * @access protected
   */
  protected static function get_row( $sql, $input_array = false )
  {
    $db = \sabretooth\session::self()->get_db();
    $result = $db->GetRow( $sql, $input_array );
    if( false === $result )
    {
      // pass the db error code instead of a class error code
      throw new \sabretooth\exception\database( $db->ErrorMsg(), $sql, $db->ErrorNo() );
    }

    return $result;
  }
  
  /**
   * Database convenience method.
   * 
   * Executes the SQL and returns the all the rows as a 2-dimensional array.
   * Note: This is a convenience wrapper for ADOdb::GetAll()
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $sql SQL statement
   * @param array $input_array binding variables to parameters
   * @return array (empty if no records are found)
   * @throws exception\database
   * @static
   * @access protected
   */
  protected static function get_all( $sql, $input_array = false )
  {
    $db = \sabretooth\session::self()->get_db();
    $result = $db->GetAll( $sql, $input_array );
    if( false === $result )
    {
      // pass the db error code instead of a class error code
      throw new \sabretooth\exception\database( $db->ErrorMsg(), $sql, $db->ErrorNo() );
    }

    return $result;
  }
  
  /**
   * Database convenience method.
   * 
   * Executes the SQL and returns all elements of the first column as a 1-dimensional array.
   * Note: This is a convenience wrapper for ADOdb::GetCol()
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $sql SQL statement
   * @param array $input_array binding variables to parameters
   * @param boolean $trim determines whether to right trim CHAR fields
   * @return array (empty if no records are found)
   * @throws exception\database
   * @static
   * @access protected
   */
  protected static function get_col( $sql, $input_array = false, $trim = false )
  {
    $db = \sabretooth\session::self()->get_db();
    $result = $db->GetCol( $sql, $input_array, $trim );
    if( false === $result )
    {
      // pass the db error code instead of a class error code
      throw new \sabretooth\exception\database( $db->ErrorMsg(), $sql, $db->ErrorNo() );
    }

    return $result;
  }
  
  /**
   * Database convenience method.
   * 
   * Returns the last autonumbering ID inserted.
   * Note: This is a convenience wrapper for ADOdb::Insert_ID()
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return int
   * @static
   * @access protected
   */
  protected static function insert_id()
  {
    return \sabretooth\session::self()->get_db()->Insert_ID();
  }
  
  /**
   * Database convenience method.
   * 
   * Returns the number of rows affected by a update or delete statement.
   * Note: This is a convenience wrapper for ADOdb::Affected_Rows()
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return int
   * @static
   * @access protected
   */
  protected static function affected_rows( $sql, $input_array = false, $trim = false )
  {
    return \sabretooth\session::self()->get_db()->Affected_Rows();
  }
  
  /**
   * Determines whether the record is read only (no modifying the database).
   * @var boolean
   * @access protected
   */
  protected $read_only = false;

  /**
   * The name of the table's primary key column.
   * @var string
   * @access protected
   */
  protected static $primary_key_name = 'id';

  /**
   * Holds all table column values in an associate array where key=>value is
   * column_name=>column_value
   * @var array
   * @access private
   */
  private $columns = array();

  /**
   * Determines whether or not to write changes to the database on deletion.
   * This value affects ALL active records.
   * @var boolean
   * @static
   * @access public
   */
  public static $auto_save = false;
}
?>
