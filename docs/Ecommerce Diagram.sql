CREATE TABLE `users` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(100) UNIQUE NOT NULL,
  `email` varchar(255) UNIQUE NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100),
  `last_name` varchar(100),
  `phone` varchar(30),
  `status` ENUM ('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp
);

CREATE TABLE `roles` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) UNIQUE NOT NULL,
  `description` varchar(255)
);

CREATE TABLE `user_roles` (
  `user_id` bigint NOT NULL,
  `role_id` bigint NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`)
);

CREATE TABLE `permissions` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(150) UNIQUE NOT NULL,
  `description` varchar(255)
);

CREATE TABLE `role_permissions` (
  `role_id` bigint NOT NULL,
  `permission_id` bigint NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`)
);

CREATE TABLE `categories` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `parent_id` bigint,
  `name_vi` varchar(255) NOT NULL,
  `name_en` varchar(255) NOT NULL,
  `slug` varchar(255) UNIQUE NOT NULL,
  `image_url` varchar(1024),
  `sort_order` int NOT NULL DEFAULT 0,
  `status` boolean NOT NULL DEFAULT true,
  `created_at` timestamp,
  `updated_at` timestamp
);

CREATE TABLE `brands` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name_vi` varchar(255) NOT NULL,
  `name_en` varchar(255) NOT NULL,
  `slug` varchar(255) UNIQUE NOT NULL,
  `image_url` varchar(1024),
  `status` boolean NOT NULL DEFAULT true,
  `created_at` timestamp,
  `updated_at` timestamp
);

CREATE TABLE `products` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `category_id` bigint NOT NULL,
  `brand_id` bigint,
  `sku` varchar(100) UNIQUE NOT NULL,
  `name_vi` varchar(255) NOT NULL,
  `name_en` varchar(255) NOT NULL,
  `slug` varchar(255) UNIQUE NOT NULL,
  `description_vi` text,
  `description_en` text,
  `price` decimal(18,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT 0,
  `is_featured` boolean NOT NULL DEFAULT false,
  `status` boolean NOT NULL DEFAULT true,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp
);

CREATE TABLE `product_images` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `product_id` bigint NOT NULL,
  `image_url` varchar(1024) NOT NULL,
  `alt_text` varchar(255),
  `sort_order` int NOT NULL DEFAULT 0,
  `is_primary` boolean NOT NULL DEFAULT false,
  `created_at` timestamp
);

CREATE TABLE `carts` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint,
  `session_token` varchar(255) UNIQUE,
  `created_at` timestamp,
  `updated_at` timestamp
);

CREATE TABLE `cart_items` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `cart_id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `quantity` int NOT NULL DEFAULT 1,
  `created_at` timestamp,
  `updated_at` timestamp
);

CREATE TABLE `wishlists` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `created_at` timestamp
);

CREATE TABLE `orders` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `order_code` varchar(50) UNIQUE NOT NULL,
  `user_id` bigint,
  `status` ENUM ('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` ENUM ('cod', 'bank_transfer', 'card', 'e_wallet') NOT NULL DEFAULT 'cod',
  `payment_status` ENUM ('unpaid', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',
  `subtotal` decimal(18,2) NOT NULL,
  `shipping_fee` decimal(18,2) NOT NULL DEFAULT 0,
  `total_amount` decimal(18,2) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `recipient_phone` varchar(30) NOT NULL,
  `shipping_address` text NOT NULL,
  `note` text,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp
);

CREATE TABLE `order_items` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `order_id` bigint NOT NULL,
  `product_id` bigint,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(100),
  `quantity` int NOT NULL,
  `price` decimal(18,2) NOT NULL,
  `total_price` decimal(18,2) NOT NULL
);

CREATE TABLE `contacts` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `first_name` varchar(100),
  `last_name` varchar(100),
  `email` varchar(255),
  `phone` varchar(30),
  `reason` varchar(255),
  `message` text NOT NULL,
  `status` ENUM ('new', 'processing', 'resolved', 'closed') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp
);

CREATE TABLE `faqs` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `question_vi` varchar(500) NOT NULL,
  `question_en` varchar(500) NOT NULL,
  `answer_vi` text NOT NULL,
  `answer_en` text NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `status` boolean NOT NULL DEFAULT true,
  `created_at` timestamp,
  `updated_at` timestamp
);

CREATE TABLE `menus` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `parent_id` bigint,
  `name_vi` varchar(255) NOT NULL,
  `name_en` varchar(255) NOT NULL,
  `url` varchar(1024),
  `location` varchar(50) NOT NULL DEFAULT 'header',
  `sort_order` int NOT NULL DEFAULT 0,
  `status` boolean NOT NULL DEFAULT true
);

CREATE TABLE `settings` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `setting_key` varchar(150) UNIQUE NOT NULL,
  `setting_value` text,
  `updated_by` bigint,
  `updated_at` timestamp
);

CREATE UNIQUE INDEX `cart_items_index_0` ON `cart_items` (`cart_id`, `product_id`);

CREATE UNIQUE INDEX `wishlists_index_1` ON `wishlists` (`user_id`, `product_id`);

ALTER TABLE `user_roles` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_roles` ADD FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `role_permissions` ADD FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `role_permissions` ADD FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

ALTER TABLE `categories` ADD FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

ALTER TABLE `products` ADD FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT;

ALTER TABLE `products` ADD FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;

ALTER TABLE `product_images` ADD FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `carts` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `cart_items` ADD FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE;

ALTER TABLE `cart_items` ADD FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;

ALTER TABLE `wishlists` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `wishlists` ADD FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `orders` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `order_items` ADD FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

ALTER TABLE `order_items` ADD FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

ALTER TABLE `menus` ADD FOREIGN KEY (`parent_id`) REFERENCES `menus` (`id`) ON DELETE SET NULL;

ALTER TABLE `settings` ADD FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
