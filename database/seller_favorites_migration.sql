USE book_exchange;

ALTER TABLE books
 MODIFY COLUMN status ENUM('Available','Unavailable','Borrowed','Sold','Donated','Exchanged') NOT NULL DEFAULT 'Available';

CREATE TABLE IF NOT EXISTS favorites (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 book_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_user_book (user_id, book_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE
);