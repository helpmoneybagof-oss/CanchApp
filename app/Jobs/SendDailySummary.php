<?php

namespace App\Jobs;

use App\Mail\DailySummaryAdmin;
use App\Models\Reservation;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDailySummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $adminEmail = Setting::getValue('contact_email');
        if (! $adminEmail) {
            return;
        }

        $today = Carbon::today();
        $reservations = Reservation::with(['user', 'court'])
            ->forDate($today->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->get();

        $dateFormatted = $today->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY');

        Mail::to($adminEmail)
            ->send(new DailySummaryAdmin($reservations, ucfirst($dateFormatted)));
    }
}
