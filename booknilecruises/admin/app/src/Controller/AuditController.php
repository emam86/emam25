<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Db;
use Bnc\Request;

final class AuditController extends Controller
{
    public const PER_PAGE = 50;

    public function index(): string
    {
        $entity = Request::str('entity', '', true);
        $actor = Request::str('actor', '', true);
        $page = max(1, Request::int('page', 1, true));
        $where = [];
        $params = [];
        if ($entity !== '') { $where[] = 'entity = ?'; $params[] = $entity; }
        if ($actor !== '') { $where[] = 'actor LIKE ?'; $params[] = '%' . addcslashes($actor, '%_\\') . '%'; }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Db::value("SELECT COUNT(*) FROM audit_log $sqlWhere", $params);
        $rows = Db::all(
            "SELECT * FROM audit_log $sqlWhere ORDER BY id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params
        );
        return $this->view('audit/index', [
            'title' => 'سجل العمليات',
            'rows' => $rows,
            'entities' => array_column(Db::all('SELECT DISTINCT entity FROM audit_log ORDER BY entity'), 'entity'),
            'filters' => ['entity' => $entity, 'actor' => $actor],
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }
}
