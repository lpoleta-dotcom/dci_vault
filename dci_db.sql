-- phpMyAdmin SQL Dump
-- version 4.8.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Nov 06, 2026 at 03:23 AM
-- Server version: 10.1.31-MariaDB
-- PHP Version: 7.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;

/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `dci_db` DEFAULT CHARACTER SET utf8mb4;
USE `dci_db`;

-- QUICK VIEW: these queries display every row from each table after import.
-- They are also included as executable statements at the end of this file.
-- SELECT * FROM users;
-- SELECT * FROM categories;
-- SELECT * FROM products;
-- SELECT * FROM product_images;
-- SELECT * FROM product_variants;
-- SELECT * FROM orders;
-- SELECT * FROM order_items;

--
-- Database: `dci_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_no` varchar(20) NOT NULL,
  `street` varchar(150) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal` varchar(10) NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `middle_name`, `last_name`, `username`, `password`, `contact_no`, `street`, `barangay`, `city`, `province`, `postal`, `position`, `avatar`) VALUES
(1, 'Lyra Janelle', 'Pilapil', 'Oleta', 'luraloo@123', '$2y$10$7aQYxsgVglIawKN6IDx2lOwDmjosg.RFdxTAPGPF7JG3reVOYJnVO', '09658323208', 'Blk 2, Lt 22', 'San Cristobal', 'Calamba', 'Laguna', '4027', 1, 'assets/user.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- DCI Vault store tables. These support the product catalog, size selection,
-- checkout, order history, and admin sales reports shown in the app.
-- Existing `users.position` values are retained for compatibility.

CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_categories_name` (`name`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL COMMENT 'VAT-inclusive retail price; VAT portion is 12/112 of this amount',
  `sku` varchar(60) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_category_active` (`category_id`,`is_active`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `view_label` varchar(40) NOT NULL DEFAULT 'Front',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`image_id`),
  UNIQUE KEY `uq_product_image_path` (`product_id`,`image_url`(190)),
  KEY `idx_product_images_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Each size is a purchasable variant with its own stock count.
CREATE TABLE IF NOT EXISTS `product_variants` (
  `variant_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `size` varchar(20) NOT NULL DEFAULT 'One Size',
  `color` varchar(50) NOT NULL DEFAULT 'Default',
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`variant_id`),
  UNIQUE KEY `uq_product_variant` (`product_id`,`size`,`color`),
  CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Processing','Completed','Cancelled') NOT NULL DEFAULT 'Processing',
  `payment_method` enum('gcash','card','cod') NOT NULL,
  `payment_status` enum('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `subtotal` decimal(10,2) NOT NULL COMMENT 'VAT-inclusive item total before shipping',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT portion already included in subtotal',
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_name` varchar(200) NOT NULL,
  `shipping_contact` varchar(20) NOT NULL,
  `shipping_address` varchar(500) NOT NULL,
  `placed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_user_date` (`user_id`,`placed_at`),
  KEY `idx_orders_status_date` (`status`,`placed_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snapshot the product name and price so old receipts stay accurate after catalog edits.
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `size` varchar(20) NOT NULL DEFAULT 'One Size',
  `color` varchar(50) NOT NULL DEFAULT 'Default',
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Unit price with VAT included',
  `quantity` int(11) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(11) NOT NULL,
  `notification_type` varchar(40) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(500) NOT NULL,
  `target_url` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notifications_recipient_read_date` (`recipient_user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_size_stock_alerts` (
  `product_id` int(11) NOT NULL,
  `size` varchar(20) NOT NULL,
  `is_low_stock` tinyint(1) NOT NULL DEFAULT 0,
  `last_stock` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`,`size`),
  CONSTRAINT `fk_stock_alert_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the categories used by the existing customer product filters.
INSERT IGNORE INTO `categories` (`name`,`slug`) VALUES
  ('T-Shirt','tshirt'),
  ('Bottom','bottom'),
  ('Outerwear','outerwear'),
  ('Accessory','accessory');

-- Sample catalog data based on product image folders in /assets.
INSERT IGNORE INTO `products` (`product_id`,`category_id`,`name`,`description`,`price`,`sku`) VALUES
  (1,1,'CodeBreaker T-Shirt','DCI graphic T-shirt.',250.00,'DCI-TS-CODEBREAKER'),
  (2,2,'AlgoArk Bottoms','Casual DCI bottoms.',650.00,'DCI-BT-ALGOARK'),
  (3,3,'AlgoWeave Hoodie','Warm DCI outerwear.',850.00,'DCI-OW-ALGOWEAVE'),
  (4,4,'AlgoRhythm Accessory','DCI branded accessory.',180.00,'DCI-AC-ALGORHYTHM');

INSERT IGNORE INTO `product_images` (`product_id`,`image_url`,`view_label`,`sort_order`) VALUES
  (1,'assets/tshirt/CodeBreaker/CodeBreaker_Front.jpg','Front',1),
  (1,'assets/tshirt/CodeBreaker/CodeBreaker_Back.jpg','Back',2),
  (1,'assets/tshirt/CodeBreaker/CodeBreaker_Side.jpg','Side',3),
  (2,'assets/bottom/AlgoArk/AlgoArk_Front.jpg','Front',1),
  (2,'assets/bottom/AlgoArk/AlgoArk_Back.jpg','Back',2),
  (2,'assets/bottom/AlgoArk/AlgoArk_Side.jpg','Side',3),
  (3,'assets/outerwear/AlgoWeave/AlgoWeave_Front.jpg','Front',1),
  (3,'assets/outerwear/AlgoWeave/AlgoWeave_Back.jpg','Back',2),
  (3,'assets/outerwear/AlgoWeave/AlgoWeave_Side.jpg','Side',3),
  (4,'assets/accessory/AlgoRhythm/AlgoRhythm_Front.jpg','Front',1),
  (4,'assets/accessory/AlgoRhythm/AlgoRhythm_Back.jpg','Back',2),
  (4,'assets/accessory/AlgoRhythm/AlgoRhythm_Zoom.jpg','Detail',3);

INSERT IGNORE INTO `product_variants` (`product_id`,`size`,`color`,`stock_quantity`) VALUES
  (1,'S','Black',12),(1,'M','Black',18),(1,'L','Black',15),(1,'XL','Black',8),
  (2,'S','Black',6),(2,'M','Black',10),(2,'L','Black',8),(2,'XL','Black',5),
  (3,'M','Gray',7),(3,'L','Gray',9),(3,'XL','Gray',4),
  (4,'One Size','Default',25);

-- Example order uses the existing demo user (user_id 1) from the users seed.
INSERT IGNORE INTO `orders` (`order_id`,`order_number`,`user_id`,`status`,`payment_method`,`payment_status`,`subtotal`,`vat_amount`,`shipping_fee`,`total_amount`,`shipping_name`,`shipping_contact`,`shipping_address`,`placed_at`) VALUES
  (1,'ORD-2026-001',1,'Completed','gcash','Paid',500.00,53.58,30.00,530.00,'Lyra Janelle Oleta','09658323208','Blk 2, Lt 22, San Cristobal, Calamba, Laguna 4027','2026-10-26 09:30:00');

INSERT IGNORE INTO `order_items` (`order_item_id`,`order_id`,`product_id`,`variant_id`,`product_name`,`size`,`color`,`unit_price`,`quantity`,`line_total`) VALUES
  (1,1,1,2,'CodeBreaker T-Shirt','M','Black',250.00,2,500.00);

COMMIT;

-- Display all seeded data after import. Keep these after table creation and inserts.
SELECT * FROM `users`;
SELECT * FROM `categories`;
SELECT * FROM `products`;
SELECT * FROM `product_images`;
SELECT * FROM `product_variants`;
SELECT * FROM `orders`;
SELECT * FROM `order_items`;
