-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3307
-- Tiempo de generación: 19-11-2025 a las 03:45:04
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

CREATE TABLE `address` (
  `ID_Address` int(11) NOT NULL,
  `FK_ID_Student` int(11) NOT NULL,
  `Street` varchar(15) NOT NULL,
  `City` varchar(15) NOT NULL,
  `Postal_Code` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `collection_point`
--

CREATE TABLE `collection_point` (
  `ID_Point` int(11) NOT NULL,
  `FK_ID_Company_Address` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `Phone_number` varchar(15) DEFAULT NULL,
  `FK_ID_Company` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `company`
--

CREATE TABLE `company` (
  `ID_Company` int(11) NOT NULL,
  `FK_ID_Company_Address` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `RFC` varchar(15) NOT NULL,
  `Email` varchar(30) NOT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `company_address`
--

CREATE TABLE `company_address` (
  `ID_Company_Address` int(11) NOT NULL,
  `Street` varchar(100) NOT NULL,
  `City` varchar(100) NOT NULL,
  `State` varchar(100) NOT NULL,
  `Postal_Code` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `delivery`
--

CREATE TABLE `delivery` (
  `ID_Delivery` int(11) NOT NULL,
  `FK_ID_Student` int(11) NOT NULL,
  `FK_ID_Kit` int(11) NOT NULL,
  `FK_ID_Point` int(11) NOT NULL,
  `Date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dependency`
--

CREATE TABLE `dependency` (
  `ID_Dependency` int(11) NOT NULL,
  `Type` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `donation`
--

CREATE TABLE `donation` (
  `ID_Donation` int(11) NOT NULL,
  `FK_ID_Company` int(11) NOT NULL,
  `FK_ID_Supply` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `extra_delivery`
--

CREATE TABLE `extra_delivery` (
  `ID_Extra` int(11) NOT NULL,
  `FK_ID_Student` int(11) NOT NULL,
  `FK_ID_Supply` int(11) NOT NULL,
  `Date` datetime DEFAULT current_timestamp(),
  `Unit` int(11) NOT NULL,
  `FK_ID_Semester` int(11) NOT NULL,
  `FK_ID_Point` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kit`
--

CREATE TABLE `kit` (
  `ID_Kit` int(11) NOT NULL,
  `FK_ID_Semester` int(11) NOT NULL,
  `Start_date` date NOT NULL,
  `End_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kit_material`
--

CREATE TABLE `kit_material` (
  `ID_KitMaterial` int(11) NOT NULL,
  `FK_ID_Kit` int(11) NOT NULL,
  `FK_ID_Supply` int(11) NOT NULL,
  `Unit` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `limit`
--

CREATE TABLE `limit` (
  `ID_Limit` int(11) NOT NULL,
  `FK_ID_Supply` int(11) NOT NULL,
  `FK_ID_Semester` int(11) NOT NULL,
  `Maximum` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role`
--

CREATE TABLE `role` (
  `ID_Role` int(11) NOT NULL,
  `Type` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `role`
--

INSERT INTO `role` (`ID_Role`, `Type`) VALUES
(1, 'Student'),
(2, 'Staff');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `semester`
--

CREATE TABLE `semester` (
  `ID_Semester` int(11) NOT NULL,
  `Period` varchar(15) NOT NULL,
  `Year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff`
--

CREATE TABLE `staff` (
  `ID_Staff` int(11) NOT NULL,
  `FK_ID_User` int(11) NOT NULL,
  `Firstname` varchar(15) NOT NULL,
  `Lastname` varchar(15) NOT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Email` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `staff`
--

INSERT INTO `staff` (`ID_Staff`, `FK_ID_User`, `Firstname`, `Lastname`, `Phone`, `Email`) VALUES
(1, 4, 'salma', 'moreno', '1234567890', 'unemail@gmail.com'),
(2, 5, 'salma', 'moreno', '45454545454', 'unemail2@gmail.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `student`
--

CREATE TABLE `student` (
  `ID_Student` int(11) NOT NULL,
  `FK_ID_User` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `Last_Name` varchar(15) NOT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Email_Address` varchar(15) NOT NULL,
  `Profile_Image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `student`
--

INSERT INTO `student` (`ID_Student`, `FK_ID_User`, `Name`, `Last_Name`, `Phone_Number`, `Email_Address`, `Profile_Image`) VALUES
(1, 7, 'Alexa', 'Bernabe', '6641740936', 'unemail3@gmail.', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `student_details`
--

CREATE TABLE `student_details` (
  `ID_Details` int(11) NOT NULL,
  `FK_ID_Student` int(11) NOT NULL,
  `Birthdate` date DEFAULT NULL,
  `High_school` varchar(30) DEFAULT NULL,
  `Grade` varchar(5) DEFAULT NULL,
  `License` varchar(20) DEFAULT NULL,
  `Average` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `supplies`
--

CREATE TABLE `supplies` (
  `ID_Supply` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL,
  `Unit` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor_data`
--

CREATE TABLE `tutor_data` (
  `ID_Data` int(11) NOT NULL,
  `FK_ID_Student` int(11) NOT NULL,
  `FK_ID_Dependency` int(11) NOT NULL,
  `Tutor_name` varchar(15) NOT NULL,
  `Tutor_lastname` varchar(15) NOT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Address` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user`
--

CREATE TABLE `user` (
  `ID_User` int(11) NOT NULL,
  `FK_ID_Role` int(11) NOT NULL,
  `Username` varchar(15) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `Status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `user`
--

INSERT INTO `user` (`ID_User`, `FK_ID_Role`, `Username`, `Password`, `registration_date`, `Status`) VALUES
(4, 2, 'Usuario', '$2y$10$UX4r46tL2srHYVLWLN7u6.PXbq05bPMN/BHLTWtCcNocGyHNSwWOi', '2025-10-27 01:52:46', 'Active'),
(5, 2, 'Usuario 2', '$2y$10$euA4.iXZ5OXa1RMvVRDti.isPk0AW7kL9avhDrs6BwJAUAPq.9Dlq', '2025-11-01 21:23:55', 'Active'),
(7, 1, 'Ale', '$2y$10$TgE.MFa3fpoBREhFPvtJzudBggUx8pz3oUzwyBC9.lRinfGAD2kBa', '2025-11-02 01:18:07', 'Active');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `address`
--
ALTER TABLE `address`
  ADD PRIMARY KEY (`ID_Address`),
  ADD KEY `FK_ID_Student` (`FK_ID_Student`);

--
-- Indices de la tabla `collection_point`
--
ALTER TABLE `collection_point`
  ADD PRIMARY KEY (`ID_Point`),
  ADD KEY `FK_ID_Company_Address` (`FK_ID_Company_Address`),
  ADD KEY `FK_ID_Company` (`FK_ID_Company`);

--
-- Indices de la tabla `company`
--
ALTER TABLE `company`
  ADD PRIMARY KEY (`ID_Company`),
  ADD UNIQUE KEY `RFC` (`RFC`),
  ADD KEY `FK_ID_Company_Address` (`FK_ID_Company_Address`);

--
-- Indices de la tabla `company_address`
--
ALTER TABLE `company_address`
  ADD PRIMARY KEY (`ID_Company_Address`);

--
-- Indices de la tabla `delivery`
--
ALTER TABLE `delivery`
  ADD PRIMARY KEY (`ID_Delivery`),
  ADD KEY `FK_ID_Student` (`FK_ID_Student`),
  ADD KEY `FK_ID_Kit` (`FK_ID_Kit`),
  ADD KEY `FK_ID_Point` (`FK_ID_Point`);

--
-- Indices de la tabla `dependency`
--
ALTER TABLE `dependency`
  ADD PRIMARY KEY (`ID_Dependency`);

--
-- Indices de la tabla `donation`
--
ALTER TABLE `donation`
  ADD PRIMARY KEY (`ID_Donation`),
  ADD KEY `FK_ID_Company` (`FK_ID_Company`),
  ADD KEY `FK_ID_Supply` (`FK_ID_Supply`);

--
-- Indices de la tabla `extra_delivery`
--
ALTER TABLE `extra_delivery`
  ADD PRIMARY KEY (`ID_Extra`),
  ADD KEY `FK_ID_Student` (`FK_ID_Student`),
  ADD KEY `FK_ID_Supply` (`FK_ID_Supply`),
  ADD KEY `FK_ID_Semester` (`FK_ID_Semester`),
  ADD KEY `FK_ID_Point` (`FK_ID_Point`);

--
-- Indices de la tabla `kit`
--
ALTER TABLE `kit`
  ADD PRIMARY KEY (`ID_Kit`),
  ADD KEY `FK_ID_Semester` (`FK_ID_Semester`);

--
-- Indices de la tabla `kit_material`
--
ALTER TABLE `kit_material`
  ADD PRIMARY KEY (`ID_KitMaterial`),
  ADD KEY `FK_ID_Kit` (`FK_ID_Kit`),
  ADD KEY `FK_ID_Supply` (`FK_ID_Supply`);

--
-- Indices de la tabla `limit`
--
ALTER TABLE `limit`
  ADD PRIMARY KEY (`ID_Limit`),
  ADD KEY `FK_ID_Supply` (`FK_ID_Supply`),
  ADD KEY `FK_ID_Semester` (`FK_ID_Semester`);

--
-- Indices de la tabla `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`ID_Role`);

--
-- Indices de la tabla `semester`
--
ALTER TABLE `semester`
  ADD PRIMARY KEY (`ID_Semester`);

--
-- Indices de la tabla `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`ID_Staff`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `FK_ID_User` (`FK_ID_User`);

--
-- Indices de la tabla `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`ID_Student`),
  ADD UNIQUE KEY `Email_Address` (`Email_Address`),
  ADD KEY `FK_ID_User` (`FK_ID_User`);

--
-- Indices de la tabla `student_details`
--
ALTER TABLE `student_details`
  ADD PRIMARY KEY (`ID_Details`),
  ADD KEY `FK_ID_Student` (`FK_ID_Student`);

--
-- Indices de la tabla `supplies`
--
ALTER TABLE `supplies`
  ADD PRIMARY KEY (`ID_Supply`);

--
-- Indices de la tabla `tutor_data`
--
ALTER TABLE `tutor_data`
  ADD PRIMARY KEY (`ID_Data`),
  ADD KEY `FK_ID_Student` (`FK_ID_Student`),
  ADD KEY `FK_ID_Dependency` (`FK_ID_Dependency`);

--
-- Indices de la tabla `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`ID_User`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `FK_ID_Role` (`FK_ID_Role`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `address`
--
ALTER TABLE `address`
  MODIFY `ID_Address` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `collection_point`
--
ALTER TABLE `collection_point`
  MODIFY `ID_Point` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `company`
--
ALTER TABLE `company`
  MODIFY `ID_Company` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `company_address`
--
ALTER TABLE `company_address`
  MODIFY `ID_Company_Address` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `delivery`
--
ALTER TABLE `delivery`
  MODIFY `ID_Delivery` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `dependency`
--
ALTER TABLE `dependency`
  MODIFY `ID_Dependency` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `donation`
--
ALTER TABLE `donation`
  MODIFY `ID_Donation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `extra_delivery`
--
ALTER TABLE `extra_delivery`
  MODIFY `ID_Extra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `kit`
--
ALTER TABLE `kit`
  MODIFY `ID_Kit` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `kit_material`
--
ALTER TABLE `kit_material`
  MODIFY `ID_KitMaterial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `limit`
--
ALTER TABLE `limit`
  MODIFY `ID_Limit` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `role`
--
ALTER TABLE `role`
  MODIFY `ID_Role` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `semester`
--
ALTER TABLE `semester`
  MODIFY `ID_Semester` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `staff`
--
ALTER TABLE `staff`
  MODIFY `ID_Staff` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `student`
--
ALTER TABLE `student`
  MODIFY `ID_Student` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `student_details`
--
ALTER TABLE `student_details`
  MODIFY `ID_Details` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `supplies`
--
ALTER TABLE `supplies`
  MODIFY `ID_Supply` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tutor_data`
--
ALTER TABLE `tutor_data`
  MODIFY `ID_Data` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user`
--
ALTER TABLE `user`
  MODIFY `ID_User` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  ADD CONSTRAINT `collection_point_ibfk_1` FOREIGN KEY (`FK_ID_Company_Address`) REFERENCES `company_address` (`ID_Company_Address`),
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
