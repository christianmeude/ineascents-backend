<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'is_available' => (bool) $this->is_available,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
