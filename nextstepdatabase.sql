-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 08:32 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nextstepdatabase`
--
CREATE DATABASE IF NOT EXISTS `nextstepdatabase` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `nextstepdatabase`;

-- --------------------------------------------------------

--
-- Table structure for table `address`
--

CREATE TABLE IF NOT EXISTS `address` (
  `ID_Address` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Student` int(11) NOT NULL,
  `Street` varchar(15) NOT NULL,
  `City` varchar(15) NOT NULL,
  `Postal_Code` varchar(10) NOT NULL,
  PRIMARY KEY (`ID_Address`),
  KEY `FK_ID_Student` (`FK_ID_Student`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `address`:
--   `FK_ID_Student`
--       `student` -> `ID_Student`
--

-- --------------------------------------------------------

--
-- Table structure for table `aplication`
--

CREATE TABLE IF NOT EXISTS `aplication` (
  `ID_status` int(11) NOT NULL,
  `FK_ID_Student` int(11) NOT NULL,
  `FK_ID_Kit` int(11) NOT NULL,
  `status` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`ID_status`),
  KEY `FK_ID_Student` (`FK_ID_Student`),
  KEY `FK_ID_Kit` (`FK_ID_Kit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `aplication`:
--

-- --------------------------------------------------------

--
-- Table structure for table `collection_point`
--

CREATE TABLE IF NOT EXISTS `collection_point` (
  `ID_Point` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(15) NOT NULL,
  `address` varchar(255) NOT NULL,
  `Phone_number` varchar(15) DEFAULT NULL,
  `FK_ID_Company` int(11) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`ID_Point`),
  KEY `FK_ID_Company` (`FK_ID_Company`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `collection_point`:
--   `FK_ID_Company`
--       `company` -> `ID_Company`
--

-- --------------------------------------------------------

--
-- Table structure for table `company`
--

CREATE TABLE IF NOT EXISTS `company` (
  `ID_Company` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Company_Address` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `RFC` varchar(15) NOT NULL,
  `Email` varchar(30) NOT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`ID_Company`),
  UNIQUE KEY `RFC` (`RFC`),
  KEY `FK_ID_Company_Address` (`FK_ID_Company_Address`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `company`:
--   `FK_ID_Company_Address`
--       `company_address` -> `ID_Company_Address`
--

--
-- Dumping data for table `company`
--

INSERT INTO `company` (`ID_Company`, `FK_ID_Company_Address`, `Name`, `RFC`, `Email`, `Phone_Number`) VALUES
(1, 1, 'Office Depot', 'ODM950324V2A ', 'sclientes@officedepot.com.mx', '0155-25-82-09-0'),
(2, 2, 'Monerick', 'PBR990524P76', 'contacto@monerick.com', '52 664 608 0040');

-- --------------------------------------------------------

--
-- Table structure for table `company_address`
--

CREATE TABLE IF NOT EXISTS `company_address` (
  `ID_Company_Address` int(11) NOT NULL AUTO_INCREMENT,
  `Street` varchar(100) NOT NULL,
  `City` varchar(100) NOT NULL,
  `State` varchar(100) NOT NULL,
  `Postal_Code` varchar(10) NOT NULL,
  PRIMARY KEY (`ID_Company_Address`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `company_address`:
--

--
-- Dumping data for table `company_address`
--

INSERT INTO `company_address` (`ID_Company_Address`, `Street`, `City`, `State`, `Postal_Code`) VALUES
(1, 'Av. Miguel Ángel de Quevedo 1144, locales A-4 y A-5, colonia Parque San Andrés', '-', 'Ciudad de México', '04040'),
(2, 'Villasana 12045, Anexa 20 de Noviembre', 'Tijuana', 'Baja California ', '22100');

-- --------------------------------------------------------

--
-- Table structure for table `delivery`
--

CREATE TABLE IF NOT EXISTS `delivery` (
  `ID_Delivery` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Student` int(11) NOT NULL,
  `FK_ID_Kit` int(11) NOT NULL,
  `FK_ID_Point` int(11) NOT NULL,
  `Date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID_Delivery`),
  KEY `FK_ID_Student` (`FK_ID_Student`),
  KEY `FK_ID_Kit` (`FK_ID_Kit`),
  KEY `FK_ID_Point` (`FK_ID_Point`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `delivery`:
--   `FK_ID_Student`
--       `student` -> `ID_Student`
--   `FK_ID_Kit`
--       `kit` -> `ID_Kit`
--   `FK_ID_Point`
--       `collection_point` -> `ID_Point`
--

-- --------------------------------------------------------

--
-- Table structure for table `dependency`
--

CREATE TABLE IF NOT EXISTS `dependency` (
  `ID_Dependency` int(11) NOT NULL AUTO_INCREMENT,
  `Type` varchar(15) NOT NULL,
  PRIMARY KEY (`ID_Dependency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `dependency`:
--

-- --------------------------------------------------------

--
-- Table structure for table `donation`
--

CREATE TABLE IF NOT EXISTS `donation` (
  `ID_Donation` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Company` int(11) NOT NULL,
  `FK_ID_Supply` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID_Donation`),
  KEY `FK_ID_Company` (`FK_ID_Company`),
  KEY `FK_ID_Supply` (`FK_ID_Supply`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `donation`:
--   `FK_ID_Company`
--       `company` -> `ID_Company`
--   `FK_ID_Supply`
--       `supplies` -> `ID_Supply`
--

-- --------------------------------------------------------

--
-- Table structure for table `extra_delivery`
--

CREATE TABLE IF NOT EXISTS `extra_delivery` (
  `ID_Extra` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Student` int(11) NOT NULL,
  `FK_ID_Supply` int(11) NOT NULL,
  `Date` datetime DEFAULT current_timestamp(),
  `Unit` int(11) NOT NULL,
  `FK_ID_Semester` int(11) NOT NULL,
  `FK_ID_Point` int(11) NOT NULL,
  PRIMARY KEY (`ID_Extra`),
  KEY `FK_ID_Student` (`FK_ID_Student`),
  KEY `FK_ID_Supply` (`FK_ID_Supply`),
  KEY `FK_ID_Semester` (`FK_ID_Semester`),
  KEY `FK_ID_Point` (`FK_ID_Point`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `extra_delivery`:
--   `FK_ID_Student`
--       `student` -> `ID_Student`
--   `FK_ID_Supply`
--       `supplies` -> `ID_Supply`
--   `FK_ID_Semester`
--       `semester` -> `ID_Semester`
--   `FK_ID_Point`
--       `collection_point` -> `ID_Point`
--

-- --------------------------------------------------------

--
-- Table structure for table `kit`
--

CREATE TABLE IF NOT EXISTS `kit` (
  `ID_Kit` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Semester` int(11) NOT NULL,
  `Start_date` date NOT NULL,
  `End_date` date NOT NULL,
  PRIMARY KEY (`ID_Kit`),
  KEY `FK_ID_Semester` (`FK_ID_Semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `kit`:
--   `FK_ID_Semester`
--       `semester` -> `ID_Semester`
--

-- --------------------------------------------------------

--
-- Table structure for table `kit_material`
--

CREATE TABLE IF NOT EXISTS `kit_material` (
  `ID_KitMaterial` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Kit` int(11) NOT NULL,
  `FK_ID_Supply` int(11) NOT NULL,
  `Unit` int(11) NOT NULL,
  PRIMARY KEY (`ID_KitMaterial`),
  KEY `FK_ID_Kit` (`FK_ID_Kit`),
  KEY `FK_ID_Supply` (`FK_ID_Supply`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `kit_material`:
--   `FK_ID_Kit`
--       `kit` -> `ID_Kit`
--   `FK_ID_Supply`
--       `supplies` -> `ID_Supply`
--

-- --------------------------------------------------------

--
-- Table structure for table `limit`
--

CREATE TABLE IF NOT EXISTS `limit` (
  `ID_Limit` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Supply` int(11) NOT NULL,
  `FK_ID_Semester` int(11) NOT NULL,
  `Maximum` int(11) NOT NULL,
  PRIMARY KEY (`ID_Limit`),
  KEY `FK_ID_Supply` (`FK_ID_Supply`),
  KEY `FK_ID_Semester` (`FK_ID_Semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `limit`:
--   `FK_ID_Supply`
--       `supplies` -> `ID_Supply`
--   `FK_ID_Semester`
--       `semester` -> `ID_Semester`
--

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE IF NOT EXISTS `role` (
  `ID_Role` int(11) NOT NULL AUTO_INCREMENT,
  `Type` varchar(10) NOT NULL,
  PRIMARY KEY (`ID_Role`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `role`:
--

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`ID_Role`, `Type`) VALUES
(1, 'Student'),
(2, 'Staff');

-- --------------------------------------------------------

--
-- Table structure for table `semester`
--

CREATE TABLE IF NOT EXISTS `semester` (
  `ID_Semester` int(11) NOT NULL AUTO_INCREMENT,
  `Period` varchar(15) NOT NULL,
  `Year` int(11) NOT NULL,
  PRIMARY KEY (`ID_Semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `semester`:
--

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE IF NOT EXISTS `staff` (
  `ID_Staff` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_User` int(11) NOT NULL,
  `Firstname` varchar(15) NOT NULL,
  `Lastname` varchar(15) NOT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Email` varchar(30) NOT NULL,
  PRIMARY KEY (`ID_Staff`),
  UNIQUE KEY `Email` (`Email`),
  KEY `FK_ID_User` (`FK_ID_User`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff`:
--   `FK_ID_User`
--       `user` -> `ID_User`
--

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`ID_Staff`, `FK_ID_User`, `Firstname`, `Lastname`, `Phone`, `Email`) VALUES
(1, 4, 'salma', 'moreno', '1234567890', 'unemail@gmail.com'),
(2, 5, 'salma', 'moreno', '45454545454', 'unemail2@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE IF NOT EXISTS `student` (
  `ID_Student` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_User` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `Last_Name` varchar(15) NOT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Email_Address` varchar(15) NOT NULL,
  `Profile_Image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID_Student`),
  UNIQUE KEY `Email_Address` (`Email_Address`),
  KEY `FK_ID_User` (`FK_ID_User`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student`:
--   `FK_ID_User`
--       `user` -> `ID_User`
--

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`ID_Student`, `FK_ID_User`, `Name`, `Last_Name`, `Phone_Number`, `Email_Address`, `Profile_Image`) VALUES
(1, 7, 'Alexa', 'Bernabe', '6641740936', 'unemail3@gmail.', NULL),
(2, 8, 'Salma', 'Moreno', '6641740936', 'l22211911@tecti', NULL),
(4, 10, 'Salma', 'Bernabe', '6641740936', 'unemail@gmail.c', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_details`
--

CREATE TABLE IF NOT EXISTS `student_details` (
  `ID_Details` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Student` int(11) NOT NULL,
  `Birthdate` date DEFAULT NULL,
  `High_school` varchar(30) DEFAULT NULL,
  `Grade` varchar(5) DEFAULT NULL,
  `License` varchar(20) DEFAULT NULL,
  `Average` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID_Details`),
  KEY `FK_ID_Student` (`FK_ID_Student`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_details`:
--   `FK_ID_Student`
--       `student` -> `ID_Student`
--

-- --------------------------------------------------------

--
-- Table structure for table `supplies`
--

CREATE TABLE IF NOT EXISTS `supplies` (
  `ID_Supply` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(15) NOT NULL,
  `Unit` varchar(15) NOT NULL,
  PRIMARY KEY (`ID_Supply`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `supplies`:
--

-- --------------------------------------------------------

--
-- Table structure for table `tutor_data`
--

CREATE TABLE IF NOT EXISTS `tutor_data` (
  `ID_Data` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Student` int(11) NOT NULL,
  `FK_ID_Dependency` int(11) NOT NULL,
  `Tutor_name` varchar(15) NOT NULL,
  `Tutor_lastname` varchar(15) NOT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Address` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`ID_Data`),
  KEY `FK_ID_Student` (`FK_ID_Student`),
  KEY `FK_ID_Dependency` (`FK_ID_Dependency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `tutor_data`:
--   `FK_ID_Student`
--       `student` -> `ID_Student`
--   `FK_ID_Dependency`
--       `dependency` -> `ID_Dependency`
--

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `ID_User` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Role` int(11) NOT NULL,
  `Username` varchar(15) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `Status` varchar(10) NOT NULL,
  PRIMARY KEY (`ID_User`),
  UNIQUE KEY `Username` (`Username`),
  KEY `FK_ID_Role` (`FK_ID_Role`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `user`:
--   `FK_ID_Role`
--       `role` -> `ID_Role`
--

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`ID_User`, `FK_ID_Role`, `Username`, `Password`, `registration_date`, `Status`) VALUES
(4, 2, 'Usuario', '$2y$10$UX4r46tL2srHYVLWLN7u6.PXbq05bPMN/BHLTWtCcNocGyHNSwWOi', '2025-10-27 01:52:46', 'Active'),
(5, 2, 'Usuario 2', '$2y$10$euA4.iXZ5OXa1RMvVRDti.isPk0AW7kL9avhDrs6BwJAUAPq.9Dlq', '2025-11-01 21:23:55', 'Active'),
(7, 1, 'Ale', '$2y$10$TgE.MFa3fpoBREhFPvtJzudBggUx8pz3oUzwyBC9.lRinfGAD2kBa', '2025-11-02 01:18:07', 'Active'),
(8, 1, 'Salma', '$2y$10$36VTtfFCYMleMI1Ymn3.qOx5yE8UgdYuXapR97DsXkPj78Wyfww/m', '2025-11-23 23:05:23', 'Active'),
(10, 1, 'admin', '$2y$10$2gbxooOFb0TgU1uTRbKLtuT1aYqhBPndqrMbX6l8HshoIfQm4ZMr6', '2025-11-24 00:04:05', 'Active');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `address`
--
ALTER TABLE `address`
  ADD CONSTRAINT `address_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`);

--
-- Constraints for table `collection_point`
--
ALTER TABLE `collection_point`
  ADD CONSTRAINT `collection_point_ibfk_2` FOREIGN KEY (`FK_ID_Company`) REFERENCES `company` (`ID_Company`);

--
-- Constraints for table `company`
--
ALTER TABLE `company`
  ADD CONSTRAINT `company_ibfk_1` FOREIGN KEY (`FK_ID_Company_Address`) REFERENCES `company_address` (`ID_Company_Address`);

--
-- Constraints for table `delivery`
--
ALTER TABLE `delivery`
  ADD CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`),
  ADD CONSTRAINT `delivery_ibfk_2` FOREIGN KEY (`FK_ID_Kit`) REFERENCES `kit` (`ID_Kit`),
  ADD CONSTRAINT `delivery_ibfk_3` FOREIGN KEY (`FK_ID_Point`) REFERENCES `collection_point` (`ID_Point`);

--
-- Constraints for table `donation`
--
ALTER TABLE `donation`
  ADD CONSTRAINT `donation_ibfk_1` FOREIGN KEY (`FK_ID_Company`) REFERENCES `company` (`ID_Company`),
  ADD CONSTRAINT `donation_ibfk_2` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`);

--
-- Constraints for table `extra_delivery`
--
ALTER TABLE `extra_delivery`
  ADD CONSTRAINT `extra_delivery_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`),
  ADD CONSTRAINT `extra_delivery_ibfk_2` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`),
  ADD CONSTRAINT `extra_delivery_ibfk_3` FOREIGN KEY (`FK_ID_Semester`) REFERENCES `semester` (`ID_Semester`),
  ADD CONSTRAINT `extra_delivery_ibfk_4` FOREIGN KEY (`FK_ID_Point`) REFERENCES `collection_point` (`ID_Point`);

--
-- Constraints for table `kit`
--
ALTER TABLE `kit`
  ADD CONSTRAINT `kit_ibfk_1` FOREIGN KEY (`FK_ID_Semester`) REFERENCES `semester` (`ID_Semester`);

--
-- Constraints for table `kit_material`
--
ALTER TABLE `kit_material`
  ADD CONSTRAINT `kit_material_ibfk_1` FOREIGN KEY (`FK_ID_Kit`) REFERENCES `kit` (`ID_Kit`),
  ADD CONSTRAINT `kit_material_ibfk_2` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`);

--
-- Constraints for table `limit`
--
ALTER TABLE `limit`
  ADD CONSTRAINT `limit_ibfk_1` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`),
  ADD CONSTRAINT `limit_ibfk_2` FOREIGN KEY (`FK_ID_Semester`) REFERENCES `semester` (`ID_Semester`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`FK_ID_User`) REFERENCES `user` (`ID_User`);

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`FK_ID_User`) REFERENCES `user` (`ID_User`);

--
-- Constraints for table `student_details`
--
ALTER TABLE `student_details`
  ADD CONSTRAINT `student_details_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`);

--
-- Constraints for table `tutor_data`
--
ALTER TABLE `tutor_data`
  ADD CONSTRAINT `tutor_data_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`),
  ADD CONSTRAINT `tutor_data_ibfk_2` FOREIGN KEY (`FK_ID_Dependency`) REFERENCES `dependency` (`ID_Dependency`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`FK_ID_Role`) REFERENCES `role` (`ID_Role`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
