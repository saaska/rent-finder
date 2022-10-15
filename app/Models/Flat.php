<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flat extends Model
{
    use HasFactory;

    protected $casts = [
        'photos' => 'array',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
