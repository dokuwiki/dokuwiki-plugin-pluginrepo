--- updates needed for the old repository database
ALTER TABLE plugins
    ADD COLUMN screenshot varchar(255) default NULL,
    ADD COLUMN tags varchar(255) default NULL,
    ADD COLUMN securitywarning varchar(255) default NULL,
    ADD COLUMN bestcompatible varchar(50) default NULL;

--- fill the new tags column with data from the old table
UPDATE plugins AS target
    INNER JOIN (
        SELECT plugin, GROUP_CONCAT(tag SEPARATOR ', ') as tags FROM plugin_tags GROUP BY plugin
    ) as source
ON target.plugin = source.plugin
SET target.tags = source.tags;

