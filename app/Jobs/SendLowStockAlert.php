<?php

namespace App\Jobs;

use App\Mail\LowStockAlert;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendLowStockAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $adminEmail = Setting::getValue('contact_email');
        if (! $adminEmail) {
            return;
        }

        $lowStockProducts = Product::lowStock()->active()->get();

        if ($lowStockProducts->isEmpty()) {
            return;
        }

        Mail::to($adminEmail)
            ->send(new LowStockAlert($lowStockProducts));
    }
}
