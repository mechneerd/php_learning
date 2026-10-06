@props(['card' => null, 'flipped' => false, 'nextIntervals' => null])

@if ($card)
    <div class="space-y-4">
        <p class="text-center text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400 uppercase">
            {{ $card->card_type->label() }}
            @if ($card->concept)
                &middot; {{ $card->concept->name }}
            @endif
        </p>

        {{-- Card --}}
        <button
            type="button"
            wire:click="flip"
            class="block w-full rounded-2xl border border-zinc-200 bg-white p-8 text-left shadow-sm transition hover:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
            style="min-height: 16rem"
            aria-label="Flip card"
        >
            <p class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400 uppercase">
                {{ $flipped ? 'Back' : 'Front' }} &middot; {{ $flipped ? 'click to hide' : 'click to reveal' }}
            </p>
            <div class="mt-4 text-base leading-relaxed text-zinc-800 dark:text-zinc-100">
                {!! nl2br(e($flipped ? $card->back : $card->front)) !!}
            </div>
        </button>

        {{-- Grading --}}
        @if ($flipped && $nextIntervals)
            <div class="grid grid-cols-3 gap-3">
                @foreach (['hard' => 'Hard', 'ok' => 'Got it', 'easy' => 'Easy'] as $grade => $label)
                    <button
                        type="button"
                        wire:click="grade('{{ $grade }}')"
                        @class([
                            'rounded-xl border px-4 py-3 text-sm font-medium transition',
                            'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-950/40' => $grade === 'hard',
                            'border-sky-300 text-sky-700 hover:bg-sky-50 dark:border-sky-700 dark:text-sky-300 dark:hover:bg-sky-950/40' => $grade === 'ok',
                            'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40' => $grade === 'easy',
                        ])
                    >
                        {{ $label }}
                        <span class="mt-0.5 block text-xs font-normal opacity-70">
                            next in {{ $nextIntervals[$grade] }}d
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
@endif
