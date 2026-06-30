<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\MacroLogFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MacroLog extends Model
{
    /** @use HasFactory<MacroLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'logged_at',
        'protein_g',
        'carbs_g',
        'fat_g',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'protein_g' => 'decimal:2',
            'carbs_g'   => 'decimal:2',
            'fat_g'     => 'decimal:2',
        ];
    }

    // Stores as Y-m-d string; the date cast would serialize via fromDateTime()
    // which appends H:i:s, breaking assertDatabaseHas comparisons in SQLite.
    protected function loggedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
