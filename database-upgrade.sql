--- updates needed for the old repository database
ALTER TABLE plugins
    ADD COLUMN screenshot varchar(255) default NULL,
    ADD COLUMN tags varchar(255) default NULL,
    ADD COLUMN securitywarning varchar(255) default NULL,
    ADD COLUMN bestcompatible varchar(50) default NULL;


