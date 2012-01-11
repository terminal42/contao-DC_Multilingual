-- 
-- Table `tl_user`
-- 

CREATE TABLE `tl_user` (
  `language_dc` varchar(2) NOT NULL default '',
  `langPid` int(10) unsigned NOT NULL default '0',
  KEY `language_dc` (`language_dc`),
  KEY `langPid` (`langPid`),  
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
