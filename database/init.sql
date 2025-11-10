-- Database initialization script for DMG Klantportaal

-- Drop existing tables to ensure clean slate
DROP TABLE IF EXISTS cancellation_requests;
DROP TABLE IF EXISTS product_requests;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS ticket_messages;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS payment_preferences;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_types;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(255),
    address VARCHAR(255),
    postal_code VARCHAR(20),
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Nederland',
    phone VARCHAR(50),
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    active TINYINT(1) DEFAULT 1,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create product types table
CREATE TABLE product_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    default_duration_months INT DEFAULT 12,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_type_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    domain_name VARCHAR(255),
    registration_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    duration_months INT DEFAULT 12,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'expired', 'cancelled', 'pending') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_type_id) REFERENCES product_types(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment preferences table
CREATE TABLE payment_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    payment_method ENUM('invoice', 'direct_debit') NOT NULL DEFAULT 'invoice',
    iban VARCHAR(34),
    account_holder_name VARCHAR(255),
    mandate_date DATE,
    mandate_signature VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tickets table
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('new', 'in_progress', 'closed') NOT NULL DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create ticket messages table
CREATE TABLE ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_staff_reply TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create chat messages table
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_staff_reply TINYINT(1) DEFAULT 0,
    read_by_customer TINYINT(1) DEFAULT 0,
    read_by_staff TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create product requests table
CREATE TABLE product_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_type_id INT NOT NULL,
    requested_name VARCHAR(255) NOT NULL,
    requested_domain VARCHAR(255),
    additional_info TEXT,
    status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_type_id) REFERENCES product_types(id),
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create cancellation requests table
CREATE TABLE cancellation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_product_id (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default product types
INSERT INTO product_types (name, description, default_duration_months) VALUES
('Hosting', 'Webhosting service', 12),
('Domeinnaam', 'Domain name registration', 12),
('E-mailpakket', 'Email hosting package', 12),
('SLA Contract', 'Service Level Agreement', 12);

-- Insert default admin user (password: admin123 - MUST BE CHANGED IN PRODUCTION!)
INSERT INTO users (email, password, first_name, last_name, role) VALUES
('admin@dmg.nl', '$2y$10$GofcEn.3KMQ9p.mqYGqEGOezkn0GvRA.2Pk4eVTKchG/rv3MhRN7S', 'Admin', 'User', 'admin');

-- Insert demo customer user (password: customer123)
INSERT INTO users (email, password, first_name, last_name, company_name, address, postal_code, city, role) VALUES
('demo@example.com', '$2y$10$nZj0U6gQE/epkECbDeo2kOkjfYbeIn00M6R3vT.t/bTZgwIIR7Cnu', 'Demo', 'Customer', 'Demo Company', 'Demostraat 1', '1234 AB', 'Amsterdam', 'customer');

-- Insert demo products
INSERT INTO products (user_id, product_type_id, name, description, domain_name, registration_date, expiry_date, price, status) VALUES
(2, 1, 'Basis Hosting', 'Webhosting pakket basis', 'demo.nl', '2024-01-01', '2025-01-01', 99.99, 'active'),
(2, 2, 'demo.nl', 'Domeinnaam registratie', 'demo.nl', '2024-01-01', '2025-01-01', 14.99, 'active'),
(2, 3, 'E-mail Standaard', 'E-mail hosting 10 accounts', 'demo.nl', '2024-01-01', '2025-01-01', 49.99, 'active'),
(2, 4, 'SLA Bronze', 'Service Level Agreement Bronze', NULL, '2024-01-01', '2025-01-01', 199.99, 'active');

-- Additional data from klantportaal database (data rows only - no table structure changes)
SET FOREIGN_KEY_CHECKS=0;

INSERT INTO users (id, email, password, first_name, last_name, company_name, address, postal_code, city, country, phone, role, created_at, updated_at, last_login, active) VALUES
(1, 'halilhassan988@gmail.com', '$2y$10$GofcEn.3KMQ9p.mqYGqEGOezkn0GvRA.2Pk4eVTKchG/rv3MhRN7S', 'Admin', 'User', NULL, NULL, NULL, NULL, 'Nederland', NULL, 'admin', '2025-11-03 11:39:46', '2025-11-09 17:27:34', '2025-11-09 17:27:34', 1),
(5, 'alice@example.com', '$2y$10$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Alice', 'Customer', 'Alice Co', 'Straat 2', '1111 AA', 'Rotterdam', 'Nederland', NULL, 'customer', '2025-11-03 13:48:48', '2025-11-03 13:48:48', NULL, 1),
(6, 'bob@example.com', '$2y$10$bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'Bob', 'Customer', 'Bob BV', 'Straat 3', '2222 BB', 'Utrecht', 'Nederland', NULL, 'customer', '2025-11-03 13:48:48', '2025-11-03 13:48:48', NULL, 1),
(7, 'carol@example.com', '$2y$10$cccccccccccccccccccccccccccccccccccccccccccccc', 'Carol', 'Customer', 'Carol NV', 'Straat 4', '3333 CC', 'Eindhoven', 'Nederland', NULL, 'customer', '2025-11-03 13:48:48', '2025-11-03 13:48:48', NULL, 1),
(8, 'halilhasam@gmail.com', '$2y$10$ga5YeItfxnsdke8Z5pXIt.0Gn1nzvde7BhQo4OkZZY6XU/powdVti', 'Khalil', 'Hasan', '', 'gsads 14', '3213 MT', 'Utrecht', 'Nederland', '0638524948', 'customer', '2025-11-07 09:19:44', '2025-11-10 10:18:52', '2025-11-10 10:18:52', 1);

INSERT INTO product_types (id, name, description, default_duration_months, created_at) VALUES
(5, 'Hosting', 'Webhosting service', 12, '2025-11-03 13:37:55'),
(6, 'Domeinnaam', 'Domain name registration', 12, '2025-11-03 13:37:55'),
(7, 'E-mailpakket', 'Email hosting package', 12, '2025-11-03 13:37:55'),
(8, 'SLA Contract', 'Service Level Agreement', 12, '2025-11-03 13:37:55'),
(9, 'Hosting', 'Webhosting service', 12, '2025-11-03 13:38:16'),
(10, 'Domeinnaam', 'Domain name registration', 12, '2025-11-03 13:38:16'),
(11, 'E-mailpakket', 'Email hosting package', 12, '2025-11-03 13:38:16'),
(12, 'SLA Contract', 'Service Level Agreement', 12, '2025-11-03 13:38:16');

INSERT INTO products (id, user_id, product_type_id, name, description, domain_name, registration_date, expiry_date, duration_months, price, status, created_at, updated_at) VALUES
(6, 5, 1, 'Alice Hosting - Today', 'Test hosting expiring today', 'alice.example', '2025-11-03', '2025-11-03', 12, 49.00, 'active', '2025-11-03 13:48:48', '2025-11-03 13:48:48'),
(7, 5, 2, 'alice.nl', 'Domein expiring binnen 10 dagen', 'alice.nl', '2025-11-03', '2025-11-13', 12, 12.00, 'active', '2025-11-03 13:48:48', '2025-11-03 13:48:48'),
(8, 6, 1, 'Bob Hosting - 30d', 'Hosting expiring in 30 days', 'bob.example', '2025-11-03', '2025-12-03', 12, 79.00, 'active', '2025-11-03 13:48:48', '2025-11-03 13:48:48'),
(9, 6, 2, 'bob.nl', 'Domein expiring in 31 days', 'bob.nl', '2025-11-03', '2025-12-04', 12, 15.00, 'active', '2025-11-03 13:48:48', '2025-11-03 13:48:48'),
(10, 7, 3, 'Carol E-mail - 60d', 'E-mail pakket expiring in 60 days', 'carol.example', '2025-11-03', '2026-01-02', 12, 39.00, 'active', '2025-11-03 13:48:48', '2025-11-03 13:48:48'),
(11, 7, 4, 'Carol SLA - expired', 'SLA already expired', NULL, '2024-09-29', '2025-10-04', 12, 129.00, 'expired', '2025-11-03 13:48:48', '2025-11-03 13:48:48');

INSERT INTO payment_preferences (id, user_id, payment_method, iban, account_holder_name, mandate_date, mandate_signature, created_at, updated_at) VALUES
(1, 5, 'direct_debit', 'NL91ABNA0417164300', 'Alice Customer', '2025-11-03', 'sig_alice', '2025-11-03 13:48:48', '2025-11-03 13:48:48'),
(2, 6, 'invoice', NULL, NULL, NULL, NULL, '2025-11-03 13:48:48', '2025-11-03 13:48:48');

INSERT INTO product_requests (id, user_id, product_type_id, requested_name, requested_domain, additional_info, status, created_at, processed_at, processed_by) VALUES
(1, 5, 2, 'alice.nl', 'alice.nl', 'Graag registratie voor alice.nl', 'pending', '2025-11-03 13:48:48', NULL, NULL),
(2, 7, 1, 'Carol Hosting X', 'carol.example', 'Extra resources benodigd', 'approved', '2025-09-04 13:48:48', '2025-09-05 13:48:48', 1),
(3, 8, 4, 'SLA Contract', '', '123456', 'pending', '2025-11-07 09:20:56', NULL, NULL);

INSERT INTO tickets (id, user_id, subject, status, priority, created_at, updated_at, closed_at) VALUES
(1, 5, 'Login probleem', 'closed', 'high', '2025-11-03 13:48:48', '2025-11-07 11:00:27', '2025-11-07 11:00:27'),
(2, 6, 'Vervanging SSL', 'in_progress', 'medium', '2025-10-21 13:48:48', '2025-11-07 09:08:46', NULL),
(3, 7, 'Factuur vraag', 'closed', 'low', '2025-09-14 13:48:48', '2025-09-19 13:48:48', '2025-09-20 13:48:48'),
(4, 8, 'sla', 'in_progress', 'medium', '2025-11-07 09:21:40', '2025-11-07 09:22:20', NULL),
(5, 8, 'ewreewer', 'in_progress', 'medium', '2025-11-07 10:34:24', '2025-11-07 10:35:24', NULL),
(6, 8, 'alise', 'new', 'medium', '2025-11-09 16:46:38', '2025-11-09 16:46:38', NULL),
(7, 8, 'alise', 'in_progress', 'medium', '2025-11-09 16:46:42', '2025-11-09 17:27:44', NULL),
(8, 8, 'dd', 'in_progress', 'medium', '2025-11-09 16:47:15', '2025-11-09 17:12:38', NULL),
(9, 8, 'alise', 'closed', 'medium', '2025-11-09 16:47:36', '2025-11-09 16:52:59', '2025-11-09 16:52:59'),
(10, 8, 'alise alise', 'in_progress', 'medium', '2025-11-09 17:17:39', '2025-11-09 17:22:37', NULL),
(11, 8, 'xss', 'new', 'medium', '2025-11-09 17:30:24', '2025-11-09 17:30:24', NULL),
(12, 8, 'alise', 'new', 'medium', '2025-11-10 10:03:25', '2025-11-10 10:03:25', NULL),
(13, 8, 'alise tristes', 'new', 'medium', '2025-11-10 10:04:59', '2025-11-10 10:04:59', NULL),
(14, 8, 'alise tristes', 'new', 'medium', '2025-11-10 10:14:35', '2025-11-10 10:14:35', NULL),
(15, 8, 'alise tristes', 'new', 'medium', '2025-11-10 10:17:05', '2025-11-10 10:17:05', NULL),
(16, 8, 'alsie', 'new', 'medium', '2025-11-10 10:19:11', '2025-11-10 10:19:11', NULL),
(17, 8, 'alsie', 'new', 'medium', '2025-11-10 10:21:41', '2025-11-10 10:21:41', NULL);

INSERT INTO ticket_messages (id, ticket_id, user_id, message, is_staff_reply, created_at) VALUES
(1, 1, 5, 'Ik kan niet inloggen sinds gisteren.', 0, '2025-11-03 11:48:48'),
(2, 1, 1, 'We onderzoeken het probleem en nemen contact op.', 1, '2025-11-03 12:48:48'),
(3, 2, 6, 'Kunnen we het SSL-certificaat verlengen voor volgende maand?', 0, '2025-10-22 13:48:48'),
(4, 3, 7, 'Ik heb een vraag over de factuur, klopt het bedrag?', 0, '2025-09-14 13:48:48'),
(5, 3, 1, 'De factuur is bijgewerkt en opnieuw verzonden.', 1, '2025-09-20 13:48:48'),
(6, 2, 1, 'yuy', 1, '2025-11-07 09:08:54'),
(7, 4, 8, 'hi hoelang duurt totdat mijn aanvraag goedgekeurd wordt', 0, '2025-11-07 09:21:40'),
(8, 4, 1, 'hi het is goedgekeurd', 1, '2025-11-07 09:22:36'),
(9, 4, 1, 'loihgfhjkl', 1, '2025-11-07 09:23:57'),
(10, 4, 1, 'hi', 1, '2025-11-07 09:51:55'),
(11, 4, 1, 'hi', 1, '2025-11-07 09:53:13'),
(12, 4, 1, 'dsdsdfs', 1, '2025-11-07 09:53:17'),
(13, 4, 1, 'test test test', 1, '2025-11-07 09:54:57'),
(14, 4, 1, 'dftygu', 1, '2025-11-07 10:13:32'),
(15, 4, 1, 'dsgfhhreq', 1, '2025-11-07 10:19:00'),
(16, 4, 1, 'dsgfhhreq', 1, '2025-11-07 10:26:16'),
(17, 4, 1, 'wsgffgewefdgfdwq', 1, '2025-11-07 10:26:19'),
(18, 4, 1, 'wsgffgewefdgfdwq', 1, '2025-11-07 10:26:32'),
(19, 4, 1, 'wsgffgewefdgfdwq', 1, '2025-11-07 10:26:37'),
(20, 4, 1, 'wsgffgewefdgfdwq', 1, '2025-11-07 10:26:50'),
(21, 4, 1, 'wsgffgewefdgfdwq', 1, '2025-11-07 10:28:45'),
(22, 4, 1, 'wewdgrewqr', 1, '2025-11-07 10:28:47'),
(23, 4, 1, 'wewdgrewqr', 1, '2025-11-07 10:33:59'),
(24, 5, 8, 'eweretrrgfrertgh', 0, '2025-11-07 10:34:24'),
(25, 5, 1, 'dasdasdasdasdasdasdadasdasd', 1, '2025-11-07 10:35:24'),
(26, 5, 1, 'sadhgjhfdesf', 1, '2025-11-07 11:00:37'),
(27, 5, 1, 'sadhgjhfdesf', 1, '2025-11-07 11:03:07'),
(28, 5, 1, 'dsdfdsdfsd', 1, '2025-11-07 11:03:38'),
(29, 5, 1, 'sadhgjhfdesf', 1, '2025-11-07 11:05:38'),
(30, 5, 1, 'dsdfdsdfsd', 1, '2025-11-07 11:06:08'),
(31, 5, 1, 'sadhgjhfdesf', 1, '2025-11-07 11:08:08'),
(32, 5, 1, 'dsdfdsdfsd', 1, '2025-11-07 11:08:39'),
(33, 5, 1, 'sadhgjhfdesf', 1, '2025-11-07 11:10:38'),
(34, 5, 1, 'sadhgjhfdesf', 1, '2025-11-07 11:13:09'),
(35, 5, 1, 'sadhgjhfdesf', 1, '2025-11-07 11:22:43'),
(36, 5, 1, 'aaaaa', 1, '2025-11-07 11:25:37'),
(37, 5, 1, 'aaaaa', 1, '2025-11-07 11:28:08'),
(38, 5, 1, 'aaaaa', 1, '2025-11-07 11:30:38'),
(39, 5, 1, 'aaaaa', 1, '2025-11-07 11:33:08'),
(40, 5, 1, 'aaaaa', 1, '2025-11-07 11:35:39'),
(41, 5, 1, 'aaaaa', 1, '2025-11-07 11:38:10'),
(42, 5, 1, 'aaaaa', 1, '2025-11-07 11:40:40'),
(43, 5, 1, 'sasdasads', 1, '2025-11-07 11:42:27'),
(44, 5, 1, 'ad', 1, '2025-11-07 11:43:31'),
(45, 5, 1, 'sasdaaasasda', 1, '2025-11-07 11:53:38'),
(46, 5, 1, 'assa', 1, '2025-11-07 11:57:22'),
(47, 4, 1, 'test test', 1, '2025-11-07 12:10:18'),
(48, 5, 1, 'sdfsd', 1, '2025-11-07 12:20:53'),
(49, 5, 1, 'fdgfhghjjk', 1, '2025-11-07 12:22:59'),
(50, 5, 1, 'dadsadasdadadasdasdada', 1, '2025-11-07 12:25:22'),
(51, 5, 1, 'hhvgsjiokdsfljbhhsdnksjbcnkjfnbvkjn ksdjcnkxcvnxcjkvnxcvxvxvxcvxcvcxv', 1, '2025-11-07 12:46:24'),
(52, 5, 1, 'sdadadasdasdasdasdasda', 1, '2025-11-07 13:02:02'),
(53, 5, 1, 'sdadadasdasdasdasdasda', 1, '2025-11-07 13:04:32'),
(54, 5, 1, 'sdadadasdasdasdasdasda', 1, '2025-11-07 13:07:02'),
(55, 5, 1, 'test', 1, '2025-11-09 16:20:00'),
(56, 5, 1, 'test', 1, '2025-11-09 16:30:04'),
(57, 5, 1, 'grgsrgsdgsfgsfgsgsfg', 1, '2025-11-09 16:35:20'),
(58, 6, 8, 'Honestly, tell me what\'s wrong. I want to live in peace.\r\nWhere did you get these words from? Why are you acting wierd?', 0, '2025-11-09 16:46:38'),
(59, 7, 8, 'Honestly, tell me what\'s wrong. I want to live in peace.\r\nWhere did you get these words from? Why are you acting wierd?', 0, '2025-11-09 16:46:42'),
(60, 8, 8, 'test', 0, '2025-11-09 16:47:15'),
(61, 9, 8, 'Honestly, tell me what\'s wrong. I want to live in peace.\r\nWhere did you get these words from? Why are you acting wierd?', 0, '2025-11-09 16:47:36'),
(62, 9, 1, 'It seems I\'m still in love. but I\'m leaving, there\'s no need to stay.\r\nand I will rise again, I\'ll walk away and look ahead.', 1, '2025-11-09 16:50:50'),
(63, 8, 1, 'test', 1, '2025-11-09 17:12:37'),
(64, 10, 8, 'yumainn o leila', 0, '2025-11-09 17:17:39'),
(65, 10, 1, 'eeaaaaah she said, \"forgive me.\" Not yours, my eyes. in a last hug, but I didn\'t feel it.\r\nI said i forgive you but I wasn\'t mean it, and the fire in my heart will burn a city', 1, '2025-11-09 17:22:36'),
(66, 10, 8, 'they said sleep wakeup you will forget. or count her ******** you will hate her.\r\nbut even her ******** makes her beuaty.', 0, '2025-11-09 17:24:43'),
(67, 10, 8, 'bb', 0, '2025-11-09 17:25:31'),
(68, 10, 8, 'czvb vbcx', 0, '2025-11-09 17:27:11'),
(69, 7, 1, 'dsfdgbgdsasdf', 1, '2025-11-09 17:27:43'),
(70, 10, 8, 'qwdcv vcfdsa', 0, '2025-11-09 17:28:58'),
(71, 11, 8, 'ss', 0, '2025-11-09 17:30:24'),
(72, 12, 8, 'El muchacho de los ojos tristes\r\nVive solo y necesita amor', 0, '2025-11-10 10:03:25'),
(73, 13, 8, 'El muchacho de los ojos tristes\r\nVive solo y necesita amor', 0, '2025-11-10 10:04:59'),
(74, 14, 8, 'El muchacho de los ojos tristes\r\nVive solo y necesita amor', 0, '2025-11-10 10:14:35'),
(75, 15, 8, 'El muchacho de los ojos tristes\r\nVive solo y necesita amor', 0, '2025-11-10 10:17:05'),
(76, 16, 8, 'El muchacho de los ojos tristes\r\nVive solo y necesita amor', 0, '2025-11-10 10:19:11'),
(77, 17, 8, 'El muchacho de los ojos tristes\r\nVive solo y necesita amor', 0, '2025-11-10 10:21:41');

INSERT INTO chat_messages (id, user_id, message, is_staff_reply, read_by_customer, read_by_staff, created_at) VALUES
(1, 5, 'Hallo, ik heb hulp nodig bij instellingen.', 0, 0, 0, '2025-11-02 13:48:48'),
(2, 1, 'We helpen u nu. Heeft u het probleem nog?', 1, 1, 0, '2025-11-02 13:49:48');

INSERT INTO cancellation_requests (id, user_id, product_id, reason, status, created_at, processed_at, processed_by) VALUES
(1, 7, 9, 'Stop service per eind van maand', 'pending', '2025-11-03 13:48:48', NULL, NULL),
(2, 6, 6, 'Geen gebruik meer', 'approved', '2025-10-04 13:48:48', '2025-10-06 13:48:48', 1);

SET FOREIGN_KEY_CHECKS=1;