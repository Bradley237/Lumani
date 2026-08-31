<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add level to subjects table if missing
        if (! Schema::hasColumn('subjects', 'level')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->string('level')->nullable()->after('exam_subsystem');
            });
        }

        // 2. Add exam_subsystem and level to chapters table if missing
        Schema::table('chapters', function (Blueprint $table) {
            if (! Schema::hasColumn('chapters', 'exam_subsystem')) {
                $table->string('exam_subsystem')->nullable()->after('title');
            }
            if (! Schema::hasColumn('chapters', 'level')) {
                $table->string('level')->nullable()->after('exam_subsystem');
            }
        });

        // 3. Normalize existing values in users table
        DB::table('users')->where('exam_system', 'anglophone')->update(['exam_system' => 'gce']);
        DB::table('users')->where('exam_system', 'francophone')->update(['exam_system' => 'obc']);

        DB::table('users')->whereIn('level', ['O-Level', 'o_level'])->update(['level' => 'ordinary_level']);
        DB::table('users')->whereIn('level', ['A-Level', 'a_level'])->update(['level' => 'advanced_level']);
        DB::table('users')->whereIn('level', ['BEPC', 'bepc'])->update(['level' => 'bepc']);
        DB::table('users')->whereIn('level', ['Probatoire', 'probatoire'])->update(['level' => 'probatoire']);
        DB::table('users')->whereIn('level', ['Baccalaureat', 'Baccalauréat', 'bac'])->update(['level' => 'bac']);

        // 4. Normalize existing values in subjects table
        DB::table('subjects')->where('exam_subsystem', 'anglophone')->update(['exam_subsystem' => 'gce']);
        DB::table('subjects')->where('exam_subsystem', 'francophone')->update(['exam_subsystem' => 'obc']);
        DB::table('subjects')->where('exam_subsystem', 'general')->update(['exam_subsystem' => null]);

        // 5. Normalize existing values in past_papers table
        DB::table('past_papers')->where('exam_subsystem', 'anglophone')->update(['exam_subsystem' => 'gce']);
        DB::table('past_papers')->where('exam_subsystem', 'francophone')->update(['exam_subsystem' => 'obc']);
        DB::table('past_papers')->where('exam_subsystem', 'general')->update(['exam_subsystem' => null]);

        DB::table('past_papers')->whereIn('level', ['O-Level', 'o_level'])->update(['level' => 'ordinary_level']);
        DB::table('past_papers')->whereIn('level', ['A-Level', 'a_level'])->update(['level' => 'advanced_level']);
        DB::table('past_papers')->whereIn('level', ['BEPC', 'bepc'])->update(['level' => 'bepc']);
        DB::table('past_papers')->whereIn('level', ['Probatoire', 'probatoire'])->update(['level' => 'probatoire']);
        DB::table('past_papers')->whereIn('level', ['Baccalaureat', 'Baccalauréat', 'bac'])->update(['level' => 'bac']);

        // 6. Normalize existing values in weekly_challenges table
        DB::table('weekly_challenges')->where('exam_subsystem', 'anglophone')->update(['exam_subsystem' => 'gce']);
        DB::table('weekly_challenges')->where('exam_subsystem', 'francophone')->update(['exam_subsystem' => 'obc']);
        DB::table('weekly_challenges')->where('exam_subsystem', 'general')->update(['exam_subsystem' => null]);

        DB::table('weekly_challenges')->whereIn('level', ['O-Level', 'o_level'])->update(['level' => 'ordinary_level']);
        DB::table('weekly_challenges')->whereIn('level', ['A-Level', 'a_level'])->update(['level' => 'advanced_level']);
        DB::table('weekly_challenges')->whereIn('level', ['BEPC', 'bepc'])->update(['level' => 'bepc']);
        DB::table('weekly_challenges')->whereIn('level', ['Probatoire', 'probatoire'])->update(['level' => 'probatoire']);
        DB::table('weekly_challenges')->whereIn('level', ['Baccalaureat', 'Baccalauréat', 'bac'])->update(['level' => 'bac']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('subjects', 'level')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('level');
            });
        }

        Schema::table('chapters', function (Blueprint $table) {
            if (Schema::hasColumn('chapters', 'exam_subsystem')) {
                $table->dropColumn('exam_subsystem');
            }
            if (Schema::hasColumn('chapters', 'level')) {
                $table->dropColumn('level');
            }
        });
    }
};
