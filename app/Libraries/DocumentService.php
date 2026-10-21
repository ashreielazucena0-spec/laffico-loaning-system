<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/** Supporting documents (receipts, vouchers, deposit slips, loan forms ...). */
class DocumentService
{
    protected $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * @param array{client_id?:int,loan_id?:int,transaction_id?:int,approval_id?:int,doc_type?:string,title?:string} $meta
     */
    public function store(?UploadedFile $file, array $meta, string $by): array
    {
        $cfg = config('Loaning');

        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            $err = $file ? $file->getErrorString() : 'No file was selected.';

            return ['ok' => false, 'error' => 'Upload failed: ' . $err . '.'];
        }
        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, $cfg->allowedUploadExt, true)) {
            return ['ok' => false, 'error' => 'Allowed file types: ' . implode(', ', $cfg->allowedUploadExt) . '.'];
        }
        if ($file->getSize() > $cfg->maxUploadKb * 1024) {
            return ['ok' => false, 'error' => 'The file is larger than ' . $cfg->maxUploadKb . ' KB.'];
        }

        $type = (string) ($meta['doc_type'] ?? 'other');
        if (! isset($cfg->docTypes[$type])) {
            $type = 'other';
        }

        $dir = WRITEPATH . 'uploads';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stored   = bin2hex(random_bytes(12)) . '.' . $ext;
        $original = $file->getClientName();
        $size     = $file->getSize();
        $mime     = $file->getClientMimeType();
        $file->move($dir, $stored);

        $this->db->table('documents')->insert([
            'client_id'      => $meta['client_id'] ?? null,
            'loan_id'        => $meta['loan_id'] ?? null,
            'transaction_id' => $meta['transaction_id'] ?? null,
            'approval_id'    => $meta['approval_id'] ?? null,
            'doc_type'       => $type,
            'title'          => ($meta['title'] ?? '') !== '' ? mb_substr((string) $meta['title'], 0, 150) : null,
            'original_name'  => mb_substr($original, 0, 255),
            'stored_name'    => $stored,
            'mime'           => $mime,
            'size'           => $size,
            'uploaded_by'    => $by,
            'uploaded_at'    => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();
        Audit::log('Uploaded supporting document', $cfg->docTypes[$type] . ' — ' . $original, $by);

        return ['ok' => true, 'id' => $id];
    }
}
