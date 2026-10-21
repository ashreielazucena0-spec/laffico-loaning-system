<?php

namespace App\Controllers;

use App\Libraries\DataStore;

/**
 * Resident portal: what a resident sees after logging in with the
 * username/password the barangay office created when registering them.
 */
class Resident extends BaseController
{
    protected ?array $resident = null;

    protected function me(): ?array
    {
        if ($this->resident === null) {
            $this->resident = DataStore::find('residents', (string) session()->get('residentId')) ?: [];
        }

        return $this->resident ?: null;
    }

    protected function myRequests(): array
    {
        return array_reverse(DataStore::where('requests', ['resident' => session()->get('residentId')]));
    }

    /** Renders a portal page, adding the bits the layout always needs. */
    protected function page(string $view, string $title, string $active, array $data = [])
    {
        $me = $this->me();
        if (! $me) {
            session()->destroy();
            return redirect()->to(site_url('login'))->with('loginError', 'Your resident record could not be found. Please contact the barangay office.');
        }

        $ready = DataStore::countWhere('requests', ['resident' => $me['id'], 'status' => 'Ready']);

        return view($view, $data + [
            'pageTitle'  => $title,
            'active'     => $active,
            'me'         => $me,
            'readyCount' => $ready,
        ]);
    }

    public function dashboard()
    {
        $requests = $this->myRequests();
        $today    = date('Y-m-d');

        $upcoming = array_values(array_filter(DataStore::all('meetings'), fn ($m) => ($m['date'] ?? '') >= $today));

        return $this->page('resident/dashboard', 'Dashboard', 'dashboard', [
            'requests'  => array_slice($requests, 0, 5),
            'ready'     => array_values(array_filter($requests, fn ($r) => $r['status'] === 'Ready')),
            'pending'   => count(array_filter($requests, fn ($r) => in_array($r['status'], ['Pending', 'Processing'], true))),
            'released'  => count($this->releasedCertificates()),
            'meetings'  => array_slice($upcoming, 0, 4),
        ]);
    }

    public function requestForm()
    {
        return $this->page('resident/request', 'Request Certificate', 'request', [
            'types' => DataStore::all('certificate_types'),
        ]);
    }

    public function submitRequest()
    {
        $me      = $this->me();
        $type    = trim((string) $this->request->getPost('type'));
        $purpose = trim((string) $this->request->getPost('purpose'));
        $back    = redirect()->to(site_url('resident/request'))->withInput();

        $validType = DataStore::where('certificate_types', ['name' => $type])[0] ?? null;

        if (! $me || ! $validType) {
            return $back->with('error', 'Please choose which certificate you need.');
        }

        if ($purpose === '' || mb_strlen($purpose) > 255) {
            return $back->with('error', 'Please tell us the purpose of the certificate (up to 255 characters).');
        }

        // The request goes straight into the secretary's "Certificate Requests" list.
        $id = DataStore::nextId('requests', 'REQ-');
        DataStore::insert('requests', [
            'id'       => $id,
            'resident' => $me['id'],
            'type'     => $validType['name'],
            'purpose'  => $purpose,
            'date'     => date('Y-m-d'),
            'status'   => 'Pending',
        ]);

        DataStore::log((string) session()->get('username'), 'Requested a certificate (' . $id . ': ' . $validType['name'] . ')');

        return redirect()->to(site_url('resident/requests'))
            ->with('success', 'Your request for a ' . $validType['name'] . ' was sent to the barangay secretary.');
    }

    public function requests()
    {
        return $this->page('resident/requests', 'My Requests', 'requests', [
            'requests' => $this->myRequests(),
        ]);
    }

    /** Certificates already released to this resident (their own requests that were released + any issued directly by the office). */
    protected function releasedCertificates(): array
    {
        $myId     = session()->get('residentId');
        $statuses = array_column(DataStore::where('requests', ['resident' => $myId]), 'status', 'id');

        $out = [];
        foreach (DataStore::where('certificates', ['resident' => $myId]) as $c) {
            $linked = $c['request_id'] ?? null;
            if (! $linked || ($statuses[$linked] ?? '') === 'Released') {
                $out[] = $c;
            }
        }

        return array_reverse($out);
    }

    public function certificates()
    {
        return $this->page('resident/certificates', 'Released Certificates', 'certificates', [
            'certificates' => $this->releasedCertificates(),
            'receipts'     => array_column(DataStore::where('payments', ['resident' => session()->get('residentId')]), 'id', 'request_id'),
        ]);
    }

    public function household()
    {
        $me = $this->me();
        $hh = $me && ! empty($me['household_id']) ? DataStore::find('households', $me['household_id']) : null;

        $members = [];
        $head    = null;
        if ($hh) {
            $members = DataStore::where('residents', ['household_id' => $hh['id']]);
            $head    = DataStore::find('residents', $hh['head']);
        }

        return $this->page('resident/household', 'My Household', 'household', [
            'household' => $hh,
            'head'      => $head,
            'members'   => $members,
        ]);
    }

    public function documents()
    {
        return $this->page('resident/documents', 'Ordinances & Resolutions', 'documents', [
            'documents' => array_reverse(DataStore::all('documents')),
        ]);
    }

    public function account()
    {
        $user = DataStore::find('users', (string) session()->get('userId'));

        return $this->page('resident/account', 'My Account', 'account', [
            'user' => $user,
        ]);
    }

    public function changePassword()
    {
        $user = DataStore::find('users', (string) session()->get('userId'));
        $back = redirect()->to(site_url('resident/account'));

        $current = (string) $this->request->getPost('current');
        $new     = (string) $this->request->getPost('new');
        $confirm = (string) $this->request->getPost('confirm');

        $stored = (string) ($user['password'] ?? '');
        $isHash = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
        $ok     = $isHash ? password_verify($current, $stored) : hash_equals($stored, $current);

        if (! $user || ! $ok) {
            return $back->with('error', 'Your current password is not correct.');
        }

        if (strlen($new) < 6) {
            return $back->with('error', 'The new password must be at least 6 characters.');
        }

        if ($new !== $confirm) {
            return $back->with('error', 'The new passwords do not match.');
        }

        DataStore::update('users', $user['id'], [
            'password'      => password_hash($new, PASSWORD_DEFAULT),
            'temp_password' => 0,
        ]);
        session()->set('tempPassword', false);

        DataStore::log((string) session()->get('username'), 'Changed their password');

        return $back->with('success', 'Your password was changed.');
    }
}
