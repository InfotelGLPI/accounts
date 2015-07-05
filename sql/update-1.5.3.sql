DROP TABLE IF EXISTS `glpi_plugin_compte_aeskey`;
CREATE TABLE `glpi_plugin_compte_aeskey` (
  `ID` int(11) NOT NULL auto_increment,
  `aeskey` varchar(255) collate utf8_unicode_ci NOT NULL default '',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;