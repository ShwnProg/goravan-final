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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id_pk` int unsigned NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `role` enum('user','admin') NOT NULL,
  `created_at` datetime NOT NULL,
  `birthdate` date DEFAULT NULL,
  PRIMARY KEY (`user_id_pk`),
  UNIQUE KEY `email_UNIQUE` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (26,'Dump20','dump21@gmail.com','$2y$12$BxuPXJxaB2.a/mx5F9RoJeTKvDc4c5aZ1G66BKmD25syKEPCokFz.','09705641607','user','2026-04-30 01:52:59','2008-08-08'),(27,'Shawn Geroso','galdoshawn24@gmail.com','$2y$12$IGWQG8JrrlllKmwjeVy9eOOpcUmpFauGHbcagKY0qxKcLhk68Dmgi','09705641607','user','2026-04-30 02:12:43','2005-08-08'),(28,'Shawn Geroso','admin@gmail.com','$2y$12$V0I70sMb1uvLtMZr24xcO.yz.JmL0e/joSGyVjZLoZmH2jwxWIjRW','09705641607','admin','2026-04-30 07:11:44','2005-08-08'),(29,'Anna Lea Maasin','annaleamaasin@gmail.com','$2y$12$igyhFEAs5a0fl8OLedh0te0A.VOk1wJ2tqZZPfJpRgx/RP6Zj8xCS','09705641607','user','2026-04-30 07:29:48','2005-10-31'),(30,'Shawn Geroso','gerososhawn12@gmail.com','$2y$12$bZc3iyQlNOaB2UFYNNUkS.Jmu0KmlzKDJrq4Yk5CKeIamD2k21TmS','09705641607','user','2026-04-30 09:39:31','2006-03-08'),(31,'Anna Lea Maasin','haha@gmail.com','$2y$12$h7GRh6Zto.jbPj8Fjb8HwunpqmwbF5KqGvfddV9Iw5a8rDrush9ce','09705641607','user','2026-04-30 09:53:06','2007-07-11'),(32,'Jeff','jeff@gmail.com','$2y$12$0MBXXEdS2MACBv5CyVkxPeAn7fJgKTpiAt2/HlQmLq7bsTvaoLe7q','09120379846','user','2026-04-30 10:18:39','2005-07-25'),(33,'John Kerbey M Maaslom','johnkervzmanatadmaaslom@gmail.com','$2y$12$VyQSppkxlSxStlO2lMCZlerJkdG7ozhTCci3510PWstcUbEg6CNXC','09659425124','user','2026-04-30 10:48:10','2006-01-22'),(34,'Jeff','jeffpogi@gmail.com','$2y$12$dIALVN9qsUdO5E2NiyVwQuPC/oMQMT4czc4AIJEYW8IlXGKMAyYu2','09705641607','user','2026-04-30 11:19:13','2015-07-20'),(35,'Dump19','dump19@gmail.com','$2y$12$Epxf5oECpmxAyNRPLgvhgOEY5rEEQWr1IBnWsUpG4KxDxm6xZ.6sO','09705641607','user','2026-04-30 16:01:22','2005-08-08');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
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
