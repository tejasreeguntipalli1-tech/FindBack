-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: fsd_lost_and_found
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `fsd_lost_and_found`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `fsd_lost_and_found` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `fsd_lost_and_found`;

--
-- Table structure for table `admin_activity_log`
--

DROP TABLE IF EXISTS `admin_activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `report_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `admin_activity_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_activity_log`
--

LOCK TABLES `admin_activity_log` WRITE;
/*!40000 ALTER TABLE `admin_activity_log` DISABLE KEYS */;
INSERT INTO `admin_activity_log` VALUES (1,1,1,'VERIFY_REPORT','Verified report #1 \"Black Casio fx-991EX Scientific Calculator\" submitted by Alex Johnson.','2026-10-02 06:30:00'),(2,1,2,'VERIFY_REPORT','Verified report #2 \"Black Casio Calculator\" submitted by Sarah Smith.','2026-10-02 08:30:00'),(3,1,6,'VERIFY_REPORT','Verified report #6 \"Student ID Card - Sarah Smith\".','2026-09-20 04:45:00'),(4,1,6,'MARK_RECOVERED','Marked report #6 as RECOVERED upon student pickup confirmation at Security Desk.','2026-09-22 08:30:00'),(5,1,12,'REJECT_REPORT','Rejected report #12: Invalid report content / test submission.','2026-08-20 06:35:00'),(6,1,13,'VERIFY_REPORT','Verified report #13 \"Apple iPhone 13 White/Starlight\" submitted by Priya Patel.','2026-10-03 03:15:00'),(7,1,15,'VERIFY_REPORT','Admin verified report #15 \'Blue Parker Vector Fountain Pen\'.','2026-10-03 12:26:17'),(8,1,16,'DELETE_REPORT','Soft deleted report #16.','2026-10-03 12:26:17'),(9,1,19,'REJECT_REPORT','Admin rejected report #19: Item photo is blurry and description lacks distinguishing brand/tag marks. Please re-submit with clear details.','2026-10-03 12:29:22'),(10,1,22,'REJECT_REPORT','Admin rejected report #22: Item photo is blurry and description lacks distinguishing brand/tag marks. Please re-submit with clear details.','2026-10-03 12:33:47');
/*!40000 ALTER TABLE `admin_activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-box',
  `description` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'ID Cards & Badges','fa-id-card','Student IDs, access cards, library badges'),(2,'Mobile Phones','fa-mobile-screen','Smartphones, feature phones, cases'),(3,'Wallets & Purses','fa-wallet','Wallets, coin purses, money clips'),(4,'Keys & Keychains','fa-key','Hostel keys, vehicle keys, locker keys'),(5,'Electronics & Gadgets','fa-laptop','Laptops, calculators, chargers, earphones, smart watches'),(6,'Books & Stationery','fa-book','Textbooks, notebooks, scientific calculators, stationery pouches'),(7,'Bags & Backpacks','fa-bag-shopping','Backpacks, laptop sleeves, gym bags'),(8,'Accessories & Wearables','fa-glasses','Glasses, wristwatches, jewelry, water bottles'),(9,'Other Belongings','fa-box-open','Umbrellas, sports gear, misc items');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_requests`
--

DROP TABLE IF EXISTS `contact_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `contact_email` varchar(150) NOT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `report_id` (`report_id`),
  CONSTRAINT `contact_requests_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_requests_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_requests_ibfk_3` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_requests`
--

LOCK TABLES `contact_requests` WRITE;
/*!40000 ALTER TABLE `contact_requests` DISABLE KEYS */;
INSERT INTO `contact_requests` VALUES (1,2,3,2,'Hi Sarah, I saw your found post for the Casio calculator in CSE Block room 204. That is mine! Can we meet at the security desk to verify?','alex@campus.edu','+91 91234 56789','pending','2026-10-02 08:40:00');
/*!40000 ALTER TABLE `contact_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('match','verification','recovery','contact','system') DEFAULT 'system',
  `link` varchar(255) DEFAULT NULL,
  `related_report_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `related_report_id` (`related_report_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,'Smart Match Found (87%)','A found report \"#2 Black Casio Calculator\" closely matches your lost report \"#1 Black Casio fx-991EX\".','match','student/matches.php?report_id=1',NULL,0,'2026-10-02 08:16:00'),(2,5,'Smart Match Found (85%)','A found item matching your iPhone 13 was reported at Central Cafeteria.','match','student/matches.php?report_id=13',NULL,0,'2026-10-03 03:46:00'),(3,3,'Report Verified by Security Admin','Your report for \"Student ID Card - Sarah Smith\" was verified and published.','verification','item_details.php?id=6',NULL,1,'2026-09-20 04:45:00'),(4,4,'Report Action: Rejected','Your submission was rejected: Invalid report content / test submission without real item details.','verification','student/my_reports.php',NULL,1,'2026-08-20 06:35:00'),(5,2,'Smart Match (88%)','A potential match for your lost item \'Blue Parker Vector Fountain Pen\' was detected with found report \'Blue Parker Pen\'.','match','/fsd_lost_and_found/student/matches.php?report_id=15',16,0,'2026-10-03 12:26:17'),(6,3,'Smart Match (88%)','Your found item \'Blue Parker Pen\' may match a lost item reported on campus.','match','/fsd_lost_and_found/student/matches.php?report_id=16',15,0,'2026-10-03 12:26:17'),(7,2,'Report Verified','Your report for \'Blue Parker Vector Fountain Pen\' has been approved by admin.','verification','item_details.php?id=15',NULL,0,'2026-10-03 12:26:17');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `potential_matches`
--

DROP TABLE IF EXISTS `potential_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `potential_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lost_report_id` int(11) NOT NULL,
  `found_report_id` int(11) NOT NULL,
  `match_score` int(11) NOT NULL,
  `factors_json` text NOT NULL,
  `status` enum('pending','contacted','resolved','dismissed') DEFAULT 'pending',
  `notification_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lost_report_id` (`lost_report_id`),
  KEY `found_report_id` (`found_report_id`),
  CONSTRAINT `potential_matches_ibfk_1` FOREIGN KEY (`lost_report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `potential_matches_ibfk_2` FOREIGN KEY (`found_report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `potential_matches`
--

LOCK TABLES `potential_matches` WRITE;
/*!40000 ALTER TABLE `potential_matches` DISABLE KEYS */;
INSERT INTO `potential_matches` VALUES (1,1,2,87,'{\"category\":{\"score\":30,\"max\":30,\"status\":\"exact\",\"label\":\"Exact Category Match (Electronics & Gadgets)\"},\"name\":{\"score\":20,\"max\":25,\"status\":\"high\",\"label\":\"Similar Item Name (\\\"Black Casio fx-991EX\\\" vs \\\"Black Casio\\\")\"},\"description\":{\"score\":14,\"max\":20,\"status\":\"moderate\",\"label\":\"Shared keywords: Casio, calculator, CSE Block, 204\"},\"location\":{\"score\":13,\"max\":15,\"status\":\"high\",\"label\":\"Matching Location: CSE Block Room 204\"},\"date\":{\"score\":10,\"max\":10,\"status\":\"exact\",\"label\":\"Same Date: Oct 02, 2026\"}}','pending',0,'2026-10-02 08:16:00'),(2,13,14,85,'{\"category\":{\"score\":30,\"max\":30,\"status\":\"exact\",\"label\":\"Exact Category Match (Mobile Phones)\"},\"name\":{\"score\":19,\"max\":25,\"status\":\"high\",\"label\":\"High Name Similarity: Apple iPhone 13\"},\"description\":{\"score\":12,\"max\":20,\"status\":\"moderate\",\"label\":\"Shared context: Cafeteria, White\\/Starlight, patterned cover\"},\"location\":{\"score\":14,\"max\":15,\"status\":\"exact\",\"label\":\"Identical Location: Central Cafeteria\"},\"date\":{\"score\":10,\"max\":10,\"status\":\"exact\",\"label\":\"Same Date: Oct 03, 2026\"}}','pending',0,'2026-10-03 03:46:00'),(3,15,16,88,'{\"category\":{\"score\":30,\"max\":30,\"status\":\"exact\",\"symbol\":\"\\u2713\",\"label\":\"Exact Category Match (Books & Stationery)\"},\"name\":{\"score\":19,\"max\":25,\"status\":\"exact\",\"symbol\":\"\\u2713\",\"label\":\"Highly similar item name (blue, parker, pen)\"},\"description\":{\"score\":15,\"max\":20,\"status\":\"exact\",\"symbol\":\"\\u2713\",\"label\":\"Shared description keywords: blue, parker, fountain, pen\"},\"location\":{\"score\":14,\"max\":15,\"status\":\"exact\",\"symbol\":\"\\u2713\",\"label\":\"Same campus building \\/ block zone (LIBRARY)\"},\"date\":{\"score\":10,\"max\":10,\"status\":\"exact\",\"symbol\":\"\\u2713\",\"label\":\"Reported on the exact same date\"}}','resolved',1,'2026-10-03 12:26:17');
/*!40000 ALTER TABLE `potential_matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('lost','found') NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(150) NOT NULL,
  `item_date` date NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('active','recovered','closed') DEFAULT 'active',
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `recovered_at` datetime DEFAULT NULL,
  `recovered_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
INSERT INTO `reports` VALUES (1,2,'lost',5,'Black Casio fx-991EX Scientific Calculator','Left my black scientific calculator on the second bench in CSE Block room 204 after the morning math lecture. It has a small scratch on the back cover.','CSE Block Room 204','2026-10-02',NULL,'active','verified',NULL,0,NULL,NULL,'2026-10-02 06:00:00','2026-10-03 12:03:03'),(2,3,'found',5,'Black Casio Calculator','Found a Casio calculator lying on desk row 2 in CSE Block lecture hall 204 around 1:00 PM. Handed it to the security desk on the ground floor.','CSE Block','2026-10-02',NULL,'active','verified',NULL,0,NULL,NULL,'2026-10-02 08:15:00','2026-10-03 12:03:03'),(3,4,'lost',3,'Brown Leather Tommy Hilfiger Wallet','Lost a dark brown leather bi-fold wallet possibly near the Central Cafeteria or sports complex benches. Contains my student ID and driver license.','Central Cafeteria','2026-10-01',NULL,'active','pending',NULL,0,NULL,NULL,'2026-10-01 10:45:00','2026-10-03 12:03:03'),(4,5,'found',4,'Hostel Room Keys on Blue Campus Lanyard','Found three brass keys with a blue University lanyard on the pathway between Library and Academic Block B.','Library Pathway','2026-09-28',NULL,'active','verified',NULL,0,NULL,NULL,'2026-09-28 03:50:00','2026-10-03 12:03:03'),(5,2,'found',5,'OnePlus Nord Buds Black Case','Found an oval matte black charging case with earbuds inside near the study table at Central Library 1st Floor.','Central Library 1st Floor','2026-09-25',NULL,'active','pending',NULL,0,NULL,NULL,'2026-09-25 09:40:00','2026-10-03 12:03:03'),(6,3,'lost',1,'Student ID Card - Sarah Smith','Dropped my ID card while rushing to the ECE seminar hall.','ECE Seminar Hall','2026-09-20',NULL,'recovered','verified',NULL,0,'2026-09-22 14:00:00',NULL,'2026-09-20 04:30:00','2026-10-03 12:03:03'),(7,4,'found',1,'Found Student ID Card Sarah Smith','Found Sarah Smith ID card near ECE hall entrance and returned it via Security Desk.','ECE Hall Entrance','2026-09-21',NULL,'recovered','verified',NULL,0,'2026-09-22 14:00:00',NULL,'2026-09-21 06:00:00','2026-10-03 12:03:03'),(8,5,'lost',7,'Navy Blue Dell Laptop Backpack','Forgot backpack in the IT Computer Lab 3 under table 14. Contained notebooks and charger.','IT Computer Lab 3','2026-09-15',NULL,'recovered','verified',NULL,0,'2026-09-16 11:00:00',NULL,'2026-09-15 11:30:00','2026-10-03 12:03:03'),(9,4,'lost',6,'Data Structures & Algorithms in Java Textbook','Hardcover textbook with yellow sticky notes on pages. Left in Room 102 Mechanical Block.','Mechanical Block Room 102','2026-09-12',NULL,'active','verified',NULL,0,NULL,NULL,'2026-09-12 09:00:00','2026-10-03 12:03:03'),(10,2,'lost',8,'Silver Metal Frame Sunglasses','Lost prescription sunglasses in leather case near the outdoor basketball court.','Sports Complex Court 2','2026-09-05',NULL,'active','verified',NULL,0,NULL,NULL,'2026-09-05 12:30:00','2026-10-03 12:03:03'),(11,3,'found',8,'Silver Insulated Milton Water Bottle','Left on the bench near the open auditorium during the evening club meeting.','Open Air Auditorium','2026-08-28',NULL,'active','verified',NULL,0,NULL,NULL,'2026-08-28 13:45:00','2026-10-03 12:03:03'),(12,4,'found',9,'Random flying UFO found','Test submission to check system response.','Campus Sky','2026-08-20',NULL,'closed','rejected','Invalid report content / test submission without real item details.',0,NULL,NULL,'2026-08-20 06:30:00','2026-10-03 12:03:03'),(13,5,'lost',2,'Apple iPhone 13 White/Starlight with Floral Case','Slipped out of my jacket pocket in the campus cafe. Phone is locked with PIN.','Central Cafeteria','2026-10-03',NULL,'active','verified',NULL,0,NULL,NULL,'2026-10-03 03:00:00','2026-10-03 12:03:03'),(14,2,'found',2,'White Apple iPhone with clear patterned cover','Found on a sofa table in the cafeteria around 9:00 AM. Handed to cafe cashier.','Central Cafeteria','2026-10-03',NULL,'active','pending',NULL,0,NULL,NULL,'2026-10-03 03:45:00','2026-10-03 12:03:03'),(15,2,'lost',6,'Blue Parker Vector Fountain Pen','Lost a dark blue metallic Parker fountain pen with silver clip on reading table 5 in Central Library.','Central Library 2nd Floor','2026-10-03',NULL,'recovered','verified',NULL,0,'2026-10-03 17:56:17',NULL,'2026-10-03 12:26:17','2026-10-03 12:26:17'),(16,3,'found',6,'Blue Parker Pen','Found a blue Parker fountain pen on a desk near the bookshelves in Library.','Central Library','2026-10-03',NULL,'active','pending',NULL,1,NULL,NULL,'2026-10-03 12:26:17','2026-10-03 12:26:17');
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','admin') DEFAULT 'student',
  `student_id` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Campus Administrator','admin@campus.edu','$2y$10$pDYokkhRHzQOuhh49AO9MuCv5upS.iZKU9rRrgaiMG.H0SODSfdYe','admin','ADM-001','Campus Security & Welfare','+91 98765 43210',NULL,'2026-10-03 12:03:03'),(2,'Alex Johnson','alex@campus.edu','$2y$10$TIt2CfQkAGCylQR3AGARrufQaRxbhdlkGpwOyzw.OPdSOP2cS0BxO','student','CS2024-042','Computer Science & Engineering','+91 91234 56789',NULL,'2026-10-03 12:03:03'),(3,'Sarah Smith','sarah@campus.edu','$2y$10$TIt2CfQkAGCylQR3AGARrufQaRxbhdlkGpwOyzw.OPdSOP2cS0BxO','student','EC2023-118','Electronics & Communication','+91 98111 22334',NULL,'2026-10-03 12:03:03'),(4,'Rahul Sharma','rahul@campus.edu','$2y$10$TIt2CfQkAGCylQR3AGARrufQaRxbhdlkGpwOyzw.OPdSOP2cS0BxO','student','ME2024-089','Mechanical Engineering','+91 97222 33445',NULL,'2026-10-03 12:03:03'),(5,'Priya Patel','priya@campus.edu','$2y$10$TIt2CfQkAGCylQR3AGARrufQaRxbhdlkGpwOyzw.OPdSOP2cS0BxO','student','IT2024-015','Information Technology','+91 96333 44556',NULL,'2026-10-03 12:03:03');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'fsd_lost_and_found'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-03 18:11:13
