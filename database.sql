CREATE DATABASE IF NOT EXISTS extra
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE extra;

CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(50) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  price INT UNSIGNED NOT NULL,
  color VARCHAR(50) NOT NULL,
  category VARCHAR(100) NOT NULL,
  image_primary VARCHAR(255) NOT NULL,
  images_json LONGTEXT NULL,
  description TEXT NOT NULL,
  storefront VARCHAR(20) NOT NULL DEFAULT 'extra',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO products (id, name, price, color, category, image_primary, images_json, description, storefront) VALUES
('umbrella-red', 'Classic Red Auto Umbrella', 24000, 'Red', 'Travel Essential', 'assets/red-product-clean.png', '["assets/red-product-clean.png","assets/pink.jpg"]', 'A compact automatic umbrella with windproof protection and a polished red finish for everyday carry.', 'extra'),
('umbrella-green', 'Green Windproof Umbrella', 24000, 'Green', 'Travel Essential', 'assets/green.jpg', '["assets/green.jpg","assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg"]', 'A lightweight umbrella built for rain-ready protection and easy storage.', 'extra'),
('umbrella-blue', 'Blue Compact Auto Umbrella', 24000, 'Blue', 'Travel Essential', 'assets/blue.jpg', '["assets/blue.jpg","assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg","assets/tfgh.png"]', 'A stylish compact umbrella with automatic open and close convenience.', 'extra'),
('umbrella-pink', 'Pink Compact Umbrella', 24000, 'Pink', 'Travel Essential', 'assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg', '["assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg"]', 'A bright, practical umbrella with a soft finish and travel-friendly format.', 'extra'),
('solar-clip-lamp', 'Solar Rechargeable Clip-On Desk Lamp', 19500, 'White', 'Reading & Study', 'assets/imgi_1_1.jpg', '["assets/imgi_1_1.jpg","assets/imgi_4_4.jpg","assets/imgi_5_5.jpeg"]', 'A rechargeable clip-on desk lamp with adjustable neck, solar charging, and soft eye-friendly lighting.', 'light'),
('iron-clip-lamp', 'Compact Rechargeable Handheld Iron Portable Cordless Mini Iron Wireless Home Appliance Digital Display, Orange', 3707, 'Orange', 'Home Appliance', 'assets/irons.png', '["assets/irons.png","assets/imgi_168_8f55bbf1-d051-4f33-88f1-d9eadb78fbc5.jpg","assets/imgi_170_989c29a9-4407-4832-bcd1-81220c90b9be.jpeg","assets/imgi_197_55e6259d-a021-42e5-9a92-f38f920b8246.jpg","assets/imgi_21_46dd3813-eeab-4189-b5fa-aec62eb53fb4.jpg"]', 'Compact rechargeable handheld iron with a digital display, cordless convenience, and a travel-ready orange finish.', 'iron')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  price = VALUES(price),
  color = VALUES(color),
  category = VALUES(category),
  image_primary = VALUES(image_primary),
  images_json = VALUES(images_json),
  description = VALUES(description),
  storefront = VALUES(storefront);

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(20) NOT NULL UNIQUE,
  product_id VARCHAR(50) NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  product_image VARCHAR(255) NOT NULL,
  product_description TEXT NULL,
  product_category VARCHAR(100) NOT NULL,
  qty INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price INT UNSIGNED NOT NULL,
  total_price INT UNSIGNED NOT NULL,
  customer_name VARCHAR(255) NOT NULL,
  first_name VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  email VARCHAR(255) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NOT NULL,
  order_note TEXT NULL,
  receipt_original_name VARCHAR(255) NOT NULL,
  receipt_path VARCHAR(255) NOT NULL,
  status ENUM('pending','processing','paid','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_orders_product_id (product_id),
  INDEX idx_orders_email (email),
  CONSTRAINT fk_orders_product_id
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
