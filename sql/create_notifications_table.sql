-- Create notifications table for BookWagon application
-- This table stores all user notifications, particularly for book buddy requests

-- Check if the table already exists and drop it if it does
DROP TABLE IF EXISTS `notifications`;

-- Create the notifications table
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `sender_id` INT(11) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `content` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notification_user_idx` (`user_id`),
  KEY `notification_sender_idx` (`sender_id`),
  KEY `notification_read_status_idx` (`user_id`, `is_read`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notification_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create book_buddies table if it doesn't exist yet
DROP TABLE IF EXISTS `book_buddies`;

CREATE TABLE `book_buddies` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `follower_id` INT(11) NOT NULL,
  `following_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_relationship` (`follower_id`, `following_id`),
  KEY `follower_idx` (`follower_id`),
  KEY `following_idx` (`following_id`),
  KEY `status_idx` (`status`),
  CONSTRAINT `fk_buddy_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_buddy_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add sample notification data (optional - comment out if not needed)
-- INSERT INTO `notifications` (`user_id`, `sender_id`, `type`, `content`) VALUES 
-- (2, 1, 'buddy_request', 'John Doe has sent you a book buddy request'),
-- (1, 3, 'buddy_request', 'Jane Smith has sent you a book buddy request'),
-- (3, 1, 'buddy_accepted', 'John Doe has accepted your book buddy request'); 