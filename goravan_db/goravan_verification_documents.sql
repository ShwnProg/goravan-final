-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: goravan
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `verification_documents`
--

DROP TABLE IF EXISTS `verification_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_documents` (
  `document_id_pk` int unsigned NOT NULL AUTO_INCREMENT,
  `document_type` enum('senior','student','pwd') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  `user_id_fk` int unsigned NOT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`document_id_pk`),
  KEY `fk_verification_documents_users_idx` (`user_id_fk`),
  CONSTRAINT `fk_verification_documents_users` FOREIGN KEY (`user_id_fk`) REFERENCES `users` (`user_id_pk`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_documents`
--

LOCK TABLES `verification_documents` WRITE;
/*!40000 ALTER TABLE `verification_documents` DISABLE KEYS */;
INSERT INTO `verification_documents` VALUES (5,'student','1777534187_logo_white.png',NULL,'2026-04-30 07:29:48',29,NULL,NULL),(6,'student','1777542786_goravan_edited.png',NULL,'2026-04-30 09:53:06',31,NULL,NULL),(7,'pwd','1777546089_Bal.jpg',NULL,'2026-04-30 10:48:10',33,NULL,NULL);
/*!40000 ALTER TABLE `verification_documents` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-03 18:02:46
