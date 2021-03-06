-- MySQL dump 10.11
--
-- Host: localhost    Database: memex_test
-- ------------------------------------------------------
-- Server version	5.0.77-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `logins`
--

DROP TABLE IF EXISTS `logins`;
CREATE TABLE `logins` (
  `id` int(11) NOT NULL auto_increment,
  `login_name` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(32) NOT NULL,
  `created` datetime default NULL,
  `last_login` datetime default NULL,
  `active` tinyint(2) NOT NULL default '1',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `login_name` (`login_name`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `login_email_verification_tokens`
--

DROP TABLE IF EXISTS `login_email_verification_tokens`;
CREATE TABLE `login_email_verification_tokens` (
  `id` int(11) NOT NULL auto_increment,
  `login_id` int(11) default NULL,
  `token` varchar(32) default NULL,
  `value` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `login_email_verification_tokens`
    ADD CONSTRAINT `login_email_verification_tokens_ibfk_2` FOREIGN KEY (`login_id`) REFERENCES `logins` (`id`) ON DELETE CASCADE;

--
-- Table structure for table `login_password_reset_tokens`
--

DROP TABLE IF EXISTS `login_password_reset_tokens`;
CREATE TABLE `login_password_reset_tokens` (
  `id` int(11) NOT NULL auto_increment,
  `login_id` int(11) default NULL,
  `token` varchar(32) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `login_password_reset_tokens`
    ADD CONSTRAINT `login_password_reset_tokens_ibfk_2` FOREIGN KEY (`login_id`) REFERENCES `logins` (`id`) ON DELETE CASCADE;

--
-- Table structure for table `profiles`
--

DROP TABLE IF EXISTS `profiles`;
CREATE TABLE `profiles` (
  `id` int(11) NOT NULL auto_increment,
  `uuid` varchar(40) NOT NULL,
  `screen_name` varchar(64) NOT NULL,
  `full_name` varchar(128) NOT NULL,
  `bio` text,
  `created` datetime default NULL,
  `last_login` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `screen_name` (`screen_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `logins_profiles`
--

DROP TABLE IF EXISTS `logins_profiles`;
CREATE TABLE `logins_profiles` (
  `id` int(11) NOT NULL auto_increment,
  `login_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `login_id_profile_id` (`login_id`,`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `logins_profiles`
    ADD CONSTRAINT `logins_profiles_ibfk_1` FOREIGN KEY (`login_id`) REFERENCES `logins` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `logins_profiles_ibfk_2` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE;

--
-- Table structure for table `profile_attributes`
--

DROP TABLE IF EXISTS `profile_attributes`;
CREATE TABLE `profile_attributes` (
  `id` int(11) NOT NULL auto_increment,
  `profile_id` int(11) NOT NULL,
  `name` varchar(255) default NULL,
  `value` text,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `profile_id_name` (`profile_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `profile_attributes`
    ADD CONSTRAINT `profile_attributes_ibfk_1` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(32) default NULL,
  `description` text,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(32) default NULL,
  `description` text,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `permissions_roles`
--

DROP TABLE IF EXISTS `permissions_roles`;
CREATE TABLE `permissions_roles` (
  `id` int(11) NOT NULL auto_increment,
  `role_id` int(11) default NULL,
  `permission_id` int(11) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `permissions_roles`
    ADD CONSTRAINT `permissions_roles_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `permissions_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Table structure for table `profiles_roles`
--

DROP TABLE IF EXISTS `profiles_roles`;
CREATE TABLE `profiles_roles` (
  `id` int(11) NOT NULL auto_increment,
  `profile_id` int(11) default NULL,
  `role_id` int(11) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `profiles_roles`
    ADD CONSTRAINT `profiles_roles_ibfk_1` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `profiles_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
CREATE TABLE `posts` (
  `id` int(11) NOT NULL auto_increment,
  `uuid` varchar(40) NOT NULL,
  `signature` varchar(40) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `url_id` int(11) NOT NULL,
  `title` varchar(255) default NULL,
  `notes` text,
  `tags` text,
  `visibility` int(11) default NULL,
  `user_date` datetime default NULL,
  `created` datetime default NULL,
  `modified` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `profile_id_url_id` (`profile_id`,`url_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `url_id` (`url_id`),
  KEY `user_date` (`user_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL auto_increment,
  `post_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `url_id` int(11) NOT NULL,
  `tag` varchar(128) default NULL,
  `position` int(11) default '0',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `profile_id_url_id_tag` (`profile_id`,`url_id`,`tag`),
  KEY `post_id` (`post_id`),
  KEY `profile_id_tag` (`profile_id`,`tag`),
  KEY `url_id` (`url_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `updates`
--

DROP TABLE IF EXISTS `updates`;
CREATE TABLE `updates` (
  `hash` char(32) NOT NULL default '',
  `updated` datetime default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `urls`
--

DROP TABLE IF EXISTS `urls`;
CREATE TABLE `urls` (
  `id` int(11) NOT NULL auto_increment,
  `url` text NOT NULL,
  `hostname` varchar(255) default NULL,
  `hash` varchar(32) default NULL,
  `content` text,
  `first_profile_id` int(11) default NULL,
  `latest_profile_id` int(11) default NULL,
  `created` datetime default NULL,
  `modified` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `url` (`url`(255)),
  KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `message_queue`
--

DROP TABLE IF EXISTS `message_queue`;
CREATE TABLE `message_queue` (
  `uuid` varchar(40) NOT NULL,
  `owner` varchar(255) default NULL,
  `batch_uuid` varchar(40) default NULL,
  `batch_seq` int(11) default '0',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `scheduled_for` datetime default NULL,
  `reserved_at` datetime default NULL,
  `reserved_until` datetime default NULL,
  `finished_at` datetime default NULL,
  `priority` int(11) default '0',
  `topic` varchar(255) default NULL,
  `object` varchar(255) default NULL,
  `method` varchar(255) default NULL,
  `context` text,
  `body` text,
  `signature` char(32) default NULL,
  PRIMARY KEY  (`uuid`),
  KEY `created` (`created`),
  KEY `priority` (`priority`),
  KEY `batch_seq` (`batch_seq`),
  KEY `signature` (`signature`),
  KEY `reserved_at` (`reserved_at`),
  KEY `finished_at` (`finished_at`),
  KEY `scheduled_for` (`scheduled_for`),
  KEY `batch_uuid` (`batch_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2009-06-08 22:12:01
