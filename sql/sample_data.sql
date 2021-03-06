-- ----------------------------------------------------------------------------------------------------
-- This file has sample data for help with development.
-- It is highly recommended to not run this script for anything other than development purposes.
-- ----------------------------------------------------------------------------------------------------
SET AUTOCOMMIT=0;

DELETE FROM site;
INSERT INTO site( name ) VALUES( 'Dalhousie' );
INSERT INTO site( name ) VALUES( 'McMaster' );
INSERT INTO site( name ) VALUES( 'Manitoba' );
INSERT INTO site( name ) VALUES( 'Sherbrooke' );
INSERT INTO site( name ) VALUES( 'Usher' );
INSERT INTO site( name ) VALUES( 'Victoria' );

DELETE FROM user;
INSERT INTO user( name ) VALUES( 'patrick' );
INSERT INTO user( name ) VALUES( 'dipietv' );
INSERT INTO user( name ) VALUES( 'kamzic' );

DELETE FROM access;
INSERT INTO access
SET user_id = ( SELECT id FROM user WHERE name = 'patrick' ),
    role_id = ( SELECT id FROM role WHERE name = 'administrator' ),
    site_id = ( SELECT id FROM site WHERE name = 'McMaster' );
INSERT INTO access
SET user_id = ( SELECT id FROM user WHERE name = 'dipietv' ),
    role_id = ( SELECT id FROM role WHERE name = 'administrator' ),
    site_id = ( SELECT id FROM site WHERE name = 'McMaster' );
INSERT INTO access
SET user_id = ( SELECT id FROM user WHERE name = 'kamzic' ),
    role_id = ( SELECT id FROM role WHERE name = 'administrator' ),
    site_id = ( SELECT id FROM site WHERE name = 'McMaster' );

COMMIT;
