<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class FreeChapterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Subject::with(['chapters' => fn ($q) => $q->orderBy('order', 'asc')])
            ->get()
            ->each(function (Subject $subject): void {
                $firstTwo = $subject->chapters->take(2);
                foreach ($firstTwo as $chapter) {
                    $chapter->is_free = true;
                    $chapter->save();
                }
            });
    }
}
