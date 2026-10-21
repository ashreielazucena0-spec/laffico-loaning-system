<?php

namespace App\Controllers;

use App\Libraries\BackupService;

class Backups extends BaseController
{
    public function index(): string
    {
        return view('backups', ['page_title' => 'Backups', 'active' => 'backups', 'files' => (new BackupService())->list()]);
    }

    public function create()
    {
        $res = (new BackupService())->create(current_user_name(), trim((string) $this->request->getPost('note')));

        return $res['ok'] ? $this->done('backups', 'Backup ' . $res['name'] . ' created.') : $this->fail('backups', $res['error']);
    }

    public function download(string $name)
    {
        $p = (new BackupService())->path($name);
        if ($p === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($p, null, true)->setFileName($name);
    }

    public function restore()
    {
        $res = (new BackupService())->restore((string) $this->request->getPost('name'), current_user_name());
        if (! $res['ok']) {
            return $this->fail('backups', $res['error']);
        }

        return $this->done('backups', 'Database restored. A safety copy of the previous data was saved as ' . $res['safety'] . '.');
    }

    public function delete()
    {
        $ok = (new BackupService())->delete((string) $this->request->getPost('name'), current_user_name());

        return $ok ? $this->done('backups', 'Backup deleted.') : $this->fail('backups', 'Backup not found.');
    }
}
