<?php
declare(strict_types=1);

use Bnc\{Config, Db, Roles};
use Bnc\Media\Usage;

$mediaOwner = $GLOBALS['ownerBrowser'];
$mediaFile = function (string $name, int $width, int $height, string $format = 'jpg'): array {
    $path = __DIR__ . '/tmp/' . $name;
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 20, 80, 100, 127));
    if ($format === 'png') imagepng($image, $path);
    else imagejpeg($image, $path, 90);
    imagedestroy($image);
    return ['path' => $path, 'name' => $name];
};
$mediaDisk = fn (string $path): string => Config::get('images_dir') . substr($path, strlen('/images'));
$mediaLatest = fn (): array => Db::one('SELECT * FROM media ORDER BY id DESC LIMIT 1');

test('media JPEG is re-encoded, scaled and has WordPress copies', function () use ($mediaOwner, $mediaFile, $mediaDisk, $mediaLatest) {
    $file = $mediaFile('Nile Photo.JPG', 3000, 2000);
    assert_same(303, $mediaOwner->upload('/admin/media/upload', [$file])['status']);
    $row = $mediaLatest();
    assert_same('/images/' . date('Y/m') . '/nile-photo.jpg', $row['path']);
    assert_same(2560, (int) $row['width']);
    assert_true(in_array((int) $row['height'], [1706, 1707], true));
    assert_same('image/jpeg', $row['mime']);
    assert_same(filesize($mediaDisk($row['path'])), (int) $row['filesize']);
    assert_same((int) Db::value("SELECT id FROM users WHERE email = 'owner@example.com'"), (int) $row['uploaded_by']);
    $sizes = json_decode($row['sizes'], true, 512, JSON_THROW_ON_ERROR);
    assert_same(['medium', 'medium_large', 'large', '1536x1536'], array_keys($sizes));
    foreach (['medium' => 300, 'medium_large' => 768, 'large' => 1024, '1536x1536' => 1536] as $key => $width) {
        $size = $sizes[$key];
        assert_same($width, $size['width']);
        assert_same('image/jpeg', $size['mime_type']);
        assert_same("nile-photo-{$size['width']}x{$size['height']}.jpg", $size['file']);
        $info = getimagesize(dirname($mediaDisk($row['path'])) . '/' . $size['file']);
        assert_same([$size['width'], $size['height']], [$info[0], $info[1]]);
    }
    $GLOBALS['largeMedia'] = $row;
});

test('small transparent PNG stays PNG with no resized copies', function () use ($mediaOwner, $mediaFile, $mediaLatest, $mediaDisk) {
    assert_same(303, $mediaOwner->upload('/admin/media/upload', [$mediaFile('Small.png', 200, 100, 'png')])['status']);
    $row = $mediaLatest();
    assert_same('image/png', $row['mime']);
    assert_same([], json_decode($row['sizes'], true));
    $image = imagecreatefrompng($mediaDisk($row['path']));
    assert_same(127, (imagecolorat($image, 0, 0) >> 24) & 127);
    imagedestroy($image);
    $GLOBALS['smallMedia'] = $row;
});

test('disguised PHP is rejected; appended PHP is stripped and collisions get a suffix', function () use ($mediaOwner, $mediaFile, $mediaLatest, $mediaDisk) {
    $fake = __DIR__ . '/tmp/fake.jpg';
    file_put_contents($fake, '<?php echo 1; ?>');
    $count = (int) Db::value('SELECT COUNT(*) FROM media');
    $before = glob(Config::get('images_dir') . '/' . date('Y/m') . '/*');
    $mediaOwner->upload('/admin/media/upload', [['path' => $fake, 'name' => 'photo.jpg', 'mime' => 'image/jpeg']]);
    assert_same($count, (int) Db::value('SELECT COUNT(*) FROM media'));
    assert_same($before, glob(Config::get('images_dir') . '/' . date('Y/m') . '/*'));
    assert_contains('JPEG', $mediaOwner->get('/admin/media')['body']);
    $valid = $mediaFile('Nile Photo.JPG', 200, 100);
    file_put_contents($valid['path'], '<?php echo 1; ?>', FILE_APPEND);
    $mediaOwner->upload('/admin/media/upload', [$valid]);
    $row = $mediaLatest();
    assert_contains('/nile-photo-1.jpg', $row['path']);
    assert_not_contains('<?php', file_get_contents($mediaDisk($row['path'])));
});

test('media permissions and multipart CSRF are enforced', function () use ($mediaOwner, $mediaFile, $base) {
    Db::insert('users', ['name' => 'Sales', 'email' => 'sales-media@example.com', 'password_hash' => password_hash('password-sales-123', PASSWORD_DEFAULT), 'role_id' => Roles::bySlug('sales')['id']]);
    foreach ([['sales-media@example.com', 'password-sales-123', 403], ['editor@example.com', 'password-editor-2', 200]] as [$email, $pass, $status]) {
        $browser = new Browser($base);
        $browser->post('/admin/login', ['email' => $email, 'password' => $pass]);
        assert_same($status, $browser->get('/admin/media')['status']);
        assert_same($status, $browser->get('/admin/media/picker.json')['status']);
        $id = $GLOBALS['smallMedia']['id'];
        assert_same($status, $browser->get("/admin/media/$id")['status']);
        assert_same($status === 200 ? 303 : 403, $browser->upload('/admin/media/upload', [$mediaFile('Role.jpg', 100, 100)])['status']);
        assert_same(403, $browser->post("/admin/media/$id/delete")['status']);
    }
    assert_same(400, $mediaOwner->upload('/admin/media/upload', [$mediaFile('Csrf.jpg', 100, 100)], [], false)['status']);
});

test('picker returns JSON, filters literal searches and paginates', function () use ($mediaOwner) {
    $r = $mediaOwner->get('/admin/media/picker.json?q=nile-photo-1');
    assert_same(200, $r['status']);
    assert_contains('Content-Type: application/json', $r['headers']);
    $data = json_decode($r['body'], true, 512, JSON_THROW_ON_ERROR);
    assert_same(1, count($data['items']));
    assert_contains('nile-photo-1', $data['items'][0]['path']);
    assert_same($data['items'][0]['path'], $data['items'][0]['thumb']);
    assert_same(1, $data['page']);
    assert_same(1, $data['pages']);
    assert_same([], json_decode($mediaOwner->get('/admin/media/picker.json?q=%25')['body'], true)['items']);
    $r = json_decode($mediaOwner->get('/admin/media/picker.json?q=nile-photo.jpg')['body'], true);
    assert_contains('-300x', $r['items'][0]['thumb']);
});

test('alt text is validated, audited and escaped on output', function () use ($mediaOwner) {
    $id = $GLOBALS['smallMedia']['id'];
    assert_same(422, $mediaOwner->post("/admin/media/$id/edit", ['alt' => str_repeat('a', 256)])['status']);
    $alt = '<img src=x onerror=alert(1)>';
    assert_same(303, $mediaOwner->post("/admin/media/$id/edit", ['alt' => $alt])['status']);
    $r = $mediaOwner->get("/admin/media/$id");
    assert_contains(e($alt), $r['body']);
    assert_not_contains($alt, $r['body']);
    assert_contains(e($alt), $mediaOwner->get('/admin/media')['body']);
    assert_same($alt, Db::value('SELECT alt FROM media WHERE id = ?', [$id]));
    assert_true((int) Db::value("SELECT COUNT(*) FROM audit_log WHERE entity = 'media' AND action = 'update'") > 0);
});

test('usage finds featured images, galleries and embedded main or resized images', function () {
    $row = $GLOBALS['largeMedia'];
    $sizes = json_decode($row['sizes'], true);
    $trip = Db::insert('trips', ['slug' => 'media-usage', 'title' => 'رحلة الصور', 'image_id' => $row['id']]);
    foreach ([['gallery' => json_encode([(int) $row['id']])], ['overview_html' => '<img src="' . $row['path'] . '">'], ['itinerary' => json_encode([['html' => $sizes['medium']['file']]])], ['faqs' => json_encode([['a' => $sizes['large']['file']]])]] as $fields) {
        Db::update('trips', array_replace(['image_id' => null, 'gallery' => null, 'overview_html' => null, 'itinerary' => null, 'faqs' => null], $fields), 'id = ?', [$trip]);
        $uses = Usage::of($row);
        assert_same(1, count($uses));
        assert_same('/trips/' . $trip . '/edit', $uses[0]['url']);
    }
    Db::run('DELETE FROM trips WHERE id = ?', [$trip]);
    $post = Db::insert('posts', ['slug' => 'media-post', 'url' => '/media-post/', 'title' => 'مقال الصور', 'content_html' => '', 'image_id' => $row['id']]);
    assert_same('/posts/' . $post . '/edit', Usage::of($row)[0]['url']);
    Db::update('posts', ['image_id' => null, 'content_html' => $sizes['medium_large']['file']], 'id = ?', [$post]);
    assert_same(1, count(Usage::of($row)));
    Db::run('DELETE FROM posts WHERE id = ?', [$post]);
});

test('used media cannot be deleted; unused media deletes its row and all files', function () use ($mediaOwner, $mediaDisk) {
    $row = $GLOBALS['largeMedia'];
    $id = $row['id'];
    $trip = Db::insert('trips', ['slug' => 'delete-media', 'title' => 'رحلة مستخدمة', 'image_id' => $id]);
    $mediaOwner->post("/admin/media/$id/delete");
    assert_contains('رحلة مستخدمة', $mediaOwner->get("/admin/media/$id")['body']);
    assert_true(Db::one('SELECT * FROM media WHERE id = ?', [$id]) !== null);
    Db::run('DELETE FROM trips WHERE id = ?', [$trip]);
    assert_same(303, $mediaOwner->post("/admin/media/$id/delete")['status']);
    assert_same(null, Db::one('SELECT * FROM media WHERE id = ?', [$id]));
    assert_true(!file_exists($mediaDisk($row['path'])));
    foreach (json_decode($row['sizes'], true) as $size) assert_true(!file_exists(dirname($mediaDisk($row['path'])) . '/' . $size['file']));
    assert_true((int) Db::value("SELECT COUNT(*) FROM audit_log WHERE entity = 'media' AND action = 'delete'") > 0);
});


test('GIF and WebP uploads preserve format and transparency', function () use ($mediaOwner, $mediaFile, $mediaLatest, $mediaDisk) {
    foreach (['gif', 'webp'] as $format) {
        $source = $mediaFile("transparent-$format.png", 400, 200, 'png');
        $image = imagecreatefrompng($source['path']);
        $path = __DIR__ . '/tmp/transparent.' . $format;
        if ($format === 'gif') {
            imagecolortransparent($image, imagecolorallocatealpha($image, 20, 80, 100, 127));
            imagegif($image, $path);
        } else imagewebp($image, $path, 80);
        imagedestroy($image);
        assert_same(303, $mediaOwner->upload('/admin/media/upload', [['path' => $path, 'name' => "transparent-$format.jpg"]])['status']);
        $row = $mediaLatest();
        assert_same('image/' . $format, $row['mime']);
        assert_contains('.' . $format, $row['path']);
        $paths = [$mediaDisk($row['path'])];
        foreach (json_decode($row['sizes'], true) as $size) $paths[] = dirname($mediaDisk($row['path'])) . '/' . $size['file'];
        foreach ($paths as $saved) {
            $decoded = $format === 'gif' ? imagecreatefromgif($saved) : imagecreatefromwebp($saved);
            $color = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
            assert_same(127, $color['alpha']);
            imagedestroy($decoded);
        }
    }
});

test('JPEG EXIF orientation is applied before saving', function () use ($mediaOwner, $mediaFile, $mediaLatest, $mediaDisk) {
    $file = $mediaFile('orientation.jpg', 300, 100);
    $exif = "Exif\0\0II" . pack('vV', 42, 8) . pack('v', 1) . pack('vvV', 0x112, 3, 1) . pack('v', 6) . "\0\0" . pack('V', 0);
    $jpeg = file_get_contents($file['path']);
    file_put_contents($file['path'], substr($jpeg, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));
    assert_same(6, exif_read_data($file['path'])['Orientation']);
    $mediaOwner->upload('/admin/media/upload', [$file]);
    $row = $mediaLatest();
    assert_same([100, 300], [(int) $row['width'], (int) $row['height']]);
    assert_not_contains('Exif', file_get_contents($mediaDisk($row['path'])));
});

test('mixed uploads report per-file outcomes and reject oversized dimensions and bytes', function () use ($mediaOwner, $mediaFile) {
    $good = $mediaFile('mixed-good.jpg', 100, 100);
    $bad = __DIR__ . '/tmp/mixed-bad.jpg';
    file_put_contents($bad, 'not an image');
    $before = (int) Db::value('SELECT COUNT(*) FROM media');
    $mediaOwner->upload('/admin/media/upload', [$good, ['path' => $bad, 'name' => 'mixed-bad.jpg']]);
    assert_same($before + 1, (int) Db::value('SELECT COUNT(*) FROM media'));
    $body = $mediaOwner->get('/admin/media')['body'];
    assert_contains('تم رفع الصورة: mixed-good.jpg', $body);
    assert_contains('mixed-bad.jpg: اختر صورة', $body);

    $huge = $mediaFile('huge.png', 200, 100, 'png');
    $png = file_get_contents($huge['path']);
    $ihdr = pack('NN', 7000, 6000) . substr($png, 24, 5);
    file_put_contents($huge['path'], substr($png, 0, 16) . $ihdr . pack('N', crc32('IHDR' . $ihdr)) . substr($png, 33));
    $mediaOwner->upload('/admin/media/upload', [$huge]);
    assert_contains('40 ميجابكسل', $mediaOwner->get('/admin/media')['body']);

    $configPath = __DIR__ . '/tmp/config.php';
    $original = file_get_contents($configPath);
    $config = require $configPath;
    $config['max_upload_mb'] = 0.1;
    file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';');
    try {
        file_put_contents($good['path'], str_repeat('x', 200_000), FILE_APPEND);
        clearstatcache(true, $good['path']);
        assert_true(filesize($good['path']) > 200_000);
        $mediaOwner->upload('/admin/media/upload', [$good]);
        $body = $mediaOwner->get('/admin/media')['body'];
        assert_contains('0.1 ميجابايت', $body, 'test config read by server');
        assert_contains('حجم الصورة يتجاوز', $body);
        assert_same($before + 1, (int) Db::value('SELECT COUNT(*) FROM media'));
    } finally {
        file_put_contents($configPath, $original);
    }
});

test('failed audit rolls back uploads and restores files on failed deletion', function () use ($mediaOwner, $mediaFile, $mediaDisk) {
    $before = glob(Config::get('images_dir') . '/' . date('Y/m') . '/*');
    $count = (int) Db::value('SELECT COUNT(*) FROM media');
    Db::run("CREATE TRIGGER media_test_audit_failure BEFORE INSERT ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'");
    try {
        $mediaOwner->upload('/admin/media/upload', [$mediaFile('rollback.jpg', 1200, 800)]);
        assert_same($count, (int) Db::value('SELECT COUNT(*) FROM media'));
        assert_same($before, glob(Config::get('images_dir') . '/' . date('Y/m') . '/*'));
        $id = $GLOBALS['smallMedia']['id'];
        $mediaOwner->post("/admin/media/$id/delete");
        assert_true(Db::one('SELECT * FROM media WHERE id = ?', [$id]) !== null);
        assert_true(is_file($mediaDisk($GLOBALS['smallMedia']['path'])));
        assert_same([], glob(Config::get('images_dir') . '/' . date('Y/m') . '/.delete-*'));
    } finally {
        Db::run('DROP TRIGGER media_test_audit_failure');
    }
});

test('deletion never follows paths outside images_dir', function () use ($mediaOwner) {
    $external = __DIR__ . '/tmp/keep.txt';
    file_put_contents($external, 'keep');
    $folder = Config::get('images_dir') . '/' . date('Y/m');
    symlink($external, $folder . '/outside.jpg');
    $id = Db::insert('media', ['path' => '/images/' . date('Y/m') . '/outside.jpg', 'sizes' => json_encode(['medium' => ['file' => '../../../keep.txt']])]);
    assert_same(303, $mediaOwner->post("/admin/media/$id/delete")['status']);
    assert_same('keep', file_get_contents($external));
    unlink($folder . '/outside.jpg');
    $id = Db::insert('media', ['path' => '/images/../keep.txt']);
    $mediaOwner->post("/admin/media/$id/delete");
    assert_same('keep', file_get_contents($external));
});

test('library and picker paginate 48 images and search alt text', function () use ($mediaOwner, $mediaFile) {
    $source = $mediaFile('pagination.jpg', 10, 10);
    for ($batch = 0; $batch < 3; $batch++) {
        $files = [];
        for ($i = $batch * 20; $i < min(50, ($batch + 1) * 20); $i++) $files[] = ['path' => $source['path'], 'name' => "paginated-$i.jpg"];
        assert_same(303, $mediaOwner->upload('/admin/media/upload', $files)['status']);
    }
    $first = json_decode($mediaOwner->get('/admin/media/picker.json?q=paginated-')['body'], true);
    $second = json_decode($mediaOwner->get('/admin/media/picker.json?q=paginated-&page=2')['body'], true);
    assert_same(48, count($first['items']));
    assert_same(2, count($second['items']));
    assert_same(2, $second['page']);
    assert_same(2, $second['pages']);
    assert_contains('aria-current="page">2', $mediaOwner->get('/admin/media?q=paginated-&page=2')['body']);
    $id = $first['items'][0]['id'];
    $mediaOwner->post("/admin/media/$id/edit", ['alt' => 'بحث بديل فقط']);
    $results = json_decode($mediaOwner->get('/admin/media/picker.json?q=' . rawurlencode('بحث بديل فقط'))['body'], true);
    assert_same($id, $results['items'][0]['id']);
    assert_true((int) Db::value("SELECT COUNT(*) FROM audit_log WHERE entity = 'media' AND action = 'create'") >= 50);
});


test('panel images_url can differ while stored paths and public URLs stay canonical', function () use ($mediaOwner, $mediaFile, $mediaLatest) {
    $configPath = __DIR__ . '/tmp/config.php';
    $original = file_get_contents($configPath);
    $config = require $configPath;
    $config['images_url'] = '/cdn/photos';
    file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';');
    try {
        $mediaOwner->upload('/admin/media/upload', [$mediaFile('configured-url.jpg', 400, 200)]);
        $row = $mediaLatest();
        assert_same('/images/' . date('Y/m') . '/configured-url.jpg', $row['path']);
        $body = $mediaOwner->get('/admin/media/' . $row['id'])['body'];
        assert_contains('src="/cdn/photos/' . date('Y/m') . '/configured-url.jpg"', $body);
        assert_contains('data-copy="https://booknilecruises.net' . $row['path'] . '"', $body);
        assert_contains('src="/cdn/photos/' . date('Y/m') . '/configured-url-300x150.jpg"', $mediaOwner->get('/admin/media')['body']);
    } finally {
        file_put_contents($configPath, $original);
    }
});
