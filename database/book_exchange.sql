CREATE DATABASE IF NOT EXISTS book_exchange CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE book_exchange;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
<<<<<<< HEAD
 role ENUM('user','admin') NOT NULL DEFAULT 'user',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE books (
 id INT AUTO_INCREMENT PRIMARY KEY,
 owner_id INT NOT NULL,
 title VARCHAR(200) NOT NULL,
 author VARCHAR(150) NOT NULL,
 description TEXT,
 status ENUM('Available','Borrowed') DEFAULT 'Available',
 moderation_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE borrow_requests (
 id INT AUTO_INCREMENT PRIMARY KEY,
 book_id INT NOT NULL,
 requester_id INT NOT NULL,
 message TEXT,
 status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE,
 FOREIGN KEY(requester_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users(name,email,password) VALUES
('Demo User','demo@bookbridge.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7z3GJw2L7f4XvG8jGm');
INSERT INTO books(owner_id,title,author,description,status,moderation_status) VALUES
(1,'Introduction to Programming','John Smith','A beginner-friendly programming book for students.','Available','Approved'),
(1,'Database Systems','Maria Santos','Learn database concepts, SQL, and data modeling.','Available','Approved'),
(1,'Web Development Basics','Alex Cruz','HTML, CSS, PHP, and practical web development fundamentals.','Available','Approved');
CREATE DATABASE IF NOT EXISTS book_exchange CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE book_exchange;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
=======
>>>>>>> a767c97596d3adbc1f69c6ea40c8d80ee54424f0
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE books (
 id INT AUTO_INCREMENT PRIMARY KEY,
 owner_id INT NOT NULL,
 title VARCHAR(200) NOT NULL,
 author VARCHAR(150) NOT NULL,
 description TEXT,
 status ENUM('Available','Borrowed') DEFAULT 'Available',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE borrow_requests (
 id INT AUTO_INCREMENT PRIMARY KEY,
 book_id INT NOT NULL,
 requester_id INT NOT NULL,
 message TEXT,
 status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE,
 FOREIGN KEY(requester_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users(name,email,password) VALUES
('Demo User','demo@bookbridge.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC7z3GJw2L7f4XvG8jGm');
INSERT INTO books(owner_id,title,author,description,status) VALUES
(1,'Introduction to Programming','John Smith','A beginner-friendly programming book for students.','Available'),
(1,'Database Systems','Maria Santos','Learn database concepts, SQL, and data modeling.','Available'),
(1,'Web Development Basics','Alex Cruz','HTML, CSS, PHP, and practical web development fundamentals.','Available');
