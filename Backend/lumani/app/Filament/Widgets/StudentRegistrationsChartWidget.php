<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class StudentRegistrationsChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Student Registrations (Last 30 Days)';

    protected function getType(): string
    {
        return 'line';
    }

    public function getData(): array
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        $registrations = User::where('role', UserRole::Student)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get()
            ->groupBy(function ($user) {
                return $user->created_at->format('Y-m-d');
            })
            ->map(fn ($group) => $group->count());

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $formattedKey = $date->format('Y-m-d');
            $labels[] = $date->format('M d');
            $data[] = $registrations->get($formattedKey, 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Students',
                    'data' => $data,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => 'start',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
