<?php
session_start(); require_once "config.php";
$id=(int)($_GET['id']??0);
$stmt=$conn->prepare("SELECT b.*,u.name owner_name,u.email owner_email FROM books b JOIN users u ON b.owner_id=u.id WHERE b.id=?");
$stmt->bind_param("i",$id);$stmt->execute();$book=$stmt->get_result()->fetch_assoc();
if(!$book){http_response_code(404);die("Book not found.");}
$can_view_unapproved=isset($_SESSION['user_id']) && (int)$_SESSION['user_id']===(int)$book['owner_id'];
if(isset($_SESSION['user_id']) && !$can_view_unapproved){$roleCheck=$conn->prepare("SELECT role FROM users WHERE id=?");$roleCheck->bind_param("i",$_SESSION['user_id']);$roleCheck->execute();$role=$roleCheck->get_result()->fetch_assoc();$can_view_unapproved=$role && $role['role']==='admin';}
if($book['moderation_status']!=='Approved' && !$can_view_unapproved){http_response_code(404);die("Book not found.");}
$msg="";
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_SESSION['user_id'])){
  if((int)$_SESSION['user_id']===(int)$book['owner_id']) $msg="You cannot request your own book.";
  elseif($book['status']!=='Available') $msg="This book is not currently available.";
  else{
    $s=$conn->prepare("INSERT INTO borrow_requests(book_id,requester_id,message,status) VALUES(?,?,?,'Pending')");
    $m=trim($_POST['message']??'');$s->bind_param("iis",$id,$_SESSION['user_id'],$m);$s->execute();$msg="Borrow request sent successfully.";
  }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Book Details</title><link rel="stylesheet" href="css/style.css"></head>
<body><header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a href="books.php">Browse Books</a><?php if(isset($_SESSION['user_id'])):?><a href="post-book.php">Post a Book</a><a href="requests.php">Requests</a><a href="profile.php">Profile</a><a class="btn small" href="logout.php">Logout</a><?php else:?><a href="login.php">Login</a><?php endif;?></nav></header>
<main class="container page"><div class="detail"><div class="detail-cover"><?=htmlspecialchars(strtoupper(substr($book['title'],0,1)))?></div><div><span class="badge"><?=htmlspecialchars($book['status'])?></span><?php if($book['moderation_status']!=='Approved'):?><p class="alert">Listing review status: <?=htmlspecialchars($book['moderation_status'])?></p><?php endif;?><h1><?=htmlspecialchars($book['title'])?></h1><h3>by <?=htmlspecialchars($book['author'])?></h3><p><?=nl2br(htmlspecialchars($book['description']))?></p><div class="owner"><strong>Owner:</strong> <?=htmlspecialchars($book['owner_name'])?></div><?php if($msg):?><div class="alert"><?=htmlspecialchars($msg)?></div><?php endif;?><?php if(isset($_SESSION['user_id']) && $_SESSION['user_id']!=$book['owner_id'] && $book['status']==='Available' && $book['moderation_status']==='Approved'):?><form method="post" class="form"><label>Message to owner<textarea name="message" placeholder="Hi! I would like to borrow this book."></textarea></label><button class="btn">Request to Borrow</button></form><?php elseif(!isset($_SESSION['user_id'])):?><a class="btn" href="login.php">Login to Request</a><?php endif;?></div></div></main><footer>© <?=date("Y")?> BookBridge</footer></body></html>
