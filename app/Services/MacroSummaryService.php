<?php

namespace App\Services;

use App\Models\MacroLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class MacroSummaryService
{
    public function dailySummaryKey(int $userId, string $date): string
    {
        return "daily-summary:user:{$userId}:date:{$date}";
    }

    public function dailySummary(User $user, string $date): array
    {
        return Cache::remember(
            $this->dailySummaryKey($user->id, $date),
            now()->addDay(),
            fn () => $this->calculateDailySummary($user->id, $date)
        );
    }

    public function calculateDailySummary(int $userId, string $date): array
    {
        $row = MacroLog::query()
            ->where('user_id', $userId)
            ->where('logged_at', $date)
            ->selectRaw('
                COALESCE(SUM(protein_g), 0) as total_protein_g,
                COALESCE(SUM(carbs_g), 0)   as total_carbs_g,
                COALESCE(SUM(fat_g), 0)     as total_fat_g,
                COUNT(*)                     as entry_count
            ')
            ->first();

        $protein = (float) $row->total_protein_g;
        $carbs   = (float) $row->total_carbs_g;
        $fat     = (float) $row->total_fat_g;

        return [
            'date'            => $date,
            'total_calories'  => (int) round(($protein * 4) + ($carbs * 4) + ($fat * 9)),
            'total_protein_g' => $protein,
            'total_carbs_g'   => $carbs,
            'total_fat_g'     => $fat,
            'entry_count'     => (int) $row->entry_count,
        ];
    }

    public function forgetDailySummary(int $userId, string|CarbonInterface $date): void
    {
        $formatted = $date instanceof CarbonInterface ? $date->format('Y-m-d') : $date;
        Cache::forget($this->dailySummaryKey($userId, $formatted));
    }

    public function weeklySummary(User $user, string $startDate): array
    {
        // Implemented in Step 9
        return [];
    }
}
