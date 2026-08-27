<x-filament-panels::page>
    @php
        $ungraded = $this->getUngradedAnswers();
    @endphp

    @if($ungraded->isEmpty())
        <div class="p-8 text-center bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="flex justify-center mb-3 text-emerald-500">
                <x-filament::icon icon="heroicon-o-check-circle" class="w-12 h-12" />
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">All Structural Answers Graded!</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">There are no pending structural answers awaiting grading in the queue.</p>
        </div>
    @else
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                    {{ $ungraded->count() }} pending answer(s) awaiting grading
                </span>
            </div>

            @foreach($ungraded as $ans)
                <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 space-y-4 shadow-sm" wire:key="ans-{{ $ans->id }}">
                    <div class="flex flex-wrap items-center justify-between border-b pb-3 border-gray-100 dark:border-gray-800 gap-2">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                {{ $ans->attempt->challenge->title }}
                            </span>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Student: {{ $ans->attempt->user->name }} ({{ $ans->attempt->user->email }})
                            </h4>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Submitted: {{ $ans->attempt->submitted_at?->diffForHumans() ?? 'N/A' }}
                        </div>
                    </div>

                    <div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Question</span>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
                            {{ $ans->question->question_text }}
                        </p>
                    </div>

                    <div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Student Answer</span>
                        <div class="mt-1 text-sm text-gray-800 dark:text-gray-200 bg-amber-50/50 dark:bg-amber-950/20 p-3 rounded-lg border border-amber-200 dark:border-amber-900/50 whitespace-pre-wrap">
                            {{ $ans->answer_text ?: 'No answer provided by student.' }}
                        </div>
                    </div>

                    <div class="pt-2 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Score (Max {{ $ans->question->max_points }}):
                            </label>
                            <input
                                type="number"
                                min="0"
                                max="{{ $ans->question->max_points }}"
                                wire:model="grades.{{ $ans->id }}"
                                placeholder="0 - {{ $ans->question->max_points }}"
                                class="w-32 rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm focus:border-primary-500 focus:ring-primary-500"
                            />
                        </div>

                        <x-filament::button
                            wire:click="gradeAnswer({{ $ans->id }})"
                            color="success"
                            size="sm"
                        >
                            Submit Grade
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
