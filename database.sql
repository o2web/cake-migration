
-- --------------------------------------------------------

--
-- Table structure for table `migration_missings`
--

CREATE TABLE IF NOT EXISTS `migration_missings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration_remote_id` int(11) DEFAULT NULL,
  `model` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `local_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `migration_nodes`
--

CREATE TABLE IF NOT EXISTS `migration_nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `local_id` int(11) DEFAULT NULL,
  `tracked` tinyint(1) DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=7 ;

-- --------------------------------------------------------

--
-- Table structure for table `migration_remotes`
--

CREATE TABLE IF NOT EXISTS `migration_remotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `instance` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `local_id` int(11) DEFAULT NULL,
  `remote_id` int(11) DEFAULT NULL,
  `sign` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `overridable` text COLLATE utf8_unicode_ci,
  `updated` datetime DEFAULT NULL,
  `invalidated` tinyint(1) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=25 ;
