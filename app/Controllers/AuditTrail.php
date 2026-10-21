<?php

namespace App\Controllers;

use App\Libraries\ApprovalService;
use App\Libraries\LoanService;

class AuditTrail extends BaseController
{
    public function index(): string
    {
        $db  = db_connect();
        $tab = (string) $this->request->getGet('tab');
        $tab = in_array($tab, ['collections', 'approvals', 'activity'], true) ? $tab : 'collections';

        $loans = array_values(array_filter((new LoanService())->allLoans(), static fn ($l) => $l['approval_status'] === 'approved'));
        usort($loans, static fn ($a, $b) => [$a['client_name'], $a['id']] <=> [$b['client_name'], $b['id']]);

        $approvals = $db->query("SELECT a.*, c.name AS client_name FROM approvals a LEFT JOIN clients c ON c.id = a.client_id ORDER BY a.status = 'pending' DESC, a.id DESC LIMIT 200")->getResultArray();
        $svc = new ApprovalService();
        foreach ($approvals as &$a) {
            $docs = $a['entity_type'] === 'loan_release'
                ? $db->table('documents')->where('loan_id', $a['entity_id'])->get()->getResultArray()
                : $db->table('documents')->where('approval_id', $a['id'])->get()->getResultArray();
            $a['docs']    = $docs;
            $a['blocker'] = $svc->blocker($a);
        }
        unset($a);

        $q = trim((string) $this->request->getGet('q'));
        $b = $db->table('audit_logs')->orderBy('id', 'DESC')->limit(300);
        if ($q !== '') {
            $b->groupStart()->like('user_name', $q)->orLike('action', $q)->orLike('detail', $q)->groupEnd();
        }

        return view('audit_trail', [
            'page_title' => 'Audit trail',
            'active'     => 'audit',
            'tab'        => $tab,
            'loans'      => $loans,
            'approvals'  => $approvals,
            'logs'       => $b->get()->getResultArray(),
            'q'          => $q,
        ]);
    }

    public function decide(int $id)
    {
        $decision = $this->request->getPost('decision') === 'approve' ? 'approve' : 'reject';
        $res      = (new ApprovalService())->decide($id, $decision, (string) $this->request->getPost('note'));
        if (! $res['ok']) {
            return $this->fail('audit-trail?tab=approvals', $res['error']);
        }

        return $this->done('audit-trail?tab=approvals', $res['approved'] ? 'Approved. The change is now in effect.' : 'Request rejected.');
    }
}
