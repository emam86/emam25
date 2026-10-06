<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Audit, Config, Db, Redirect, Request, Settings};
use Bnc\Enquiries\{Csv, EnquiryService};

final class EnquiriesController extends Controller
{
    public function index(array $errors = [], ?string $notify = null): string
    {
        $q = Request::str('q', '', true); $status = Request::str('status', '', true);
        [$where, $params] = EnquiryService::filter($status, $q);
        $total = (int) Db::value("SELECT COUNT(*) FROM enquiries WHERE $where", $params);
        $pages = max(1, (int) ceil($total / 50)); $page = min($pages, max(1, Request::int('page', 1, true)));
        $rows = Db::all("SELECT * FROM enquiries WHERE $where ORDER BY id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $params);
        $notify ??= (string) Settings::get('enquiry_notify_email', (string) Config::get('mail.notify', ''));
        return $this->view('enquiries/index', ['title' => 'الاستفسارات'] + compact('rows', 'total', 'pages', 'page', 'q', 'status', 'notify', 'errors'));
    }

    public function show(int $id): string
    {
        return $this->details(EnquiryService::find($id));
    }

    public function update(int $id): string|Redirect
    {
        $row = EnquiryService::find($id);
        $status = Request::str('status'); $notes = Request::str('notes');
        if (!isset(EnquiryService::STATUSES[$status]) || mb_strlen($notes) > 10000) {
            http_response_code(422);
            return $this->details(array_replace($row, compact('status', 'notes')), ['اختر حالة صحيحة واكتب ملاحظات حتى ١٠٠٠٠ حرف.']);
        }
        Db::tx(function () use ($id, $status, $notes): void {
            Db::update('enquiries', compact('status', 'notes'), 'id = ?', [$id]);
            Audit::log('update', 'enquiry', $id, 'تحديث حالة وملاحظات الاستفسار');
        });
        return $this->redirect("/enquiries/$id", 'تم حفظ الاستفسار.');
    }

    public function delete(int $id): Redirect
    {
        EnquiryService::find($id);
        Db::tx(function () use ($id): void {
            Db::run('DELETE FROM enquiries WHERE id = ?', [$id]);
            Audit::log('delete', 'enquiry', $id, 'حذف الاستفسار');
        });
        return $this->redirect('/enquiries', 'تم حذف الاستفسار.');
    }

    public function notify(): string|Redirect
    {
        $notify = Request::str('enquiry_notify_email');
        if ($notify !== '' && (mb_strlen($notify) > 190 || !filter_var($notify, FILTER_VALIDATE_EMAIL))) {
            http_response_code(422); return $this->index(['اكتب بريدًا إلكترونيًا صحيحًا.'], $notify);
        }
        Db::tx(function () use ($notify): void {
            Settings::set('enquiry_notify_email', $notify);
            Audit::log('update', 'settings', null, 'تحديث بريد إشعارات الاستفسارات');
        });
        return $this->redirect('/enquiries', 'تم حفظ بريد الإشعارات.');
    }

    public function csv(): Csv
    {
        [$where, $params] = EnquiryService::filter(Request::str('status', '', true), Request::str('q', '', true));
        $out = fopen('php://temp', 'w+'); fwrite($out, "\xEF\xBB\xBF");
        $columns = ['id', 'name', 'email', 'phone', 'trip_id', 'trip_title', 'travel_date', 'adults', 'children', 'message', 'page_url', 'channel', 'status', 'notes', 'ip', 'created_at', 'updated_at'];
        fputcsv($out, $columns, ',', '"', '');
        foreach (Db::all("SELECT * FROM enquiries WHERE $where ORDER BY id DESC", $params) as $row) fputcsv($out, array_map(fn ($k) => EnquiryService::csvCell($row[$k]), $columns), ',', '"', '');
        rewind($out); $body = stream_get_contents($out); fclose($out);
        return new Csv($body);
    }

    private function details(array $row, array $errors = []): string
    {
        return $this->view('enquiries/show', ['title' => 'تفاصيل الاستفسار'] + compact('row', 'errors'));
    }
}
