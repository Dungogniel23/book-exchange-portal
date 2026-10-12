<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit;
}

$userId = (int)$_SESSION['user_id'];
if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	$action = $_POST['action'] ?? '';
	$recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
	$notice = 'Invalid request.';

	if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
		$notice = 'Your session expired. Please try again.';
	} elseif ($recordId && $action === 'set_status') {
		$status = $_POST['status'] ?? '';
		$allowedStatuses = ['Sold', 'Donated', 'Exchanged', 'Available', 'Unavailable'];
		if (!in_array($status, $allowedStatuses, true)) {
			$notice = 'Choose a valid listing status.';
		} else {
			$statement = $conn->prepare("UPDATE books SET status=? WHERE id=? AND owner_id=?");
			$statement->bind_param("sii", $status, $recordId, $userId);
			$statement->execute();
			$notice = $statement->affected_rows ? 'Listing status updated.' : 'Listing not found or status unchanged.';
		}
	} elseif ($recordId && $action === 'toggle_availability') {
		$statement = $conn->prepare("SELECT status FROM books WHERE id=? AND owner_id=?");
		$statement->bind_param("ii", $recordId, $userId);
		$statement->execute();
		$ownedBook = $statement->get_result()->fetch_assoc();
		if (!$ownedBook) {
			$notice = 'Listing not found.';
		} elseif (!in_array($ownedBook['status'], ['Available', 'Unavailable'], true)) {
			$notice = 'Availability can only be toggled for available or unavailable listings.';
		} else {
			$status = $ownedBook['status'] === 'Available' ? 'Unavailable' : 'Available';
			$statement = $conn->prepare("UPDATE books SET status=? WHERE id=? AND owner_id=?");
			$statement->bind_param("sii", $status, $recordId, $userId);
			$statement->execute();
			$notice = 'Listing marked ' . strtolower($status) . '.';
		}
	} elseif ($recordId && $action === 'edit_listing') {
		$title = trim($_POST['title'] ?? '');
		$author = trim($_POST['author'] ?? '');
		$description = trim($_POST['description'] ?? '');
		if ($title === '' || $author === '') {
			$notice = 'Title and author are required.';
		} else {
			$statement = $conn->prepare("UPDATE books SET title=?, author=?, description=?, moderation_status='Pending' WHERE id=? AND owner_id=?");
			$statement->bind_param("sssii", $title, $author, $description, $recordId, $userId);
			$statement->execute();
			$notice = $statement->affected_rows
				? 'Listing updated and sent for admin review.'
				: 'Listing was unchanged or could not be found.';
		}
	} elseif ($recordId && $action === 'delete_listing') {
		$statement = $conn->prepare("DELETE FROM books WHERE id=? AND owner_id=?");
		$statement->bind_param("ii", $recordId, $userId);
		$statement->execute();
		$notice = $statement->affected_rows ? 'Listing deleted.' : 'Listing not found.';
	} elseif ($recordId && $action === 'unsave_book') {
		$statement = $conn->prepare("DELETE FROM favorites WHERE user_id=? AND book_id=?");
		$statement->bind_param("ii", $userId, $recordId);
		$statement->execute();
		$notice = $statement->affected_rows ? 'Book removed from favorites.' : 'Saved book not found.';
	} elseif ($recordId && in_array($action, ['approve_request', 'reject_request'], true)) {
		$conn->begin_transaction();
		$statement = $conn->prepare("SELECT br.status AS request_status, br.book_id, b.owner_id, b.status AS book_status, b.moderation_status
			FROM borrow_requests br JOIN books b ON b.id=br.book_id
			WHERE br.id=? FOR UPDATE");
		$statement->bind_param("i", $recordId);
		$statement->execute();
		$request = $statement->get_result()->fetch_assoc();

		if (!$request || (int)$request['owner_id'] !== $userId || $request['request_status'] !== 'Pending') {
			$conn->rollback();
			$notice = 'Request not found or no longer pending.';
		} elseif ($action === 'approve_request' && ($request['book_status'] !== 'Available' || $request['moderation_status'] !== 'Approved')) {
			$conn->rollback();
			$notice = 'This book is no longer available to approve.';
		} else {
			$newStatus = $action === 'approve_request' ? 'Approved' : 'Rejected';
			$statement = $conn->prepare("UPDATE borrow_requests SET status=? WHERE id=? AND status='Pending'");
			$statement->bind_param("si", $newStatus, $recordId);
			$statement->execute();
			$updated = $statement->affected_rows === 1;
			if ($updated && $newStatus === 'Approved') {
				$statement = $conn->prepare("UPDATE books SET status='Borrowed' WHERE id=? AND status='Available'");
				$statement->bind_param("i", $request['book_id']);
				$statement->execute();
				$updated = $statement->affected_rows === 1;
			}
			if ($updated) {
				$conn->commit();
				$notice = $newStatus === 'Approved' ? 'Borrow request approved.' : 'Borrow request rejected.';
			} else {
				$conn->rollback();
				$notice = 'Request could not be updated; refresh and try again.';
			}
		}
	}

	$_SESSION['profile_notice'] = $notice;
	header("Location: profile.php");
	exit;
}

$notice = $_SESSION['profile_notice'] ?? '';
unset($_SESSION['profile_notice']);

$statement = $conn->prepare("SELECT name, email FROM users WHERE id=?");
$statement->bind_param("i", $userId);
$statement->execute();
$user = $statement->get_result()->fetch_assoc();

$statement = $conn->prepare("SELECT * FROM books WHERE owner_id=? ORDER BY created_at DESC");
$statement->bind_param("i", $userId);
$statement->execute();
$books = $statement->get_result();

$statement = $conn->prepare("SELECT br.id, br.status, br.message, br.created_at, b.id AS book_id, b.title, requester.name AS requester_name
	FROM borrow_requests br
	JOIN books b ON b.id=br.book_id
	JOIN users requester ON requester.id=br.requester_id
	WHERE b.owner_id=?
	ORDER BY br.created_at DESC");
$statement->bind_param("i", $userId);
$statement->execute();
$incomingRequests = $statement->get_result();

$statement = $conn->prepare("SELECT br.status, br.created_at, b.id AS book_id, b.title, owner.name AS owner_name
	FROM borrow_requests br
	JOIN books b ON b.id=br.book_id
	JOIN users owner ON owner.id=b.owner_id
	WHERE br.requester_id=?
	ORDER BY br.created_at DESC");
$statement->bind_param("i", $userId);
$statement->execute();
$outgoingRequests = $statement->get_result();

$statement = $conn->prepare("SELECT b.id, b.title, b.author, b.status, b.owner_id, owner.name AS owner_name
	FROM favorites f
	JOIN books b ON b.id=f.book_id
	JOIN users owner ON owner.id=b.owner_id
	WHERE f.user_id=? AND b.moderation_status='Approved'
	ORDER BY f.created_at DESC");
$statement->bind_param("i", $userId);
$statement->execute();
$favorites = $statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile | BookBridge</title>
<link rel="stylesheet" href="css/style.css">
<style>
.profile-listing-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;width:100%;}
.profile-listing-card{display:grid;grid-template-columns:minmax(130px,190px) minmax(0,1fr);min-width:0;overflow:visible;}
.profile-listing-card .cover-placeholder{height:100%;min-height:200px;border-radius:13px 0 0 13px;}
.profile-listing-card .book-info{display:flex;flex-direction:column;align-items:flex-start;gap:10px;min-width:0;width:100%;}
.profile-listing-card .book-info h3,.profile-listing-card .book-info p,.profile-listing-card .book-info>a{max-width:100%;overflow-wrap:anywhere;}
.profile-listing-card .seller-status-form{display:flex;flex-wrap:wrap;align-items:center;gap:10px;width:100%;min-width:0;margin:8px 0;}
.profile-listing-card .seller-status-form .btn{flex:0 1 auto;max-width:100%;min-width:0;white-space:normal;text-align:center;}
.profile-listing-card .seller-status-form select{flex:1 1 190px;width:auto;min-width:0;max-width:100%;padding:8px 10px;border:1px solid #d9dde7;border-radius:8px;background:#fff;font:inherit;}
.profile-listing-card details,.profile-listing-card details form,.profile-listing-card .seller-delete-form{width:100%;min-width:0;max-width:100%;}
.profile-listing-card details summary{cursor:pointer;font-weight:700;}
.profile-listing-card .seller-delete-form{margin:0;}
@media(max-width:640px){.profile-listing-card{grid-template-columns:minmax(0,1fr);}.profile-listing-card .cover-placeholder{height:150px;min-height:150px;border-radius:13px 13px 0 0;}.profile-listing-card .seller-status-form select{flex-basis:100%;}}
</style>
</head>
<body>
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="books.php">Browse Books</a><a href="post-book.php">Post a Book</a><a href="requests.php">Requests</a><a class="btn small" href="logout.php">Logout</a></nav></header>
<main class="container page">
	<div class="profile"><div class="avatar"><?=htmlspecialchars(strtoupper(substr($user['name'] ?? '', 0, 1)))?></div><div><h1><?=htmlspecialchars($user['name'] ?? '')?></h1><p><?=htmlspecialchars($user['email'] ?? '')?></p></div></div>
	<?php if ($notice): ?><div class="success" role="status"><?=htmlspecialchars($notice)?></div><?php endif; ?>

	<h2>My Posted Books</h2>
	<?php if ($books->num_rows === 0): ?>
		<p>You have not posted any books yet.</p>
	<?php else: ?>
		<div class="book-grid profile-listing-grid">
		<?php while ($book = $books->fetch_assoc()): ?>
			<?php $moderationLabel = $book['moderation_status'] === 'Pending' ? 'Pending Approval' : $book['moderation_status']; ?>
			<article class="book-card profile-listing-card">
				<div class="cover-placeholder"><?=htmlspecialchars(strtoupper(substr($book['title'], 0, 1)))?></div>
				<div class="book-info">
					<span class="badge"><?=htmlspecialchars($book['status'])?></span>
					<span class="badge"><?=htmlspecialchars($moderationLabel)?></span>
					<h3><?=htmlspecialchars($book['title'])?></h3>
					<p><?=htmlspecialchars($book['author'])?></p>
					<a href="book-details.php?id=<?=(int)$book['id']?>">View listing</a>
					<form method="post" class="hero-actions seller-status-form">
						<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
						<input type="hidden" name="record_id" value="<?=(int)$book['id']?>">
						<?php if (in_array($book['status'], ['Available', 'Unavailable'], true)): ?>
							<button class="btn small" name="action" value="toggle_availability"><?=$book['status'] === 'Available' ? 'Mark Unavailable' : 'Mark Available'?></button>
						<?php endif; ?>
						<select name="status" aria-label="Set listing status">
							<option value="Sold">Mark as Sold</option>
							<option value="Donated">Mark as Donated</option>
							<option value="Exchanged">Mark as Exchanged</option>
						</select>
						<button class="btn small" name="action" value="set_status">Update status</button>
					</form>
					<details>
						<summary>Edit Listing</summary>
						<form method="post" class="form">
							<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
							<input type="hidden" name="record_id" value="<?=(int)$book['id']?>">
							<label>Title<input name="title" value="<?=htmlspecialchars($book['title'])?>" required></label>
							<label>Author<input name="author" value="<?=htmlspecialchars($book['author'])?>" required></label>
							<label>Description<textarea name="description"><?=htmlspecialchars($book['description'] ?? '')?></textarea></label>
							<button class="btn" name="action" value="edit_listing">Save Listing</button>
						</form>
					</details>
					<form method="post" class="seller-delete-form" onsubmit="return confirm('Delete this listing and its related requests and saved-book entries?')">
						<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
						<input type="hidden" name="record_id" value="<?=(int)$book['id']?>">
						<button class="btn small outline" name="action" value="delete_listing">Delete Listing</button>
					</form>
				</div>
			</article>
		<?php endwhile; ?>
		</div>
	<?php endif; ?>

	<h2>My Saved / Favorite Books</h2>
	<?php if ($favorites->num_rows === 0): ?>
		<p>You have not saved any books yet.</p>
	<?php else: ?>
		<div class="book-grid">
		<?php while ($favorite = $favorites->fetch_assoc()): ?>
			<article class="book-card"><div class="cover-placeholder"><?=htmlspecialchars(strtoupper(substr($favorite['title'], 0, 1)))?></div><div class="book-info"><span class="badge"><?=htmlspecialchars($favorite['status'])?></span><h3><?=htmlspecialchars($favorite['title'])?></h3><p>by <?=htmlspecialchars($favorite['author'])?></p><small>Owner: <?=htmlspecialchars($favorite['owner_name'])?></small><a class="btn full" href="book-details.php?id=<?=(int)$favorite['id']?>">View Details</a><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="record_id" value="<?=(int)$favorite['id']?>"><button class="btn small outline" name="action" value="unsave_book">Remove from Favorites</button></form></div></article>
		<?php endwhile; ?>
		</div>
	<?php endif; ?>

	<h2>Incoming Requests</h2>
	<?php if ($incomingRequests->num_rows === 0): ?><p>No one has requested your books yet.</p><?php else: ?>
		<div class="request-list">
		<?php while ($request = $incomingRequests->fetch_assoc()): ?>
			<article class="request"><div><strong><a href="book-details.php?id=<?=(int)$request['book_id']?>"><?=htmlspecialchars($request['title'])?></a></strong><p>Requested by <?=htmlspecialchars($request['requester_name'])?></p><?php if ($request['message']): ?><small><?=nl2br(htmlspecialchars($request['message']))?></small><?php endif; ?><p><small>Submitted <?=htmlspecialchars($request['created_at'])?></small></p></div><div><span class="badge"><?=htmlspecialchars($request['status'])?></span><?php if ($request['status'] === 'Pending'): ?><form method="post" class="hero-actions"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="record_id" value="<?=(int)$request['id']?>"><button class="btn small" name="action" value="approve_request">Approve</button><button class="btn small outline" name="action" value="reject_request">Reject</button></form><?php endif; ?></div></article>
		<?php endwhile; ?>
		</div>
	<?php endif; ?>

	<h2>My Borrow Requests</h2>
	<?php if ($outgoingRequests->num_rows === 0): ?><p>You have not made any borrow requests yet.</p><?php else: ?>
		<div class="request-list">
		<?php while ($request = $outgoingRequests->fetch_assoc()): ?>
			<article class="request"><div><strong><a href="book-details.php?id=<?=(int)$request['book_id']?>"><?=htmlspecialchars($request['title'])?></a></strong><p>Owner: <?=htmlspecialchars($request['owner_name'])?></p><small>Submitted <?=htmlspecialchars($request['created_at'])?></small></div><span class="badge"><?=htmlspecialchars($request['status'])?></span></article>
		<?php endwhile; ?>
		</div>
	<?php endif; ?>
</main>
<footer>© <?=date("Y")?> BookBridge</footer>
</body>
</html>