<?php
session_start();
require_once "config.php";
require_once "includes/book_images.php";

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
    $action = $_POST['action'] ?? '';
    $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
    $notice = 'Invalid moderation request.';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $notice = 'Your session expired. Please try again.';
    } elseif ($recordId && in_array($action, ['approve_book', 'reject_book', 'delete_book'], true)) {
        if ($action === 'delete_book') {
            $statement = $conn->prepare("SELECT image FROM books WHERE id=? AND moderation_status='Pending'");
            $statement->bind_param("i", $recordId);
            $statement->execute();
            $bookToDelete = $statement->get_result()->fetch_assoc();
            $statement = $conn->prepare("DELETE FROM books WHERE id=? AND moderation_status='Pending'");
            $statement->bind_param("i", $recordId);
            $statement->execute();
            if ($statement->affected_rows) {
                book_image_delete($bookToDelete['image'] ?? null);
                $notice = 'Book listing deleted.';
            } else {
                $notice = 'That listing is no longer pending review.';
            }
        } else {
            $status = $action === 'approve_book' ? 'Approved' : 'Rejected';
            $statement = $conn->prepare("UPDATE books SET moderation_status=? WHERE id=? AND moderation_status='Pending'");
            $statement->bind_param("si", $status, $recordId);
            $statement->execute();
            $notice = $statement->affected_rows
                ? ($status === 'Approved' ? 'Book listing published!' : 'Book listing rejected.')
                : 'That listing is no longer pending review.';
        }
    } elseif ($recordId && in_array($action, ['approve_request', 'reject_request', 'delete_request'], true)) {
        if ($action === 'delete_request') {
            $statement = $conn->prepare("DELETE FROM borrow_requests WHERE id=?");
            $statement->bind_param("i", $recordId);
            $statement->execute();
            $notice = $statement->affected_rows ? 'Borrow request deleted successfully.' : 'Borrow request not found.';
        } else {
            $status = $action === 'approve_request' ? 'Approved' : 'Rejected';
            $statement = $conn->prepare("UPDATE borrow_requests SET status=? WHERE id=? AND status='Pending'");
            $statement->bind_param("si", $status, $recordId);
            $statement->execute();
            $notice = $statement->affected_rows
                ? "Borrow request " . strtolower($status) . " successfully!"
                : 'That borrow request is no longer pending.';
        }
    } elseif ($recordId && $action === 'change_role') {
        $role = $_POST['role'] ?? '';
        if ($recordId === $userId) {
            $notice = 'You cannot change your own role.';
        } elseif (!in_array($role, ['user', 'admin'], true)) {
            $notice = 'Invalid user role.';
        } else {
            $statement = $conn->prepare("UPDATE users SET role=? WHERE id=?");
            $statement->bind_param("si", $role, $recordId);
            $statement->execute();
            $notice = $statement->affected_rows ? 'User role updated successfully.' : 'User role was unchanged or the user was not found.';
        }
    } elseif ($recordId && $action === 'delete_user') {
        if ($recordId === $userId) {
            $notice = 'You cannot remove your own account.';
        } else {
            $statement = $conn->prepare("SELECT image FROM books WHERE owner_id=?");
            $statement->bind_param("i", $recordId);
            $statement->execute();
            $imageResult = $statement->get_result();
            $imagesToDelete = [];
            while ($imageRow = $imageResult->fetch_assoc()) {
                $imagesToDelete[] = $imageRow['image'];
            }
            $statement = $conn->prepare("DELETE FROM users WHERE id=?");
            $statement->bind_param("i", $recordId);
            $statement->execute();
            if ($statement->affected_rows) {
                foreach ($imagesToDelete as $imageToDelete) {
                    book_image_delete($imageToDelete);
                }
                $notice = 'User removed successfully.';
            } else {
                $notice = 'User not found.';
            }
        }
    }

    $_SESSION['admin_notice'] = $notice;
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
    b.id AS book_id, b.title, b.author,
    requester.name AS requester_name, requester.email AS requester_email,
    owner.name AS owner_name
    FROM borrow_requests br
    JOIN books b ON b.id = br.book_id
    JOIN users requester ON requester.id = br.requester_id
    JOIN users owner ON owner.id = b.owner_id
    WHERE br.status = 'Pending'
    ORDER BY br.created_at ASC");
$users = $conn->query("SELECT u.id, u.name, u.email, u.role, u.created_at, COUNT(b.id) AS book_count
    FROM users u LEFT JOIN books b ON b.owner_id = u.id
    GROUP BY u.id, u.name, u.email, u.role, u.created_at
    ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration | BookBridge</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/book-images.css">
</head>
<body>
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a href="books.php">Browse Books</a><a class="active" href="admin.php">Moderation</a><a class="btn small" href="logout.php">Logout</a></nav></header>
<main class="container page">
    <div class="section-head"><div><span class="eyebrow">ADMINISTRATION</span><h1>Moderation and users</h1></div></div>
    <?php if ($notice): ?><div class="success" role="status"><span><?=htmlspecialchars($notice)?></span> <button type="button" class="dismiss-alert" aria-label="Dismiss notification">Dismiss</button></div><?php endif; ?>
    <h2>Book listings awaiting review</h2>
    <?php if ($pendingBooks->num_rows === 0): ?>
        <p>No listings are waiting for review.</p>
    <?php else: ?>
        <div class="book-grid">
        <?php while ($book = $pendingBooks->fetch_assoc()): ?>
            <article class="book-card">
                <div class="cover-placeholder"><img class="book-image" src="<?=htmlspecialchars(book_image_url($book['image'] ?? null), ENT_QUOTES, 'UTF-8')?>" alt="Cover of <?=htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8')?>"></div>
                <div class="book-info">
                    <?php if ($book['is_duplicate']): ?><div class="alert">Possible duplicate: another listing has the same title and author.</div><?php endif; ?>
                    <span class="badge">Pending review</span>
                    <h2><?=htmlspecialchars($book['title'])?></h2>
                    <p>by <?=htmlspecialchars($book['author'])?></p>
                    <?php if (!empty($book['condition'])): ?><span class="badge">Condition: <?=htmlspecialchars($book['condition'])?></span><?php endif; ?>
                    <?php if (!empty($book['condition_review'])): ?><p class="condition-review"><?=nl2br(htmlspecialchars($book['condition_review']))?></p><?php endif; ?>
                    <p><?=nl2br(htmlspecialchars($book['description'] ?? ''))?></p>
                    <small>Posted by <?=htmlspecialchars($book['owner_name'])?> on <?=htmlspecialchars($book['created_at'])?></small>
                    <p><a href="book-details.php?id=<?=(int)$book['id']?>">View listing</a></p>
                    <form method="post" class="hero-actions">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                        <input type="hidden" name="record_id" value="<?=(int)$book['id']?>">
                        <button class="btn" name="action" value="approve_book">Approve &amp; Publish</button>
                        <button class="btn outline" name="action" value="reject_book">Reject Listing</button>
                        <button class="btn outline" name="action" value="delete_book">Delete Listing</button>
                    </form>
                </div>
            </article>
        <?php endwhile; ?>
        </div>
    <?php endif; ?>
    <h2>Pending borrow requests</h2>
    <?php if ($pendingRequests->num_rows === 0): ?>
        <p>No borrow requests are waiting for review.</p>
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
                <div>
                    <span class="badge">Pending owner approval</span>
                    <form method="post" class="hero-actions">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                        <input type="hidden" name="record_id" value="<?=(int)$request['id']?>">
                        <button class="btn" name="action" value="approve_request">Approve Request</button>
                        <button class="btn outline" name="action" value="reject_request">Reject Request</button>
                        <button class="btn outline" name="action" value="delete_request">Delete Request</button>
                    </form>
                </div>
            </article>
        <?php endwhile; ?>
        </div>
    <?php endif; ?>
    <h2>Registered users</h2>
    <div class="request-list">
    <?php while ($user = $users->fetch_assoc()): ?>
        <article class="request">
            <div>
                <strong><?=htmlspecialchars($user['name'])?></strong>
                <p><?=htmlspecialchars($user['email'])?></p>
                <small><?= (int)$user['book_count'] ?> posted book(s) · Role: <?=htmlspecialchars($user['role'])?></small>
            </div>
            <div>
                <?php if ((int)$user['id'] !== $userId): ?>
                    <form method="post" class="inline">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                        <input type="hidden" name="record_id" value="<?=(int)$user['id']?>">
                        <select name="role" aria-label="Role for <?=htmlspecialchars($user['name'])?>">
                            <option value="user" <?=$user['role'] === 'user' ? 'selected' : ''?>>User</option>
                            <option value="admin" <?=$user['role'] === 'admin' ? 'selected' : ''?>>Admin</option>
                        </select>
                        <button class="btn small" name="action" value="change_role">Save role</button>
                        <button class="btn small outline" name="action" value="delete_user" onclick="return confirm('Remove this user and their books and requests?')">Remove user</button>
                    </form>
                <?php else: ?>
                    <span class="badge">Current admin</span>
                <?php endif; ?>
            </div>
        </article>
    <?php endwhile; ?>
    </div>
</main>
<footer>© <?=date("Y")?> BookBridge</footer>
<script>
document.querySelectorAll('.dismiss-alert').forEach(function (button) {
    button.addEventListener('click', function () {
        button.closest('[role="status"]').remove();
    });
});
</script>
</body>
</html>