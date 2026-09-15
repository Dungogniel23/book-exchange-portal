<?php
session_start();
require_once "config.php";

$result = $conn->query("SELECT b.*, u.name AS owner_name FROM books b JOIN users u ON b.owner_id=u.id ORDER BY b.created_at DESC LIMIT 12");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BookBridge - Book Borrowing & Exchange</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="navbar">
  <a class="brand" href="index.php">📚 BookBridge</a>
  <nav>
    <a href="index.php">Home</a>
    <a href="books.php">Browse Books</a>
    <?php if(isset($_SESSION['user_id'])): ?>
      <a href="post-book.php">Post a Book</a>
      <a href="requests.php">Requests</a>
      <a href="profile.php">Profile</a>
      <a class="btn small" href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a class="btn small" href="register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>

<section class="hero">
  <div>
    <span class="eyebrow">COMMUNITY BOOK EXCHANGE</span>
    <h1>Read more. <span>Share more.</span></h1>
    <p>Borrow books from fellow students, exchange books you no longer need, and build a community of readers.</p>
    <div class="hero-actions">
      <a class="btn" href="books.php">Browse Books</a>
      <a class="btn outline" href="post-book.php">Share a Book</a>
    </div>
  </div>
  <div class="hero-card">
    <div class="book-cover">BOOK<br>BRIDGE</div>
    <p>Give a book a new reader.</p>
  </div>
</section>

<section class="container">
  <div class="section-head">
    <div><span class="eyebrow">AVAILABLE NOW</span><h2>Recently Added Books</h2></div>
    <a href="books.php">View all →</a>
  </div>
  <div class="book-grid">
  <?php while($book=$result->fetch_assoc()): ?>
    <article class="book-card">
      <div class="cover-placeholder"><?=htmlspecialchars(strtoupper(substr($book['title'],0,1)))?></div>
      <div class="book-info">
        <span class="badge"><?=htmlspecialchars($book['status'])?></span>
        <h3><?=htmlspecialchars($book['title'])?></h3>
        <p>by <?=htmlspecialchars($book['author'])?></p>
        <small>Posted by <?=htmlspecialchars($book['owner_name'])?></small>
        <a class="btn full" href="book-details.php?id=<?=$book['id']?>">View Details</a>
      </div>
    </article>
  <?php endwhile; ?>
  </div>
</section>

<footer>© <?=date("Y")?> BookBridge · Student Book Borrowing & Exchange Portal</footer>
</body>
</html>
