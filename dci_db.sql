-- phpMyAdmin SQL Dump
-- version 4.8.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Nov 12, 2026 at 10:54 PM
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

--
-- Database: `dci_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `slug`, `description`, `is_active`) VALUES
(1, 'Tops', 'tshirt', NULL, 1),
(2, 'Bottom', 'bottom', NULL, 1),
(3, 'Outerwear', 'outerwear', NULL, 1),
(4, 'Accessories', 'accessory', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `notification_type` varchar(40) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(500) NOT NULL,
  `target_url` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `recipient_user_id`, `notification_type`, `title`, `message`, `target_url`, `is_read`, `created_at`) VALUES
(1, 2, 'welcome', 'Welcome to DCI Vault', 'Your account is ready. Happy shopping!', 'cstmr-prod.php', 1, '2026-11-11 07:44:33'),
(2, 1, 'new_signup', 'New customer signed up', 'aaron_it created a customer account.', 'admin-users.php?highlight-user=2', 1, '2026-11-11 07:44:33'),
(3, 2, 'order_placed', 'Order placed: ORD-20261111132550-021970', 'Your order is now processing. Weâ€™ll keep you updated.', 'cstmr-order.php?highlight-order=1', 1, '2026-11-11 12:25:50'),
(4, 1, 'new_order', 'New order received', 'ORD-20261111132550-021970 from Aaron Edriel Espina Dalago â€” â‚±479.00.', 'admin-order.php?highlight-order=1', 1, '2026-11-11 12:25:50'),
(5, 2, 'order_status', 'Order status updated', 'Your order ORD-20261111132550-021970 is now Completed.', 'cstmr-order.php?highlight-order=1', 1, '2026-11-11 12:26:13'),
(6, 2, 'order_placed', 'Order placed: ORD-20261111132745-A11DAF', 'Your order is now processing. Weâ€™ll keep you updated.', 'cstmr-order.php?highlight-order=2', 1, '2026-11-11 12:27:45'),
(7, 1, 'new_order', 'New order received', 'ORD-20261111132745-A11DAF from Aaron Edriel Espina Dalago â€” â‚±479.00.', 'admin-order.php?highlight-order=2', 1, '2026-11-11 12:27:45'),
(8, 1, 'order_status', 'Customer cancelled an order', 'A customer cancelled order ORD-20261111132745-A11DAF.', 'admin-order.php?highlight-order=2', 1, '2026-11-11 12:27:52'),
(9, 1, 'low_stock', 'Low stock: Cloud Sentinel T-Shirt', 'Only 0 unit(s) remain in size S.', 'admin-prod.php?highlight-product=9&highlight-size=S', 1, '2026-11-11 12:35:39'),
(10, 1, 'low_stock', 'Low stock: Cloud Sentinel T-Shirt', 'Only 0 unit(s) remain in size L.', 'admin-prod.php?highlight-product=9&highlight-size=L', 1, '2026-11-11 12:35:39'),
(11, 1, 'low_stock', 'Low stock: Cloud Sentinel T-Shirt', 'Only 0 unit(s) remain in size XL.', 'admin-prod.php?highlight-product=9&highlight-size=XL', 1, '2026-11-11 12:35:39'),
(12, 2, 'order_status', 'Order status updated', 'Your order ORD-20261111132745-A11DAF is now Processing.', 'cstmr-order.php?highlight-order=2', 1, '2026-11-12 00:13:25'),
(13, 2, 'order_status', 'Order status updated', 'Your order ORD-20261111132745-A11DAF is now Cancelled.', 'cstmr-order.php?highlight-order=2', 1, '2026-11-12 00:13:32'),
(14, 2, 'order_status', 'Order status updated', 'Your order ORD-20261111132745-A11DAF is now Processing.', 'cstmr-order.php?highlight-order=2', 1, '2026-11-12 00:13:34'),
(15, 2, 'order_status', 'Order status updated', 'Your order ORD-20261111132745-A11DAF is now Completed.', 'cstmr-order.php?highlight-order=2', 1, '2026-11-12 00:13:35'),
(16, 2, 'order_placed', 'Order placed: ORD-20261112043631-6F3D56', 'Your order is now processing. Weâ€™ll keep you updated.', 'cstmr-order.php?highlight-order=3', 1, '2026-11-12 03:36:31'),
(17, 1, 'new_order', 'New order received', 'ORD-20261112043631-6F3D56 from Aaron Edriel Espina Dalago â€” â‚±479.00.', 'admin-order.php?highlight-order=3', 1, '2026-11-12 03:36:31'),
(18, 2, 'order_placed', 'Order placed: ORD-20261112043726-C55AFE', 'Your order is now processing. Weâ€™ll keep you updated.', 'cstmr-order.php?highlight-order=4', 1, '2026-11-12 03:37:26'),
(19, 1, 'new_order', 'New order received', 'ORD-20261112043726-C55AFE from Aaron Edriel Espina Dalago â€” â‚±479.00.', 'admin-order.php?highlight-order=4', 1, '2026-11-12 03:37:26'),
(20, 2, 'order_placed', 'Order placed: ORD-20261112044006-FC7452', 'Your order is now processing. Weâ€™ll keep you updated.', 'cstmr-order.php?highlight-order=5', 1, '2026-11-12 03:40:06'),
(21, 1, 'new_order', 'New order received', 'ORD-20261112044006-FC7452 from Aaron Edriel Espina Dalago â€” â‚±479.00.', 'admin-order.php?highlight-order=5', 1, '2026-11-12 03:40:06'),
(22, 1, 'order_status', 'Customer cancelled an order', 'A customer cancelled order ORD-20261112044006-FC7452.', 'admin-order.php?highlight-order=5', 1, '2026-11-12 03:40:11'),
(23, 2, 'order_status', 'Order status updated', 'Your order ORD-20261112043726-C55AFE is now Completed.', 'cstmr-order.php?highlight-order=4', 1, '2026-11-12 03:49:47'),
(24, 1, 'low_stock', 'Low stock: CodeBreaker T-Shirt', 'Only 0 unit(s) remain in size S.', 'admin-prod.php?highlight-product=1&highlight-size=S', 1, '2026-11-12 03:55:00'),
(25, 2, 'order_status', 'Order status updated', 'Your order ORD-20261112043631-6F3D56 is now Completed.', 'cstmr-order.php?highlight-order=3', 1, '2026-11-12 04:15:05'),
(26, 2, 'order_placed', 'Order placed: ORD-20261112051644-E743FD', 'Your order is now processing. Weâ€™ll keep you updated.', 'cstmr-order.php?highlight-order=6', 1, '2026-11-12 04:16:44'),
(27, 1, 'new_order', 'New order received', 'ORD-20261112051644-E743FD from Aaron Espina Dalago â€” â‚±529.00.', 'admin-order.php?highlight-order=6', 1, '2026-11-12 04:16:44'),
(28, 2, 'order_status', 'Order status updated', 'Your order ORD-20261112051644-E743FD is now Completed.', 'cstmr-order.php?highlight-order=6', 1, '2026-11-12 04:18:17'),
(29, 4, 'welcome', 'Welcome to DCI Vault', 'Your account is ready. Happy shopping!', 'cstmr-prod.php', 0, '2026-11-12 05:42:27'),
(30, 1, 'new_signup', 'New customer signed up', 'kayne123@ created a customer account.', 'admin-users.php?highlight-user=4', 0, '2026-11-12 05:42:27'),
(31, 4, 'order_placed', 'Order placed: ORD-20261112064507-0793C8', 'Your order is now processing. Weâ€™ll keep you updated.', 'cstmr-order.php?highlight-order=7', 0, '2026-11-12 05:45:07'),
(32, 1, 'new_order', 'New order received', 'ORD-20261112064507-0793C8 from kayne1234 mingo@ â€” â‚±13,051.00.', 'admin-order.php?highlight-order=7', 0, '2026-11-12 05:45:07'),
(33, 1, 'low_stock', 'Low stock: CodeBreaker T-Shirt', 'Only 0 unit(s) remain in size M.', 'admin-prod.php?highlight-product=1&highlight-size=M', 0, '2026-11-12 05:45:07'),
(34, 1, 'low_stock', 'Low stock: CodeBreaker T-Shirt', 'Only 0 unit(s) remain in size L.', 'admin-prod.php?highlight-product=1&highlight-size=L', 1, '2026-11-12 05:45:07'),
(35, 4, 'order_status', 'Order status updated', 'Your order ORD-20261112064507-0793C8 is now Cancelled.', 'cstmr-order.php?highlight-order=7', 1, '2026-11-12 05:48:42'),
(36, 1, 'low_stock', 'Low stock: Cloud Sentinel T-Shirt', 'Only 0 unit(s) remain in size M.', 'admin-prod.php?highlight-product=9&highlight-size=M', 0, '2026-11-12 05:55:17'),
(37, 1, 'low_stock', 'Low stock: Meow', 'Only 0 unit(s) remain in size XS.', 'admin-prod.php?highlight-product=7296&highlight-size=XS', 0, '2026-11-12 16:54:47');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Processing','Completed','Cancelled') NOT NULL DEFAULT 'Processing',
  `payment_method` enum('gcash','card','cod') NOT NULL,
  `payment_status` enum('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `subtotal` decimal(10,2) NOT NULL COMMENT 'VAT-inclusive item total before shipping',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT portion already included in subtotal',
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_name` varchar(200) NOT NULL,
  `shipping_contact` varchar(20) NOT NULL,
  `shipping_address` varchar(500) NOT NULL,
  `placed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_number`, `user_id`, `status`, `payment_method`, `payment_status`, `subtotal`, `vat_amount`, `shipping_fee`, `total_amount`, `shipping_name`, `shipping_contact`, `shipping_address`, `placed_at`, `updated_at`) VALUES
(1, 'ORD-20261111132550-021970', 2, 'Completed', 'gcash', 'Paid', '449.00', '48.11', '30.00', '479.00', 'Aaron Edriel Espina Dalago', '09675978682', 'Blk 36, Lot 24, Phase 2, Villa De Calamba, La Mesa, Calamba, Laguna, 4027', '2026-11-11 12:25:50', '2026-11-11 12:26:13'),
(2, 'ORD-20261111132745-A11DAF', 2, 'Completed', 'gcash', 'Paid', '449.00', '48.11', '30.00', '479.00', 'Aaron Edriel Espina Dalago', '09675978682', 'Blk 36, Lot 24, Phase 2, Villa De Calamba, La Mesa, Calamba, Laguna, 4027', '2026-11-11 12:27:45', '2026-11-12 00:13:35'),
(3, 'ORD-20261112043631-6F3D56', 2, 'Completed', 'gcash', 'Paid', '449.00', '48.11', '30.00', '479.00', 'Aaron Edriel Espina Dalago', '09675978682', 'Blk 36, Lot 24, Phase 2, Villa De Calamba, La Mesa, Calamba, Laguna, 4027', '2026-11-12 03:36:31', '2026-11-12 04:15:05'),
(4, 'ORD-20261112043726-C55AFE', 2, 'Completed', 'gcash', 'Paid', '449.00', '48.11', '30.00', '479.00', 'Aaron Edriel Espina Dalago', '09675978682', 'Blk 36, Lot 24, Phase 2, Villa De Calamba, La Mesa, Calamba, Laguna, 4027', '2026-11-12 03:37:26', '2026-11-12 03:49:47'),
(5, 'ORD-20261112044006-FC7452', 2, 'Cancelled', 'gcash', 'Pending', '449.00', '48.11', '30.00', '479.00', 'Aaron Edriel Espina Dalago', '09675978682', 'Blk 36, Lot 24, Phase 2, Villa De Calamba, La Mesa, Calamba, Laguna, 4027', '2026-11-12 03:40:06', '2026-11-12 03:40:11'),
(6, 'ORD-20261112051644-E743FD', 2, 'Completed', 'card', 'Paid', '499.00', '53.46', '30.00', '529.00', 'Aaron Espina Dalago', '09675978682', 'Blk 36, Lot 24, Phase 2, Villa De Calamba, La Mesa, Calamba, Laguna, 4027', '2026-11-12 04:16:44', '2026-11-12 04:18:17'),
(7, 'ORD-20261112064507-0793C8', 4, 'Cancelled', 'gcash', 'Pending', '13021.00', '1395.19', '30.00', '13051.00', 'kayne1234 mingo@', '12456789745852', 'fghjk, fghjk, ghjk, fghjk, dfghj', '2026-11-12 05:45:07', '2026-11-12 05:48:42');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `size` varchar(20) NOT NULL DEFAULT 'One Size',
  `color` varchar(50) NOT NULL DEFAULT 'Default',
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Unit price with VAT included',
  `quantity` int(11) NOT NULL,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `variant_id`, `product_name`, `size`, `color`, `unit_price`, `quantity`, `line_total`) VALUES
(1, 1, 1, 3, 'CodeBreaker T-Shirt', 'L', 'Black', '449.00', 1, '449.00'),
(2, 2, 1, 3, 'CodeBreaker T-Shirt', 'L', 'Black', '449.00', 1, '449.00'),
(3, 3, 1, 3, 'CodeBreaker T-Shirt', 'L', 'Black', '449.00', 1, '449.00'),
(4, 4, 1, 3, 'CodeBreaker T-Shirt', 'L', 'Black', '449.00', 1, '449.00'),
(5, 5, 1, 3, 'CodeBreaker T-Shirt', 'L', 'Black', '449.00', 1, '449.00'),
(6, 6, 9, 26, 'Cloud Sentinel T-Shirt', 'M', 'Default', '499.00', 1, '499.00'),
(7, 7, 1, 2, 'CodeBreaker T-Shirt', 'M', 'Black', '449.00', 18, '8082.00'),
(8, 7, 1, 3, 'CodeBreaker T-Shirt', 'L', 'Black', '449.00', 11, '4939.00');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL COMMENT 'VAT-inclusive retail price; VAT portion is 12/112 of this amount',
  `sku` varchar(60) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `name`, `description`, `price`, `sku`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'CodeBreaker T-Shirt', 'Level up your streetwear with this oversized vintage tee featuring an intricate graphic for a unique tech aesthetic.', '449.00', 'DCI-TS-CODEBREAKER', 1, '2026-11-10 09:13:56', '2026-11-11 08:15:05'),
(2, 2, 'AlgoArk Bottoms', 'Vintage indigo A-line denim skirt featuring subtle runic details and a minimalist Viking ship engraving.', '925.00', 'DCI-BT-ALGOARK', 1, '2026-11-10 09:13:56', '2026-11-11 07:52:09'),
(3, 3, 'AlgoWeave Hoodie', 'Fresh ice-blue colorway detailing recursive code loops - for those running complex threads daily.', '550.00', 'DCI-OW-ALGOWEAVE', 1, '2026-11-10 09:13:56', '2026-11-11 08:07:32'),
(4, 4, 'AlgoRhythm Accessory', 'Handcrafted adjustable woven bracelet with a subtle binary pattern braid. Designed for tech enthusiasts and students who want a minimalist everyday accessory to complement their casual campus outfit.', '149.00', 'DCI-AC-ALGORHYTHM', 1, '2026-11-10 09:13:56', '2026-11-11 08:12:42'),
(9, 1, 'Cloud Sentinel T-Shirt', 'Elevate your street outfit with this sky-forged warrior tee crafted for maximum comfort in a modern relaxed fit.', '499.00', 'DCI-TS-CLOUDSENTINEL', 1, '2026-11-10 09:14:47', '2026-11-12 03:58:29'),
(11, 1, 'Code Weaver T-Shirt', 'Embrace the ultimate dev aesthetic with this comfortable oversized shirt showcasing detailed algorithmic back-art.', '529.00', 'DCI-TS-CODEWEAVER', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(12, 1, 'Crypto Key T-Shirt', 'Add a mysterious cryptographic edge to your wardrobe with this relaxed-fit tee featuring a detailed key and lock design.', '479.00', 'DCI-TS-CRYPTOKEY', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(13, 1, 'Data Forge T-Shirt', 'Forged for style and comfort, this oversized graphic tee highlights an epic mythological forge graphic printed on premium fabric.', '529.00', 'DCI-TS-DATAFORGE', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(14, 1, 'Debug Axe T-Shirt', 'Conquer every bug in style with this rugged streetwear graphic tee made for programmers and tech enthusiasts.', '459.00', 'DCI-TS-DEBUGAXE', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(15, 1, 'Global Net T-Shirt', 'Connect your style to the world with this washed black oversized tee featuring a serpent-encircled globe print.', '499.00', 'DCI-TS-GLOBALNET', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(16, 1, 'Info Serpent T-Shirt', 'Level up your streetwear with this oversized vintage tee featuring an intricate serpent-meets-code graphic for a unique tech aesthetic.', '549.00', 'DCI-TS-INFOSERPENT', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(17, 1, 'Machine Spirit T-Shirt', 'Showcase your love for retro-futurism with this unique oversized tee starring a detailed cyber-cybernetic graphic.', '489.00', 'DCI-TS-MACHINESPIRIT', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(18, 1, 'Sentinel Fire T-Shirt', 'Unleash fiery energy on the streets with this heavy-duty vintage graphic tee featuring a bold elemental design.', '469.00', 'DCI-TS-SENTINELFIRE', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(19, 1, 'Silver Vikings T-Shirt', 'Combine legendary Norse vibes with modern street fashion in this heavy-duty, relaxed-fit vintage graphic tee.', '549.00', 'DCI-TS-SILVERVIKINGS', 1, '2026-11-10 09:14:47', '2026-11-11 08:15:05'),
(21, 2, 'Cipher Fortress Bottoms', 'Obsidian black baggy cargo pants featuring cryptographic runic details and a Nordic-inspired shield design.', '850.00', 'DCI-BT-CIPHERFORTRESS', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(22, 2, 'Nautical Crest Bottoms', 'Off-white Bermuda shorts featuring elegant gold celestial embroidery and intricate geometric patterns along the hem.', '750.00', 'DCI-BT-NAUTICALCREST', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(23, 2, 'Runic Matrix Bottoms', 'Dark-wash flared denim pants featuring minimalist cyber-runic graphics, Nordic knotwork, and subtle matrix-inspired detailing.', '950.00', 'DCI-BT-RUNICMATRIX', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(24, 2, 'Runic Viking Bottoms', 'Light wash baggy denim jeans featuring subtle runic details and a vintage-inspired Viking helmet pocket design.', '1000.00', 'DCI-BT-RUNICVIKING', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(25, 2, 'Synapse Weaver Bottoms', 'Heavyweight slate grey fleece shorts featuring an AI-inspired runic emblem and an intricate Yggdrasil neural network design.', '600.00', 'DCI-BT-SYNAPSEWEAVER', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(26, 2, 'Valhalla Cloud Bottoms', 'Storm cloud grey wide-leg fleece sweatpants featuring vertical runic lettering and an intricate floating monolith design.', '750.00', 'DCI-BT-VALHALLACLOUD', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(27, 2, 'Viking Cargo Bottoms', 'Chestnut brown straight-fit corduroy pants featuring white contrast stitching and a minimalist embroidered Viking emblem.', '1075.00', 'DCI-BT-VIKINGCARGO', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(28, 2, 'Void Denim Bottoms', 'Dark charcoal high-waisted denim shorts featuring a clean minimalist design, classic five-pocket styling, and subtle brand detailing.', '825.00', 'DCI-BT-VOIDDENIM', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(29, 2, 'Washed Charcoal Bottoms', 'Vintage-washed charcoal wide-leg jeans featuring a minimalist Vegvisir compass engraving and a classic runic leather patch.', '750.00', 'DCI-BT-WASHEDCHARCOAL', 1, '2026-11-10 09:14:47', '2026-11-11 07:52:09'),
(31, 3, 'Cloud Nexus Outerwear', 'Global sync in physical form; local style paired with distributed cloud compass graphics for everywhere you move.', '600.00', 'DCI-OW-CLOUDNEXUS', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(32, 3, 'Firewall Sentinel Outerwear', 'Clean navy fleece, thick leather sleeves, and a cyber-shield emblem for those who keep their circle locked tight.', '570.00', 'DCI-OW-FIREWALLSENTINEL', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(33, 3, 'Game Engine Outerwear', 'Built for the ones who construct worlds from scratch - heavyweight jacket featuring crisp, high-detail render graphics on the back.', '530.00', 'DCI-OW-GAMEENGINE', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(34, 3, 'Kernel Root Outerwear', 'High-vis yellow panels with root-level graphics for maximum privilege and pure system control.', '415.00', 'DCI-OW-KERNELROOT', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(35, 3, 'Neural Batice Outerwear', 'Deep learning meets street level - synaptic brain circuitry mapped out on a dark, premium varsity base.', '625.00', 'DCI-OW-NEURALBATICE', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(36, 3, 'Packet Router Outerwear', 'Standout deep green tones stamped with routing node paths to keep your fit moving fast.', '530.00', 'DCI-OW-PACKETROUTER', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(37, 3, 'Quantum Qubit Outerwear', 'Clean silver finish with atomic quantum runes - lowkey futuristic, zero unnecessary noise.', '500.00', 'DCI-OW-QUANTUMQUBIT', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(38, 3, 'Relational Vault Outerwear', 'Heavy-duty architecture for the back end - structured grey palette tagged with raw ACID vault engravings.', '500.00', 'DCI-OW-RELATIONALVAULT', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(39, 3, 'Software Artisan Outerwear', 'Tailored turtleneck ribbing paired with charcoal fleece - crafted for the ones who write clean code and wear better fits.', '550.00', 'DCI-OW-SOFTWAREARTISAN', 1, '2026-11-10 09:14:48', '2026-11-11 08:07:32'),
(41, 4, 'Bandwidth Paracord Accessory', 'Rugged military-grade paracord wristband with a quick-detach alloy buckle. A sporty accessory built for durability, active outdoor org events, and daily wear.', '189.00', 'DCI-AC-BANDWIDTHPARACORD', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(42, 4, 'Cyber Drive Accessory', 'Durable acrylic keychain shaped like a retro motherboard chip with holographic glass finish. Perfect for organizing dormitory keys, flash drives, and ID lanyards.', '99.00', 'DCI-AC-CYBERDRIVE', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(43, 4, 'Debug Glasses Accessory', 'Stylish matte-black square frame with anti-blue light coated lenses. Built specifically for long hours of coding, studying, and screen usage to prevent eye strain and headaches.', '399.00', 'DCI-AC-DEBUGGLASSES', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(44, 4, 'Mainframe Beanie Accessory', 'Soft knit ribbed beanie featuring a minimal embroidered emblem on the cuff. Provides comfort and style inside cold air-conditioned computer laboratories and lecture halls.', '229.00', 'DCI-AC-MAINFRAMEBEANIE', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(45, 4, 'Patch-Set Embroidered Pin Accessory', 'Enamel metal lapel pin with a rubber clutch backing. Features vibrant retro 8-bit designs to customize tote bags, denim jackets, and backpack straps.', '89.00', 'DCI-AC-PATCHSETEMBROIDEREDPIN', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(46, 4, 'Runtime Stainless Ring Accessory', 'Smooth-finish silver stainless steel spinner ring with engraved tech-inspired geometric lines. Designed to relieve anxiety and help with focus during high-pressure exam seasons.', '179.00', 'DCI-AC-RUNTIMESTAINLESSRING', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(47, 4, 'Silver Chain Accessory', 'Premium stainless steel curb chain with a custom circular pendant featuring a sleek gear/circuit motif. Rust-proof, hypoallergenic, and ideal for layering with any outfit.', '249.00', 'DCI-AC-SILVERCHAIN', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(48, 4, 'Syntax Bag Accessory', 'Heavy-duty canvas tote bag printed with stylized course-inspired graphics and quotes. Features a spacious main compartment with a laptop sleeve and a quick-access front pocket for small essentials.', '299.00', 'DCI-AC-SYNTAXBAG', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(49, 4, 'Terminal Lanyard Accessory', 'High-density polyester lanyard with a quick-release buckle and transparent waterproof ID badge holder. Printed with department colors and custom typography.', '129.00', 'DCI-AC-TERMINALLANYARD', 1, '2026-11-10 09:14:48', '2026-11-11 08:12:42'),
(7115, 1, 'Cloud Sentinel T-Shirts', 'Elevate your street outfit with this sky-forged warrior tee crafted for maximum comfort in a modern relaxed fit.', '499.00', 'DCI-TS-2667B18FFA5E', 0, '2026-11-12 05:54:42', '2026-11-12 05:56:59'),
(7296, 4, 'Meow', 'meow meow', '34.00', 'DCI-AC-38D6882FCA57', 1, '2026-11-12 16:54:47', '2026-11-12 16:54:47');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `view_label` varchar(40) NOT NULL DEFAULT 'Front',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_url`, `view_label`, `sort_order`) VALUES
(4, 2, 'assets/bottom/AlgoArk/AlgoArk_Front.jpg', 'Front', 1),
(5, 2, 'assets/bottom/AlgoArk/AlgoArk_Back.jpg', 'Back', 2),
(6, 2, 'assets/bottom/AlgoArk/AlgoArk_Side.jpg', 'Side', 3),
(7, 3, 'assets/outerwear/AlgoWeave/AlgoWeave_Front.jpg', 'Front', 1),
(8, 3, 'assets/outerwear/AlgoWeave/AlgoWeave_Back.jpg', 'Back', 2),
(9, 3, 'assets/outerwear/AlgoWeave/AlgoWeave_Side.jpg', 'Side', 3),
(10, 4, 'assets/accessory/AlgoRhythm/AlgoRhythm_Front.jpg', 'Front', 1),
(11, 4, 'assets/accessory/AlgoRhythm/AlgoRhythm_Back.jpg', 'Back', 2),
(12, 4, 'assets/accessory/AlgoRhythm/AlgoRhythm_Zoom.jpg', 'Detail', 3),
(31, 11, 'assets/tshirt/CodeWeaver/CodeWeaver_Back.jpg', 'Back', 1),
(32, 11, 'assets/tshirt/CodeWeaver/CodeWeaver_Front.jpg', 'Front', 2),
(33, 11, 'assets/tshirt/CodeWeaver/CodeWeaver_Side.jpg', 'Side', 3),
(34, 12, 'assets/tshirt/CryptoKey/CryptoKey_Back.jpg', 'Back', 1),
(35, 12, 'assets/tshirt/CryptoKey/CryptoKey_Front.jpg', 'Front', 2),
(36, 12, 'assets/tshirt/CryptoKey/CryptoKey_Side.jpg', 'Side', 3),
(37, 13, 'assets/tshirt/DataForge/DataForge_Back.jpg', 'Back', 1),
(38, 13, 'assets/tshirt/DataForge/DataForge_Front.jpg', 'Front', 2),
(39, 13, 'assets/tshirt/DataForge/DataForge_Side.jpg', 'Side', 3),
(40, 14, 'assets/tshirt/DebugAxe/DebugAxe_Back.jpg', 'Back', 1),
(41, 14, 'assets/tshirt/DebugAxe/DebugAxe_Front.jpg', 'Front', 2),
(42, 14, 'assets/tshirt/DebugAxe/DebugAxe_Side.jpg', 'Side', 3),
(43, 15, 'assets/tshirt/GlobalNet/GlobalNet_Back.jpg', 'Back', 1),
(44, 15, 'assets/tshirt/GlobalNet/GlobalNet_Front.jpg', 'Front', 2),
(45, 15, 'assets/tshirt/GlobalNet/GlobalNet_Side.jpg', 'Side', 3),
(46, 16, 'assets/tshirt/InfoSerpent/InfoSerpent_Back.jpg', 'Back', 1),
(47, 16, 'assets/tshirt/InfoSerpent/InfoSerpent_Front.jpg', 'Front', 2),
(48, 16, 'assets/tshirt/InfoSerpent/InfoSerpent_Side.jpg', 'Side', 3),
(49, 17, 'assets/tshirt/MachineSpirit/MachineSpirit_Back.jpg', 'Back', 1),
(50, 17, 'assets/tshirt/MachineSpirit/MachineSpirit_Front.jpg', 'Front', 2),
(51, 17, 'assets/tshirt/MachineSpirit/MachineSpirit_Side.jpg', 'Side', 3),
(52, 18, 'assets/tshirt/SentinelFire/SentinelFire_Back.jpg', 'Back', 1),
(53, 18, 'assets/tshirt/SentinelFire/SentinelFire_Front.jpg', 'Front', 2),
(54, 18, 'assets/tshirt/SentinelFire/SentinelFire_Side.jpg', 'Side', 3),
(55, 19, 'assets/tshirt/SilverVikings/SilverVikings_Back.jpg', 'Back', 1),
(56, 19, 'assets/tshirt/SilverVikings/SilverVikings_Front.jpg', 'Front', 2),
(57, 19, 'assets/tshirt/SilverVikings/SilverVikings_Side.jpg', 'Side', 3),
(61, 21, 'assets/bottom/CipherFortress/CipherFortress_Back.jpg', 'Back', 1),
(62, 21, 'assets/bottom/CipherFortress/CipherFortress_Front.jpg', 'Front', 2),
(63, 21, 'assets/bottom/CipherFortress/CipherFortress_Side.jpg', 'Side', 3),
(64, 22, 'assets/bottom/NauticalCrest/NauticalCrest_Back.jpg', 'Back', 1),
(65, 22, 'assets/bottom/NauticalCrest/NauticalCrest_Front.jpg', 'Front', 2),
(66, 22, 'assets/bottom/NauticalCrest/NauticalCrest_Side.jpg', 'Side', 3),
(67, 23, 'assets/bottom/RunicMatrix/RunicMatrix_Back.jpg', 'Back', 1),
(68, 23, 'assets/bottom/RunicMatrix/RunicMatrix_Front.jpg', 'Front', 2),
(69, 23, 'assets/bottom/RunicMatrix/RunicMatrix_Side.jpg', 'Side', 3),
(70, 24, 'assets/bottom/RunicViking/RunicViking_Back.jpg', 'Back', 1),
(71, 24, 'assets/bottom/RunicViking/RunicViking_Front.jpg', 'Front', 2),
(72, 24, 'assets/bottom/RunicViking/RunicViking_Side.jpg', 'Side', 3),
(73, 25, 'assets/bottom/SynapseWeaver/SynapseWeaver_Back.jpg', 'Back', 1),
(74, 25, 'assets/bottom/SynapseWeaver/SynapseWeaver_Front.jpg', 'Front', 2),
(75, 25, 'assets/bottom/SynapseWeaver/SynapseWeaver_Side.jpg', 'Side', 3),
(76, 26, 'assets/bottom/ValhallaCloud/ValhallaCloud_Back.jpg', 'Back', 1),
(77, 26, 'assets/bottom/ValhallaCloud/ValhallaCloud_Front.jpg', 'Front', 2),
(78, 26, 'assets/bottom/ValhallaCloud/ValhallaCloud_Side.jpg', 'Side', 3),
(79, 27, 'assets/bottom/VikingCargo/VikingCargo_Back.jpg', 'Back', 1),
(80, 27, 'assets/bottom/VikingCargo/VikingCargo_Front.jpg', 'Front', 2),
(81, 27, 'assets/bottom/VikingCargo/VikingCargo_Side.jpg', 'Side', 3),
(82, 28, 'assets/bottom/VoidDenim/VoidDenim_Back.jpg', 'Back', 1),
(83, 28, 'assets/bottom/VoidDenim/VoidDenim_Front.jpg', 'Front', 2),
(84, 28, 'assets/bottom/VoidDenim/VoidDenim_Side.jpg', 'Side', 3),
(85, 29, 'assets/bottom/WashedCharcoal/WashedCharcoal_Back.jpg', 'Back', 1),
(89, 31, 'assets/outerwear/CloudNexus/CloudNexus_Back.jpg', 'Back', 1),
(90, 31, 'assets/outerwear/CloudNexus/CloudNexus_Front.jpg', 'Front', 2),
(91, 31, 'assets/outerwear/CloudNexus/CloudNexus_Side.jpg', 'Side', 3),
(92, 32, 'assets/outerwear/FirewallSentinel/FirewallSentinel_Back.jpg', 'Back', 1),
(93, 32, 'assets/outerwear/FirewallSentinel/FirewallSentinel_Front.jpg', 'Front', 2),
(94, 32, 'assets/outerwear/FirewallSentinel/FirewallSentinel_Side.jpg', 'Side', 3),
(95, 33, 'assets/outerwear/GameEngine/GameEngine_Back.jpg', 'Back', 1),
(96, 33, 'assets/outerwear/GameEngine/GameEngine_Front.jpg', 'Front', 2),
(97, 33, 'assets/outerwear/GameEngine/GameEngine_Side.jpg', 'Side', 3),
(98, 34, 'assets/outerwear/KernelRoot/KernelRoot_Back.jpg', 'Back', 1),
(99, 34, 'assets/outerwear/KernelRoot/KernelRoot_Front.jpg', 'Front', 2),
(100, 34, 'assets/outerwear/KernelRoot/KernelRoot_Side.jpg', 'Side', 3),
(101, 35, 'assets/outerwear/NeuralBatice/NeuralBatice_Back.jpg', 'Back', 1),
(102, 35, 'assets/outerwear/NeuralBatice/NeuralBatice_Front.jpg', 'Front', 2),
(103, 35, 'assets/outerwear/NeuralBatice/NeuralBatice_Side.jpg', 'Side', 3),
(104, 36, 'assets/outerwear/PacketRouter/PacketRouter_Back.jpg', 'Back', 1),
(105, 36, 'assets/outerwear/PacketRouter/PacketRouter_Front.jpg', 'Front', 2),
(106, 36, 'assets/outerwear/PacketRouter/PacketRouter_Side.jpg', 'Side', 3),
(107, 37, 'assets/outerwear/QuantumQubit/QuantimQubit_Side.jpg', 'Side', 1),
(108, 37, 'assets/outerwear/QuantumQubit/QuantumQubit_Front.jpg', 'Front', 2),
(109, 38, 'assets/outerwear/RelationalVault/RelationalVault_Back.jpg', 'Back', 1),
(110, 38, 'assets/outerwear/RelationalVault/RelationalVault_Front.jpg', 'Front', 2),
(111, 38, 'assets/outerwear/RelationalVault/RelationalVault_Side.jpg', 'Side', 3),
(112, 39, 'assets/outerwear/SoftwareArtisan/SoftwareArtisan_Back.jpg', 'Back', 1),
(113, 39, 'assets/outerwear/SoftwareArtisan/SoftwareArtisan_Front.jpg', 'Front', 2),
(114, 39, 'assets/outerwear/SoftwareArtisan/SoftwareArtisan_Side.jpg', 'Side', 3),
(118, 41, 'assets/accessory/BandwidthParacord/BandwidthParacord_Front.jpg', 'Front', 1),
(119, 41, 'assets/accessory/BandwidthParacord/BandwidthParacord_Zoom1.jpg', 'Detail', 2),
(120, 41, 'assets/accessory/BandwidthParacord/BandwidthParacord_Zoom2.jpg', 'Detail', 3),
(121, 42, 'assets/accessory/CyberDrive/CyberDrive_Front.jpg', 'Front', 1),
(122, 42, 'assets/accessory/CyberDrive/CyberDrive_Zoom.jpg', 'Detail', 2),
(123, 42, 'assets/accessory/CyberDrive/CyberDrive_ZoomOut.jpg', 'Detail', 3),
(124, 43, 'assets/accessory/DebugGlasses/DebugGlasses_Back.jpg', 'Back', 1),
(125, 43, 'assets/accessory/DebugGlasses/DebugGlasses_Side.jpg', 'Side', 2),
(126, 43, 'assets/accessory/DebugGlasses/DebugGlasses_Zoom.jpg', 'Detail', 3),
(127, 44, 'assets/accessory/MainframeBeanie/MainframeBeanie_Front.jpg', 'Front', 1),
(128, 44, 'assets/accessory/MainframeBeanie/MainframeBeanie_Side.jpg', 'Side', 2),
(129, 44, 'assets/accessory/MainframeBeanie/MainframeBeanie_Zoom.jpg', 'Detail', 3),
(130, 45, 'assets/accessory/Patch-Set Embroidered Pin/front.jpg', 'Front', 1),
(131, 45, 'assets/accessory/Patch-Set Embroidered Pin/zoom again.jpg', 'Detail', 2),
(132, 45, 'assets/accessory/Patch-Set Embroidered Pin/zoom in.jpg', 'Detail', 3),
(133, 46, 'assets/accessory/Runtime Stainless Ring/zoom again.jpg', 'Detail', 1),
(134, 46, 'assets/accessory/Runtime Stainless Ring/zoom in.jpg', 'Detail', 2),
(135, 46, 'assets/accessory/Runtime Stainless Ring/zoom out.jpg', 'Detail', 3),
(136, 47, 'assets/accessory/SilverChain/SilverChain_Front.jpg', 'Front', 1),
(137, 47, 'assets/accessory/SilverChain/SilverChain_Zoom.jpg', 'Detail', 2),
(138, 47, 'assets/accessory/SilverChain/SilverChain_ZoomOut.jpg', 'Detail', 3),
(139, 48, 'assets/accessory/SyntaxBag/SyntaxBag_Back.jpg', 'Back', 1),
(140, 48, 'assets/accessory/SyntaxBag/SyntaxBag_Front.jpg', 'Front', 2),
(141, 48, 'assets/accessory/SyntaxBag/SyntaxBag_Zoom.jpg', 'Detail', 3),
(142, 49, 'assets/accessory/TerminalLanyard/TerminalLanyard_Front.jpg', 'Front', 1),
(143, 49, 'assets/accessory/TerminalLanyard/TerminalLanyard_Zoom.jpg', 'Detail', 2),
(144, 49, 'assets/accessory/TerminalLanyard/TerminalLanyard_ZoomOut.jpg', 'Detail', 3),
(45304, 1, 'assets/tshirt/CodeBreaker/CodeBreaker_Front.jpg', 'Front', 1),
(45305, 1, 'assets/tshirt/CodeBreaker/CodeBreaker_Side.jpg', 'Side', 2),
(45306, 1, 'assets/tshirt/CodeBreaker/CodeBreaker_Back.jpg', 'Back', 3),
(59827, 7115, 'uploads/products/c4f5e29766dbbe413218cc2e2453a397.jpg', 'Front', 1),
(59828, 7115, 'uploads/products/89ccf073c9dbbaf56aea4c23d7a28ab1.jpg', 'Side', 2),
(59829, 7115, 'uploads/products/50fbdba348da4d4d358aee68226c0faa.jpg', 'Back', 3),
(60094, 9, 'assets/tshirt/CloudSentinel/CloudSentinel_Front.jpg', 'Front', 1),
(60095, 9, 'assets/tshirt/CloudSentinel/CloudSentinel_3-4.jpg', 'Side', 2),
(60096, 9, 'assets/tshirt/CloudSentinel/CloudSentinel_Back.jpg', 'Back', 3),
(60625, 7296, 'uploads/products/d839a47f1775eae384eaee4ee3dafd8f.jpg', 'Front', 1),
(60626, 7296, 'uploads/products/a924bd603f3495c5c54e7b815fa956bc.jpg', 'Side', 2),
(60627, 7296, 'uploads/products/c5af0a9d88d0f964c62a0011a6da5776.jpg', 'Back', 3);

-- --------------------------------------------------------

--
-- Table structure for table `product_size_stock_alerts`
--

CREATE TABLE `product_size_stock_alerts` (
  `product_id` int(11) NOT NULL,
  `size` varchar(20) NOT NULL,
  `is_low_stock` tinyint(1) NOT NULL DEFAULT '0',
  `last_stock` int(11) NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `product_size_stock_alerts`
--

INSERT INTO `product_size_stock_alerts` (`product_id`, `size`, `is_low_stock`, `last_stock`, `updated_at`) VALUES
(1, 'L', 0, 11, '2026-11-12 05:48:42'),
(1, 'M', 0, 18, '2026-11-12 05:48:42'),
(1, 'S', 1, 1, '2026-11-12 03:55:29'),
(1, 'XL', 0, 8, '2026-11-12 03:55:00'),
(9, 'L', 0, 134, '2026-11-12 03:52:56'),
(9, 'M', 1, 0, '2026-11-12 05:55:17'),
(9, 'S', 0, 36, '2026-11-12 03:52:56'),
(9, 'XL', 0, 36, '2026-11-12 03:52:56'),
(7115, 'L', 0, 134, '2026-11-12 05:54:42'),
(7115, 'M', 0, 9, '2026-11-12 05:54:42'),
(7115, 'S', 0, 36, '2026-11-12 05:54:42'),
(7115, 'XL', 0, 36, '2026-11-12 05:54:42'),
(7296, 'XS', 1, 0, '2026-11-12 16:54:47');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `variant_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` varchar(20) NOT NULL DEFAULT 'One Size',
  `color` varchar(50) NOT NULL DEFAULT 'Default',
  `stock_quantity` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`variant_id`, `product_id`, `size`, `color`, `stock_quantity`, `is_active`) VALUES
(1, 1, 'S', 'Black', 1, 1),
(2, 1, 'M', 'Black', 18, 1),
(3, 1, 'L', 'Black', 11, 1),
(4, 1, 'XL', 'Black', 8, 1),
(5, 2, 'S', 'Black', 6, 1),
(6, 2, 'M', 'Black', 10, 1),
(7, 2, 'L', 'Black', 8, 1),
(8, 2, 'XL', 'Black', 5, 1),
(9, 3, 'M', 'Gray', 7, 1),
(10, 3, 'L', 'Gray', 9, 1),
(11, 3, 'XL', 'Gray', 4, 1),
(12, 4, 'One Size', 'Default', 25, 1),
(25, 9, 'S', 'Default', 36, 1),
(26, 9, 'M', 'Default', 0, 1),
(27, 9, 'L', 'Default', 134, 1),
(28, 9, 'XL', 'Default', 36, 1),
(29, 1, 'S', 'Default', 0, 0),
(30, 1, 'M', 'Default', 0, 0),
(31, 1, 'L', 'Default', 0, 0),
(32, 1, 'XL', 'Default', 0, 0),
(33, 11, 'S', 'Default', 0, 1),
(34, 11, 'M', 'Default', 0, 1),
(35, 11, 'L', 'Default', 0, 1),
(36, 11, 'XL', 'Default', 0, 1),
(37, 12, 'S', 'Default', 0, 1),
(38, 12, 'M', 'Default', 0, 1),
(39, 12, 'L', 'Default', 0, 1),
(40, 12, 'XL', 'Default', 0, 1),
(41, 13, 'S', 'Default', 0, 1),
(42, 13, 'M', 'Default', 0, 1),
(43, 13, 'L', 'Default', 0, 1),
(44, 13, 'XL', 'Default', 0, 1),
(45, 14, 'S', 'Default', 0, 1),
(46, 14, 'M', 'Default', 0, 1),
(47, 14, 'L', 'Default', 0, 1),
(48, 14, 'XL', 'Default', 0, 1),
(49, 15, 'S', 'Default', 0, 1),
(50, 15, 'M', 'Default', 0, 1),
(51, 15, 'L', 'Default', 0, 1),
(52, 15, 'XL', 'Default', 0, 1),
(53, 16, 'S', 'Default', 0, 1),
(54, 16, 'M', 'Default', 0, 1),
(55, 16, 'L', 'Default', 0, 1),
(56, 16, 'XL', 'Default', 0, 1),
(57, 17, 'S', 'Default', 0, 1),
(58, 17, 'M', 'Default', 0, 1),
(59, 17, 'L', 'Default', 0, 1),
(60, 17, 'XL', 'Default', 0, 1),
(61, 18, 'S', 'Default', 0, 1),
(62, 18, 'M', 'Default', 0, 1),
(63, 18, 'L', 'Default', 0, 1),
(64, 18, 'XL', 'Default', 0, 1),
(65, 19, 'S', 'Default', 0, 1),
(66, 19, 'M', 'Default', 0, 1),
(67, 19, 'L', 'Default', 0, 1),
(68, 19, 'XL', 'Default', 0, 1),
(69, 2, 'S', 'Default', 0, 1),
(70, 2, 'M', 'Default', 0, 1),
(71, 2, 'L', 'Default', 0, 1),
(72, 2, 'XL', 'Default', 0, 1),
(73, 21, 'S', 'Default', 0, 1),
(74, 21, 'M', 'Default', 0, 1),
(75, 21, 'L', 'Default', 0, 1),
(76, 21, 'XL', 'Default', 0, 1),
(77, 22, 'S', 'Default', 0, 1),
(78, 22, 'M', 'Default', 0, 1),
(79, 22, 'L', 'Default', 0, 1),
(80, 22, 'XL', 'Default', 0, 1),
(81, 23, 'S', 'Default', 0, 1),
(82, 23, 'M', 'Default', 0, 1),
(83, 23, 'L', 'Default', 0, 1),
(84, 23, 'XL', 'Default', 0, 1),
(85, 24, 'S', 'Default', 0, 1),
(86, 24, 'M', 'Default', 0, 1),
(87, 24, 'L', 'Default', 0, 1),
(88, 24, 'XL', 'Default', 0, 1),
(89, 25, 'S', 'Default', 0, 1),
(90, 25, 'M', 'Default', 0, 1),
(91, 25, 'L', 'Default', 0, 1),
(92, 25, 'XL', 'Default', 0, 1),
(93, 26, 'S', 'Default', 0, 1),
(94, 26, 'M', 'Default', 0, 1),
(95, 26, 'L', 'Default', 0, 1),
(96, 26, 'XL', 'Default', 0, 1),
(97, 27, 'S', 'Default', 0, 1),
(98, 27, 'M', 'Default', 0, 1),
(99, 27, 'L', 'Default', 0, 1),
(100, 27, 'XL', 'Default', 0, 1),
(101, 28, 'S', 'Default', 0, 1),
(102, 28, 'M', 'Default', 0, 1),
(103, 28, 'L', 'Default', 0, 1),
(104, 28, 'XL', 'Default', 0, 1),
(105, 29, 'S', 'Default', 0, 1),
(106, 29, 'M', 'Default', 0, 1),
(107, 29, 'L', 'Default', 0, 1),
(108, 29, 'XL', 'Default', 0, 1),
(109, 3, 'S', 'Default', 0, 1),
(110, 3, 'M', 'Default', 0, 1),
(111, 3, 'L', 'Default', 0, 1),
(112, 3, 'XL', 'Default', 0, 1),
(113, 31, 'S', 'Default', 0, 1),
(114, 31, 'M', 'Default', 0, 1),
(115, 31, 'L', 'Default', 0, 1),
(116, 31, 'XL', 'Default', 0, 1),
(117, 32, 'S', 'Default', 0, 1),
(118, 32, 'M', 'Default', 0, 1),
(119, 32, 'L', 'Default', 0, 1),
(120, 32, 'XL', 'Default', 0, 1),
(121, 33, 'S', 'Default', 0, 1),
(122, 33, 'M', 'Default', 0, 1),
(123, 33, 'L', 'Default', 0, 1),
(124, 33, 'XL', 'Default', 0, 1),
(125, 34, 'S', 'Default', 0, 1),
(126, 34, 'M', 'Default', 0, 1),
(127, 34, 'L', 'Default', 0, 1),
(128, 34, 'XL', 'Default', 0, 1),
(129, 35, 'S', 'Default', 0, 1),
(130, 35, 'M', 'Default', 0, 1),
(131, 35, 'L', 'Default', 0, 1),
(132, 35, 'XL', 'Default', 0, 1),
(133, 36, 'S', 'Default', 0, 1),
(134, 36, 'M', 'Default', 0, 1),
(135, 36, 'L', 'Default', 0, 1),
(136, 36, 'XL', 'Default', 0, 1),
(137, 37, 'S', 'Default', 0, 1),
(138, 37, 'M', 'Default', 0, 1),
(139, 37, 'L', 'Default', 0, 1),
(140, 37, 'XL', 'Default', 0, 1),
(141, 38, 'S', 'Default', 0, 1),
(142, 38, 'M', 'Default', 0, 1),
(143, 38, 'L', 'Default', 0, 1),
(144, 38, 'XL', 'Default', 0, 1),
(145, 39, 'S', 'Default', 0, 1),
(146, 39, 'M', 'Default', 0, 1),
(147, 39, 'L', 'Default', 0, 1),
(148, 39, 'XL', 'Default', 0, 1),
(150, 41, 'One Size', 'Default', 0, 1),
(151, 42, 'One Size', 'Default', 0, 1),
(152, 43, 'One Size', 'Default', 0, 1),
(153, 44, 'One Size', 'Default', 0, 1),
(154, 45, 'One Size', 'Default', 0, 1),
(155, 46, 'One Size', 'Default', 0, 1),
(156, 47, 'One Size', 'Default', 0, 1),
(157, 48, 'One Size', 'Default', 0, 1),
(158, 49, 'One Size', 'Default', 0, 1),
(23081, 7115, 'S', 'Default', 36, 1),
(23082, 7115, 'M', 'Default', 9, 1),
(23083, 7115, 'L', 'Default', 134, 1),
(23084, 7115, 'XL', 'Default', 36, 1),
(23669, 7296, 'XS', 'Default', 0, 1);

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
  `verification_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `id_document` varchar(64) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `middle_name`, `last_name`, `username`, `password`, `contact_no`, `street`, `barangay`, `city`, `province`, `postal`, `position`, `is_active`, `verification_status`, `id_document`, `avatar`) VALUES
(1, 'Lyra Janelle', 'Pilapil', 'Oleta', 'LyraOleta@Admin', '$2y$10$QucM5fABfxcTPpGQuDkps.lCH2aN4wYkHwwoqbaDsSw84Vy/3wgTS', '09658323208', 'Blk 2, Lt 22, Garden Homes', 'San Cristobal', 'Calamba', 'Laguna', '4027', 2, 1, 'Pending', NULL, NULL),
(2, 'Aaron', 'Espina', 'Dalago', 'aaron_it', '$2y$10$SYc9RCbWzd4xS9kahSyuduI1RgucxGh/P4TcpSXZYSMpGuQKn2kK2', '09675978682', 'Blk 36, Lot 24, Phase 2, Villa De Calamba', 'La Mesa', 'Calamba', 'Laguna', '4027', 1, 1, 'Pending', '7513b987cdb08c9773af1c9e77783739642992d483338690.png', NULL),
(3, 'Alejandro', 'Arsolacia', 'Albay', 'drew_commits', '$2y$10$gqiUPZgzPUziC0YwnXhEiuvoB2FwJKBFFY.9Ikm0Zl6JkQYBtak8O', '09934328772', 'Purok 3, Hibiscus St.', 'Canlubang', 'Calamba', 'Laguna', '4027', 1, 1, 'Pending', NULL, NULL),
(4, 'kayne1234', '', 'mingo@', 'kayne123@', '$2y$10$lmPGmkWA5J3F8DwA2XECQeaz119fWPMVkYO0wAOzx0uvxrMDWr5Ny', '12456789745852', 'fghjk', 'fghjk', 'ghjk', 'fghjk', 'dfghj', 1, 1, 'Pending', '6c28b22fffa333e82c4f18e5c820ec3b0b85a5ece041813e.jpg', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_categories_name` (`name`),
  ADD UNIQUE KEY `uq_categories_slug` (`slug`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_recipient_read_date` (`recipient_user_id`,`is_read`,`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `uq_orders_number` (`order_number`),
  ADD KEY `idx_orders_user_date` (`user_id`,`placed_at`),
  ADD KEY `idx_orders_status_date` (`status`,`placed_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`),
  ADD KEY `fk_order_items_variant` (`variant_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD KEY `idx_products_category_active` (`category_id`,`is_active`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD UNIQUE KEY `uq_product_image_path` (`product_id`,`image_url`(190)),
  ADD KEY `idx_product_images_product` (`product_id`);

--
-- Indexes for table `product_size_stock_alerts`
--
ALTER TABLE `product_size_stock_alerts`
  ADD PRIMARY KEY (`product_id`,`size`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`variant_id`),
  ADD UNIQUE KEY `uq_product_variant` (`product_id`,`size`,`color`);

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
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7297;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60628;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `variant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23670;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_size_stock_alerts`
--
ALTER TABLE `product_size_stock_alerts`
  ADD CONSTRAINT `fk_stock_alert_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
