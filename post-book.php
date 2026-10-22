<?php
session_start();
require_once "config.php";
require_once "includes/book_images.php";

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit;
}

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$ok = '';
$conditions = ['Like New', 'Good', 'Fair', 'Poor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	$titleInput = $_POST['title'] ?? '';
	$authorInput = $_POST['author'] ?? '';
	$descriptionInput = $_POST['description'] ?? '';
	$title = is_string($titleInput) ? trim($titleInput) : '';
	$author = is_string($authorInput) ? trim($authorInput) : '';
	$description = is_string($descriptionInput) ? trim($descriptionInput) : '';
	$status = $_POST['status'] ?? 'Available';
	$condition = $_POST['condition'] ?? '';
	$conditionReviewInput = $_POST['condition_review'] ?? '';
	$conditionReview = is_string($conditionReviewInput) ? trim($conditionReviewInput) : '';

	if (!empty($_SERVER['CONTENT_LENGTH']) && $_POST === [] && $_FILES === []) {
		$error = 'The submitted form exceeded the server request-size limit. Please choose a smaller image.';
	} elseif (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
		$error = 'Your session expired. Please try again.';
	} elseif ($title === '' || $author === '') {
		$error = 'Title and author are required.';
	} elseif (!in_array($status, ['Available', 'Borrowed'], true)) {
		$error = 'Choose a valid availability status.';
	} elseif (!is_valid_book_condition($condition)) {
		$error = 'Choose a valid book condition.';
	} elseif ($conditionReview === '') {
		$error = 'Please describe the book’s actual condition.';
	} else {
		$uploadError = '';
		$image = book_image_upload($_FILES['image'] ?? [], $uploadError);
		if ($uploadError !== '') {
			$error = $uploadError;
		} else {
			try {
				$ownerId = (int)$_SESSION['user_id'];
				$statement = $conn->prepare("INSERT INTO books(owner_id, title, author, description, status, image, `condition`, condition_review, moderation_status)
					VALUES(?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
				if (!$statement) {
					throw new mysqli_sql_exception($conn->error);
				}
				$statement->bind_param("isssssss", $ownerId, $title, $author, $description, $status, $image, $condition, $conditionReview);
				if (!$statement->execute()) {
					throw new mysqli_sql_exception($statement->error);
				}
				$ok = 'Book submitted for admin review.';
				$_POST = [];
			} catch (mysqli_sql_exception $exception) {
				book_image_delete($image);
				error_log('Unable to create book listing: ' . $exception->getMessage());
				$error = 'The book could not be saved. Please try again.';
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Post a Book | BookBridge</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/book-images.css">
<script src="js/book-image-preview.js" defer></script>
</head>
<body>
<header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="books.php">Browse Books</a><a href="requests.php">Requests</a><a href="profile.php">Profile</a><a class="btn small" href="logout.php">Logout</a></nav></header>
<main class="auth">
	<div class="form-card wide">
		<span class="eyebrow">SHARE A BOOK</span>
		<h1>Post a Book</h1>
		<?php if ($error): ?><div class="alert" role="alert"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
		<?php if ($ok): ?><div class="success" role="status"><?=htmlspecialchars($ok, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
		<form method="post" enctype="multipart/form-data" class="form">
			<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>">
			<label>Book Title<input name="title" maxlength="200" value="<?=htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8')?>" required></label>
			<label>Author<input name="author" maxlength="150" value="<?=htmlspecialchars($_POST['author'] ?? '', ENT_QUOTES, 'UTF-8')?>" required></label>
			<label>Description<textarea name="description"><?=htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8')?></textarea></label>
			<label>Book image <span class="field-hint">JPG, JPEG, PNG, or WEBP; maximum 5 MB.</span>
				<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-preview="new-book-image">
			</label>
			<div class="image-preview-frame"><img id="new-book-image" class="image-preview" src="pictures/default-book.svg" alt="Preview of the selected book image"></div>
			<label>Book Condition
				<select name="condition" required>
					<option value="">Select the book’s condition</option>
					<?php foreach ($conditions as $conditionOption): ?>
						<option value="<?=htmlspecialchars($conditionOption, ENT_QUOTES, 'UTF-8')?>" <?=($_POST['condition'] ?? '') === $conditionOption ? 'selected' : ''?>><?=htmlspecialchars($conditionOption, ENT_QUOTES, 'UTF-8')?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Condition Description <span class="field-hint">Mention worn covers, markings, missing or torn pages, other defects, or say “No visible defects.”</span>
				<textarea name="condition_review" rows="4" required><?=htmlspecialchars($_POST['condition_review'] ?? '', ENT_QUOTES, 'UTF-8')?></textarea>
			</label>
			<label>Availability
				<select name="status">
					<option value="Available" <?=($_POST['status'] ?? 'Available') === 'Available' ? 'selected' : ''?>>Available</option>
					<option value="Borrowed" <?=($_POST['status'] ?? '') === 'Borrowed' ? 'selected' : ''?>>Borrowed</option>
				</select>
			</label>
			<button class="btn full">Post Book</button>
		</form>
	</div>
</main>
</body>
</html>
