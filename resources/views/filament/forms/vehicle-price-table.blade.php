@php
    $containers = $getChildComponentContainers();
    $addAction = $getAction($getAddActionName());
    $deleteAction = $getAction($getDeleteActionName());
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{}" class="space-y-3">
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700" tabindex="0" role="region" aria-label="Vehicle wash price table">
            <table class="w-full min-w-[720px] text-left text-sm">
                <caption class="sr-only">Vehicle wash prices in Ghana cedis. Combined total is the sum of Body, Under and Engine.</caption>
                <thead class="bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <tr>
                        <th scope="col" class="w-64 px-4 py-3 font-semibold">Vehicle type</th>
                        @foreach (['Body', 'Under', 'Engine', 'Combined total'] as $heading)
                            <th scope="col" class="px-3 py-3 font-semibold">{{ $heading }}</th>
                        @endforeach
                        <th scope="col" class="px-3 py-3"><span class="sr-only">Remove vehicle</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach ($containers as $uuid => $item)
                        <tr wire:key="{{ $getStatePath() }}.{{ $uuid }}" class="align-top">
                            @foreach ($item->getComponents() as $component)
                                <td class="px-3 py-3 {{ $loop->last ? 'whitespace-nowrap font-semibold text-gray-950 dark:text-white' : 'min-w-[110px]' }}">{{ $component }}</td>
                            @endforeach
                            <td class="px-3 py-3">
                                @if ($isDeletable())
                                    {{ $deleteAction(['item' => $uuid]) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">All prices are in GH₵. Leave unavailable options blank. On a phone, swipe the table sideways to see all columns.</p>
        @if ($isAddable())
            {{ $addAction }}
        @endif
    </div>
</x-dynamic-component>
