<x-filament-panels::page>
    @php($parsedResult = $this->getRecord()->parsed_result ?? [])

    @if (blank($parsedResult))
        <x-filament::section>
            Результати обробки для цього імпорту відсутні.
        </x-filament::section>
    @else
        @foreach ($parsedResult['motions'] ?? [] as $motion)
            <x-filament::section :heading="'Питання № ' . ($motion['question_number'] ?? '')">
                @php($reviewStatus = ($motion['review_status'] ?? null) === \App\Enums\MotionReviewStatus::Approved->value ? \App\Enums\MotionReviewStatus::Approved : null)

                <x-slot name="afterHeader">
                    @if ($reviewStatus)
                        <x-filament::badge :color="$reviewStatus->getColor()">
                            {{ $reviewStatus->getLabel() }}
                        </x-filament::badge>
                    @endif
                </x-slot>

                <div class="space-y-4">
                    <div class="space-y-1">
                        <p class="font-medium">
                            @if (filled($motion['project_number'] ?? null))
                                № {{ $motion['project_number'] }} —
                            @endif
                            {{ $motion['title'] ?? '' }}
                        </p>
                        <p class="text-center font-bold">{{ mb_strtoupper($motion['result'] ?? '') }}</p>
                    </div>

                    <dl class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                        <div><dt class="font-medium">За</dt><dd>{{ $motion['counts']['for'] ?? 0 }}</dd></div>
                        <div><dt class="font-medium">Проти</dt><dd>{{ $motion['counts']['against'] ?? 0 }}</dd></div>
                        <div><dt class="font-medium">Утрималися</dt><dd>{{ $motion['counts']['abstain'] ?? 0 }}</dd></div>
                        <div><dt class="font-medium">Не голосували</dt><dd>{{ $motion['counts']['not_voting'] ?? 0 }}</dd></div>
                        <div><dt class="font-medium">Відсутні</dt><dd>{{ $motion['counts']['absent'] ?? 0 }}</dd></div>
                    </dl>

                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($motion['votes'] ?? [] as $vote)
                            @php($voteColor = match ($vote['result'] ?? '') {
                                'За' => 'success',
                                'Проти' => 'danger',
                                'Утримався' => 'warning',
                                default => 'gray',
                            })

                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $vote['name'] ?? '' }}
                                </span>
                                <x-filament::badge :color="$voteColor">
                                    {{ $vote['result'] ?? '' }}
                                </x-filament::badge>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
