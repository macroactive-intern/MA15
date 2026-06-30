<?php

namespace App\Models;

use Database\Factories\MacroLogFactory;
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
            'logged_at' => 'date',
            'protein_g' => 'decimal:2',
            'carbs_g'   => 'decimal:2',
            'fat_g'     => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
