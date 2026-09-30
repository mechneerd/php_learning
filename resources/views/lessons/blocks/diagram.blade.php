@php
    $payload = $block['payload'] ?? [];
    $diagramId = $payload['diagram_id'] ?? null;
    $diagram = $diagramId !== null ? ($diagrams[$diagramId] ?? null) : null;
    $sanitizer = app(\App\Services\Content\MermaidSanitizer::class);
@endphp
@if ($diagram)
    <figure class="my-2 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <figcaption class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 bg-zinc-50 px-4 py-2 text-sm font-medium dark:border-zinc-800 dark:bg-zinc-900">
            <span>{{ $diagram['title'] }}</span>
            <span class="flex items-center gap-2 text-xs font-normal text-zinc-500">
                <span class="rounded-full bg-zinc-200 px-2 py-0.5 dark:bg-zinc-800">{{ \App\Enums\DiagramKind::tryFrom($diagram['kind'])?->label() ?? ucfirst($diagram['kind']) }}</span>
                <x-source-badge :source="$diagram['source']" :label="\App\Enums\DiagramSource::tryFrom($diagram['source'])?->label() ?? 'AI'" />
            </span>
        </figcaption>
        <div class="mermaid overflow-x-auto p-4 text-center">{{ $sanitizer->sanitize($diagram['mermaid_source']) }}</div>
        <div class="border-t border-zinc-100 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-800">
            {{ $diagram['source'] === 'book_figure' ? 'Reproduced from the book' : 'AI-generated illustration' }}@if ($diagram['figure_ref'] !== null) · Figure {{ $diagram['figure_ref'] }}@endif@if ($diagram['page_pdf'] !== null) · PDF {{ $diagram['page_pdf'] }}@endif
        </div>
    </figure>
@else
    <div class="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
        Diagram #{{ $diagramId ?? '?' }} is not linked to this lesson.
    </div>
@endif
