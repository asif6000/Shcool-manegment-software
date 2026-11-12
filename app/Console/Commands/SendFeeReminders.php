<?php

namespace App\Console\Commands;

use App\Models\FeeInvoice;
use App\Services\SchoolEventNotifier;
use Illuminate\Console\Command;
use Throwable;

class SendFeeReminders extends Command
{
    protected $signature = 'fees:send-reminders
        {--days=3 : Include invoices due within this many days}
        {--dry-run : List the invoices without sending notifications}';

    protected $description = 'Send fee reminders for open invoices that are due soon or overdue';

    public function handle(SchoolEventNotifier $notifier): int
    {
        $days = max(0, (int) $this->option('days'));
        $limit = now()->startOfDay()->addDays($days)->toDateString();
        $dryRun = (bool) $this->option('dry-run');
        $invoices = 0;
        $sent = 0;

        FeeInvoice::query()
            ->outstanding()
            ->where('due_date', '<=', $limit)
            ->with(['student.user', 'student.guardians.user'])
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$invoices, &$sent, $dryRun, $notifier): void {
                foreach ($chunk as $invoice) {
                    $invoices++;

                    if ($dryRun) {
                        $this->line(__('Invoice :invoice is due on :date.', [
                            'invoice' => $invoice->invoice_no,
                            'date' => $invoice->due_date?->format('d M Y'),
                        ]));

                        continue;
                    }

                    try {
                        $sent += $notifier->feeReminder($invoice);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->warn(__('Could not send the reminder for invoice :invoice.', [
                            'invoice' => $invoice->invoice_no,
                        ]));
                    }
                }
            });

        if ($dryRun) {
            $this->info(__(':count open invoice(s) matched.', ['count' => $invoices]));

            return self::SUCCESS;
        }

        $this->info(__(':count reminder(s) sent for :invoices open invoice(s).', [
            'count' => $sent,
            'invoices' => $invoices,
        ]));

        return self::SUCCESS;
    }
}
