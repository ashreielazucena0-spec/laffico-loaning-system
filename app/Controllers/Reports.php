<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\ReportBuilder;
use CodeIgniter\Exceptions\PageNotFoundException;

class Reports extends BaseController
{
    public function index(): string
    {
        return view('reports', ['page_title' => 'Reports', 'active' => 'reports', 'groups' => ReportBuilder::catalog()]);
    }

    public function view(?string $slug = null)
    {
        $month  = (string) $this->request->getGet('month');
        $report = $slug ? (new ReportBuilder())->build($slug, $month) : null;
        if ($report === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($this->request->getGet('format') === 'csv') {
            Audit::log('Exported report', $report['title'] . ' · ' . $report['period']);
            $out = fopen('php://temp', 'r+');
            fputcsv($out, [$report['title'], $report['period']]);
            foreach ($report['sections'] as $s) {
                fputcsv($out, []);
                if (! empty($s['heading'])) {
                    fputcsv($out, [$s['heading']]);
                }
                fputcsv($out, $s['columns']);
                foreach ($s['rows'] as $r) {
                    fputcsv($out, $r);
                }
                if (! empty($s['totals'])) {
                    fputcsv($out, $s['totals']);
                }
            }
            rewind($out);
            $csv = "\xEF\xBB\xBF" . stream_get_contents($out);
            fclose($out);

            return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $slug . '-' . $report['month'] . '.csv"')
                ->setBody($csv);
        }

        Audit::log('Generated report', $report['title'] . ' · ' . $report['period']);

        return view('report', ['page_title' => $report['title'], 'active' => 'reports', 'report' => $report]);
    }
}
