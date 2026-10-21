<?php

namespace App\Controllers;

use App\Libraries\DocumentService;

class Documents extends BaseController
{
    public function upload()
    {
        $db   = db_connect();
        $cid  = (int) $this->request->getPost('client_id');
        $c    = $db->table('clients')->where('id', $cid)->get()->getRowArray();
        if (! $c) {
            return $this->fail('accounts', 'Client not found.');
        }
        $back   = 'accounts/detail/' . $c['acct_no'] . '?tab=docs';
        $loanId = (int) $this->request->getPost('loan_id') ?: null;
        if ($loanId && ! $db->table('loans')->where('id', $loanId)->where('client_id', $cid)->countAllResults()) {
            return $this->fail($back, 'That loan does not belong to this client.');
        }

        $res = (new DocumentService())->store($this->request->getFile('document'), [
            'client_id' => $cid,
            'loan_id'   => $loanId,
            'doc_type'  => (string) $this->request->getPost('doc_type'),
            'title'     => trim((string) $this->request->getPost('title')),
        ], current_user_name());

        return $res['ok'] ? $this->done($back, 'Document uploaded.') : $this->fail($back, $res['error']);
    }

    public function file(int $id)
    {
        $d = db_connect()->table('documents')->where('id', $id)->get()->getRowArray();
        $p = $d ? WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $d['stored_name'] : '';
        if (! $d || ! is_file($p)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Document not found.');
        }

        return $this->response->download($p, null, true)->setFileName($d['original_name']);
    }
}
