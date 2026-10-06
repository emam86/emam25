# Admin panel (PHP + MySQL)

Plan and phases: [`../ADMIN-PLAN.md`](../ADMIN-PLAN.md).

## Layout

| Repo path | On the server | What |
|---|---|---|
| `app/` | `domains/booknilecruises.net/bnc-app/` | All code: `bootstrap.php`, `src/` (namespace `Bnc\`), `views/`, `migrations/`, `bin/` |
| `public/admin/` | `public_html/admin/` | `index.php` entry point, `.htaccess`, `assets/` |
| `public/api/` | `public_html/api/` | API entry point (phase 5) |
| `config.sample.php` | `domains/booknilecruises.net/bnc-config.php` | Secrets and settings, never committed |

## Running locally

```bash
# Tests (wipe and use the bnc_test database; user bnc/bnc on 127.0.0.1 by default)
php tests/run.php

# Try the panel in a browser
cp config.sample.php bnc-config.php   # edit db + install_token
php -S 127.0.0.1:8790 -t public tests/server.php
# open http://127.0.0.1:8790/admin/
```

## Conventions (follow these in every phase)

- **PHP 8.2+, no framework, no Composer packages.** `declare(strict_types=1);` in every file.
- **Database:** only through `Bnc\Db` (`all`, `one`, `value`, `insert`, `update`, `run`, `tx`). Always bound
  parameters; never interpolate request data into SQL. Table and column names in `insert`/`update` come from
  code, never from input. Schema changes go in a new `app/migrations/NNN_name.sql`; never edit an applied one.
- **Routes:** register in `Bnc\App::routes()` with the permission they need (`null` = any signed-in user,
  `'guest'` = public). The router enforces sign-in, the permission and CSRF on every POST before the
  controller runs. Finer checks (ownership, "can't grant more than you have") go in the controller.
- **Permissions:** new keys go in `Bnc\Permissions::GROUPS` with an Arabic label, and into the presets
  that should have them. Check with `can('key')` in views and `Auth::can()` in code.
- **Controllers:** `app/src/Controller/*Controller.php`, extend `Controller`. Return a string (rendered
  view) or a `Redirect` (`$this->redirect('/path', 'flash message')`). POST handlers that succeed always
  redirect (POST/redirect/GET). Validation failures re-render the form with HTTP 422 and a list of
  Arabic error messages (`partials/errors`).
- **Input:** `Request::str/int/bool/list`. Validate everything (length, format, existence of referenced rows).
- **Views:** `app/views/<area>/<name>.php`, Arabic, RTL. Escape every value with `e()`; the only raw output
  allowed is HTML that was sanitised on save (trip/post bodies, phase 2/4) and partials. Every form has
  `<?= csrf_field() ?>`. Destructive buttons sit in a POST form with `data-confirm="…"`. Build links with `url()`.
- **Audit:** every create/update/delete calls `Audit::log(action, entity, id, Arabic summary, details)`.
- **Sidebar:** add the section to `Bnc\Nav::items()` with its permission.
- **CSS/JS:** `public/admin/assets/admin.css` and `admin.js` only (CSP allows `'self'` only: no inline
  scripts, styles or CDNs). Reuse the existing classes (`card`, `stack`, `btn`, `table-wrap`, `tag`, `flash`).
- **Tests:** add `tests/test_NN_area.php` using the helpers in `tests/lib.php` (`test()`, `assert_*`,
  `Browser`). Cover permissions (a role without the permission gets 403), validation (422), CSRF, and the
  happy path. `php tests/run.php` must stay green.

## Installing on Hostinger (once)

1. hPanel → Databases → create a MySQL database and user.
2. Upload `app/` as `domains/booknilecruises.net/bnc-app/` and `public/admin/` as `public_html/admin/`.
3. Create `domains/booknilecruises.net/bnc-config.php` from `config.sample.php` (database, a long random
   `install_token`, `images_dir`).
4. Open `https://booknilecruises.net/admin/install`, enter the token, your name, email and password.
5. Remove `install_token` from the config file.

After later updates, the owner sees an "apply database update" button on the dashboard when new migrations ship.
