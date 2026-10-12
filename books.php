<?php
session_start();
require_once "config.php";

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$query = trim($_GET['q'] ?? '');
if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	$bookId = filter_var($_POST['book_id'] ?? '', FILTER_VALIDATE_INT);
	$action = $_POST['action'] ?? '';
	$message = 'Invalid favorites request.';

	if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
		$message = 'Your session expired. Please try again.';
	} elseif (!$userId) {
		$message = 'Please log in to save books.';
	} elseif ($bookId && in_array($action, ['save_favorite', 'remove_favorite'], true)) {
		if ($action === 'save_favorite') {
			$statement = $conn->prepare("SELECT id FROM books WHERE id=? AND moderation_status='Approved' AND status='Available'");
			$statement->bind_param("i", $bookId);
			$statement->execute();
			if ($statement->get_result()->fetch_assoc()) {
				$statement = $conn->prepare("INSERT IGNORE INTO favorites(user_id, book_id) VALUES(?, ?)");
				$statement->bind_param("ii", $userId, $bookId);
				$statement->execute();
				$message = 'Book saved to your favorites.';
			} else {
				$message = 'This book is no longer available to save.';
			}
		} else {
			$statement = $conn->prepare("DELETE FROM favorites WHERE user_id=? AND book_id=?");
			$statement->bind_param("ii", $userId, $bookId);
			$statement->execute();
			$message = 'Book removed from your favorites.';
		}
	}
	$_SESSION['books_notice'] = $message;
	$redirect = 'books.php' . ($query !== '' ? '?q=' . rawurlencode($query) : '');
	header("Location: " . $redirect);
	exit;
}

$notice = $_SESSION['books_notice'] ?? '';
unset($_SESSION['books_notice']);
if ($query !== '') {
	$statement = $conn->prepare("SELECT b.*, u.name AS owner_name, f.id IS NOT NULL AS is_favorite
		FROM books b
		JOIN users u ON u.id=b.owner_id
		LEFT JOIN favorites f ON f.book_id=b.id AND f.user_id=?
		WHERE b.moderation_status='Approved' AND b.status='Available'
		AND (b.title LIKE ? OR b.author LIKE ?)
		ORDER BY b.created_at DESC");
	$like = "%$query%";
	$statement->bind_param("iss", $userId, $like, $like);
	$statement->execute();
	$result = $statement->get_result();
} else {
	$statement = $conn->prepare("SELECT b.*, u.name AS owner_name, f.id IS NOT NULL AS is_favorite
		FROM books b
		JOIN users u ON u.id=b.owner_id
		LEFT JOIN favorites f ON f.book_id=b.id AND f.user_id=?
		WHERE b.moderation_status='Approved' AND b.status='Available'
		ORDER BY b.created_at DESC");
	$statement->bind_param("i", $userId);
	$statement->execute();
	$result = $statement->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Browse Books | BookBridge</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a class="active" href="books.php">Browse Books</a><?php if (isset($_SESSION['user_id'])): ?><?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="admin.php">Moderation</a><?php endif; ?><a href="post-book.php">Post a Book</a><a href="requests.php">Requests</a><a href="profile.php">Profile</a><a class="btn small" href="logout.php">Logout</a><?php else: ?><a href="login.php">Login</a><a class="btn small" href="register.php">Register</a><?php endif; ?></nav></header>
<main class="container page">
	<div class="section-head"><div><span class="eyebrow">BOOK LIBRARY</span><h1>Browse Books</h1></div></div>
	<?php if ($notice): ?><div class="success" role="status"><?=htmlspecialchars($notice)?></div><?php endif; ?>
	<form class="search" method="get"><input name="q" value="<?=htmlspecialchars($query)?>" placeholder="Search by title or author..."><button class="btn">Search</button></form>
	<?php if ($result->num_rows === 0): ?><p>No available approved books matched your search.</p><?php else: ?>
		<div class="book-grid">
		<?php while ($book = $result->fetch_assoc()): ?>
			<article class="book-card"><div class="cover-placeholder"><?=htmlspecialchars(strtoupper(substr($book['title'], 0, 1)))?></div><div class="book-info"><span class="badge"><?=htmlspecialchars($book['status'])?></span><h3><?=htmlspecialchars($book['title'])?></h3><p>by <?=htmlspecialchars($book['author'])?></p><small>Owner: <?=htmlspecialchars($book['owner_name'])?></small><a class="btn full" href="book-details.php?id=<?=(int)$book['id']?>">View Details</a>
				<?php if ($userId): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="book_id" value="<?=(int)$book['id']?>"><button class="btn full outline" name="action" value="<?=$book['is_favorite'] ? 'remove_favorite' : 'save_favorite'?>"><?=$book['is_favorite'] ? 'Unsave' : '♥ Save to Favorites'?></button></form><?php else: ?><a class="btn full outline" href="login.php">♥ Save to Favorites</a><?php endif; ?>
			</div></article>
		<?php endwhile; ?>
		</div>
	<?php endif; ?>
</main>
<footer>© <?=date("Y")?> BookBridge</footer>
</body>
</html>