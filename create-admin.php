<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return rtrim((string)fgets(STDIN), "\r\n");
}

$name = trim(prompt('Admin name: '));
$email = trim(prompt('Admin email: '));
$password = prompt('Admin password (at least 12 characters): ');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Enter a name, valid email, and password of at least 12 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$lookup = $conn->prepare("SELECT id FROM users WHERE email=?");
$lookup->bind_param("s", $email);
$lookup->execute();
$existingUser = $lookup->get_result()->fetch_assoc();

if ($existingUser) {
    $update = $conn->prepare("UPDATE users SET name=?, password=?, role='admin' WHERE id=?");
    $update->bind_param("ssi", $name, $hash, $existingUser['id']);
    $update->execute();
    fwrite(STDOUT, "Existing account promoted to admin and password updated.\n");
} else {
    $insert = $conn->prepare("INSERT INTO users(name, email, password, role) VALUES(?,?,?,'admin')");
    $insert->bind_param("sss", $name, $email, $hash);
    $insert->execute();
    fwrite(STDOUT, "Admin account created.\n");
}