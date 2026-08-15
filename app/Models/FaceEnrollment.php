<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class FaceEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'descriptors',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDescriptorsAttribute(?string $value): array
    {
        if (! $value) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($value), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setDescriptorsAttribute(array $value): void
    {
        $this->attributes['descriptors'] = Crypt::encryptString(json_encode($value));
    }
}