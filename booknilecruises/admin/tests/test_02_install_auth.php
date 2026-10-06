<?php
declare(strict_types=1);

use Bnc\{Auth, Db, Policy, Roles};

$owner = new Browser($base);
$GLOBALS['ownerBrowser'] = $owner;
const OWNER_PASS = 'owner-password-123';

test('before install every page sends you to the installer', function () use ($owner) {
    $r = $owner->get('/admin/');
    assert_same(303, $r['status']);
    assert_contains('/admin/install', (string) $r['location']);
    $page = $owner->get('/admin/install');
    assert_same(200, $page['status']);
    assert_contains('dir="rtl"', $page['body']);
    assert_contains("default-src 'self'", $page['headers']);
});

test('installer refuses a wrong token', function () use ($owner) {
    $r = $owner->post('/admin/install', ['token' => 'wrong', 'name' => 'O', 'email' => 'owner@example.com', 'password' => OWNER_PASS]);
    assert_same(422, $r['status']);
    assert_contains('رمز التثبيت غير صحيح', $r['body']);
});

test('installer creates tables, preset roles and the owner, then locks itself', function () use ($owner) {
    $r = $owner->post('/admin/install', ['token' => 'test-install-token-0123456789abcdef', 'name' => 'Owner', 'email' => 'Owner@Example.com', 'password' => OWNER_PASS]);
    assert_same(303, $r['status']);
    assert_same(5, (int) Db::value('SELECT COUNT(*) FROM roles'));
    assert_same('owner@example.com', Db::value('SELECT email FROM users'));
    $again = $owner->get('/admin/install');
    assert_same(303, $again['status']);
    assert_contains('/admin/login', (string) $again['location']);
});

test('POST without a CSRF token is rejected', function () use ($owner) {
    $r = $owner->post('/admin/login', ['email' => 'owner@example.com', 'password' => OWNER_PASS], false);
    assert_same(400, $r['status']);
});

test('owner can sign in; panel pages require a session', function () use ($owner, $base) {
    $anon = new Browser($base);
    $r = $anon->get('/admin/users');
    assert_same(303, $r['status']);
    assert_contains('/admin/login?next=%2Fusers', (string) $r['location']);

    $r = $owner->post('/admin/login', ['email' => 'owner@example.com', 'password' => OWNER_PASS, 'next' => '/users']);
    assert_same(303, $r['status']);
    assert_contains('/admin/users', (string) $r['location']);
    $page = $owner->get('/admin/users');
    assert_same(200, $page['status']);
    assert_contains('owner@example.com', $page['body']);
});

test('login ignores an off-site "next" target', function () use ($base) {
    $b = new Browser($base);
    $r = $b->post('/admin/login', ['email' => 'owner@example.com', 'password' => OWNER_PASS, 'next' => '//evil.example/x']);
    assert_same(303, $r['status']);
    assert_not_contains('evil', (string) $r['location']);
});

test('five wrong passwords lock the account for a while', function () use ($base) {
    $b = new Browser($base);
    for ($i = 0; $i < 5; $i++) {
        $r = $b->post('/admin/login', ['email' => 'owner@example.com', 'password' => 'nope-nope-nope']);
        assert_same(422, $r['status']);
    }
    $r = $b->post('/admin/login', ['email' => 'owner@example.com', 'password' => OWNER_PASS]);
    assert_same(422, $r['status']);
    assert_contains('محاولات دخول كثيرة', $r['body']);
    // Unlock for the remaining tests.
    Db::run("DELETE FROM login_attempts WHERE succeeded = 0");
});

test('unknown pages give 404 inside the panel', function () use ($owner) {
    $r = $owner->get('/admin/nothing-here');
    assert_same(404, $r['status']);
});
