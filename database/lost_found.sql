-- =================================================================
-- School Lost & Found Portal - Database Schema Script
-- Designed for beginners / intermediate university students
-- Suitable for importing into phpMyAdmin / MySQL (XAMPP)
-- =================================================================

-- 1. Create and select the database
CREATE DATABASE IF NOT EXISTS `school_lost_found_portal`;
USE `school_lost_found_portal`;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    student_staff_id VARCHAR(30) UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO users
(full_name, student_staff_id, email, phone, password, role)VALUES
('System Administrator','ADMIN001','admin@school.com','0240000000', '$2y$10$examplehash','admin'),
('Abdul Rahman','ITE22001','abdul@example.com','0241111111','$2y$10$examplehash','user'),
('Mary Mensah','ITE22002','mary@example.com','0242222222', '$2y$10$examplehash','user');


CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories
(category_name, description)VALUES
('Phone','Mobile phones'),
('Laptop','Laptop computers'),
('Wallet','Wallets'),
('Keys','House and vehicle keys'),
('Bag','School bags'),
('Books','Books and notebooks'),
('ID Card','Student or staff IDs'),
('Electronics','Electronic devices'),
('Clothing','Clothing and accessories'),
('Others','Miscellaneous items');

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    item_type ENUM('Lost','Found') NOT NULL,
    color VARCHAR(50),
    brand VARCHAR(100),
    location VARCHAR(150) NOT NULL,
    date_lost_found DATE NOT NULL,
    image VARCHAR(255) DEFAULT 'default-item.png',
    status ENUM('Open','Claimed','Returned') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);


INSERT INTO items
(user_id, category_id, title, description, item_type, color, brand, location, date_lost_found, image) VALUES
(2,1,'Black Samsung Phone','Lost Samsung Galaxy A34','Lost','Black','Samsung','ICT Block','2026-07-10','phone1.jpg'),
(3,4,'Car Keys','Found car keys near the library','Found','Silver','Toyota','University Library','2026-07-15','keys.jpg');


CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    item_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_receiver FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_item FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
);

INSERT INTO messages (sender_id, receiver_id, item_id, message)VALUES
(3,2,1,'I think I found your phone. Can you describe the lock screen?'),
(2,3,2,'Those keys belong to me. They have a blue key holder.');

CREATE TABLE claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    claimant_id INT NOT NULL,
    claim_message TEXT NOT NULL,
    owner_response ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_claim_item FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_claim_user FOREIGN KEY(claimant_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO claims (item_id, claimant_id, claim_message)VALUES
(2,2,'These are my car keys. The key holder is blue with a small Ghana flag attached.');



CREATE INDEX idx_items_type ON items(item_type);
CREATE INDEX idx_items_status ON items(status);
CREATE INDEX idx_items_location ON items(location);
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_items_user ON items(user_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_receiver ON messages(receiver_id);
CREATE INDEX idx_claims_item ON claims(item_id);
