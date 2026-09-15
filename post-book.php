<?php
session_start();require_once "config.php";if(!isset($_SESSION['user_id'])){header("Location:login.php");exit;}
$error="";$ok="";
if($_SERVER['REQUEST_METHOD']==='POST'){
 $title=trim($_POST['title']);$author=trim($_POST['author']);$description=trim($_POST['description']);$status=$_POST['status'];
 if(!$title||!$author)$error="Title and author are required.";
 else{$s=$conn->prepare("INSERT INTO books(owner_id,title,author,description,status) VALUES(?,?,?,?,?)");$s->bind_param("issss",$_SESSION['user_id'],$title,$author,$description,$status);$s->execute();$ok="Book posted successfully.";$_POST=[];}
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Post a Book</title><link rel="stylesheet" href="css/style.css"></head><body><header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="books.php">Browse Books</a><a href="requests.php">Requests</a><a href="profile.php">Profile</a><a class="btn small" href="logout.php">Logout</a></nav></header><main class="auth"><div class="form-card wide"><span class="eyebrow">SHARE A BOOK</span><h1>Post a Book</h1><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($ok):?><div class="success"><?=htmlspecialchars($ok)?></div><?php endif;?><form method="post" class="form"><label>Book Title<input name="title" value="<?=htmlspecialchars($_POST['title']??'')?>" required></label><label>Author<input name="author" value="<?=htmlspecialchars($_POST['author']??'')?>" required></label><label>Description<textarea name="description"><?=htmlspecialchars($_POST['description']??'')?></textarea></label><label>Availability<select name="status"><option>Available</option><option>Borrowed</option></select></label><button class="btn full">Post Book</button></form></div></main></body></html>
