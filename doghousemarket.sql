-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 08, 2026 at 09:43 AM
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
-- Database: `doghousemarket`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `created_at`) VALUES
(1, 'admin', '$2y$10$wUENSFBreQOW3t9I5v1p/.TxshdWUkJAYcJnE6dmRL3M5rGOe0xuy', 'admin@example.com', '2025-09-24 12:39:15');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dog_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `dog_id`, `added_at`) VALUES
(10, 5, 1, '2025-11-02 14:47:20');

-- --------------------------------------------------------

--
-- Table structure for table `company_info`
--

CREATE TABLE `company_info` (
  `id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `email_general` varchar(150) DEFAULT NULL,
  `email_support` varchar(150) DEFAULT NULL,
  `working_hours` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `font` enum('Arial','Verdana','Helvetica','Tahoma','Trebuchet MS','Times New Roman','Georgia','Garamond','Courier New','Brush Script MT','Lucida Sans','Lucida Console','Palatino Linotype','Book Antiqua','Impact','Comic Sans MS','Segoe UI','Candara','Optima','Calibri','Cambria','Franklin Gothic Medium','Gill Sans','Century Gothic','Perpetua','Rockwell','Baskerville','Didot','Monaco','Geneva') DEFAULT 'Arial'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_info`
--

INSERT INTO `company_info` (`id`, `company_name`, `address`, `city`, `postal_code`, `phone`, `color`, `logo`, `favicon`, `banner_image`, `email_general`, `email_support`, `working_hours`, `created_at`, `font`) VALUES
(1, 'DOGMARKET', '123 Pet Street, Lekki Phase 1', 'Lagos', '101245', '+2348012345678', '#ffa500', 'images/dogs/Doghouse Market.jpg', 'uploads/favicon.ico', 'uploads/banner.jpg', 'info@doghousemarket.com', 'support@doghousemarket.com', 'Mon - Sat, 9am - 6pm', '2025-09-24 17:00:29', 'Verdana');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dogs`
--

CREATE TABLE `dogs` (
  `dog_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `breed` varchar(100) NOT NULL,
  `age` varchar(50) NOT NULL,
  `trait` text DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dogs`
--

INSERT INTO `dogs` (`dog_id`, `name`, `breed`, `age`, `trait`, `image_url`, `price`, `created_at`, `updated_at`) VALUES
(1, 'HUSKY', 'Origin: Siberia (developed by the Chukchi people as sled dogs)', '', 'Temperament: Outgoing, mischievous, alert, and friendly; rarely aggressive\r\n\r\nIntelligence: Independent thinkers, clever but sometimes stubborn\r\n\r\nEnergy Level: Very high – bred for endurance and long-distance running\r\n\r\nGood with Families: Yes, but they need an active household\r\n\r\nCoat: Thick double coat, protects from extreme cold\r\n\r\nColors: Wide variety – black, gray, red, agouti, sable, and pure white, often with striking facial markings\r\n\r\nEyes: Blue, brown, amber, or heterochromia (two different colors)', 'images/dogs/dog_68d29cc6a6ac1.jpg', 1000.00, '2025-09-23 13:12:38', '2025-09-23 13:42:48'),
(2, 'Shih Tzu', 'Shih Tzu', '8-14 weeks', 'Affectionate, lively, sociable good with people and other pets can be a bit stubborn and Potty trained.', 'images/dogs/dog_68d2a4a619684.jpg', 600.00, '2025-09-23 13:46:14', '2025-09-23 13:46:14'),
(3, 'Yorkers', 'Yorkers', '8-14 weeks', 'Affectionate Very loving with their owners and enjoy being close.\r\nEnergetic & Playful  Full of energy despite their small size.', 'images/dogs/dog_68d2a59c67e09.jpg', 500.00, '2025-09-23 13:50:20', '2025-09-23 13:50:20'),
(5, 'French bulldog', 'French bulldog', '8-14 weeks', 'Affectionate & Loyal  Very attached to their owners, love cuddles.\r\nPlayful & Fun-Loving Goofy personality, often called “clowns.”\r\nCalm & Adaptable Perfect for apartments or small spaces.', 'images/dogs/dog_68d2a700014d6.jpg', 600.00, '2025-09-23 13:56:16', '2025-09-23 13:56:16'),
(6, 'American bully', 'American bully', '8-14 weeks', 'Loyal & Protective Very devoted to family, excellent guard dog instincts.\r\nGentle & Affectionate Despite their muscular build, they are known as “gentle giants” with their families.\r\nConfident & Brave Strong, fearless personality but not naturally aggressive when well-bred and trained.', 'images/dogs/dog_68d2a7d3907ee.jpg', 1000.00, '2025-09-23 13:59:47', '2025-09-23 13:59:47'),
(7, 'English bully', 'English bully', '8-14 weeks', 'Calm & Gentle Known for their laid-back and easygoing nature.\r\nAffectionate & Loyal Very attached to family, loves companionship.\r\nCourageous & Determined Brave and strong-willed, with a protective instinct.', 'images/dogs/dog_68d2a8955c8e8.jpg', 1000.00, '2025-09-23 14:03:01', '2025-09-23 14:03:01'),
(8, 'Pomeranian', 'Pomeranian', '8-14 weeks', 'Playful & Energetic Full of life and always ready for fun.\r\nAffectionate & Loyal Loves being close to their family.\r\nAlert & Watchful  Makes an excellent little watchdog, quick to bark at new sounds.', 'images/dogs/dog_68d2a8fc05a55.jpg', 800.00, '2025-09-23 14:04:44', '2025-09-23 14:04:44'),
(9, 'Shihtzu', 'Shihtzu', '8-14 weeks', 'Playful & Energetic  Full of life and always ready for fun.\r\nAffectionate & Loyal Loves being close to their family.\r\nAlert & Watchful Makes an excellent little watchdog, quick to bark at new sounds.', 'images/dogs/dog_68d2cc84e7e76.jpg', 700.00, '2025-09-23 16:36:20', '2025-09-23 16:36:20'),
(10, 'Tibetan Mastiff', 'Tibetan Mastiff', '8-14 weeks', 'Coat: Thick double coat with a lion-like mane, especially around the neck.\r\nTemperament: Independent, strong-willed, and protective  often aloof with strangers.\r\nLifespan: Around 10–12 years.\r\nIntelligence: Smart but stubborn, not always easy to train.\r\nRole: Traditionally used as a guard dog for livestock and property in the Himalayas.', 'images/dogs/dog_68d662413af3f.jpeg', 2000.00, '2025-09-26 09:52:01', '2025-09-26 09:52:01'),
(11, 'sammy dog', 'eskimo', '23', 'ttytt', 'images/dogs/dog_68e43c6122f50.jpg', 500.00, '2025-10-06 22:02:09', '2025-10-06 22:02:09');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dog_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Processing','Completed','Cancelled') DEFAULT 'Pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `dog_id`, `total_amount`, `status`, `order_date`, `updated_at`) VALUES
(2, 4, 11, 500.00, 'Pending', '2025-10-08 17:26:59', '2025-10-08 17:26:59'),
(3, 4, 10, 2000.00, 'Pending', '2025-10-08 17:27:42', '2025-10-08 17:27:42'),
(4, 1, 10, 2000.00, 'Pending', '2025-10-09 12:28:22', '2025-10-09 12:28:22'),
(5, 5, 10, 2000.00, 'Cancelled', '2025-10-30 16:30:19', '2025-10-30 16:32:01'),
(6, 5, 10, 2000.00, 'Cancelled', '2025-10-30 19:00:27', '2025-10-30 19:01:29'),
(7, 5, 6, 1000.00, 'Cancelled', '2025-10-30 19:02:58', '2025-10-30 19:07:32'),
(8, 5, 10, 2000.00, 'Cancelled', '2025-10-30 19:03:24', '2025-10-30 19:07:24'),
(9, 5, 11, 500.00, 'Cancelled', '2025-10-30 19:08:49', '2025-10-30 19:08:58'),
(10, 5, 6, 1000.00, 'Cancelled', '2025-10-30 19:11:07', '2025-10-30 19:11:16'),
(11, 5, 10, 2000.00, 'Cancelled', '2025-10-30 19:12:40', '2025-10-30 19:12:49'),
(12, 5, 11, 500.00, 'Cancelled', '2025-10-30 19:17:03', '2025-10-30 19:29:08'),
(13, 5, 11, 500.00, 'Cancelled', '2025-11-02 01:48:38', '2025-11-02 01:51:23'),
(14, 5, 9, 700.00, 'Cancelled', '2025-11-02 01:49:06', '2025-11-02 01:51:19'),
(15, 5, 10, 2000.00, 'Cancelled', '2025-11-02 01:51:52', '2025-11-02 01:53:32'),
(16, 5, 7, 1000.00, 'Cancelled', '2025-11-02 02:00:24', '2025-11-02 02:09:57'),
(17, 5, 11, 500.00, 'Cancelled', '2025-11-02 14:38:25', '2025-11-02 14:43:31'),
(18, 5, 9, 700.00, 'Cancelled', '2025-11-02 14:43:37', '2025-11-02 14:47:39'),
(19, 5, 8, 800.00, 'Cancelled', '2025-11-02 14:46:39', '2025-11-02 14:47:33');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `dog_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `is_admin`, `created_at`) VALUES
(1, 'segun', 'segun', 'olufawosegun0001@gmail.com', '$2y$10$MZRy2kKzr4JRJYFAuCQrkOGFGlg5UCym.cOYRy4d37bhCKk8tCV1q', 0, '2025-09-23 15:14:26'),
(2, 'System', 'Admin', 'admin@doghouse.com', '$2y$10$uG1a7QBhkDXFgDHTYY9HGeOHSvD2wC1pHBGooGjd0xkg7ENd44Egm', 1, '2025-09-24 11:59:45'),
(3, 'Omolara', 'Adeyemi', 'adeyemiomolara671@gmail.com', '$2y$12$vsWfwnYKPKHlWKM51yPdmO6yGKyYxFFk5qnaw.YaimoFFop20SfdC', 0, '2025-09-26 09:16:02'),
(4, 'segun', 'segun', 'segyrictech@gmail.com', '$2y$12$rYS2eKqjiuCA4pFE4GKK/Ou6.gfn7c60ZndW9l5bNimqpkWjdXQtq', 0, '2025-10-08 16:36:11'),
(5, 'Segun', 'Segun', 'olufawosegun00001@gmail.com', '$2y$12$C7NTH2bpHc5k2uXhgKrtt.pB7gl2jNHibquBBu8JfDmorBXBxrg5y', 0, '2025-10-30 15:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_dogs`
--

CREATE TABLE `user_dogs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dog_id` int(11) NOT NULL,
  `adoption_status` enum('Available','Adopted','Pending') DEFAULT 'Available',
  `adoption_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`user_id`,`dog_id`),
  ADD KEY `dog_id` (`dog_id`);

--
-- Indexes for table `company_info`
--
ALTER TABLE `company_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `dogs`
--
ALTER TABLE `dogs`
  ADD PRIMARY KEY (`dog_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `dog_id` (`dog_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_dogs`
--
ALTER TABLE `user_dogs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `company_info`
--
ALTER TABLE `company_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dogs`
--
ALTER TABLE `dogs`
  MODIFY `dog_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_dogs`
--
ALTER TABLE `user_dogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`dog_id`) REFERENCES `dogs` (`dog_id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`dog_id`) REFERENCES `dogs` (`dog_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
