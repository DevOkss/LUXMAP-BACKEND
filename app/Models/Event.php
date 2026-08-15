<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'academic_term_id',
        'title',
        'description',
        'venue',
        'uuid',
        'time_from',
        'time_to',
        'event_date',
        'qr_secret',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (! $event->uuid) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function qrConfigurations(): HasMany
    {
        return $this->hasMany(QrConfiguration::class);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Aggregated required year levels across the event's QR configurations.
     * Returns ['all'] when any configuration requests all years.
     */
    public function requiredYears(): array
    {
        $years = [];

        foreach ($this->qrConfigurations as $configuration) {
            $list = $configuration->required_years ?? [];

            if (in_array('all', $list, true)) {
                return ['all'];
            }

            foreach ($list as $year) {
                $year = (string) $year;
                if (!in_array($year, $years, true)) {
                    $years[] = $year;
                }
            }
        }

        return $years;
    }

    public function scopeUpcoming($query)
    {
        return $query->where(function ($q) {
            $q->whereDate('event_date', '>', now()->toDateString())
                ->orWhere(function ($q2) {
                    $q2->whereDate('event_date', now()->toDateString())
                        ->whereRaw('COALESCE(time_to, ?) >= ?', ['23:59', now()->format('H:i')]);
                });
        });
    }

    /**
     * Events whose date + time_to have fully passed (i.e. no longer upcoming).
     */
    public function scopeEnded($query)
    {
        return $query->where(function ($q) {
            $q->whereDate('event_date', '<', now()->toDateString())
                ->orWhere(function ($q2) {
                    $q2->whereDate('event_date', now()->toDateString())
                        ->whereRaw('COALESCE(time_to, ?) < ?', ['23:59', now()->format('H:i')]);
                });
        });
    }
}
