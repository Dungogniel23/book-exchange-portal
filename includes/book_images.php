<?php

function book_image_upload(array $file, &$error): ?string
{
	$error = '';

	if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
		return null;
	}

	if ($file['error'] !== UPLOAD_ERR_OK) {
		$error = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
			? 'The image is too large. Choose an image smaller than 5 MB.'
			: 'The image could not be uploaded. Please try again.';
		return null;
	}

	if (!isset($file['tmp_name'], $file['size'])
		|| !is_string($file['tmp_name'])
		|| !is_numeric($file['size'])
		|| !is_uploaded_file($file['tmp_name'])) {
		$error = 'The uploaded image could not be validated.';
		return null;
	}

	$actualSize = filesize($file['tmp_name']);
	if ($actualSize === false || $actualSize < 1 || $actualSize > 5 * 1024 * 1024) {
		$error = 'Choose an image no larger than 5 MB.';
		return null;
	}

	$mimeTypes = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
	];
	$fileInfo = new finfo(FILEINFO_MIME_TYPE);
	$mimeType = $fileInfo->file($file['tmp_name']);
	if (!isset($mimeTypes[$mimeType])) {
		$error = 'Choose a JPG, JPEG, PNG, or WEBP image.';
		return null;
	}

	$imageInfo = @getimagesize($file['tmp_name']);
	if (!$imageInfo || $imageInfo['mime'] !== $mimeType
		|| $imageInfo[0] < 1 || $imageInfo[1] < 1
		|| $imageInfo[0] > 8000 || $imageInfo[1] > 8000
		|| $imageInfo[0] * $imageInfo[1] > 40000000) {
		$error = 'The image file is invalid or has unsupported dimensions.';
		return null;
	}

	$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'books';
	if (!is_dir($directory) || !is_writable($directory)) {
		$error = 'Image storage is unavailable. Please contact the site administrator.';
		return null;
	}

	try {
		$filename = bin2hex(random_bytes(16)) . '.' . $mimeTypes[$mimeType];
	} catch (Throwable $exception) {
		error_log('Unable to generate a unique book image name: ' . $exception->getMessage());
		$error = 'The image could not be saved. Please try again.';
		return null;
	}

	if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
		$error = 'The image could not be saved. Check the upload folder permissions.';
		return null;
	}

	return 'uploads/books/' . $filename;
}

function book_image_url($image): string
{
	if (is_string($image)
		&& preg_match('/\Auploads\/books\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/D', $image)
		&& is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image))
		&& !is_link(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image))) {
		return $image;
	}

	return 'pictures/default-book.svg';
}

function book_image_delete($image): void
{
	if (!is_string($image)
		|| !preg_match('/\Auploads\/books\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/D', $image)) {
		return;
	}

	$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image);
	if (is_file($path) && !is_link($path) && !unlink($path)) {
		error_log('Unable to remove book image: ' . $image);
	}
}

function is_valid_book_condition($condition): bool
{
	return is_string($condition) && in_array($condition, ['Like New', 'Good', 'Fair', 'Poor'], true);
}
