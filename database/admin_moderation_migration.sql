USE book_exchange;

ALTER TABLE users
 ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user';

ALTER TABLE books
 ADD COLUMN moderation_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved';

ALTER TABLE books
 ALTER COLUMN moderation_status SET DEFAULT 'Pending';