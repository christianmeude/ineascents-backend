<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_reference' => $this->booking_reference,
            'user_id' => $this->user_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'pax' => $this->pax,
            'event_date' => $this->event_date,
            'event_time' => $this->event_time,
            'venue_address' => $this->venue_address,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'checkout_url' => $this->checkout_url,
            'consent_privacy_version' => $this->consent_privacy_version,
            'consented_at' => $this->consented_at,
            'package' => new PackageResource($this->whenLoaded('package')),
            'scents' => ScentResource::collection($this->whenLoaded('scents')),
        ];
    }
}
