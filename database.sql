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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO products (id, name, price, color, category, image_primary, images_json, description) VALUES
('umbrella-red', 'Classic Red Auto Umbrella', 24000, 'Red', 'Travel Essential', 'assets/red-product-clean.png', '["assets/red-product-clean.png","assets/pink.jpg"]', 'A compact automatic umbrella with windproof protection and a polished red finish for everyday carry.'),
('umbrella-green', 'Green Windproof Umbrella', 24000, 'Green', 'Travel Essential', 'assets/green.jpg', '["assets/green.jpg","assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg"]', 'A lightweight umbrella built for rain-ready protection and easy storage.'),
('umbrella-blue', 'Blue Compact Auto Umbrella', 24000, 'Blue', 'Travel Essential', 'assets/blue.jpg', '["assets/blue.jpg","assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg","assets/tfgh.png"]', 'A stylish compact umbrella with automatic open and close convenience.'),
('umbrella-pink', 'Pink Compact Umbrella', 24000, 'Pink', 'Travel Essential', 'assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg', '["assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg"]', 'A bright, practical umbrella with a soft finish and travel-friendly format.')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  price = VALUES(price),
  color = VALUES(color),
  category = VALUES(category),
  image_primary = VALUES(image_primary),
  images_json = VALUES(images_json),
  description = VALUES(description);

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
