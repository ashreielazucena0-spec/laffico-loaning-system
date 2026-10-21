<?php

namespace App\Controllers;

use App\Libraries\DataStore;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Serves uploaded ordinance / resolution files. They live in writable/,
 * outside the public web root, so they can only be reached through here
 * (and only by a logged-in account — see the route filter).
 */
class Files extends BaseController
{
    public function document(string $id)
    {
        $doc = DataStore::find('documents', $id);
        if (! $doc || empty($doc['file_path'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . 'uploads/documents/' . basename($doc['file_path']);
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->download($path, null)
            ->setFileName($doc['file_name'] ?: basename($path));
    }
}
