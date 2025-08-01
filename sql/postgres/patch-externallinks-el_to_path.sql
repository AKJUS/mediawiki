-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-externallinks-el_to_path.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP INDEX el_from;

ALTER TABLE externallinks
  ADD el_to_domain_index TEXT DEFAULT '' NOT NULL;

ALTER TABLE externallinks
  ADD el_to_path TEXT DEFAULT NULL;

CREATE INDEX el_to_domain_index_to_path ON externallinks (el_to_domain_index, el_to_path);

CREATE INDEX el_from ON externallinks (el_from);
