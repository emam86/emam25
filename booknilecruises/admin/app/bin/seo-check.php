<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$report = (new \Bnc\Seo\Checker())->run();
if (in_array('--email', $argv, true)) \Bnc\Seo\ReportMailer::send($report);
echo 'SEO report #' . $report['id'] . ': ' . $report['error_count'] . ' errors, ' . $report['warning_count'] . " warnings\n";
exit(0);
