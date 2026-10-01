<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateBookingWithCheckout;
use App\Enums\InquiryStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentLinkFailedException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\User;
use App\Rules\PaxInTiers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InquiryController extends Controller
{
    private const STATUS_ORDER = [
        'new' => 0,
        'contacted' => 1,
        'booked' => 2,
        'closed' => 3,
    ];

    public function index(Request $request)
    {
        $query = Inquiry::query()
            ->orderByRaw('event_date ASC NULLS LAST')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('archived')) {
            $query->where('archived', $request->boolean('archived'));
        } else {
            $query->where('archived', false);
        }

        return Inertia::render('Inquiries/Index', [
            'inquiries' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('search', 'status', 'archived'),
        ]);
    }

    public function show(Inquiry $inquiry)
    {
        return Inertia::render('Inquiries/Show', [
            'inquiry' => $inquiry,
            'packages' => Package::all(['id', 'name']),
        ]);
    }

    public function update(Request $request, Inquiry $inquiry)
    {
        $current = $inquiry->status->value;

        $validated = $request->validate([
            'status' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) use ($current) {
                    if ($value === 'booked') {
                        $fail('Booked status is set by the promote flow only.');
                    } elseif (! isset(self::STATUS_ORDER[$value])
                        || self::STATUS_ORDER[$value] < self::STATUS_ORDER[$current]
                    ) {
                        $fail('Status can only move forward.');
                    }
                },
            ],
            'archived' => 'sometimes|boolean',
        ]);

        $inquiry->update($validated);

        return redirect()->route('admin.inquiries.show', $inquiry)->with('success', 'Inquiry updated successfully.');
    }

    public function promote(Request $request, Inquiry $inquiry, CreateBookingWithCheckout $checkout)
    {
        if ($inquiry->status !== InquiryStatus::Contacted) {
            return back()->withErrors(['inquiry' => 'Only contacted inquiries can be promoted.']);
        }

        if (Booking::where('inquiry_id', $inquiry->id)->exists()) {
            return back()->withErrors(['inquiry' => 'This inquiry has already been promoted to a booking.']);
        }

        $request->merge([
            'customer_name' => $request->input('customer_name', $inquiry->name),
            'customer_email' => $request->input('customer_email', $inquiry->email),
            'customer_phone' => $request->input('customer_phone', $inquiry->phone),
            'event_date' => $request->input('event_date', $inquiry->event_date?->toDateString()),
        ]);

        $request->validate(['package_id' => 'required|exists:packages,id']);
        $package = Package::findOrFail($request->input('package_id'));

        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:255',
            'package_id' => 'required|exists:packages,id',
            'pax' => ['required', new PaxInTiers($package)],
            'event_date' => $inquiry->event_date ? 'nullable|date' : 'required|date',
            'event_time' => 'nullable',
            'venue_address' => 'required|string|max:255',
            'payment_method' => ['required', Rule::in([
                PaymentMethod::CASH->value,
            ])],
            'notes' => 'nullable|string',
            'scent_ids' => 'nullable|array',
            'scent_ids.*' => 'exists:scents,id',
        ]);

        $data['event_date'] ??= $inquiry->event_date?->toDateString();

        if (! empty($data['customer_email'])) {
            $customer = User::where('email', $data['customer_email'])
                ->where('is_admin', false)
                ->first();

            if ($customer) {
                $data['user_id'] = $customer->id;
            }
        }

        $data['inquiry_id'] = $inquiry->id;

        // A18: carry the inquiry's own consent record onto the booking
        // (provenance, not fabrication — pre-policy inquiries stay null).
        $data['consent_privacy_version'] = $inquiry->consent_privacy_version;
        $data['consented_at'] = $inquiry->consented_at;

        try {
            DB::transaction(fn () => [$checkout->execute($data), $inquiry->update(['status' => InquiryStatus::Booked])]);
        } catch (PaymentLinkFailedException $e) {
            return back()->withErrors(['payment_method' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.bookings.index')->with('success', 'Inquiry promoted to booking.');
    }
}
