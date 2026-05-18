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
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `schedule_id_pk` int unsigned NOT NULL AUTO_INCREMENT,
  `route_id_fk` int unsigned NOT NULL,
  `driver_id_fk` int unsigned NOT NULL,
  `van_id_fk` int unsigned NOT NULL,
  `departure_date` date NOT NULL,
  `departure_time` time NOT NULL,
  `trip_status` enum('boarding','departed','arrived','cancelled') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `arrived_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id_pk`),
  KEY `fk_schedules_routes1_idx` (`route_id_fk`),
  KEY `fk_schedules_drivers1_idx` (`driver_id_fk`),
  KEY `fk_schedules_vans1_idx` (`van_id_fk`),
  CONSTRAINT `fk_schedules_drivers1` FOREIGN KEY (`driver_id_fk`) REFERENCES `drivers` (`driver_id_pk`),
  CONSTRAINT `fk_schedules_routes1` FOREIGN KEY (`route_id_fk`) REFERENCES `routes` (`route_id_pk`),
  CONSTRAINT `fk_schedules_vans1` FOREIGN KEY (`van_id_fk`) REFERENCES `vans` (`van_id_pk`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
INSERT INTO `schedules` VALUES (5,33,2,9,'2026-05-03','17:14:00','cancelled','2026-05-03 07:12:20','2026-05-03 08:34:25','2026-05-03 08:28:57'),(6,32,2,8,'2026-05-13','16:20:00','arrived','2026-05-03 08:21:01','2026-05-03 08:33:01','2026-05-03 08:33:01'),(7,30,1,8,'2026-06-04','16:37:00','boarding','2026-05-03 08:35:10','2026-05-03 08:35:29','2026-05-03 08:35:10');
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
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
