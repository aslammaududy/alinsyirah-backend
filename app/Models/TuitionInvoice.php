<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class TuitionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'period',
        'fee_type',
        'description',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'generation_source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentAttemptInvoices(): HasMany
    {
        return $this->hasMany(PaymentAttemptInvoice::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['paid', 'expired', 'cancelled']);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = match ($this->status) {
            'draft' => ['pending_payment', 'cancelled'],
            'pending_payment' => ['paid', 'expired', 'cancelled'],
            'expired' => ['pending_payment'],
            'paid', 'cancelled' => [],
            default => [],
        };

        return in_array($newStatus, $allowed, true);
    }

    public function transitionTo(string $newStatus): static
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition invoice {$this->id} from '{$this->status}' to '{$newStatus}'."
            );
        }

        $this->status = $newStatus;

        if ($newStatus === 'paid') {
            $this->paid_at = now();
        }

        $this->save();

        return $this;
    }
}
