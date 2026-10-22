BOOKBRIDGE - ONLINE BOOK BORROWING & EXCHANGE PORTAL

REQUIREMENTS
- XAMPP (Apache + MySQL)
- PHP 7.4+ / 8.x
- A web browser

INSTALLATION
1. Install/open XAMPP.
2. Start Apache and MySQL.
3. Copy the "book_exchange_portal" folder into:
   C:\xampp\htdocs\
4. Open:
   http://localhost/phpmyadmin
5. Click Import.
6. Select:
   C:\xampp\htdocs\book_exchange_portal\database\book_exchange.sql
7. Click Go.
8. Open:
   http://localhost/book_exchange_portal/

DATABASE
Database name: book_exchange
Username: root
Password: blank (default XAMPP setup)

FEATURES
- User registration and login
- Browse/search books
- Post books
- Book details
- Borrow requests
- Approve/reject requests
- User profile
- My posted books
- My borrow requests
- Book cover uploads with preview and validated JPG/JPEG/PNG/WEBP files
- Book condition ratings and owner condition descriptions
- Admin listing review with duplicate warnings
- MySQL database connection

BOOK IMAGES AND CONDITION DETAILS
- Fresh installation: database/book_exchange.sql creates the image and condition columns. You may also import database/book_image_condition_migration.sql; it safely skips columns that already exist.
- Existing installation: select the book_exchange database in phpMyAdmin and import database/book_image_condition_migration.sql. Do not re-import book_exchange.sql into an existing installation; the migration adds only missing columns and preserves existing rows.
- The migration checks each column separately and can be rerun safely.
- Back up the database before running the migration. Existing listings keep empty image and condition fields until their owners edit them.
- Store images under uploads/books/. Apache must be allowed to read the .htaccess file and the PHP/Apache account must be able to write to this directory. Do not allow script execution in uploads.
- The application accepts JPG/JPEG, PNG, and WEBP images up to 5 MB. If PHP's limits are lower, set upload_max_filesize to at least 6M and post_max_size to at least 8M in C:\xampp\php\php.ini, then restart Apache.
- Owners can replace an image and update condition details from Profile > Edit Listing. Replacing or deleting a listing removes its previous stored image where possible.
- To roll back, restore the database backup and the backed-up project files together. Do not drop the new columns after users have saved data unless that data is also backed up.

ADMIN SETUP
- For an existing database, import database/admin_moderation_migration.sql once in phpMyAdmin.
- For a fresh database, import database/book_exchange.sql; sample books are already approved.
- In PowerShell, run: C:\xampp\php\php.exe C:\xampp\htdocs\book_exchange_portal\create-admin.php
- Enter the admin name, email, and a password of at least 12 characters when prompted. If the email already belongs to an account, that account is promoted and its password is changed.
- Sign in through Login. Pending listings can then be approved or rejected from Moderation. Possible duplicates are identified by matching title and author.
- create-admin.php only runs from the command line and is not available through the website.

IMPORTANT
The demo account in the SQL is included only as sample data. You can create your own account through Register.
=