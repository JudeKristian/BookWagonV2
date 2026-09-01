-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS bookwagon_db;
USE bookwagon_db;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create books table
CREATE TABLE IF NOT EXISTS books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    description TEXT,
    `condition` ENUM('New', 'Like New', 'Good', 'Fair') NOT NULL,
    image_path VARCHAR(255),
    status ENUM('available', 'swapped', 'removed') DEFAULT 'available',
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Create book_swaps table
CREATE TABLE IF NOT EXISTS book_swaps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    requested_book_id INT NOT NULL,
    offered_book_id INT NOT NULL,
    requester_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_book_id) REFERENCES books(id),
    FOREIGN KEY (offered_book_id) REFERENCES books(id),
    FOREIGN KEY (requester_id) REFERENCES users(id)
); 