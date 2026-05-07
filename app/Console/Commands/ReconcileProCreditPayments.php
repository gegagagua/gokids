<?php

namespace App\Console\Commands;

use App\Models\ProCreditPayment;
use App\Services\ProCreditPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileProCreditPayments extends Command
{
    protected $signature = 'procredit:reconcile
                            {--older-than=2 : Only reconcile rows older than this many minutes}
                            {--max-age=10080 : Skip rows older than this many minutes (default 7 days)}
                            {--limit=200 : Maximum rows to process in one run}';

    protected $description = 'Reconcile pending ProCredit payments by re-checking their status with the bank';

    public function handle(ProCreditPaymentService $service): int
    {
        $olderThan = (int) $this->option('older-than');
        $maxAge = (int) $this->option('max-age');
        $limit = (int) $this->option('limit');

        $pending = ProCreditPayment::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($olderThan))
            ->where('created_at', '>', now()->subMinutes($maxAge))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $total = $pending->count();
        $this->info("Reconciling {$total} pending payments…");

        $stats = ['completed' => 0, 'failed' => 0, 'cancelled' => 0, 'pending' => 0, 'errors' => 0];

        foreach ($pending as $p) {
            try {
                $result = $service->getPaymentStatus($p->order_id);
                $newStatus = $result['status'] ?? 'pending';
                $stats[$newStatus] = ($stats[$newStatus] ?? 0) + 1;

                $this->line(sprintf(
                    '  [%d] %s → %s%s',
                    $p->id,
                    $p->order_id,
                    $newStatus,
                    $newStatus === 'completed' ? ' ✓ license activated' : ''
                ));
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('ProCredit reconcile: error on payment', [
                    'payment_id' => $p->id,
                    'order_id' => $p->order_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  [{$p->id}] {$p->order_id} → ERROR: {$e->getMessage()}");
            }

            usleep(200_000); // 200ms between bank calls
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. completed=%d  cancelled=%d  failed=%d  still_pending=%d  errors=%d',
            $stats['completed'], $stats['cancelled'], $stats['failed'], $stats['pending'], $stats['errors']
        ));

        Log::info('ProCredit reconcile: run complete', $stats + ['total' => $total]);

        return self::SUCCESS;
    }
}
