<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Audit;
use Bnc\Auth;
use Bnc\Db;
use Bnc\Migrator;
use Bnc\Redirect;

final class DashboardController extends Controller
{
    public function index(): string
    {
        $count = static fn (string $sql): int => (int) Db::value($sql);
        $stats = [
            'الرحلات المنشورة' => $count("SELECT COUNT(*) FROM trips WHERE status = 'published'"),
            'المقالات' => $count("SELECT COUNT(*) FROM posts WHERE status = 'published'"),
            'الصور' => $count('SELECT COUNT(*) FROM media'),
            'استفسارات جديدة' => $count("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"),
        ];
        return $this->view('dashboard', [
            'title' => 'الرئيسية',
            'stats' => $stats,
            'pending' => $this->user()['is_owner'] ? Migrator::pending() : [],
            'recent' => Auth::can('audit.view') ? Db::all('SELECT * FROM audit_log ORDER BY id DESC LIMIT 10') : [],
        ]);
    }

    /** Owner-only: apply database updates shipped with a new version of the panel. */
    public function migrate(): Redirect
    {
        if (!$this->user()['is_owner']) $this->forbidden();
        $applied = Migrator::run();
        Audit::log('migrate', 'system', null, 'تحديث قاعدة البيانات', ['applied' => $applied]);
        return $this->redirect('/', $applied ? 'تم تحديث قاعدة البيانات: ' . implode('، ', $applied) : 'قاعدة البيانات محدثة بالفعل.');
    }
}
