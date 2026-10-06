<?php
declare(strict_types=1);

// Create (or reset) an owner account from the command line:
//   php bnc-app/bin/create-owner.php "Name" email@example.com
// The password is read from the BNC_PASSWORD environment variable or prompted for.
require dirname(__DIR__) . '/bootstrap.php';

use Bnc\{Auth, Db, Migrator, Roles};

[$_, $name, $email] = $argv + [null, null, null];
if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php create-owner.php \"Name\" email@example.com\n");
    exit(2);
}
$password = getenv('BNC_PASSWORD') ?: (function_exists('readline') ? readline('Password: ') : trim((string) fgets(STDIN)));
if ($p = Auth::passwordProblem((string) $password)) {
    fwrite(STDERR, "$p\n");
    exit(2);
}
Migrator::run();
$owner = Roles::bySlug('owner');
$email = mb_strtolower($email);
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($id = Db::value('SELECT id FROM users WHERE email = ?', [$email])) {
    Db::update('users', ['name' => $name, 'password_hash' => $hash, 'role_id' => $owner['id'], 'is_active' => 1], 'id = ?', [$id]);
    echo "Updated owner #$id\n";
} else {
    $id = Db::insert('users', ['name' => $name, 'email' => $email, 'password_hash' => $hash, 'role_id' => $owner['id']]);
    echo "Created owner #$id\n";
}
\Bnc\Audit::log('create-owner', 'user', $id, 'حساب مالك من سطر الأوامر', null, 'cli');
