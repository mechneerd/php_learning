<ul class="space-y-0.5 text-sm">
    @foreach ($tree as $chapter)
        @php
            $chapterLessonCount = collect($chapter['sections'])->sum(fn ($section) => count($section['lessons'])) + count($chapter['lessons']);
            $hasCurrent = collect($chapter['sections'])->contains(fn ($section) => collect($section['lessons'])->contains('id', $currentId))
                || collect($chapter['lessons'])->contains('id', $currentId);
        @endphp
        <li>
            <details {{ $hasCurrent ? 'open' : '' }} class="group/chapter">
                <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <span class="{{ $chapterLessonCount === 0 ? 'text-zinc-400' : 'font-medium text-zinc-700 dark:text-zinc-200' }}">
                        Ch {{ $chapter['number'] }} · {{ $chapter['title'] }}
                    </span>
                    <span class="text-[10px] text-zinc-500 dark:text-zinc-400">{{ $chapter['page_printed_from'] !== null ? $chapter['page_printed_from'].'–'.$chapter['page_printed_to'] : '' }}</span>
                </summary>

                <div class="ms-2 mt-0.5 border-s border-zinc-200 ps-2 dark:border-zinc-700">
                    @foreach ($chapter['sections'] as $section)
                        <p class="mt-2 mb-0.5 text-xs text-zinc-500 dark:text-zinc-400" style="padding-inline-start: {{ ($section['level'] - 1) * 0.5 }}rem">
                            {{ $section['title'] }}
                        </p>
                        @if (count($section['lessons']) > 0)
                            <ul class="mb-1 space-y-0.5" style="padding-inline-start: {{ ($section['level'] - 1) * 0.5 }}rem">
                                @foreach ($section['lessons'] as $lessonNode)
                                    <li>
                                        <a
                                            href="{{ route('lessons.show', $lessonNode['slug']) }}"
                                            wire:navigate
                                            @class([
                                                'block rounded-md px-2 py-1',
                                                'bg-zinc-900 font-medium text-white dark:bg-zinc-100 dark:text-zinc-900' => $lessonNode['id'] === $currentId,
                                                'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $lessonNode['id'] !== $currentId,
                                            ])
                                        >
                                            {{ $lessonNode['title'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach

                    @foreach ($chapter['lessons'] as $lessonNode)
                        <a
                            href="{{ route('lessons.show', $lessonNode['slug']) }}"
                            wire:navigate
                            @class([
                                'mb-0.5 block rounded-md px-2 py-1',
                                'bg-zinc-900 font-medium text-white dark:bg-zinc-100 dark:text-zinc-900' => $lessonNode['id'] === $currentId,
                                'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $lessonNode['id'] !== $currentId,
                            ])
                        >
                            {{ $lessonNode['title'] }}
                        </a>
                    @endforeach
                </div>
            </details>
        </li>
    @endforeach
</ul>
