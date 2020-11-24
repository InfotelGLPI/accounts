DROP TABLE IF EXISTS `glpi_plugin_comptes`;
CREATE TABLE `glpi_plugin_comptes` (
	`ID` int(11) NOT NULL auto_increment,
	`FK_entities` int(11) NOT NULL default '0',
	`name` varchar(255) collate utf8_unicode_ci NOT NULL default '',
	`login` varchar(30) collate utf8_unicode_ci NOT NULL default '',
	`mdp` varchar(30) collate utf8_unicode_ci NOT NULL default '',
	`others` varchar(100) collate utf8_unicode_ci NOT NULL default '',
	`type` tinyint(4) NOT NULL default '1',
	`status` tinyint(4) NOT NULL default '1',
	`creation_date` date NOT NULL default '0000-00-00',
	`expiration_date` date NOT NULL default '0000-00-00',
	`requester` int(4) NOT NULL default '0',
	`comments` text,
	`notes` LONGTEXT,
	`deleted` smallint(6) NOT NULL default '0',
	PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_compte_device`;
CREATE TABLE `glpi_plugin_compte_device` (
	`ID` int(11) NOT NULL auto_increment,
	`FK_compte` int(11) NOT NULL default '0',
	`FK_device` int(11) NOT NULL default '0',
	`device_type` int(11) NOT NULL default '0',
	PRIMARY KEY  (`ID`),
	UNIQUE KEY `FK_compte` (`FK_compte`,`FK_device`,`device_type`),
	KEY `FK_compte_2` (`FK_compte`),
	KEY `FK_device` (`FK_device`,`device_type`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_dropdown_plugin_compte_type`;
CREATE TABLE `glpi_dropdown_plugin_compte_type` (
	`ID` int(11) NOT NULL auto_increment,
	`FK_entities` int(11) NOT NULL default '0',
	`name` varchar(255) collate utf8_unicode_ci NOT NULL default '',
	`comments` text,
	PRIMARY KEY  (`ID`),
	KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_dropdown_plugin_compte_status`;
CREATE TABLE `glpi_dropdown_plugin_compte_status` (
	`ID` int(11) NOT NULL auto_increment,
	`name` varchar(255) collate utf8_unicode_ci NOT NULL default '',
	`comments` text,
	PRIMARY KEY  (`ID`),
	KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_compte_profiles`;
CREATE TABLE `glpi_plugin_compte_profiles` (
`ID` int(11) NOT NULL auto_increment,
`name` varchar(255) collate utf8_unicode_ci default NULL,
`interface` varchar(50) collate utf8_unicode_ci NOT NULL default 'compte',
`is_default` smallint(6) NOT NULL default '0',
`compte` char(1) collate utf8_unicode_ci default NULL,
`all_users` char(1) default NULL,
PRIMARY KEY  (`ID`),
KEY `interface` (`interface`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_compte_config`;
CREATE TABLE `glpi_plugin_compte_config` (
  `ID` int(11) NOT NULL auto_increment,
  `delay` varchar(50) collate utf8_unicode_ci NOT NULL default '30',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `glpi_plugin_compte_config` ( `ID` , `delay` ) VALUES (1, '30');

INSERT INTO glpi_plugin_compte_mailing ( `ID` , `type` , `FK_item` , `item_type`) VALUES ('1','compte','1','1');
INSERT INTO glpi_plugin_compte_mailing ( `ID` , `type` , `FK_item` , `item_type`) VALUES ('2','alert','1','1');

INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1900','2','3','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1900','3','1','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1900','4','2','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1900','5','4','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1900','6','5','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1900','7','6','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1901','2','3','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1901','3','1','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1901','4','2','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1901','5','4','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1901','6','5','0');
INSERT INTO `glpi_display` ( `ID` , `type` , `num` , `rank` , `FK_users` )  VALUES (NULL,'1901','7','6','0');