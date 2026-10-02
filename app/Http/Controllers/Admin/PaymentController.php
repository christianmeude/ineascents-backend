<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\WebhookAlertSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(Request $request, WebhookAlertSummary $alerts)
    {
        $bookings = Booking::with('package')->latest();

        if ($request->filled('method')) {
            $bookings->where('payment_method', $request->method);
        }

        if ($request->filled('status')) {
            $bookings->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $bookings->where(function ($query) use ($search) {
                $query->where('booking_reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $events = DB::table('webhook_events')->orderByDesc('created_at')->paginate(10, ['*'], 'events')->withQueryString();

        $refs = $events->getCollection()->map(function ($event) {
            $payload = json_decode((string) $event->payload, true);
            $remarks = is_array($payload)
                ? ($payload['data']['attributes']['data']['attributes']['remarks'] ?? null)
                : null;

            return is_string($remarks) && $remarks !== '' ? $remarks : null;
        })->filter()->all();

        $ids = Booking::whereIn('booking_reference', $refs)->pluck('id', 'booking_reference');

        $events->getCollection()->transform(function ($event) use ($ids) {
            $payload = json_decode((string) $event->payload, true);
            $remarks = is_array($payload)
                ? ($payload['data']['attributes']['data']['attributes']['remarks'] ?? null)
                : null;
            $reference = is_string($remarks) && $remarks !== '' ? $remarks : null;
            $event->booking_reference = $reference;
            $event->booking_id = $reference ? $ids->get($reference) : null;

            return $event;
        });

        $revenue = (float) Booking::whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])->sum('total_price');

        return Inertia::render('Payments/Index', [
            'bookings' => $bookings->paginate(10, ['*'], 'bookings')->withQueryString(),
            'events' => $events,
            'alerts' => $alerts->summarize(),
            'revenue' => $revenue,
            'filters' => $request->only('method', 'status', 'search'),
        ]);
    }
}
