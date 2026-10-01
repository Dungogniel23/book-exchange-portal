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
- Admin listing review with duplicate warnings
- MySQL database connection

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