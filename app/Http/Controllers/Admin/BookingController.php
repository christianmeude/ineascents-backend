<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Rules\PaxInTiers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['package', 'feedbacks'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('customer_name', 'like', "%{$search}%")
                ->orWhere('booking_reference', 'like', "%{$search}%");
        }

        $bookings = $query->paginate(10)->withQueryString();
        $packages = Package::all(['id', 'name']);

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'packages' => $packages,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request, \App\Actions\CreateBookingWithCheckout $checkout)
    {
        $request->validate(['package_id' => 'required|exists:packages,id']);
        $package = Package::findOrFail($request->input('package_id'));

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:255',
            'package_id' => 'required|exists:packages,id',
            'pax' => ['required', new PaxInTiers($package)],
            'event_date' => 'required|date',
            'event_time' => 'nullable',
            'venue_address' => 'required|string|max:255',
            'payment_method' => ['required', Rule::in([
                \App\Enums\PaymentMethod::CASH->value,
            ])],
            'status' => 'required|string|in:Confirmed,Pending,Cancelled,Completed',
            'total_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (! empty($validated['customer_email'])) {
            $customer = User::where('email', $validated['customer_email'])
                ->where('is_admin', false)
                ->first();

            if ($customer) {
                $validated['user_id'] = $customer->id;
            }
        }

        try {
            $checkout->execute($validated);
        } catch (\App\Exceptions\PaymentLinkFailedException $e) {
            return back()->withErrors(['payment_method' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.bookings.index')->with('success', 'Booking created successfully.');
    }

    public function update(Request $request, Booking $booking, \App\Actions\UpdateBooking $updateBooking)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:255',
            'package_id' => 'required|exists:packages,id',
            'pax' => 'nullable|integer|min:1',
            'event_date' => 'required|date',
            'event_time' => 'nullable',
            'venue_address' => 'required|string|max:255',
            'payment_method' => ['required', Rule::in([
                \App\Enums\PaymentMethod::CASH->value,
            ])],
            'status' => 'required|string|in:Confirmed,Pending,Cancelled,Completed',
            'total_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Auto-relink only when the email value itself changed: an explicit
        // unlink sticks until the email is edited again.
        if (array_key_exists('customer_email', $validated)
            && $validated['customer_email'] !== $booking->customer_email) {
            $validated['user_id'] = ! empty($validated['customer_email'])
                ? User::where('email', $validated['customer_email'])->where('is_admin', false)->value('id')
                : null;
        }

        $updateBooking->execute($booking, $validated);

        return redirect()->route('admin.bookings.index')->with('success', 'Booking updated successfully.');
    }

    public function approve(Booking $booking)
    {
        // Completed is terminal: approving must never resurrect it to Confirmed.
        if ($booking->status === \App\Enums\BookingStatus::Completed) {
            return back()->with('success', 'Booking is already completed.');
        }

        // Capitalized because the validation rules in store/update use Title Case
        $booking->update(['status' => \App\Enums\BookingStatus::Confirmed->value]);

        app(\App\Services\AdminNotifier::class)->alert(
            'booking.confirmed',
            'Booking approved',
            "{$booking->booking_reference} confirmed by admin.",
            route('admin.bookings.index', ['search' => $booking->booking_reference]),
        );

        return back()->with('success', 'Booking approved successfully.');
    }
}
