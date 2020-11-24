ALTER TABLE `glpi_plugin_compte` CHANGE `creation_date` `creation_date` DATE NULL default NULL;
UPDATE `glpi_plugin_compte` SET `creation_date` = NULL WHERE `creation_date` ='0000-00-00';
ALTER TABLE `glpi_plugin_compte` CHANGE `expiration_date` `expiration_date` DATE NULL default NULL;
UPDATE `glpi_plugin_compte` SET `expiration_date` = NULL WHERE `expiration_date` ='0000-00-00';

ALTER TABLE `glpi_plugin_compte_profiles` DROP COLUMN `interface` , DROP COLUMN `is_default`;

ALTER TABLE `glpi_plugin_compte` CHANGE `requester` `FK_users` int(4);
ALTER TABLE `glpi_plugin_compte_profiles` ADD `open_ticket` char(1) default NULL;

CREATE TABLE `glpi_plugin_compte_hash` (
  `ID` int(11) NOT NULL auto_increment,
  `hash` varchar(255) collate utf8_unicode_ci NOT NULL default '',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `glpi_plugin_compte_hash` ( `ID` , `hash` ) VALUES (1, '');