<?php

namespace App\Libraries;

/**
 * Approval workflow: a loan release, a restructuring or a transaction
 * cancellation does not take effect until the right role signs off.
 *
 *   loan_release     → Manager
 *   loan_restructure → Manager
 *   txn_cancel       → Manager (an Employee can only request)
 *
 * Nobody may approve their own request.
 */
class ApprovalService
{
    protected $db;

    public function __construct()
    {
        helper('loaning');
        $this->db = db_connect();
    }

    public function request(string $type, int $entityId, ?int $clientId, string $summary, float $amount, string $requiredRole, array $payload = []): int
    {
        $session = null;
        try {
            $session = session();
        } catch (\Throwable $e) {
        }

        $this->db->table('approvals')->insert([
            'entity_type'     => $type,
            'entity_id'       => $entityId,
            'client_id'       => $clientId,
            'summary'         => mb_substr($summary, 0, 255),
            'amount'          => $amount,
            'payload'         => $payload ? json_encode($payload) : null,
            'required_role'   => $requiredRole,
            'status'          => 'pending',
            'requested_by'    => current_user_name(),
            'requested_by_id' => $session ? ($session->get('user_id') ?: null) : null,
            'requested_role'  => $session ? ($session->get('role') ?: null) : null,
            'requested_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /** Can the signed-in user decide this approval? Returns null if yes, otherwise the reason. */
    public function blocker(array $a): ?string
    {
        if ($a['status'] !== 'pending') {
            return 'This request was already ' . $a['status'] . '.';
        }
        if (! can('approve.manager')) {
            return 'Only a Manager can sign off on this.';
        }
        if ($a['requested_by_id'] && (int) $a['requested_by_id'] === (int) session()->get('user_id')) {
            return 'You cannot approve your own request.';
        }

        return null;
    }

    public function hasApplicationForm(int $loanId): bool
    {
        return $this->db->table('documents')->where('loan_id', $loanId)->where('doc_type', 'application_for_loan')->countAllResults() > 0;
    }

    public function decide(int $id, string $decision, string $note): array
    {
        $a = $this->db->table('approvals')->where('id', $id)->get()->getRowArray();
        if (! $a) {
            return ['ok' => false, 'error' => 'Request not found.'];
        }
        if ($why = $this->blocker($a)) {
            return ['ok' => false, 'error' => $why];
        }
        $approve = $decision === 'approve';
        $note    = trim($note);
        if (! $approve && $note === '') {
            return ['ok' => false, 'error' => 'Give a reason when rejecting a request.'];
        }

        $loans = new LoanService();
        $who   = current_user_name();

        if ($approve) {
            if ($a['entity_type'] === 'loan_release') {
                if (! $this->hasApplicationForm((int) $a['entity_id'])) {
                    return ['ok' => false, 'error' => 'Attach the signed Application for Loan to this loan before approving it.'];
                }
                $res = $loans->releaseLoan((int) $a['entity_id'], $who);
            } elseif ($a['entity_type'] === 'loan_restructure') {
                $p   = json_decode((string) $a['payload'], true) ?: [];
                $res = $loans->applyRestructure((int) $a['entity_id'], (int) ($p['new_term'] ?? 0), (string) ($p['first_due'] ?? date('Y-m-d')), $who);
            } elseif ($a['entity_type'] === 'txn_cancel') {
                $p   = json_decode((string) $a['payload'], true) ?: [];
                $res = $loans->cancelTransaction((int) $a['entity_id'], (string) ($p['reason'] ?? 'Approved cancellation'), $who);
            } else {
                $res = ['ok' => false, 'error' => 'Unknown request type.'];
            }
            if (! $res['ok']) {
                return $res;
            }
        } else {
            if ($a['entity_type'] === 'loan_release') {
                $loans->rejectLoan((int) $a['entity_id']);
            } elseif ($a['entity_type'] === 'txn_cancel') {
                $this->db->table('transactions')->where('id', (int) $a['entity_id'])->update(['cancel_requested' => 0]);
            }
        }

        $this->db->table('approvals')->where('id', $id)->update([
            'status'        => $approve ? 'approved' : 'rejected',
            'decided_by'    => $who,
            'decided_by_id' => session()->get('user_id') ?: null,
            'decided_role'  => session()->get('role') ?: null,
            'decided_at'    => date('Y-m-d H:i:s'),
            'decision_note' => $note ?: null,
        ]);
        Audit::log(($approve ? 'Approved ' : 'Rejected ') . str_replace('_', ' ', $a['entity_type']), $a['summary'] . ($note ? ' — ' . $note : ''));

        return ['ok' => true, 'approved' => $approve];
    }
}
