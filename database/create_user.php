<?php
/*
 * Creates (or resets) a Registrar sign-in account.
 *
 * The login page checks the email and password against the `users` table, but nothing else
 * creates an account, so every new database needs one. Run this once from the project folder:
 *
 *     php database/create_user.php
 *
 * It asks for a name, email and password. Nothing is stored in the code, so no password
 * ends up on GitHub. You can also pass them in:  php database/create_user.php "Name" "email" "password"
 * If the email already exists, that account's password is reset instead.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line.\n");
}

require_once __DIR__ . '/../config/db_helper.php';
$pdo = getDB();

function ask(string $label, string $default = ''): string {
    echo $label . ($default !== '' ? " [$default]" : '') . ': ';
    $line = fgets(STDIN);
    $line = $line === false ? '' : trim($line);
    return $line !== '' ? $line : $default;
}

$name = $argv[1] ?? ask('Full name', 'Registrar Staff');
$email = $argv[2] ?? ask('Email (you will sign in with this)');
$password = $argv[3] ?? ask('Password (at least 8 characters; it is shown as you type)');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("That is not a valid email address. Nothing was changed.\n");
}
if (strlen($password) < 8) {
    exit("The password must be at least 8 characters. Nothing was changed.\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$find = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
$find->execute([$email]);
$existingId = $find->fetchColumn();

if ($existingId) {
    $pdo->prepare("UPDATE users SET name = ?, password_hash = ?, role = 'registrar' WHERE id = ?")
        ->execute([$name, $hash, $existingId]);
    echo "Updated the existing account for $email (password reset).\n";
} else {
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'registrar')")
        ->execute([$name, $email, $hash]);
    echo "Created the account for $email. You can sign in now.\n";
}
