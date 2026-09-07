<?php

namespace Wave\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Services\Billing\SubscriptionLifecycleService;
use Wave\Subscription;

class CancelExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:cancel-expired';

    protected $description = 'Cancel subscriptions that have expired';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(SubscriptionLifecycleService $lifecycleService): int
    {
        $now = Carbon::now();

        $subscriptions = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(function ($query) use ($now): void {
                $query->where('ends_at', '<', $now)
                    ->orWhere('next_payment_at', '<', $now);
            })
            ->orderBy('id')
            ->cursor();

        foreach ($subscriptions as $subscription) {
            $lifecycleService->cancel($subscription);
            $this->info('Subscription ID '.$subscription->id.' has been cancelled.');
        }

        $this->info('Checked all subscriptions.');

        return self::SUCCESS;
    }
}
