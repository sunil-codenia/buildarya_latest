-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 01, 2022 at 01:39 PM
-- Server version: 10.4.22-MariaDB
-- PHP Version: 8.1.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `construction_company_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `uid` varchar(200) NOT NULL,
  `db_name` varchar(200) NOT NULL,
  `db_pass` varchar(200) NOT NULL,
  `db_host` varchar(50) NOT NULL DEFAULT 'localhost',
  `db_conn_name` varchar(250) NOT NULL,
  `db_port` varchar(20) NOT NULL DEFAULT '3306',
  `username` varchar(200) NOT NULL,
  `status` varchar(200) NOT NULL DEFAULT 'Active',
  `expired` date DEFAULT NULL,
  `max_users` int(11) DEFAULT NULL,
  `max_sites` int(11) DEFAULT NULL,
  `create_datetime` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `name`, `uid`, `db_name`, `db_pass`, `db_host`, `db_conn_name`, `db_port`, `username`, `status`, `expired`, `max_users`, `max_sites`, `create_datetime`) VALUES
(1, 'TestCompany', 'testcomp', 'test_constructon', '', 'localhost', 'test_comp_mysql', '3306', 'root', 'Active', NULL, NULL, NULL, '2022-01-23 09:26:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
