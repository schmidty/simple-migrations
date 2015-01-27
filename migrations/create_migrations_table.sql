-- migrations table creation
--
--


DROP TABLE IF EXISTS `migrations`;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `file` varchar(999) NOT NULL,
  `table` varchar(500) NOT NULL,
  `folder` varchar(50) NOT NULL,
  `success` tinyint(3) NOT NULL DEFAULT 0,
  `error_message` varchar(999) NOT NULL,
  `retrys` integer DEFAULT 0 NOT NULL,
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

