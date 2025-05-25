-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 25, 2025 at 09:50 AM
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
-- Database: `mangacms`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `series` varchar(128) NOT NULL,
  `chapter` varchar(128) NOT NULL,
  `username` varchar(32) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `series`, `chapter`, `username`, `comment`, `created_at`) VALUES
(1, 'naruto', '01', 'admin', 'yeyyy', '2025-05-23 14:13:00'),
(2, 'Mahou', '01', 'admin', 'Yayy', '2025-05-23 17:02:15');

-- --------------------------------------------------------

--
-- Table structure for table `purchased_chapters`
--

CREATE TABLE `purchased_chapters` (
  `id` int(11) NOT NULL,
  `username` varchar(64) NOT NULL,
  `series` varchar(128) NOT NULL,
  `chapter` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchased_chapters`
--

INSERT INTO `purchased_chapters` (`id`, `username`, `series`, `chapter`) VALUES
(1, 'aaa', 'Mahou', '01'),
(6, 'aaa', 'Mahou', '02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(32) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `role`, `points`) VALUES
(1, 'admin', '$2y$10$lT0HYt1L5cxZILIUHVKnTu6u1SCVg63oGgOrWMNFUEG1EVCmnI7gK', '2025-05-23 14:02:21', 'admin', 5000),
(2, 'test', '$2y$10$h08hMf7KCvCW9p9YDPfwTulqCbgQi35JJ5GqzZtG3xLpD0dFV/Tq2', '2025-05-23 14:14:09', 'user', 0),
(3, 'aaa', '$2y$10$6XvQn6R5hAlP20SZfazyIO/JpE8zjdTEYEP2uABPM57EJ4H6Pxrcq', '2025-05-23 17:22:53', 'user', 500),
(4, '009', '$2y$10$qr.p1XUjsznYpdIZ5Lso1OR3ffmH4q9EZu2VSX0K/pijx/SFTCrKa', '2025-05-24 12:26:55', 'user', 5000),
(5, 'beer', '$2y$10$JZLFKji1u8Cz.qYVEUY1muO2ZjArrQT84kQryuceBgXKa8BHFe3Q6', '2025-05-24 15:04:34', 'user', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchased_chapters`
--
ALTER TABLE `purchased_chapters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`,`series`,`chapter`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchased_chapters`
--
ALTER TABLE `purchased_chapters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
