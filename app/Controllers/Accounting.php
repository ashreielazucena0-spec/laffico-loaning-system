<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\LoanService;
use App\Libraries\ReportBuilder;

/** Bookkeeping that feeds the reports: expenses & payables, budgets, bank statement, opening balances. */
class Accounting extends BaseController
{
    private function validDate(?string $d): bool
    {
        $x = \DateTime::createFromFormat('Y-m-d', (string) $d);

        return $x && $x->format('Y-m-d') === $d;
    }

    public function index(): string
    {
        $db    = db_connect();
        $svc   = new LoanService();
        $tab   = (string) $this->request->getGet('tab');
        $tab   = in_array($tab, ['expenses', 'budgets', 'bank', 'settings'], true) ? $tab : 'expenses';
        $month = ReportBuilder::range((string) $this->request->getGet('month'))[0];

        $book = (new ReportBuilder())->bankBook(date('Y-m-d'));

        return view('accounting', [
            'page_title' => 'Accounting',
            'active'     => 'accounting',
            'tab'        => $tab,
            'month'      => $month,
            'expenses'   => $db->table('expenses')->orderBy('payment_status = \'unpaid\'', 'DESC', false)->orderBy('expense_date', 'DESC')->orderBy('id', 'DESC')->limit(200)->get()->getResultArray(),
            'budgets'    => $db->table('budgets')->where('month', $month)->orderBy('category')->get()->getResultArray(),
            'bank'       => $db->table('bank_entries')->orderBy('entry_date', 'DESC')->orderBy('id', 'DESC')->limit(200)->get()->getResultArray(),
            'bookOpen'   => array_values(array_filter($book, static fn ($b) => ! $b['matched'])),
            'openHand'   => $svc->setting('opening_cash_on_hand'),
            'openBank'   => $svc->setting('opening_cash_on_bank'),
        ]);
    }

    public function addExpense()
    {
        $p      = $this->request->getPost();
        $cfg    = config('Loaning');
        $amount = round((float) str_replace(',', '', (string) ($p['amount'] ?? 0)), 2);
        $date   = (string) ($p['expense_date'] ?? '');
        $status = ($p['payment_status'] ?? 'paid') === 'unpaid' ? 'unpaid' : 'paid';
        $via    = ($p['paid_via'] ?? 'cash') === 'bank' ? 'bank' : 'cash';
        $back   = 'accounting?tab=expenses';

        if (! in_array($p['category'] ?? '', $cfg->expenseCategories, true)) {
            return $this->fail($back, 'Choose an expense category.');
        }
        if ($amount <= 0 || ! $this->validDate($date) || trim((string) ($p['description'] ?? '')) === '') {
            return $this->fail($back, 'Enter a date, a description and an amount above zero.');
        }
        $due = (string) ($p['due_date'] ?? '');
        if ($status === 'unpaid' && $due !== '' && ! $this->validDate($due)) {
            return $this->fail($back, 'Enter a valid due date.');
        }

        db_connect()->table('expenses')->insert([
            'expense_date'   => $date,
            'category'       => $p['category'],
            'description'    => mb_substr(trim($p['description']), 0, 255),
            'supplier'       => trim((string) ($p['supplier'] ?? '')) ?: null,
            'invoice_no'     => trim((string) ($p['invoice_no'] ?? '')) ?: null,
            'amount'         => $amount,
            'payment_status' => $status,
            'due_date'       => $status === 'unpaid' ? ($due ?: add_days($date, 30)) : null,
            'paid_date'      => $status === 'paid' ? $date : null,
            'paid_via'       => $status === 'paid' ? $via : null,
            'created_by'     => current_user_name(),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        Audit::log('Recorded expense', $p['category'] . ' · ' . peso($amount) . ' · ' . $status);

        return $this->done($back, 'Expense recorded.');
    }

    public function payExpense(int $id)
    {
        $db = db_connect();
        $e  = $db->table('expenses')->where('id', $id)->get()->getRowArray();
        if (! $e || $e['payment_status'] !== 'unpaid') {
            return $this->fail('accounting?tab=expenses', 'That bill is not open.');
        }
        $paid = (string) $this->request->getPost('paid_date');
        if (! $this->validDate($paid) || $paid > date('Y-m-d') || $paid < $e['expense_date']) {
            $paid = max($e['expense_date'], date('Y-m-d'));
        }
        $via = $this->request->getPost('paid_via') === 'bank' ? 'bank' : 'cash';
        $db->table('expenses')->where('id', $id)->update(['payment_status' => 'paid', 'paid_date' => $paid, 'paid_via' => $via]);
        Audit::log('Paid supplier bill', ($e['supplier'] ?: $e['description']) . ' · ' . peso($e['amount']) . ' via ' . $via);

        return $this->done('accounting?tab=expenses', 'Bill marked as paid.');
    }

    public function saveBudget()
    {
        $month = (string) $this->request->getPost('month');
        $cat   = (string) $this->request->getPost('category');
        $amt   = round((float) str_replace(',', '', (string) $this->request->getPost('amount')), 2);
        $back  = 'accounting?tab=budgets&month=' . urlencode($month);
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || ! in_array($cat, config('Loaning')->expenseCategories, true) || $amt < 0) {
            return $this->fail($back, 'Choose a month, a category and a budget amount.');
        }
        $db = db_connect();
        $ex = $db->table('budgets')->where('month', $month)->where('category', $cat)->get()->getRowArray();
        if ($ex) {
            $db->table('budgets')->where('id', $ex['id'])->update(['amount' => $amt]);
        } else {
            $db->table('budgets')->insert(['month' => $month, 'category' => $cat, 'amount' => $amt]);
        }
        Audit::log('Set budget', $month . ' · ' . $cat . ' · ' . peso($amt));

        return $this->done($back, 'Budget saved.');
    }

    public function addBank()
    {
        $p      = $this->request->getPost();
        $amount = round((float) str_replace(',', '', (string) ($p['amount'] ?? 0)), 2);
        if (! $this->validDate($p['entry_date'] ?? '') || $amount == 0.0) {
            return $this->fail('accounting?tab=bank', 'Enter the statement date and a non-zero amount (use a minus sign for withdrawals).');
        }
        db_connect()->table('bank_entries')->insert([
            'entry_date'  => $p['entry_date'],
            'reference'   => trim((string) ($p['reference'] ?? '')) ?: null,
            'description' => trim((string) ($p['description'] ?? '')) ?: null,
            'amount'      => $amount,
            'created_by'  => current_user_name(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        Audit::log('Added bank statement entry', ($p['reference'] ?? '') . ' · ' . peso($amount));

        return $this->done('accounting?tab=bank', 'Bank entry added.');
    }

    /** Matches unmatched bank lines to unmatched book entries by amount (±7 days). */
    public function autoMatch()
    {
        $db    = db_connect();
        $book  = array_values(array_filter((new ReportBuilder())->bankBook(date('Y-m-d')), static fn ($b) => ! $b['matched']));
        $count = 0;
        foreach ($db->table('bank_entries')->where('match_id', null)->orderBy('entry_date')->get()->getResultArray() as $b) {
            $best = null;
            foreach ($book as $i => $x) {
                if (abs($x['amount'] - (float) $b['amount']) > 0.005 || abs(days_between($x['date'], $b['entry_date'])) > 7) {
                    continue;
                }
                $score = (($b['reference'] && strcasecmp((string) $b['reference'], (string) $x['ref']) === 0) ? 0 : 10) + abs(days_between($x['date'], $b['entry_date']));
                if ($best === null || $score < $best[0]) {
                    $best = [$score, $i];
                }
            }
            if ($best !== null) {
                $x = $book[$best[1]];
                $db->table('bank_entries')->where('id', $b['id'])->update(['match_type' => $x['type'], 'match_id' => $x['id']]);
                unset($book[$best[1]]);
                $count++;
            }
        }
        Audit::log('Auto-matched bank statement', $count . ' entr' . ($count === 1 ? 'y' : 'ies'));

        return $this->done('accounting?tab=bank', $count . ' bank entr' . ($count === 1 ? 'y' : 'ies') . ' matched to the books.');
    }

    public function unmatch(int $id)
    {
        db_connect()->table('bank_entries')->where('id', $id)->update(['match_type' => null, 'match_id' => null]);

        return $this->done('accounting?tab=bank', 'Match removed.');
    }

    public function saveSettings()
    {
        $svc = new LoanService();
        foreach (['opening_cash_on_hand', 'opening_cash_on_bank'] as $k) {
            $v = (float) str_replace(',', '', (string) $this->request->getPost($k));
            if ($v < 0) {
                return $this->fail('accounting?tab=settings', 'Opening balances cannot be negative.');
            }
            $svc->saveSetting($k, number_format($v, 2, '.', ''));
        }
        Audit::log('Updated opening balances', 'Cash on hand / in bank');

        return $this->done('accounting?tab=settings', 'Opening balances saved.');
    }
}
