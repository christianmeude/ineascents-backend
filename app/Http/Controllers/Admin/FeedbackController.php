<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = Feedback::with(['user:id,name,email', 'booking:id,booking_reference,status'])
            ->latest();

        if ($request->filled('stars')) {
            $query->where('stars', (int) $request->stars);
        }

        return Inertia::render('Feedbacks/Index', [
            'feedbacks' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('stars'),
        ]);
    }

    public function show(Feedback $feedback)
    {
        $feedback->load(['user:id,name,email', 'booking:id,booking_reference,status']);

        return Inertia::render('Feedbacks/Show', [
            'feedback' => $feedback,
        ]);
    }
}
