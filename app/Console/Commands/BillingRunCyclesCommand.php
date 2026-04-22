<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunBillingCycleForSubscriptionJob;
use App\Models\BusinessSubscription;
use Illuminate\Console\Command;

class BillingRunCyclesCommand extends Command
{
    protected $signature = 'billing:run-cycles {--business= : Restrict cycle generation to a business id} {--sync : Run the billing cycle job inline instead of queueing it}';

    protected $description = 'Dispatch billing cycle jobs for subscriptions that are due for invoice generation.';

    public function handle(): int
    {
        $query = BusinessSubscription::query()
            ->whereNotNull('billing_price_id')
            ->whereNotIn('lifecycle_status', ['canceled', 'expired', 'suspended'])
            ->where(function ($query): void {
                $query->whereNull('next_invoice_at')
                    ->orWhere('next_invoice_at', '<=', now())
                    ->orWhere(function ($setup): void {
                        $setup->whereNull('setup_fee_invoiced_at')
                            ->whereHas('billingPrice', function ($price): void {
                                $price->whereNotNull('setup_fee_amount_minor')
                                    ->where('setup_fee_amount_minor', '>', 0);
                            });
                    });
            });

        if ($this->option('business') !== null) {
            $query->where('business_id', (int) $this->option('business'));
        }

        $dispatched = 0;
        $sync = (bool) $this->option('sync');

        $query->chunkById(200, function ($subscriptions) use (&$dispatched, $sync): void {
            foreach ($subscriptions as $subscription) {
                if ($sync) {
                    app()->call([new RunBillingCycleForSubscriptionJob($subscription->id), 'handle']);
                } else {
                    RunBillingCycleForSubscriptionJob::dispatch($subscription->id);
                }

                $dispatched++;
            }
        });

        $this->info("Billing cycle dispatch complete. {$dispatched} subscription(s) queued.");

        return self::SUCCESS;
    }
}
