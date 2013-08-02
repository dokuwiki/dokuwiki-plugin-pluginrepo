-- updates needed for the old repository database
ALTER TABLE plugins
    ADD COLUMN screenshot varchar(255) default NULL,
    ADD COLUMN tags varchar(255) default NULL,
    ADD COLUMN securitywarning varchar(255) default NULL,
    ADD COLUMN bestcompatible varchar(50) default NULL;

-- fill the new tags column with data from the old table
UPDATE plugins AS target
    INNER JOIN (
        SELECT plugin, GROUP_CONCAT(tag SEPARATOR ', ') as tags FROM plugin_tags GROUP BY plugin
    ) as source
ON target.plugin = source.plugin
SET target.tags = source.tags;


-- 2012-01-31 updates for flattened popularity data
ALTER TABLE plugins
    ADD COLUMN popularity int default 0;

-- 2013-08-02 some indexes added
CREATE INDEX idx_type  ON plugins (type);
CREATE INDEX idx_popularity  ON plugins (popularity);
CREATE INDEX idx_lastupdate  ON plugins (lastupdate);

CREATE FULLTEXT INDEX idx_search ON plugins(plugin, name, description, author, tags);
