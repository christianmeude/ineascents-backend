<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Package;
use App\Enums\BookingStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardMetrics
{
    /**
     * Get the start date for the given filter.
     */
    private function getStartDate(string $filter): ?Carbon
    {
        return match ($filter) {
            'daily' => Carbon::today(),
            'weekly' => Carbon::now()->startOfWeek(),
            'monthly' => Carbon::now()->startOfMonth(),
            'yearly' => Carbon::now()->startOfYear(),
            default => null,
        };
    }

    /**
     * Get the base query for bookings within the filter timeframe.
     */
    private function getBaseQuery(?Carbon $startDate): Builder
    {
        $query = Booking::query();
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        return $query;
    }

    public function getTotalBookings(?Carbon $startDate): int
    {
        return $this->getBaseQuery($startDate)->count();
    }

    public function getTotalRevenue(?Carbon $startDate): float
    {
        return (float) $this->getBaseQuery($startDate)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->sum('total_price');
    }

    public function getConfirmedEvents(?Carbon $startDate): int
    {
        return $this->getBaseQuery($startDate)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->count();
    }

    public function getPopularPackages(?Carbon $startDate)
    {
        return Package::withCount(['bookings' => function ($q) use ($startDate) {
            if ($startDate) {
                $q->where('created_at', '>=', $startDate);
            }
        }])
            ->orderByDesc('bookings_count')
            ->take(4)
            ->get()
            ->map(function ($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'bookings_count' => $package->bookings_count,
                    'new_today' => Booking::where('package_id', $package->id)
                        ->whereDate('created_at', Carbon::today())
                        ->count(),
                ];
            });
    }

    public function getUpcomingBookings()
    {
        return Booking::with('package')
            ->where('event_date', '>=', Carbon::today())
            ->orderBy('event_date', 'asc')
            ->take(5)
            ->get();
    }
    
    public function getMetrics(string $filter): array
    {
        $startDate = $this->getStartDate($filter);

        return [
            'totalBookings' => $this->getTotalBookings($startDate),
            'totalRevenue' => $this->getTotalRevenue($startDate),
            'confirmedEvents' => $this->getConfirmedEvents($startDate),
            'popularPackages' => $this->getPopularPackages($startDate),
            'upcomingBookings' => $this->getUpcomingBookings(),
            'webhookAlerts' => app(WebhookAlertSummary::class)->summarize(),
        ];
    }
}
