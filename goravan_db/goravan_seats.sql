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
-- Table structure for table `seats`
--

DROP TABLE IF EXISTS `seats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seats` (
  `seat_id_pk` int unsigned NOT NULL AUTO_INCREMENT,
  `seat_number` varchar(45) NOT NULL,
  `seat_row` tinyint unsigned NOT NULL,
  `seat_col` tinyint unsigned NOT NULL,
  `van_id_fk` int unsigned NOT NULL,
  PRIMARY KEY (`seat_id_pk`),
  KEY `fk_seats_vans1_idx` (`van_id_fk`),
  CONSTRAINT `fk_seats_vans1` FOREIGN KEY (`van_id_fk`) REFERENCES `vans` (`van_id_pk`)
) ENGINE=InnoDB AUTO_INCREMENT=173 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seats`
--

LOCK TABLES `seats` WRITE;
/*!40000 ALTER TABLE `seats` DISABLE KEYS */;
INSERT INTO `seats` VALUES (135,'A1',1,1,8),(136,'A2',1,2,8),(137,'B1',2,1,8),(138,'B2',2,2,8),(139,'C1',3,1,8),(140,'C2',3,2,8),(141,'D1',4,1,8),(142,'D2',4,2,8),(143,'E1',5,1,8),(144,'E2',5,2,8),(145,'F1',6,1,8),(146,'F2',6,2,8),(147,'G1',7,1,8),(148,'G2',7,2,8),(149,'A1',1,1,7),(150,'A2',1,2,7),(151,'B1',2,1,7),(152,'B2',2,2,7),(153,'C1',3,1,7),(154,'C2',3,2,7),(155,'D1',4,1,7),(156,'D2',4,2,7),(157,'E1',5,1,7),(158,'E2',5,2,7),(159,'F1',6,1,7),(160,'F2',6,2,7),(161,'G1',7,1,7),(162,'G2',7,2,7),(163,'A1',1,1,9),(164,'A2',1,2,9),(165,'B1',2,1,9),(166,'B2',2,2,9),(167,'C1',3,1,9),(168,'C2',3,2,9),(169,'D1',4,1,9),(170,'D2',4,2,9),(171,'E1',5,1,9),(172,'E2',5,2,9);
/*!40000 ALTER TABLE `seats` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-03 18:02:45
