ALTER TABLE `glpi_plugin_compte_profiles` DROP `statistics`;
ALTER TABLE `glpi_plugin_compte_profiles` DROP `all_users`;
ALTER TABLE `glpi_plugin_compte_profiles` DROP `create_compte`;
ALTER TABLE `glpi_plugin_compte_profiles` DROP `update_compte`;
ALTER TABLE `glpi_plugin_compte_profiles` DROP `delete_compte`;
ALTER TABLE `glpi_plugin_compte_profiles` ADD `all_users` char(1) default NULL;
ALTER TABLE `glpi_plugin_compte_profiles` CHANGE `is_default` `is_default` smallint(6) NOT NULL default '0';
UPDATE `glpi_plugin_compte_profiles` SET `is_default` = '0' WHERE `is_default` = '1';
UPDATE `glpi_plugin_compte_profiles` SET `is_default` = '1' WHERE `is_default` = '2';

ALTER TABLE `glpi_plugin_comptes` ADD `FK_entities` int(11) NOT NULL default '0' AFTER `ID`;
ALTER TABLE `glpi_plugin_comptes` ADD `creation_date` date NOT NULL default '0000-00-00' AFTER `type`;
ALTER TABLE `glpi_plugin_comptes` ADD `expiration_date` date NOT NULL default '0000-00-00' AFTER `creation_date`;
ALTER TABLE `glpi_plugin_comptes` ADD `requester` int(4) NOT NULL default '0' AFTER `expiration_date`;
ALTER TABLE `glpi_plugin_comptes` ADD `others` varchar(100) collate utf8_unicode_ci NOT NULL default '' AFTER `mdp`;
ALTER TABLE `glpi_plugin_comptes` ADD `status` tinyint(4) NOT NULL default '1' AFTER `type`;
ALTER TABLE `glpi_plugin_comptes` CHANGE `deleted` `deleted` smallint(6) NOT NULL default '0';
UPDATE `glpi_plugin_comptes` SET `deleted` = '0' WHERE `deleted` = '1';
UPDATE `glpi_plugin_comptes` SET `deleted` = '1' WHERE `deleted` = '2';

DROP TABLE IF EXISTS `glpi_dropdown_plugin_compte_status`;
CREATE TABLE `glpi_dropdown_plugin_compte_status` (
	`ID` int(11) NOT NULL auto_increment,
	`name` varchar(255) collate utf8_unicode_ci NOT NULL default '',
	`comments` text,
	PRIMARY KEY  (`ID`),
	KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_compte_config`;
CREATE TABLE `glpi_plugin_compte_config` (
  `ID` int(11) NOT NULL auto_increment,
  `delay` varchar(50) collate utf8_unicode_ci NOT NULL default '30',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `glpi_plugin_compte_config` ( `ID` , `delay` ) VALUES (1, '30');

DROP TABLE IF EXISTS `glpi_plugin_compte_default`;
CREATE TABLE `glpi_plugin_compte_default` (
	`ID` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`status` INT( 11 ) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_compte_mailing`;
CREATE TABLE `glpi_plugin_compte_mailing` (
  `ID` int(11) NOT NULL auto_increment,
  `type` varchar(255) collate utf8_unicode_ci default NULL,
  `FK_item` int(11) NOT NULL default '0',
  `item_type` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ID`),
  UNIQUE KEY `mailings` (`type`,`FK_item`,`item_type`),
  KEY `type` (`type`),
  KEY `FK_item` (`FK_item`),
  KEY `item_type` (`item_type`),
  KEY `items` (`item_type`,`FK_item`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO glpi_plugin_compte_mailing ( `ID` , `type` , `FK_item` , `item_type`) VALUES ('1','compte','1','1');
INSERT INTO glpi_plugin_compte_mailing ( `ID` , `type` , `FK_item` , `item_type`) VALUES ('2','alert','1','1');

ALTER TABLE `glpi_dropdown_plugin_compte_type` ADD `FK_entities` int(11) NOT NULL default '0' AFTER `ID`;

INSERT INTO glpi_documents_items (documents_id,items_id,itemtype) SELECT FK_documents, FK_compte, '1900' FROM glpi_plugin_compte_documents;

DROP TABLE `glpi_plugin_compte_documents`;

ALTER TABLE `glpi_plugin_compte_device` CHANGE `device_type` `device_type` int(11) NOT NULL default '0';