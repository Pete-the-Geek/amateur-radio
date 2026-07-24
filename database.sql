-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 24, 2026 at 08:25 PM
-- Server version: 10.11.11-MariaDB
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `peterwil_live`
--

-- --------------------------------------------------------

--
-- Table structure for table `radio_locations`
--

CREATE TABLE `radio_locations` (
  `location_name` varchar(255) NOT NULL,
  `latitude` float NOT NULL,
  `longitude` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `radio_ofcom_bandplan_summary`
--

CREATE TABLE `radio_ofcom_bandplan_summary` (
  `id` int(20) UNSIGNED NOT NULL,
  `frequency_from` bigint(50) DEFAULT NULL,
  `frequency_to` bigint(50) DEFAULT NULL,
  `status_service` varchar(500) NOT NULL,
  `status_satellite` varchar(500) NOT NULL,
  `max_pep_foundation` varchar(500) DEFAULT NULL,
  `max_pep_intermediate` varchar(500) DEFAULT NULL,
  `max_pep_full` varchar(500) DEFAULT NULL,
  `bandplan` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `radio_repeaters`
--

CREATE TABLE `radio_repeaters` (
  `id` int(11) NOT NULL,
  `fac` tinyint(1) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `keeperCallsign` varchar(50) DEFAULT NULL,
  `town` varchar(100) DEFAULT NULL,
  `modeCodes` varchar(100) DEFAULT NULL,
  `tx` bigint(20) DEFAULT NULL,
  `repeater` varchar(50) DEFAULT NULL,
  `rx` bigint(20) DEFAULT NULL,
  `ctcss` float DEFAULT NULL,
  `txbw` float DEFAULT NULL,
  `band` varchar(20) DEFAULT NULL,
  `locator` varchar(50) DEFAULT NULL,
  `dbwErp` float DEFAULT NULL,
  `ngr` varchar(50) DEFAULT NULL,
  `antennaHeight` int(11) DEFAULT NULL,
  `polarisation` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `radio_sota_summits`
--

CREATE TABLE `radio_sota_summits` (
  `SummitCode` varchar(20) NOT NULL,
  `AssociationName` varchar(100) DEFAULT NULL,
  `RegionName` varchar(100) DEFAULT NULL,
  `SummitName` varchar(150) DEFAULT NULL,
  `AltM` int(11) DEFAULT NULL,
  `AltFt` int(11) DEFAULT NULL,
  `GridRef1` varchar(50) DEFAULT NULL,
  `GridRef2` varchar(50) DEFAULT NULL,
  `Longitude` decimal(10,6) DEFAULT NULL,
  `Latitude` decimal(10,6) DEFAULT NULL,
  `Points` int(11) DEFAULT NULL,
  `BonusPoints` int(11) DEFAULT NULL,
  `ValidFrom` date DEFAULT NULL,
  `ValidTo` date DEFAULT NULL,
  `ActivationCount` int(11) DEFAULT NULL,
  `ActivationDate` date DEFAULT NULL,
  `ActivationCall` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `radio_locations`
--
ALTER TABLE `radio_locations`
  ADD PRIMARY KEY (`location_name`);

--
-- Indexes for table `radio_ofcom_bandplan_summary`
--
ALTER TABLE `radio_ofcom_bandplan_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `frequency_from` (`frequency_from`),
  ADD UNIQUE KEY `frequency_to` (`frequency_to`);

--
-- Indexes for table `radio_repeaters`
--
ALTER TABLE `radio_repeaters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `radio_sota_summits`
--
ALTER TABLE `radio_sota_summits`
  ADD PRIMARY KEY (`SummitCode`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `radio_ofcom_bandplan_summary`
--
ALTER TABLE `radio_ofcom_bandplan_summary`
  MODIFY `id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
