<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_order_id',
        'payment_url',
        'usage_limit',
        'expiry_at',
        'status',
        'discount_amount',
        'provider_response',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expiry_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentAttemptInvoices(): HasMany
    {
        return $this->hasMany(PaymentAttemptInvoice::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(TuitionInvoice::class, 'payment_attempt_invoices')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['paid', 'expired', 'cancelled']);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = match ($this->status) {
            'creating' => ['created', 'failed'],
            'created', 'pending' => ['paid', 'expired', 'cancelled'],
            'paid', 'failed', 'expired', 'cancelled' => [],
            default => [],
        };

        return in_array($newStatus, $allowed, true);
    }

    public function transitionTo(string $newStatus): static
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition payment attempt {$this->id} from '{$this->status}' to '{$newStatus}'."
            );
        }

        $this->status = $newStatus;
        $this->save();

        return $this;
    }

    public function getTotalAllocatedAmount(): int
    {
        return $this->paymentAttemptInvoices()->sum('allocated_amount');
    }

    public function getGrossAmount(): int
    {
        return $this->getTotalAllocatedAmount() - $this->discount_amount;
    }
}
