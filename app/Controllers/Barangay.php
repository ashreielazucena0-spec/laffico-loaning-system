<?php

namespace App\Controllers;

use App\Libraries\DataStore;
use CodeIgniter\HTTP\RedirectResponse;

class Barangay extends BaseController
{
    protected const PUROKS = ['Purok Acasia', 'Purok Malipayon', 'Purok Paradise', 'Purok Subis', 'Purok Basakan', 'Purok Falcata', 'Purok Kalapukan'];

    /** File types accepted for ordinances / resolutions. */
    protected const DOC_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'pdf'];

    /**
     * Field/table schema for every module. This drives the generic
     * list + add/edit modal + delete views (see barangay/module.php)
     * the same way the original prototype drove its UI off one seed object.
     */
    protected function schemas(): array
    {
        $residentOptions = fn () => $this->optionMap('residents', fn ($r) => trim($r['first'] . ' ' . $r['last']) . ' (' . $r['id'] . ')');
        $typeOptions     = fn () => array_column(DataStore::all('certificate_types'), 'name', 'name');

        // "HH-001 — Juan Dela Cruz · Purok Falcata, Rizal Street"
        $householdOptions = function () {
            $names = $this->optionMap('residents', fn ($r) => trim($r['first'] . ' ' . $r['last']));
            $opts  = ['NEW' => '+ Register a NEW household (this resident becomes the head)'];
            foreach (DataStore::all('households') as $h) {
                $where = trim(($h['purok'] ?? '') . (($h['street'] ?? '') !== '' ? ', ' . $h['street'] : ''), ', ');
                $opts[$h['id']] = $h['id'] . ' — ' . ($names[$h['head']] ?? $h['head']) . ($where !== '' ? ' · ' . $where : '');
            }
            return $opts;
        };

        $puroks = self::PUROKS;

        return [
            'residents' => [
                'title' => 'Residents',
                'icon'  => 'bi-people',
                'idPrefix' => 'R-',
                'fields' => [
                    'first'      => ['label' => 'First Name', 'type' => 'text', 'required' => true],
                    'middle'     => ['label' => 'Middle Name', 'type' => 'text'],
                    'last'       => ['label' => 'Last Name', 'type' => 'text', 'required' => true],
                    'birth'      => ['label' => 'Birthdate', 'type' => 'date'],
                    'sex'        => ['label' => 'Sex', 'type' => 'select', 'options' => ['Male', 'Female']],
                    'civil'      => ['label' => 'Civil Status', 'type' => 'select', 'options' => ['Single', 'Married', 'Widowed', 'Separated']],
                    'contact'    => ['label' => 'Contact No.', 'type' => 'text'],
                    'purok'      => ['label' => 'Purok', 'type' => 'select', 'options' => $puroks],
                    'occupation' => ['label' => 'Occupation', 'type' => 'text'],
                    'voter'      => ['label' => 'Voter Status', 'type' => 'select', 'options' => ['Registered', 'Not Registered']],
                    'senior'     => ['label' => 'Senior Citizen', 'type' => 'select', 'options' => ['Yes', 'No']],
                    'pwd'        => ['label' => 'PWD', 'type' => 'select', 'options' => ['Yes', 'No']],
                    // handled by storeResident()/updateResident(), not the generic loop
                    'household_id' => ['label' => 'Registered Household', 'type' => 'select', 'options' => $householdOptions, 'required' => true, 'skip' => true],
                    'hh_street'      => ['label' => 'New Household — Street Name', 'type' => 'text', 'skip' => true, 'showWhen' => 'household_id=NEW'],
                    'hh_in_building' => ['label' => 'Living in a building?', 'type' => 'select', 'options' => ['No', 'Yes'], 'skip' => true, 'showWhen' => 'household_id=NEW'],
                    'hh_building'    => ['label' => 'Building Name', 'type' => 'text', 'skip' => true, 'showWhen' => 'household_id=NEW&hh_in_building=Yes'],
                    'hh_floor'       => ['label' => 'Floor', 'type' => 'text', 'skip' => true, 'showWhen' => 'household_id=NEW&hh_in_building=Yes'],
                ],
                'columns' => ['first', 'last', 'sex', 'civil', 'purok', 'household_id', 'contact'],
            ],
            'households' => [
                'title' => 'Households',
                'icon'  => 'bi-house',
                'idPrefix' => 'HH-',
                'fields' => [
                    'head'        => ['label' => 'Household Head', 'type' => 'select', 'options' => $residentOptions, 'required' => true],
                    'purok'       => ['label' => 'Purok', 'type' => 'select', 'options' => $puroks],
                    'street'      => ['label' => 'Street Name', 'type' => 'text', 'required' => true],
                    'in_building' => ['label' => 'Living in a building?', 'type' => 'select', 'options' => ['No', 'Yes']],
                    'building'    => ['label' => 'Building Name', 'type' => 'text', 'showWhen' => 'in_building=Yes'],
                    'floor'       => ['label' => 'Floor', 'type' => 'text', 'showWhen' => 'in_building=Yes'],
                    'members'     => ['label' => 'No. of Members', 'type' => 'number'],
                ],
                'columns' => ['head', 'purok', 'street', 'building', 'floor', 'members'],
            ],
            'certificates' => [
                'title' => 'Released Certificates',
                'icon'  => 'bi-file-earmark-text',
                'idPrefix' => 'CERT-',
                'fields' => [
                    'resident' => ['label' => 'Resident', 'type' => 'select', 'options' => $residentOptions],
                    'type'     => ['label' => 'Certificate Type', 'type' => 'select', 'options' => $typeOptions],
                    'purpose'  => ['label' => 'Purpose', 'type' => 'text'],
                    'date'     => ['label' => 'Date Issued', 'type' => 'date'],
                    'amount'   => ['label' => 'Amount (₱)', 'type' => 'number'],
                ],
                'columns' => ['resident', 'type', 'purpose', 'date', 'amount'],
            ],
            'certificate_types' => [
                'title' => 'Certificate Types',
                'icon'  => 'bi-patch-plus',
                'idPrefix' => 'CT-',
                'fields' => [
                    'name'        => ['label' => 'Certificate Name', 'type' => 'text', 'required' => true],
                    'fee'         => ['label' => 'Fee (₱)', 'type' => 'number', 'required' => true],
                    'description' => ['label' => 'Description / Requirements', 'type' => 'text'],
                ],
                'columns' => ['name', 'fee', 'description'],
            ],
            'requests' => [
                'title' => 'Certificate Requests',
                'icon'  => 'bi-card-checklist',
                'idPrefix' => 'REQ-',
                'fields' => [
                    'resident' => ['label' => 'Resident', 'type' => 'select', 'options' => $residentOptions],
                    'type'     => ['label' => 'Certificate Type', 'type' => 'select', 'options' => $typeOptions],
                    'purpose'  => ['label' => 'Purpose', 'type' => 'text'],
                    'date'     => ['label' => 'Date Requested', 'type' => 'date'],
                    'status'   => ['label' => 'Status', 'type' => 'select', 'options' => ['Pending', 'Processing', 'Ready', 'Released']],
                ],
                'columns' => ['resident', 'type', 'purpose', 'date', 'status'],
            ],
            'cases' => [
                'title' => 'Complaints / Blotter',
                'icon'  => 'bi-shield-exclamation',
                'idPrefix' => 'B-' . date('Y') . '-',
                'fields' => [
                    'complainant' => ['label' => 'Complainant', 'type' => 'select', 'options' => $residentOptions],
                    'respondent'  => ['label' => 'Respondent', 'type' => 'select', 'options' => $residentOptions],
                    'nature'      => ['label' => 'Nature of Complaint', 'type' => 'text'],
                    'date'        => ['label' => 'Date Filed', 'type' => 'date'],
                    'status'      => ['label' => 'Status', 'type' => 'select', 'options' => ['Pending', 'Resolved']],
                    'details'     => ['label' => 'Details', 'type' => 'textarea'],
                    'action'      => ['label' => 'Action Taken', 'type' => 'textarea'],
                ],
                'columns' => ['complainant', 'respondent', 'nature', 'date', 'status'],
            ],
            'meetings' => [
                'title' => 'Meetings & Events',
                'icon'  => 'bi-calendar-event',
                'idPrefix' => 'M-',
                'fields' => [
                    'title' => ['label' => 'Title', 'type' => 'text', 'required' => true],
                    'date'  => ['label' => 'Date', 'type' => 'date'],
                    'time'  => ['label' => 'Time', 'type' => 'time'],
                    'venue' => ['label' => 'Venue', 'type' => 'text'],
                ],
                'columns' => ['title', 'date', 'time', 'venue'],
            ],
            'documents' => [
                'title' => 'Ordinances & Resolutions',
                'icon'  => 'bi-journal-text',
                'idPrefix' => 'DOC-',
                'fields' => [
                    'number' => ['label' => 'Document No.', 'type' => 'text'],
                    'title'  => ['label' => 'Title', 'type' => 'text', 'required' => true],
                    'type'   => ['label' => 'Type', 'type' => 'select', 'options' => ['Resolution', 'Ordinance']],
                    'date'   => ['label' => 'Date Approved', 'type' => 'date'],
                    'file'   => ['label' => 'Digital Copy (Word, Excel or PDF)', 'type' => 'file', 'skip' => true],
                ],
                'columns' => ['number', 'title', 'type', 'date', 'file'],
            ],
            'payments' => [
                'title' => 'Payments',
                'icon'  => 'bi-cash-stack',
                'idPrefix' => 'OR-',
                'fields' => [
                    'resident' => ['label' => 'Resident', 'type' => 'select', 'options' => $residentOptions],
                    'type'     => ['label' => 'Payment For', 'type' => 'text'],
                    'amount'   => ['label' => 'Amount (₱)', 'type' => 'number'],
                    'date'     => ['label' => 'Date Paid', 'type' => 'date'],
                ],
                'columns' => ['resident', 'type', 'amount', 'date'],
            ],
            'officials' => [
                'title' => 'Officials',
                'icon'  => 'bi-person-badge',
                'idPrefix' => 'O-',
                'fields' => [
                    'name'     => ['label' => 'Full Name', 'type' => 'text'],
                    'position' => ['label' => 'Position', 'type' => 'select', 'options' => ['Punong Barangay', 'Barangay Secretary', 'Barangay Treasurer', 'Kagawad', 'SK Chairperson']],
                    'term'     => ['label' => 'Term', 'type' => 'text'],
                    'contact'  => ['label' => 'Contact No.', 'type' => 'text'],
                ],
                'columns' => ['name', 'position', 'term', 'contact'],
            ],
            'users' => [
                'title' => 'User Management',
                'icon'  => 'bi-person-lock',
                'idPrefix' => 'U-',
                'fields' => [
                    'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
                    'name'     => ['label' => 'Full Name', 'type' => 'text', 'required' => true],
                    'role'     => ['label' => 'Role', 'type' => 'select', 'options' => ['Admin', 'Secretary']],
                    'status'   => ['label' => 'Status', 'type' => 'select', 'options' => ['Active', 'Inactive']],
                    'password' => ['label' => 'Password', 'type' => 'password', 'required' => true],
                ],
                'columns' => ['username', 'name', 'role', 'status'],
            ],
        ];
    }

    /**
     * Defense-in-depth guard for admin-only actions (user management,
     * backup & restore). The 'admin' route filter is the primary gate;
     * this catches anything that reaches these methods some other way.
     */
    protected function requireAdmin(): ?RedirectResponse
    {
        if (strcasecmp((string) session()->get('role'), 'Admin') !== 0) {
            return redirect()->to(site_url('barangay/dashboard'))
                ->with('error', 'That area is restricted to administrator accounts.');
        }

        return null;
    }

    protected function optionMap(string $table, callable $labelFn): array
    {
        $options = [];
        foreach (DataStore::all($table) as $row) {
            $options[$row['id']] = $labelFn($row);
        }
        return $options;
    }

    protected function residentName(string $id): string
    {
        $r = DataStore::find('residents', $id);
        return $r ? trim($r['first'] . ' ' . $r['last']) : $id;
    }

    /**
     * Maps a module key to its own dedicated view file. Every view listed
     * here is a standalone copy of the same generic list/add/edit/delete
     * markup (they all read off $schema/$module/$rows), split out so each
     * module can be customized independently going forward. Falls back to
     * the shared barangay/module view for anything not listed.
     */
    protected function moduleViews(): array
    {
        return [
            'residents'    => 'barangay/residents',
            'households'   => 'barangay/households',
            'certificates' => 'barangay/certificates',
            'requests'     => 'barangay/requests',
            'cases'        => 'barangay/complaints',
            'meetings'     => 'barangay/meetings',
            'documents'    => 'barangay/documents',
            'payments'     => 'barangay/payments',
            'officials'    => 'barangay/officials',
            'users'        => 'barangay/users',
        ];
    }

    public function dashboard()
    {
        $requests = DataStore::all('requests');

        $data = [
            'pageTitle' => 'Dashboard',
            'active'    => 'dashboard',
            'stats'     => [
                'residents'    => DataStore::count('residents'),
                'households'   => DataStore::count('households'),
                'requests'     => count(array_filter($requests, fn ($r) => ($r['status'] ?? '') === 'Pending')),
                'cases'        => DataStore::count('cases'),
            ],
            'recentRequests' => array_slice(array_reverse($requests), 0, 5),
            'recentCertificates' => array_slice(array_reverse(DataStore::all('certificates')), 0, 4),
            'upcomingMeetings' => array_slice(DataStore::all('meetings'), 0, 4),
            'recentLogs' => array_slice(DataStore::all('logs'), 0, 6),
            'residentNameFn' => fn ($id) => $this->residentName($id),
        ];

        return view('barangay/dashboard', $data);
    }

    public function reports()
    {
        $schemas = $this->schemas();

        // Wording used on the Reports page only.
        $labels = [
            'residents'  => 'Registered Residents',
            'households' => 'Registered Households',
            'payments'   => 'Collections',
        ];

        $counts = [];
        foreach ($schemas as $key => $schema) {
            // resident logins are not staff accounts — don't count them under User Management
            $count = $key === 'users'
                ? count(array_filter(DataStore::all('users'), fn ($u) => ($u['role'] ?? '') !== 'Resident'))
                : DataStore::count($key);

            $counts[$key] = ['title' => $labels[$key] ?? $schema['title'], 'icon' => $schema['icon'], 'count' => $count];
        }

        $purokCounts = [];
        foreach (DataStore::all('residents') as $r) {
            $purokCounts[$r['purok'] ?? 'Unspecified'] = ($purokCounts[$r['purok'] ?? 'Unspecified'] ?? 0) + 1;
        }

        return view('barangay/reports', [
            'pageTitle' => 'Reports',
            'active'    => 'reports',
            'counts'    => $counts,
            'purokCounts' => $purokCounts,
        ]);
    }

    public function activityLogs()
    {
        return view('barangay/activity_logs', [
            'pageTitle' => 'Activity Logs',
            'active'    => 'activity_logs',
            'logs'      => DataStore::all('logs'),
        ]);
    }

    public function backup()
    {
        if ($block = $this->requireAdmin()) {
            return $block;
        }

        return view('barangay/backup', [
            'pageTitle' => 'Backup & Restore',
            'active'    => 'backup',
        ]);
    }

    public function backupExport()
    {
        if ($block = $this->requireAdmin()) {
            return $block;
        }

        DataStore::log(session()->get('username') ?? 'unknown', 'Exported a system backup');
        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setHeader('Content-Disposition', 'attachment; filename="barangay-sumadat-backup-' . date('Y-m-d') . '.json"')
            ->setBody(DataStore::export());
    }

    public function backupImport()
    {
        if ($block = $this->requireAdmin()) {
            return $block;
        }

        $file = $this->request->getFile('backupFile');
        if (! $file || ! $file->isValid()) {
            return redirect()->to(site_url('barangay/backup'))->with('error', 'Please choose a valid backup file.');
        }

        $json = file_get_contents($file->getTempName());
        if (! DataStore::import($json)) {
            return redirect()->to(site_url('barangay/backup'))->with('error', 'That file is not a valid backup.');
        }

        DataStore::log(session()->get('username') ?? 'unknown', 'Restored the system from a backup file');
        return redirect()->to(site_url('barangay/backup'))->with('success', 'Backup restored successfully.');
    }

    public function backupReset()
    {
        if ($block = $this->requireAdmin()) {
            return $block;
        }

        DataStore::reset();
        DataStore::log(session()->get('username') ?? 'unknown', 'Reset the system to demo data');
        return redirect()->to(site_url('barangay/backup'))->with('success', 'System data has been reset to the demo dataset.');
    }

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */

    protected function actor(): string
    {
        return session()->get('username') ?? 'unknown';
    }

    protected function schemaOr404(string $module): array
    {
        $schemas = $this->schemas();
        if (! isset($schemas[$module])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $schemas[$module];
    }

    /** Values for every real (non-"skip") field of a module, as posted. */
    protected function postedFields(array $schema, bool $forUpdate = false): array
    {
        $row = [];
        foreach ($schema['fields'] as $key => $field) {
            if (! empty($field['skip'])) {
                continue;
            }

            $val = $this->request->getPost($key);

            if ($field['type'] === 'password') {
                if ($val === null || $val === '') {
                    if ($forUpdate) {
                        continue; // don't overwrite the password with a blank
                    }
                    $val = '';
                } else {
                    $val = password_hash((string) $val, PASSWORD_DEFAULT);
                }
            }

            $row[$key] = $val ?? '';
        }

        return $row;
    }

    protected function randomPassword(int $length = 8): string
    {
        // no 0/O/1/l/I — easy to read out loud or copy from paper
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    protected function uniqueUsername(string $first, string $last): string
    {
        $clean = fn (string $v) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($v));
        $base  = trim($clean($first) . '.' . $clean($last), '.');
        if ($base === '') {
            $base = 'resident';
        }

        $taken = array_map('strtolower', array_column(DataStore::all('users'), 'username'));
        $name  = $base;
        $n     = 2;
        while (in_array($name, $taken, true)) {
            $name = $base . $n++;
        }

        return $name;
    }

    /**
     * Creates the resident's login (or, if they already have one, issues a new
     * temporary password). The plain password is only ever returned here —
     * it is stored hashed — so the caller must show it to the secretary now.
     */
    protected function provisionLogin(array $resident): array
    {
        $password = $this->randomPassword();
        $fullName = trim($resident['first'] . ' ' . $resident['last']);
        $existing = DataStore::where('users', ['resident_id' => $resident['id']])[0] ?? null;

        if ($existing) {
            DataStore::update('users', $existing['id'], [
                'password'      => password_hash($password, PASSWORD_DEFAULT),
                'temp_password' => 1,
                'status'        => 'Active',
                'name'          => $fullName,
            ]);
            $username = $existing['username'];
        } else {
            $username = $this->uniqueUsername($resident['first'], $resident['last']);
            DataStore::insert('users', [
                'id'            => DataStore::nextId('users', 'U-'),
                'username'      => $username,
                'name'          => $fullName,
                'role'          => 'Resident',
                'status'        => 'Active',
                'password'      => password_hash($password, PASSWORD_DEFAULT),
                'resident_id'   => $resident['id'],
                'temp_password' => 1,
            ]);
        }

        return ['name' => $fullName, 'username' => $username, 'password' => $password];
    }

    protected function adjustMembers(?string $householdId, int $delta): void
    {
        if (! $householdId) {
            return;
        }

        $hh = DataStore::find('households', $householdId);
        if ($hh) {
            DataStore::update('households', $householdId, ['members' => max(0, (int) $hh['members'] + $delta)]);
        }
    }

    protected function createHouseholdFor(array $resident): string
    {
        $in = $this->request->getPost('hh_in_building') === 'Yes';
        $id = DataStore::nextId('households', 'HH-');

        DataStore::insert('households', [
            'id'          => $id,
            'head'        => $resident['id'],
            'purok'       => $resident['purok'] ?? '',
            'street'      => trim((string) $this->request->getPost('hh_street')),
            'in_building' => $in ? 'Yes' : 'No',
            'building'    => $in ? trim((string) $this->request->getPost('hh_building')) : null,
            'floor'       => $in ? trim((string) $this->request->getPost('hh_floor')) : null,
            'members'     => 1,
        ]);

        return $id;
    }

    protected function normalizeHousehold(array $row): array
    {
        $in = ($row['in_building'] ?? 'No') === 'Yes';
        $row['in_building'] = $in ? 'Yes' : 'No';
        $row['building']    = $in ? ($row['building'] ?? '') : null;
        $row['floor']       = $in ? ($row['floor'] ?? '') : null;
        $row['members']     = max(1, (int) ($row['members'] ?? 1));

        return $row;
    }

    /** Validates + saves an uploaded ordinance/resolution file. Returns [name, path] or an error string. */
    protected function saveDocumentFile()
    {
        $file = $this->request->getFile('file');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null; // nothing uploaded
        }

        if (! $file->isValid()) {
            return 'The file could not be uploaded (' . $file->getErrorString() . ').';
        }

        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, self::DOC_EXTENSIONS, true)) {
            return 'Only Word (.doc, .docx), Excel (.xls, .xlsx) and PDF files can be uploaded.';
        }

        if ($file->getSizeByUnit('mb') > 10) {
            return 'That file is larger than 10 MB.';
        }

        $dir = WRITEPATH . 'uploads/documents';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $stored = bin2hex(random_bytes(12)) . '.' . $ext;
        $file->move($dir, $stored);

        return [$file->getClientName(), $stored];
    }

    protected function deleteDocumentFile(?string $stored): void
    {
        if ($stored) {
            $path = WRITEPATH . 'uploads/documents/' . basename($stored);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /* ---------------------------------------------------------------
     * Generic module CRUD (residents, households, certificates, ...)
     * ------------------------------------------------------------- */

    public function index(string $module)
    {
        $schema = $this->schemaOr404($module);

        if ($module === 'users' && ($block = $this->requireAdmin())) {
            return $block;
        }

        // resolve any option callbacks now that we're in a request context
        foreach ($schema['fields'] as $key => $field) {
            if (isset($field['options']) && is_callable($field['options'])) {
                $schema['fields'][$key]['options'] = ($field['options'])();
            }
        }

        $search = trim((string) $this->request->getGet('q'));
        $rows   = DataStore::all($module);

        // Resident logins are managed from the Residents page, not here.
        if ($module === 'users') {
            $rows = array_filter($rows, fn ($u) => ($u['role'] ?? '') !== 'Resident');
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_filter($rows, function ($row) use ($needle) {
                foreach ($row as $v) {
                    if (is_string($v) && str_contains(mb_strtolower($v), $needle)) {
                        return true;
                    }
                }
                return false;
            });
        }

        $viewName = $this->moduleViews()[$module] ?? 'barangay/module';

        $accounts = [];
        if ($module === 'residents') {
            foreach (DataStore::all('users') as $u) {
                if (! empty($u['resident_id'])) {
                    $accounts[$u['resident_id']] = $u['username'];
                }
            }
        }

        $receipts = [];
        if ($module === 'requests') {
            foreach (DataStore::all('payments') as $pay) {
                if (! empty($pay['request_id'])) {
                    $receipts[$pay['request_id']] = $pay['id'];
                }
            }
        }

        return view($viewName, [
            'receipts'  => $receipts,
            'pageTitle' => $schema['title'],
            'active'    => $module,
            'module'    => $module,
            'schema'    => $schema,
            'rows'      => array_reverse($rows),
            'search'    => $search,
            'accounts'  => $accounts,
            'residentLookup' => $module !== 'residents' ? $this->optionMap('residents', fn ($r) => trim($r['first'] . ' ' . $r['last'])) : [],
        ]);
    }

    public function store(string $module)
    {
        $schema = $this->schemaOr404($module);
        $back   = redirect()->to(site_url('barangay/' . $module));

        if ($module === 'users' && ($block = $this->requireAdmin())) {
            return $block;
        }

        if ($module === 'officials') {
            return $back->with('error', 'Every official position is already listed — edit the entry and use "Start New Term" when a new term begins.');
        }

        if ($module === 'residents') {
            return $this->storeResident($schema);
        }

        $row = ['id' => DataStore::nextId($module, $schema['idPrefix'])] + $this->postedFields($schema);

        if ($module === 'households') {
            if (($row['head'] ?? '') === '' || trim((string) ($row['street'] ?? '')) === '') {
                return $back->with('error', 'Please choose the household head and enter the street name.');
            }
            $row = $this->normalizeHousehold($row);
        }

        if ($module === 'certificate_types') {
            $row['fee'] = max(0, (float) ($row['fee'] ?? 0));
            if (trim((string) $row['name']) === '') {
                return $back->with('error', 'Please enter the certificate name.');
            }
            foreach (DataStore::all('certificate_types') as $t) {
                if (strcasecmp($t['name'], trim($row['name'])) === 0) {
                    return $back->with('error', 'That certificate type already exists.');
                }
            }
        }

        if ($module === 'documents') {
            $saved = $this->saveDocumentFile();
            if (is_string($saved)) {
                return $back->with('error', $saved);
            }
            $row['file_name'] = $saved[0] ?? null;
            $row['file_path'] = $saved[1] ?? null;
        }

        DataStore::insert($module, $row);

        // the household head belongs to the household he/she heads
        if ($module === 'households') {
            $head = DataStore::find('residents', $row['head']);
            if ($head && empty($head['household_id'])) {
                DataStore::update('residents', $head['id'], ['household_id' => $row['id']]);
            }
        }

        DataStore::log($this->actor(), 'Added a new record in ' . $schema['title']);

        return $back->with('success', $schema['title'] . ' record added.');
    }

    protected function storeResident(array $schema)
    {
        $back = redirect()->to(site_url('barangay/residents'));

        $first = trim((string) $this->request->getPost('first'));
        $last  = trim((string) $this->request->getPost('last'));
        $hhSel = (string) $this->request->getPost('household_id');

        if ($first === '' || $last === '') {
            return $back->with('error', 'First name and last name are required.');
        }

        if ($hhSel === '') {
            return $back->with('error', 'Please put the resident in a registered household (or register a new one).');
        }

        if ($hhSel === 'NEW' && trim((string) $this->request->getPost('hh_street')) === '') {
            return $back->with('error', 'Please enter the street name for the new household.');
        }

        if ($hhSel !== 'NEW' && ! DataStore::find('households', $hhSel)) {
            return $back->with('error', 'That household could not be found.');
        }

        $row = ['id' => DataStore::nextId('residents', $schema['idPrefix'])] + $this->postedFields($schema);
        $row['first'] = $first;
        $row['last']  = $last;

        // same Purok as the household unless one was picked
        if ($hhSel !== 'NEW' && ($row['purok'] ?? '') === '') {
            $row['purok'] = DataStore::find('households', $hhSel)['purok'] ?? '';
        }

        $db = db_connect();
        $db->transStart();

        $row['household_id'] = null;
        DataStore::insert('residents', $row);

        if ($hhSel === 'NEW') {
            $householdId = $this->createHouseholdFor($row);
        } else {
            $householdId = $hhSel;
            $this->adjustMembers($householdId, +1);
        }
        DataStore::update('residents', $row['id'], ['household_id' => $householdId]);
        $row['household_id'] = $householdId;

        $creds = $this->provisionLogin($row);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $back->with('error', 'The resident could not be saved. Please try again.');
        }

        DataStore::log($this->actor(), 'Registered a resident (' . $row['id'] . ') into household ' . $householdId . ' and created a login');

        return $back
            ->with('success', 'Resident registered in household ' . $householdId . '.')
            ->with('credentials', $creds);
    }

    public function update(string $module, string $id)
    {
        $schema = $this->schemaOr404($module);
        $back   = redirect()->to(site_url('barangay/' . $module));

        if ($module === 'users' && ($block = $this->requireAdmin())) {
            return $block;
        }

        if ($module === 'residents') {
            return $this->updateResident($schema, $id);
        }

        $changes = $this->postedFields($schema, true);

        if ($module === 'households') {
            if (trim((string) ($changes['street'] ?? '')) === '') {
                return $back->with('error', 'The street name is required.');
            }
            $changes = $this->normalizeHousehold($changes);
        }

        if ($module === 'certificate_types') {
            $changes['fee'] = max(0, (float) ($changes['fee'] ?? 0));
        }

        if ($module === 'documents') {
            $saved = $this->saveDocumentFile();
            if (is_string($saved)) {
                return $back->with('error', $saved);
            }
            if (is_array($saved)) {
                $old = DataStore::find('documents', $id);
                $this->deleteDocumentFile($old['file_path'] ?? null);
                $changes['file_name'] = $saved[0];
                $changes['file_path'] = $saved[1];
            }
        }

        DataStore::update($module, $id, $changes);
        DataStore::log($this->actor(), 'Updated a record (' . $id . ') in ' . $schema['title']);

        if ($module === 'requests' && ($changes['status'] ?? '') === 'Released') {
            $payment = $this->ensurePaymentForRequest($id);
            return $back->with('success', 'Request released and the payment was recorded.')->with('receiptId', $payment['id'] ?? null);
        }

        return $back->with('success', $schema['title'] . ' record updated.');
    }

    protected function updateResident(array $schema, string $id)
    {
        $back     = redirect()->to(site_url('barangay/residents'));
        $resident = DataStore::find('residents', $id);
        if (! $resident) {
            return $back->with('error', 'That record could not be found.');
        }

        $changes = $this->postedFields($schema, true);
        $hhSel   = (string) $this->request->getPost('household_id');
        $old     = $resident['household_id'] ?? null;

        if ($hhSel === 'NEW' && trim((string) $this->request->getPost('hh_street')) === '') {
            return $back->with('error', 'Please enter the street name for the new household.');
        }
        if ($hhSel !== '' && $hhSel !== 'NEW' && ! DataStore::find('households', $hhSel)) {
            return $back->with('error', 'That household could not be found.');
        }

        $db = db_connect();
        $db->transStart();

        DataStore::update('residents', $id, $changes);

        if ($hhSel === 'NEW') {
            $new = $this->createHouseholdFor($changes + $resident);
            $this->adjustMembers($old, -1);
            DataStore::update('residents', $id, ['household_id' => $new]);
        } elseif ($hhSel !== '' && $hhSel !== $old) {
            $this->adjustMembers($old, -1);
            $this->adjustMembers($hhSel, +1);
            DataStore::update('residents', $id, ['household_id' => $hhSel]);
        }

        // keep the login's display name in step with the resident
        $account = DataStore::where('users', ['resident_id' => $id])[0] ?? null;
        if ($account) {
            DataStore::update('users', $account['id'], [
                'name' => trim(($changes['first'] ?? $resident['first']) . ' ' . ($changes['last'] ?? $resident['last'])),
            ]);
        }

        $db->transComplete();

        DataStore::log($this->actor(), 'Updated a record (' . $id . ') in Residents');

        return $back->with('success', 'Residents record updated.');
    }

    public function resetResidentLogin(string $id)
    {
        $resident = DataStore::find('residents', $id);
        if (! $resident) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $creds = $this->provisionLogin($resident);
        DataStore::log($this->actor(), 'Created/reset the login for resident ' . $id);

        return redirect()->to(site_url('barangay/residents'))
            ->with('success', 'A new temporary password was created for ' . $creds['name'] . '.')
            ->with('credentials', $creds);
    }

    public function newOfficialsTerm()
    {
        $term = trim((string) $this->request->getPost('term'));
        if ($term === '') {
            return redirect()->to(site_url('barangay/officials'))->with('error', 'Please enter the new term (for example 2026-2029).');
        }

        DataStore::updateAll('officials', ['term' => $term]);
        DataStore::log($this->actor(), 'Started a new officials term (' . $term . ')');

        return redirect()->to(site_url('barangay/officials'))
            ->with('success', 'All positions now show the term ' . $term . '. Edit each position to enter the new name.');
    }

    /** Opens the certificate for a request (creating it the first time) and marks the request "Ready". */
    public function printRequest(string $id)
    {
        $req = DataStore::find('requests', $id);
        if (! $req) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $cert = DataStore::where('certificates', ['request_id' => $id])[0] ?? null;

        if (! $cert) {
            $type = DataStore::where('certificate_types', ['name' => $req['type']])[0] ?? null;
            $cert = [
                'id'         => DataStore::nextId('certificates', 'CERT-'),
                'resident'   => $req['resident'],
                'type'       => $req['type'],
                'purpose'    => $req['purpose'],
                'date'       => date('Y-m-d'),
                'amount'     => $type['fee'] ?? 0,
                'request_id' => $id,
            ];
            DataStore::insert('certificates', $cert);
        }

        if (in_array($req['status'], ['Pending', 'Processing'], true)) {
            DataStore::update('requests', $id, ['status' => 'Ready']);
        }

        DataStore::log($this->actor(), 'Printed certificate (' . $cert['id'] . ') for request ' . $id);

        return view('barangay/certificate_print', [
            'cert'     => $cert,
            'resident' => DataStore::find('residents', $cert['resident'] ?? ''),
        ]);
    }

    /**
     * One payment (collection) per released request. Amount comes from the
     * printed certificate, or the certificate type's fee. Safe to call twice.
     */
    protected function ensurePaymentForRequest(string $requestId): ?array
    {
        $existing = DataStore::where('payments', ['request_id' => $requestId])[0] ?? null;
        if ($existing) {
            return $existing;
        }

        $req = DataStore::find('requests', $requestId);
        if (! $req) {
            return null;
        }

        $cert = DataStore::where('certificates', ['request_id' => $requestId])[0] ?? null;
        $type = DataStore::where('certificate_types', ['name' => $req['type']])[0] ?? null;

        $payment = [
            'id'         => DataStore::nextId('payments', 'OR-'),
            'resident'   => $req['resident'],
            'type'       => $req['type'],
            'amount'     => $cert['amount'] ?? $type['fee'] ?? 0,
            'date'       => date('Y-m-d'),
            'request_id' => $requestId,
        ];
        DataStore::insert('payments', $payment);
        DataStore::log($this->actor(), 'Recorded payment (' . $payment['id'] . ') for request ' . $requestId);

        return $payment;
    }

    public function releaseRequest(string $id)
    {
        $req = DataStore::find('requests', $id);
        if (! $req) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        DataStore::update('requests', $id, ['status' => 'Released']);
        $payment = $this->ensurePaymentForRequest($id);
        DataStore::log($this->actor(), 'Released the certificate for request ' . $id);

        return redirect()->to(site_url('barangay/requests'))
            ->with('success', 'Request ' . $id . ' released and the payment was recorded.')
            ->with('receiptId', $payment['id'] ?? null);
    }

    public function printReceipt(string $id)
    {
        $payment = DataStore::find('payments', $id);
        if (! $payment) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $cert = ! empty($payment['request_id'])
            ? (DataStore::where('certificates', ['request_id' => $payment['request_id']])[0] ?? null)
            : null;

        DataStore::log($this->actor(), 'Printed receipt (' . $id . ')');

        return view('barangay/receipt_print', [
            'payment'  => $payment,
            'resident' => DataStore::find('residents', $payment['resident'] ?? ''),
            'cert'     => $cert,
            'receivedBy' => session()->get('name') ?? '',
            'officials'  => DataStore::where('officials', ['position' => 'Barangay Treasurer']),
        ]);
    }

    public function printCertificate(string $id)
    {
        $cert = DataStore::find('certificates', $id);
        if (! $cert) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $resident = DataStore::find('residents', $cert['resident'] ?? '');

        // a certificate that came from a resident's request: printing it means it's ready to claim
        if (! empty($cert['request_id'])) {
            $req = DataStore::find('requests', $cert['request_id']);
            if ($req && in_array($req['status'], ['Pending', 'Processing'], true)) {
                DataStore::update('requests', $req['id'], ['status' => 'Ready']);
            }
        }

        DataStore::log($this->actor(), 'Printed certificate (' . $id . ')');

        return view('barangay/certificate_print', [
            'cert'     => $cert,
            'resident' => $resident,
        ]);
    }

    public function toggleUserStatus(string $id)
    {
        if ($block = $this->requireAdmin()) {
            return $block;
        }

        $user = DataStore::find('users', $id);
        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ($id === session()->get('userId')) {
            return redirect()->to(site_url('barangay/users'))->with('error', 'You cannot deactivate your own account.');
        }

        $newStatus = ($user['status'] ?? 'Active') === 'Active' ? 'Inactive' : 'Active';
        DataStore::update('users', $id, ['status' => $newStatus]);

        $verb = $newStatus === 'Inactive' ? 'Deactivated' : 'Activated';
        DataStore::log($this->actor(), $verb . ' user account (' . $id . ')');

        return redirect()->to(site_url('barangay/users'))->with('success', 'User account ' . strtolower($verb) . '.');
    }

    public function delete(string $module, string $id)
    {
        $schema = $this->schemaOr404($module);

        if ($module === 'users' && ($block = $this->requireAdmin())) {
            return $block;
        }

        if ($module === 'officials') {
            return redirect()->to(site_url('barangay/officials'))
                ->with('error', 'Official positions can\'t be deleted — edit the entry instead.');
        }

        if ($module === 'users' && $id === session()->get('userId')) {
            return redirect()->to(site_url('barangay/users'))->with('error', 'You cannot delete your own account.');
        }

        $existing = DataStore::find($module, $id);

        if (! DataStore::delete($module, $id)) {
            if (DataStore::$lastDeleteBlockedByReference) {
                return redirect()->to(site_url('barangay/' . $module))
                    ->with('error', 'This ' . rtrim($schema['title'], 's') . ' record is still linked to other records (e.g. certificates, households, or requests) and cannot be deleted until those are removed first.');
            }

            return redirect()->to(site_url('barangay/' . $module))->with('error', 'That record could not be found.');
        }

        if ($module === 'documents' && $existing) {
            $this->deleteDocumentFile($existing['file_path'] ?? null);
        }

        if ($module === 'residents' && $existing) {
            $this->adjustMembers($existing['household_id'] ?? null, -1);
        }

        DataStore::log($this->actor(), 'Deleted a record (' . $id . ') from ' . $schema['title']);

        return redirect()->to(site_url('barangay/' . $module))->with('success', $schema['title'] . ' record deleted.');
    }
}
