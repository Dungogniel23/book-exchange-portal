<?php
session_start(); require_once "config.php";
$q = trim($_GET['q'] ?? '');
if($q !== ''){
  $stmt=$conn->prepare("SELECT b.*,u.name owner_name FROM books b JOIN users u ON b.owner_id=u.id WHERE b.title LIKE ? OR b.author LIKE ? ORDER BY b.created_at DESC");
  $like="%$q%"; $stmt->bind_param("ss",$like,$like); $stmt->execute(); $result=$stmt->get_result();
}else{
  $result=$conn->query("SELECT b.*,u.name owner_name FROM books b JOIN users u ON b.owner_id=u.id ORDER BY b.created_at DESC");
<<<<<<< HEAD
  $stmt=$conn->prepare("SELECT b.*,u.name owner_name FROM books b JOIN users u ON b.owner_id=u.id WHERE b.moderation_status='Approved' AND (b.title LIKE ? OR b.author LIKE ?) ORDER BY b.created_at DESC");
  $like="%$q%"; $stmt->bind_param("ss",$like,$like); $stmt->execute(); $result=$stmt->get_result();
}else{
  $result=$conn->query("SELECT b.*,u.name owner_name FROM books b JOIN users u ON b.owner_id=u.id WHERE b.moderation_status='Approved' ORDER BY b.created_at DESC");
=======
>>>>>>> a767c97596d3adbc1f69c6ea40c8d80ee54424f0
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Browse Books</title><link rel="stylesheet" href="css/style.css"></head>
<body>
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a class="active" href="books.php">Browse Books</a><?php if(isset($_SESSION['user_id'])):?><a href="post-book.php">Post a Book</a><a href="requests.php">Requests</a><a href="profile.php">Profile</a><a class="btn small" href="logout.php">Logout</a><?php else:?><a href="login.php">Login</a><a class="btn small" href="register.php">Register</a><?php endif;?></nav></header>
<<<<<<< HEAD
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a class="active" href="books.php">Browse Books</a><?php if(isset($_SESSION['user_id'])):?><?php if(($_SESSION['role']??'')==='admin'):?><a href="admin.php">Moderation</a><?php endif;?><a href="post-book.php">Post a Book</a><a href="requests.php">Requests</a><a href="profile.php">Profile</a><a class="btn small" href="logout.php">Logout</a><?php else:?><a href="login.php">Login</a><a class="btn small" href="register.php">Register</a><?php endif;?></nav></header>
=======
>>>>>>> a767c97596d3adbc1f69c6ea40c8d80ee54424f0
<main class="container page">
<div class="section-head"><div><span class="eyebrow">BOOK LIBRARY</span><h1>Browse Books</h1></div></div>
<form class="search" method="get"><input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search by title or author..."><button class="btn">Search</button></form>
<div class="book-grid"><?php while($b=$result->fetch_assoc()): ?><article class="book-card"><div class="cover-placeholder"><?=htmlspecialchars(strtoupper(substr($b['title'],0,1)))?></div><div class="book-info"><span class="badge"><?=htmlspecialchars($b['status'])?></span><h3><?=htmlspecialchars($b['title'])?></h3><p>by <?=htmlspecialchars($b['author'])?></p><small>Owner: <?=htmlspecialchars($b['owner_name'])?></small><a class="btn full" href="book-details.php?id=<?=$b['id']?>">View Details</a></div></article><?php endwhile; ?></div>
</main><footer>© <?=date("Y")?> BookBridge</footer></body></html>
