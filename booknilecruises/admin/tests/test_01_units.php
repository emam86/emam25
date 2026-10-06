<?php
declare(strict_types=1);

use Bnc\{Migrator, Permissions, Router, HttpException};

test('migration splitter skips comments and keeps statements', function () {
    $sql = "-- header\nCREATE TABLE a (x INT, -- inline\n y INT);\n\nCREATE TABLE b (z INT);\n";
    $parts = Migrator::statements($sql);
    assert_same(2, count($parts));
    assert_contains('CREATE TABLE b', $parts[1]);
});

test('permission list is cleaned and ordered', function () {
    assert_same(['trips.view', 'users.manage'], Permissions::clean(['users.manage', 'bogus', 'trips.view']));
    assert_true(!Permissions::exists('publish'));
});

test('router matches ids and rejects wrong methods', function () {
    $r = new Router();
    $r->get('/users/{id}/edit', ['X', 'edit'], 'users.manage');
    [$h, $perm, $params] = $r->match('GET', '/users/12/edit/');
    assert_same(['12'], $params);
    assert_same('users.manage', $perm);
    assert_same(null, $r->match('GET', '/users/abc/edit'));
    try {
        $r->match('POST', '/users/12/edit');
        throw new AssertionFailed('expected 405');
    } catch (HttpException $e) {
        assert_same(405, $e->status);
    }
});

test('escaping helper neutralises markup', function () {
    assert_same('&lt;script&gt;&quot;x&#039;', e('<script>"x\''));
});
