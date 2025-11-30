-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-11-2025 a las 02:56:38
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `nextstepdatabase`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `address`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aplication`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `collection_point`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `company`
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
-- Volcado de datos para la tabla `company`
--

UPDATE `company` SET `ID_Company` = 1,`FK_ID_Company_Address` = 1,`Name` = 'Office Depot',`RFC` = 'ODM950324V2A ',`Email` = 'sclientes@officedepot.com.mx',`Phone_Number` = '0155-25-82-09-0' WHERE `company`.`ID_Company` = 1;
UPDATE `company` SET `ID_Company` = 2,`FK_ID_Company_Address` = 2,`Name` = 'Monerick',`RFC` = 'PBR990524P76',`Email` = 'contacto@monerick.com',`Phone_Number` = '52 664 608 0040' WHERE `company`.`ID_Company` = 2;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `company_address`
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
-- Volcado de datos para la tabla `company_address`
--

UPDATE `company_address` SET `ID_Company_Address` = 1,`Street` = 'Av. Miguel Ángel de Quevedo 1144, locales A-4 y A-5, colonia Parque San Andrés',`City` = '-',`State` = 'Ciudad de México',`Postal_Code` = '04040' WHERE `company_address`.`ID_Company_Address` = 1;
UPDATE `company_address` SET `ID_Company_Address` = 2,`Street` = 'Villasana 12045, Anexa 20 de Noviembre',`City` = 'Tijuana',`State` = 'Baja California ',`Postal_Code` = '22100' WHERE `company_address`.`ID_Company_Address` = 2;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `delivery`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dependency`
--

CREATE TABLE IF NOT EXISTS `dependency` (
  `ID_Dependency` int(11) NOT NULL AUTO_INCREMENT,
  `Type` varchar(15) NOT NULL,
  PRIMARY KEY (`ID_Dependency`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `dependency`
--

UPDATE `dependency` SET `ID_Dependency` = 1,`Type` = 'Padre/Madre' WHERE `dependency`.`ID_Dependency` = 1;
UPDATE `dependency` SET `ID_Dependency` = 2,`Type` = 'Hermano/Hermana' WHERE `dependency`.`ID_Dependency` = 2;
UPDATE `dependency` SET `ID_Dependency` = 3,`Type` = 'Tio/Tia' WHERE `dependency`.`ID_Dependency` = 3;
UPDATE `dependency` SET `ID_Dependency` = 4,`Type` = 'Abuelo/Abuela' WHERE `dependency`.`ID_Dependency` = 4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `donation`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `extra_delivery`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kit`
--

CREATE TABLE IF NOT EXISTS `kit` (
  `ID_Kit` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_Semester` int(11) NOT NULL,
  `Start_date` date NOT NULL,
  `End_date` date NOT NULL,
  PRIMARY KEY (`ID_Kit`),
  KEY `FK_ID_Semester` (`FK_ID_Semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kit_material`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `limit`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role`
--

CREATE TABLE IF NOT EXISTS `role` (
  `ID_Role` int(11) NOT NULL AUTO_INCREMENT,
  `Type` varchar(10) NOT NULL,
  PRIMARY KEY (`ID_Role`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `role`
--

UPDATE `role` SET `ID_Role` = 1,`Type` = 'Student' WHERE `role`.`ID_Role` = 1;
UPDATE `role` SET `ID_Role` = 2,`Type` = 'Staff' WHERE `role`.`ID_Role` = 2;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `semester`
--

CREATE TABLE IF NOT EXISTS `semester` (
  `ID_Semester` int(11) NOT NULL AUTO_INCREMENT,
  `Period` varchar(15) NOT NULL,
  `Year` int(11) NOT NULL,
  PRIMARY KEY (`ID_Semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff`
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
-- Volcado de datos para la tabla `staff`
--

UPDATE `staff` SET `ID_Staff` = 1,`FK_ID_User` = 4,`Firstname` = 'salma',`Lastname` = 'moreno',`Phone` = '1234567890',`Email` = 'unemail@gmail.com' WHERE `staff`.`ID_Staff` = 1;
UPDATE `staff` SET `ID_Staff` = 2,`FK_ID_User` = 5,`Firstname` = 'salma',`Lastname` = 'moreno',`Phone` = '45454545454',`Email` = 'unemail2@gmail.com' WHERE `staff`.`ID_Staff` = 2;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `student`
--

CREATE TABLE IF NOT EXISTS `student` (
  `ID_Student` int(11) NOT NULL AUTO_INCREMENT,
  `FK_ID_User` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `Last_Name` varchar(15) NOT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Email_Address` varchar(50) NOT NULL,
  `Profile_Image` text DEFAULT NULL,
  PRIMARY KEY (`ID_Student`),
  UNIQUE KEY `Email_Address` (`Email_Address`),
  KEY `FK_ID_User` (`FK_ID_User`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `student`
--

UPDATE `student` SET `ID_Student` = 1,`FK_ID_User` = 7,`Name` = 'Alexa',`Last_Name` = 'Bernabe',`Phone_Number` = '6641740936',`Email_Address` = 'unemail3@gmail.',`Profile_Image` = NULL WHERE `student`.`ID_Student` = 1;
UPDATE `student` SET `ID_Student` = 2,`FK_ID_User` = 8,`Name` = 'Salma',`Last_Name` = 'Moreno',`Phone_Number` = '6641740936',`Email_Address` = 'l22211911@tecti',`Profile_Image` = NULL WHERE `student`.`ID_Student` = 2;
UPDATE `student` SET `ID_Student` = 4,`FK_ID_User` = 10,`Name` = 'Salma',`Last_Name` = 'Bernabe',`Phone_Number` = '6641740936',`Email_Address` = 'unemail@gmail.c',`Profile_Image` = NULL WHERE `student`.`ID_Student` = 4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `student_details`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `supplies`
--

CREATE TABLE IF NOT EXISTS `supplies` (
  `ID_Supply` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(15) NOT NULL,
  `Unit` varchar(15) NOT NULL,
  PRIMARY KEY (`ID_Supply`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor_data`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user`
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
-- Volcado de datos para la tabla `user`
--

UPDATE `user` SET `ID_User` = 4,`FK_ID_Role` = 2,`Username` = 'Usuario',`Password` = '$2y$10$UX4r46tL2srHYVLWLN7u6.PXbq05bPMN/BHLTWtCcNocGyHNSwWOi',`registration_date` = '2025-10-27 01:52:46',`Status` = 'Active' WHERE `user`.`ID_User` = 4;
UPDATE `user` SET `ID_User` = 5,`FK_ID_Role` = 2,`Username` = 'Usuario 2',`Password` = '$2y$10$euA4.iXZ5OXa1RMvVRDti.isPk0AW7kL9avhDrs6BwJAUAPq.9Dlq',`registration_date` = '2025-11-01 21:23:55',`Status` = 'Active' WHERE `user`.`ID_User` = 5;
UPDATE `user` SET `ID_User` = 7,`FK_ID_Role` = 1,`Username` = 'Ale',`Password` = '$2y$10$TgE.MFa3fpoBREhFPvtJzudBggUx8pz3oUzwyBC9.lRinfGAD2kBa',`registration_date` = '2025-11-02 01:18:07',`Status` = 'Active' WHERE `user`.`ID_User` = 7;
UPDATE `user` SET `ID_User` = 8,`FK_ID_Role` = 1,`Username` = 'Salma',`Password` = '$2y$10$36VTtfFCYMleMI1Ymn3.qOx5yE8UgdYuXapR97DsXkPj78Wyfww/m',`registration_date` = '2025-11-23 23:05:23',`Status` = 'Active' WHERE `user`.`ID_User` = 8;
UPDATE `user` SET `ID_User` = 10,`FK_ID_Role` = 1,`Username` = 'admin',`Password` = '$2y$10$2gbxooOFb0TgU1uTRbKLtuT1aYqhBPndqrMbX6l8HshoIfQm4ZMr6',`registration_date` = '2025-11-24 00:04:05',`Status` = 'Active' WHERE `user`.`ID_User` = 10;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `address`
--
ALTER TABLE `address`
  ADD CONSTRAINT `address_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`);

--
-- Filtros para la tabla `collection_point`
--
ALTER TABLE `collection_point`
  ADD CONSTRAINT `collection_point_ibfk_2` FOREIGN KEY (`FK_ID_Company`) REFERENCES `company` (`ID_Company`);

--
-- Filtros para la tabla `company`
--
ALTER TABLE `company`
  ADD CONSTRAINT `company_ibfk_1` FOREIGN KEY (`FK_ID_Company_Address`) REFERENCES `company_address` (`ID_Company_Address`);

--
-- Filtros para la tabla `delivery`
--
ALTER TABLE `delivery`
  ADD CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`),
  ADD CONSTRAINT `delivery_ibfk_2` FOREIGN KEY (`FK_ID_Kit`) REFERENCES `kit` (`ID_Kit`),
  ADD CONSTRAINT `delivery_ibfk_3` FOREIGN KEY (`FK_ID_Point`) REFERENCES `collection_point` (`ID_Point`);

--
-- Filtros para la tabla `donation`
--
ALTER TABLE `donation`
  ADD CONSTRAINT `donation_ibfk_1` FOREIGN KEY (`FK_ID_Company`) REFERENCES `company` (`ID_Company`),
  ADD CONSTRAINT `donation_ibfk_2` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`);

--
-- Filtros para la tabla `extra_delivery`
--
ALTER TABLE `extra_delivery`
  ADD CONSTRAINT `extra_delivery_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`),
  ADD CONSTRAINT `extra_delivery_ibfk_2` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`),
  ADD CONSTRAINT `extra_delivery_ibfk_3` FOREIGN KEY (`FK_ID_Semester`) REFERENCES `semester` (`ID_Semester`),
  ADD CONSTRAINT `extra_delivery_ibfk_4` FOREIGN KEY (`FK_ID_Point`) REFERENCES `collection_point` (`ID_Point`);

--
-- Filtros para la tabla `kit`
--
ALTER TABLE `kit`
  ADD CONSTRAINT `kit_ibfk_1` FOREIGN KEY (`FK_ID_Semester`) REFERENCES `semester` (`ID_Semester`);

--
-- Filtros para la tabla `kit_material`
--
ALTER TABLE `kit_material`
  ADD CONSTRAINT `kit_material_ibfk_1` FOREIGN KEY (`FK_ID_Kit`) REFERENCES `kit` (`ID_Kit`),
  ADD CONSTRAINT `kit_material_ibfk_2` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`);

--
-- Filtros para la tabla `limit`
--
ALTER TABLE `limit`
  ADD CONSTRAINT `limit_ibfk_1` FOREIGN KEY (`FK_ID_Supply`) REFERENCES `supplies` (`ID_Supply`),
  ADD CONSTRAINT `limit_ibfk_2` FOREIGN KEY (`FK_ID_Semester`) REFERENCES `semester` (`ID_Semester`);

--
-- Filtros para la tabla `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`FK_ID_User`) REFERENCES `user` (`ID_User`);

--
-- Filtros para la tabla `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`FK_ID_User`) REFERENCES `user` (`ID_User`);

--
-- Filtros para la tabla `student_details`
--
ALTER TABLE `student_details`
  ADD CONSTRAINT `student_details_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`);

--
-- Filtros para la tabla `tutor_data`
--
ALTER TABLE `tutor_data`
  ADD CONSTRAINT `tutor_data_ibfk_1` FOREIGN KEY (`FK_ID_Student`) REFERENCES `student` (`ID_Student`),
  ADD CONSTRAINT `tutor_data_ibfk_2` FOREIGN KEY (`FK_ID_Dependency`) REFERENCES `dependency` (`ID_Dependency`);

--
-- Filtros para la tabla `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`FK_ID_Role`) REFERENCES `role` (`ID_Role`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
