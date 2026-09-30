@php
    $payload = $block['payload'] ?? [];
    $exampleId = $payload['code_example_id'] ?? null;
    $example = $exampleId !== null ? ($codeExamples[$exampleId] ?? null) : null;
    $tierStyles = match ((int) ($example['tier'] ?? 1)) {
        1 => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
        2 => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200',
        3 => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200',
        default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
    };
@endphp
@if ($example)
    <figure class="group/code my-2 overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950">
        <figcaption class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-800 px-4 py-2 text-xs text-zinc-400">
            <span class="flex items-center gap-2">
                <span class="font-medium text-zinc-200">{{ $example['title'] }}</span>
                <span class="rounded-full px-2 py-0.5 font-medium {{ $tierStyles }}">{{ \App\Enums\CodeTier::tryFrom((int) $example['tier'])?->label() ?? 'Tier '.$example['tier'] }}</span>
                @if ($example['listing_ref'] !== null)
                    <span class="font-mono">listing {{ $example['listing_ref'] }}</span>
                @endif
            </span>
            <x-citation-line :citation="$block['citation'] ?? null" />
        </figcaption>
        <pre class="overflow-x-auto p-4 text-sm leading-6"><code class="language-php">{{ $example['code'] }}</code></pre>
        @if ($example['expected_output'] !== null)
            <div class="border-t border-zinc-800 px-4 py-3">
                <p class="mb-1 text-[10px] font-semibold tracking-wide text-zinc-500 uppercase">Output</p>
                <pre class="overflow-x-auto font-mono text-sm text-emerald-300">{{ $example['expected_output'] }}</pre>
            </div>
        @endif
    </figure>
    @if ($example['explanation'] !== null)
        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{!! nl2br(e($example['explanation'])) !!}</p>
    @endif
    @if ($example['syntax_notes'] !== null)
        <div class="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-sm leading-6 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
            <span class="font-semibold">Syntax notes:</span> {!! nl2br(e($example['syntax_notes'])) !!}
        </div>
    @endif
    @if ($example['common_mistake'] !== null)
        <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            <span class="font-semibold">Common mistake:</span> {!! nl2br(e($example['common_mistake'])) !!}
        </div>
    @endif
@else
    <div class="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
        Code example #{{ $exampleId ?? '?' }} is not linked to this lesson.
    </div>
@endif
