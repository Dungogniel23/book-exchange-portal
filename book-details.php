<?php
session_start();
require_once "config.php";
require_once "includes/book_images.php";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$statement = $conn->prepare("SELECT b.*, u.name AS owner_name, u.email AS owner_email
	FROM books b JOIN users u ON u.id=b.owner_id WHERE b.id=?");
$statement->bind_param("i", $id);
$statement->execute();
$book = $statement->get_result()->fetch_assoc();
if (!$book) {
	http_response_code(404);
	die("Book not found.");
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$canViewUnapproved = $userId && $userId === (int)$book['owner_id'];
if ($userId && !$canViewUnapproved) {
	$statement = $conn->prepare("SELECT role FROM users WHERE id=?");
	$statement->bind_param("i", $userId);
	$statement->execute();
	$currentUser = $statement->get_result()->fetch_assoc();
	$canViewUnapproved = $currentUser && $currentUser['role'] === 'admin';
}
if ($book['moderation_status'] !== 'Approved' && !$canViewUnapproved) {
	http_response_code(404);
	die("Book not found.");
}

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	$action = $_POST['action'] ?? '';
	$message = 'Invalid request.';

	if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
		$message = 'Your session expired. Please try again.';
	} elseif (!$userId) {
		$message = 'Please log in to continue.';
	} elseif ($action === 'save_favorite' || $action === 'remove_favorite') {
		if ($action === 'save_favorite') {
			if ($book['moderation_status'] !== 'Approved' || $book['status'] !== 'Available') {
				$message = 'Only approved, available books can be saved.';
			} else {
				$statement = $conn->prepare("INSERT IGNORE INTO favorites(user_id, book_id) VALUES(?, ?)");
				$statement->bind_param("ii", $userId, $id);
				$statement->execute();
				$message = 'Book saved to your favorites.';
			}
		} else {
			$statement = $conn->prepare("DELETE FROM favorites WHERE user_id=? AND book_id=?");
			$statement->bind_param("ii", $userId, $id);
			$statement->execute();
			$message = 'Book removed from your favorites.';
		}
	} elseif ($action === 'request_book') {
		if ($userId === (int)$book['owner_id']) {
			$message = 'You cannot request your own book.';
		} elseif ($book['moderation_status'] !== 'Approved' || $book['status'] !== 'Available') {
			$message = 'This book is not currently available.';
		} else {
			$statement = $conn->prepare("SELECT id FROM borrow_requests WHERE book_id=? AND requester_id=? AND status='Pending' LIMIT 1");
			$statement->bind_param("ii", $id, $userId);
			$statement->execute();
			if ($statement->get_result()->fetch_assoc()) {
				$message = 'You already have a pending request for this book.';
			} else {
				$requestMessage = trim($_POST['message'] ?? '');
				$statement = $conn->prepare("INSERT INTO borrow_requests(book_id, requester_id, message, status) VALUES(?, ?, ?, 'Pending')");
				$statement->bind_param("iis", $id, $userId, $requestMessage);
				$statement->execute();
				$message = 'Borrow request sent successfully.';
			}
		}
	}
	$_SESSION['book_notice'] = $message;
	header("Location: book-details.php?id=" . $id);
	exit;
}

$message = $_SESSION['book_notice'] ?? '';
unset($_SESSION['book_notice']);
$isFavorite = false;
if ($userId) {
	$statement = $conn->prepare("SELECT id FROM favorites WHERE user_id=? AND book_id=?");
	$statement->bind_param("ii", $userId, $id);
	$statement->execute();
	$isFavorite = (bool)$statement->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($book['title'])?> | BookBridge</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/book-images.css">
</head>
<body>
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a href="books.php">Browse Books</a><?php if ($userId): ?><a href="post-book.php">Post a Book</a><a href="requests.php">Requests</a><a href="profile.php">Profile</a><a class="btn small" href="logout.php">Logout</a><?php else: ?><a href="login.php">Login</a><?php endif; ?></nav></header>
<main class="container page">
	<div class="detail"><div class="detail-cover"><img class="book-image" src="<?=htmlspecialchars(book_image_url($book['image'] ?? null), ENT_QUOTES, 'UTF-8')?>" alt="Cover of <?=htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8')?>"></div><div>
		<span class="badge"><?=htmlspecialchars($book['status'])?></span>
		<?php if ($book['moderation_status'] !== 'Approved'): ?><p class="alert">Listing review status: <?=htmlspecialchars($book['moderation_status'])?></p><?php endif; ?>
		<h1><?=htmlspecialchars($book['title'])?></h1><h3>by <?=htmlspecialchars($book['author'])?></h3>
		<?php if (!empty($book['condition'])): ?><p><span class="badge">Condition: <?=htmlspecialchars($book['condition'])?></span></p><?php endif; ?>
		<?php if (!empty($book['condition_review'])): ?><section><h2>Condition Description</h2><p class="condition-review"><?=nl2br(htmlspecialchars($book['condition_review']))?></p></section><?php endif; ?>
		<p><?=nl2br(htmlspecialchars($book['description'] ?? ''))?></p><div class="owner"><strong>Owner:</strong> <?=htmlspecialchars($book['owner_name'])?></div>
		<?php if ($message): ?><div class="success" role="status"><?=htmlspecialchars($message)?></div><?php endif; ?>
		<?php if ($userId && $book['moderation_status'] === 'Approved' && $book['status'] === 'Available'): ?><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><button class="btn outline" name="action" value="<?=$isFavorite ? 'remove_favorite' : 'save_favorite'?>"><?=$isFavorite ? 'Unsave' : '♥ Save to Favorites'?></button></form><?php elseif (!$userId): ?><a class="btn outline" href="login.php">♥ Save to Favorites</a><?php endif; ?>
		<?php if ($userId && $userId !== (int)$book['owner_id'] && $book['status'] === 'Available' && $book['moderation_status'] === 'Approved'): ?><form method="post" class="form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><label>Message to owner<textarea name="message" placeholder="Hi! I would like to borrow this book."></textarea></label><button class="btn" name="action" value="request_book">Request to Borrow</button></form><?php elseif (!$userId): ?><a class="btn" href="login.php">Login to Request</a><?php endif; ?>
	</div></div>
</main>
<footer>© <?=date("Y")?> BookBridge</footer>
</body>
</html>