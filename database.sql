-- MySQL dump 10.11
--
-- Host: localhost    Database: pluginrepo
-- ------------------------------------------------------
-- Server version	5.0.51a-24+lenny1

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
-- Table structure for table `plugin_conflicts`
--

DROP TABLE IF EXISTS `plugin_conflicts`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `plugin_conflicts` (
  `plugin` varchar(50) NOT NULL,
  `other` varchar(50) NOT NULL,
  PRIMARY KEY  (`plugin`,`other`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `plugin_depends`
--

DROP TABLE IF EXISTS `plugin_depends`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `plugin_depends` (
  `plugin` varchar(50) NOT NULL,
  `other` varchar(50) NOT NULL,
  PRIMARY KEY  (`plugin`,`other`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `plugin_similar`
--

DROP TABLE IF EXISTS `plugin_similar`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `plugin_similar` (
  `plugin` varchar(50) NOT NULL,
  `other` varchar(50) NOT NULL,
  PRIMARY KEY  (`plugin`,`other`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `plugin_tags`
--

DROP TABLE IF EXISTS `plugin_tags`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `plugin_tags` (
  `plugin` varchar(50) NOT NULL,
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY  (`plugin`,`tag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Sotres the plugin tags';
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `plugins`
--

DROP TABLE IF EXISTS `plugins`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `plugins` (
  `plugin` varchar(50) NOT NULL,
  `name` varchar(255) default NULL,
  `description` varchar(255) default NULL,
  `author` varchar(255) default NULL,
  `email` varchar(255) default NULL,
  `compatible` varchar(255) default NULL,
  `lastupdate` date default NULL,
  `type` int(11) NOT NULL default '0',
  `securityissue` varchar(255) NOT NULL,
  PRIMARY KEY  (`plugin`),
  KEY `securityissue` (`securityissue`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Stores the plugins';
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `popularity`
--

DROP TABLE IF EXISTS `popularity`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `popularity` (
  `uid` varchar(32) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  KEY `uid` (`uid`,`key`),
  KEY `value` (`value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2009-09-15 19:00:40


ALTER TABLE plugins
  ADD COLUMN `downloadurl` varchar(255) NULL,
  ADD COLUMN `bugtracker` varchar(255) NULL,
  ADD COLUMN `sourcerepo` varchar(255) NULL,
  ADD COLUMN `donationurl` varchar(255) NULL;

