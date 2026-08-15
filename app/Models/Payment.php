<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    public const TYPE_FEE = 'fee';

    public const TYPE_PENALTY = 'penalty';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXEMPTED = 'exempted';

    public const STATUS_REFUNDED = 'refunded';

    public const METHOD_CASH = 'cash';

    public const METHOD_CASHLESS = 'cashless';

    public const METHOD_EXEMPTION = 'exemption';

    protected $fillable = [
        'uuid',
        'batch_id',
        'user_id',
        'organization_id',
        'academic_term_id',
        'fee_type',
        'fee_id',
        'event_id',
        'amount',
        'payment_method',
        'reference_number',
        'payment_submission_id',
        'status',
        'isExempted',
        'exempted_by',
        'exempted_at',
        'processed_by',
        'paid_at',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'isExempted' => 'boolean',
            'paid_at' => 'datetime',
            'exempted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function exemptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exempted_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class, 'payment_submission_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function scopeIsFee(Builder $query): Builder
    {
        return $query->where('fee_type', self::TYPE_FEE);
    }

    public function scopeIsPenalty(Builder $query): Builder
    {
        return $query->where('fee_type', self::TYPE_PENALTY);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeExempted(Builder $query): Builder
    {
        return $query->where('isExempted', true);
    }

    /**
     * Settled transactions: paid or exempted.
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', self::STATUS_PAID)
            ->orWhere('isExempted', true));
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_PAID
            || $this->isExempted
            || $this->status === self::STATUS_EXEMPTED;
    }
}
