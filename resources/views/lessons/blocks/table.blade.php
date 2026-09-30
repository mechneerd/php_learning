@php($payload = $block['payload'] ?? [])
<div class="my-2 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 text-left text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:bg-zinc-900">
            <tr>
                @foreach (($payload['headers'] ?? []) as $header)
                    <th class="px-4 py-2.5">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100 text-zinc-700 dark:divide-zinc-800 dark:text-zinc-300">
            @foreach (($payload['rows'] ?? []) as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td class="px-4 py-2.5 align-top">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<x-citation-line :citation="$block['citation'] ?? null" class="block text-end text-xs" />
