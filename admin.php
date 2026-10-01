<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$roleCheck = $conn->prepare("SELECT role FROM users WHERE id=?");
$roleCheck->bind_param("i", $userId);
$roleCheck->execute();
$currentUser = $roleCheck->get_result()->fetch_assoc();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    exit("You do not have permission to view this page.");
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['admin_notice'] = 'Your session expired. Please try again.';
    } elseif (!$bookId || !in_array($action, ['approve', 'reject'], true)) {
        $_SESSION['admin_notice'] = 'Invalid moderation request.';
    } else {
        $status = $action === 'approve' ? 'Approved' : 'Rejected';
        $update = $conn->prepare("UPDATE books SET moderation_status=? WHERE id=? AND moderation_status='Pending'");
        $update->bind_param("si", $status, $bookId);
        $update->execute();
        $_SESSION['admin_notice'] = $update->affected_rows ? "Listing $status in moderation." : 'That listing is no longer pending review.';
    }

    header("Location: admin.php");
    exit;
}

$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$pendingBooks = $conn->query("SELECT b.*, u.name AS owner_name,
    EXISTS (
        SELECT 1 FROM books duplicate_book
        WHERE duplicate_book.id <> b.id
          AND duplicate_book.moderation_status <> 'Rejected'
          AND LOWER(TRIM(duplicate_book.title)) = LOWER(TRIM(b.title))
          AND LOWER(TRIM(duplicate_book.author)) = LOWER(TRIM(b.author))
    ) AS is_duplicate
    FROM books b JOIN users u ON b.owner_id = u.id
    WHERE b.moderation_status = 'Pending'
    ORDER BY is_duplicate DESC, b.created_at ASC");
$pendingRequests = $conn->query("SELECT br.id, br.message, br.created_at,
    b.id AS book_id, b.title, b.author, b.moderation_status,
    requester.name AS requester_name, requester.email AS requester_email,
    owner.name AS owner_name
    FROM borrow_requests br
    JOIN books b ON b.id = br.book_id
    JOIN users requester ON requester.id = br.requester_id
    JOIN users owner ON owner.id = b.owner_id
    WHERE br.status = 'Pending'
    ORDER BY br.created_at ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Listing Moderation | BookBridge</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a href="books.php">Browse Books</a><a class="active" href="admin.php">Moderation</a><a class="btn small" href="logout.php">Logout</a></nav></header>
<main class="container page">
    <div class="section-head"><div><span class="eyebrow">ADMINISTRATION</span><h1>Listing moderation</h1></div></div>
    <?php if ($notice): ?><div class="success"><?=htmlspecialchars($notice)?></div><?php endif; ?>
    <h2>Book listings awaiting review</h2>
    <?php if ($pendingBooks->num_rows === 0): ?>
        <p>No listings are waiting for review.</p>
    <?php else: ?>
        <div class="book-grid">
        <?php while ($book = $pendingBooks->fetch_assoc()): ?>
            <article class="book-card">
                <div class="book-info">
                    <?php if ($book['is_duplicate']): ?><div class="alert">Possible duplicate: another listing has the same title and author.</div><?php endif; ?>
                    <span class="badge">Pending review</span>
                    <h2><?=htmlspecialchars($book['title'])?></h2>
                    <p>by <?=htmlspecialchars($book['author'])?></p>
                    <p><?=nl2br(htmlspecialchars($book['description'] ?? ''))?></p>
                    <small>Posted by <?=htmlspecialchars($book['owner_name'])?> on <?=htmlspecialchars($book['created_at'])?></small>
                    <p><a href="book-details.php?id=<?=(int)$book['id']?>">View listing</a></p>
                    <form method="post" class="hero-actions">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                        <input type="hidden" name="book_id" value="<?=(int)$book['id']?>">
                        <button class="btn" name="action" value="approve">Approve</button>
                        <button class="btn outline" name="action" value="reject">Reject</button>
                    </form>
                </div>
            </article>
        <?php endwhile; ?>
        </div>
    <?php endif; ?>
    <h2>Pending borrow requests</h2>
    <?php if ($pendingRequests->num_rows === 0): ?>
        <p>No borrow requests are waiting for the book owner.</p>
    <?php else: ?>
        <div class="request-list">
        <?php while ($request = $pendingRequests->fetch_assoc()): ?>
            <article class="request">
                <div>
                    <strong><a href="book-details.php?id=<?=(int)$request['book_id']?>"><?=htmlspecialchars($request['title'])?></a></strong>
                    <p>by <?=htmlspecialchars($request['author'])?> · Requested by <?=htmlspecialchars($request['requester_name'])?> (<?=htmlspecialchars($request['requester_email'])?>)</p>
                    <p>Book owner: <?=htmlspecialchars($request['owner_name'])?></p>
                    <?php if ($request['message'] !== null && $request['message'] !== ''): ?><small><?=nl2br(htmlspecialchars($request['message']))?></small><?php endif; ?>
                    <p><small>Submitted <?=htmlspecialchars($request['created_at'])?></small></p>
                </div>
                <span class="badge">Pending owner approval</span>
            </article>
        <?php endwhile; ?>
        </div>
    <?php endif; ?>
</main>
<footer>© <?=date("Y")?> BookBridge</footer>
</body>
</html>