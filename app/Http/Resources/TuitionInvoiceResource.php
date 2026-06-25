<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TuitionInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => StudentResource::make($this->whenLoaded('student')),
            'period' => $this->period,
            'fee_type' => $this->fee_type,
            'description' => $this->description,
            'amount' => $this->amount,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'generation_source' => $this->generation_source,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
