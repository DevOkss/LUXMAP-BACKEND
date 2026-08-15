<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's cashless payment submission. Each row represents one obligation
 * item covered by a single submission attempt (row set shares $group_key).
 *
 * Submissions are NOT ledger entries: a `payments` transaction is created only
 * after an authorized officer verifies and approves the submission group.
 */
class PaymentSubmission extends Model
{
    use HasFactory;

    public const TYPE_FEE = 'fee';
    public const TYPE_PENALTY = 'penalty';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const METHOD_CASHLESS = 'cashless';

    public const CHANNEL_GCASH = 'gcash';
    public const CHANNEL_MAYA = 'maya';
    public const CHANNEL_BANK = 'bank_transfer';

    protected $fillable = [
        'user_id',
        'organization_id',
        'academic_term_id',
        'fee_type',
        'fee_id',
        'event_id',
        'amount',
        'payment_method',
        'payment_channel',
        'reference_number',
        'receipt_image',
        'group_key',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'lock_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByGroup(Builder $query, string $groupKey): Builder
    {
        return $query->where('group_key', $groupKey);
    }

    /**
     * Unresolved rows (pending/approved) still carry their lock_key; a
     * rejected or approved group has its lock_key nulled so the obligation
     * can be resubmitted (only after reject, or covered by a ledger entry).
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNotNull('lock_key');
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_APPROVED
            || $this->status === self::STATUS_REJECTED;
    }

    /**
     * Canonical identity of one obligation item, used as the duplicate lock.
     */
    public static function buildLockKey(
        int $userId,
        int $organizationId,
        int $academicTermId,
        string $feeType,
        ?int $feeId = null,
        ?int $eventId = null
    ): string {
        return implode('|', [
            $userId,
            $organizationId,
            $academicTermId,
            $feeType,
            $feeId ?? 0,
            $eventId ?? 0,
        ]);
    }
}