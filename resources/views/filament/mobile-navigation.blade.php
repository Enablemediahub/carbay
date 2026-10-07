@auth
    @php
        $navigationGroups = \Filament\Facades\Filament::getNavigation();
        $navigationItems = collect($navigationGroups)
            ->flatMap(fn ($group) => $group->getItems())
            ->filter(fn ($item) => $item->isVisible() && filled($item->getUrl()));

        $isAppPanel = filament()->getCurrentPanel()->getId() === 'app';
        $userCanCreateJob = $isAppPanel && in_array(auth()->user()?->role, ['manager', 'ceo'], true);
        $createJobItem = $userCanCreateJob
            ? $navigationItems->first(fn ($item) => strcasecmp($item->getLabel(), 'New wash job') === 0)
            : null;
        $priorityLabels = $isAppPanel
            ? ['Dashboard', "Today's jobs", 'Sales']
            : ['Dashboard', 'Tenants', 'Subscription invoices', 'Global reference data'];

        $primaryItems = collect($priorityLabels)
            ->map(fn (string $label) => $navigationItems->first(
                fn ($item) => strcasecmp($item->getLabel(), $label) === 0,
            ))
            ->filter()
            ->unique(fn ($item) => $item->getUrl())
            ->reject(fn ($item) => $createJobItem && $item->getUrl() === $createJobItem->getUrl())
            ->values();

        $primaryItems = $primaryItems
            ->concat($navigationItems->reject(fn ($item) => $primaryItems->contains(
                fn ($primaryItem) => $primaryItem->getUrl() === $item->getUrl(),
            ))->reject(fn ($item) => $createJobItem && $item->getUrl() === $createJobItem->getUrl()))
            ->take($createJobItem ? 3 : 4)
            ->values();
        $leftItems = $createJobItem ? $primaryItems->take(2) : $primaryItems;
        $rightItems = $createJobItem ? $primaryItems->skip(2) : collect();
        $hasMoreItems = $navigationItems->count() > $primaryItems->count() + ($createJobItem ? 1 : 0);
    @endphp

    @if ($primaryItems->isNotEmpty())
        <nav class="carbay-mobile-dock {{ $createJobItem ? 'has-create-action' : '' }}" aria-label="Primary navigation">
            @foreach ($leftItems as $item)
                @php($isActive = $item->isActive() || $item->isChildItemsActive())
                @php($dockLabel = match (strtolower($item->getLabel())) {
                    'dashboard' => 'Home',
                    'new wash job' => 'New job',
                    "today's jobs" => 'Jobs',
                    'worker check-in' => 'Check-in',
                    'subscription invoices' => 'Plans',
                    'branch add-on invoices' => 'Branches',
                    default => $item->getLabel(),
                })
                <a
                    class="carbay-mobile-dock-item {{ $isActive ? 'is-active' : '' }}"
                    href="{{ $item->getUrl() }}"
                    aria-label="{{ $item->getLabel() }}"
                    title="{{ $item->getLabel() }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    <x-filament::icon :icon="$isActive ? ($item->getActiveIcon() ?? $item->getIcon()) : ($item->getIcon() ?? 'heroicon-o-squares-2x2')" />
                    <span>{{ $dockLabel }}</span>
                </a>
            @endforeach

            @if ($createJobItem)
                <a
                    class="carbay-mobile-dock-create {{ $createJobItem->isActive() ? 'is-active' : '' }}"
                    href="{{ $createJobItem->getUrl() }}"
                    aria-label="{{ $createJobItem->getLabel() }}"
                    title="{{ $createJobItem->getLabel() }}"
                    @if ($createJobItem->isActive()) aria-current="page" @endif
                >
                    <span class="carbay-mobile-dock-create-icon">
                        <x-filament::icon icon="heroicon-o-plus" />
                    </span>
                    <span>New job</span>
                </a>
            @endif

            @foreach ($rightItems as $item)
                @php($isActive = $item->isActive() || $item->isChildItemsActive())
                <a
                    class="carbay-mobile-dock-item {{ $isActive ? 'is-active' : '' }}"
                    href="{{ $item->getUrl() }}"
                    aria-label="{{ $item->getLabel() }}"
                    title="{{ $item->getLabel() }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    <x-filament::icon :icon="$isActive ? ($item->getActiveIcon() ?? $item->getIcon()) : ($item->getIcon() ?? 'heroicon-o-squares-2x2')" />
                    <span>{{ $item->getLabel() === 'Sales' ? 'Sales' : $item->getLabel() }}</span>
                </a>
            @endforeach

            @if ($hasMoreItems)
                <details class="carbay-mobile-more">
                    <summary aria-label="More navigation">
                        <x-filament::icon icon="heroicon-o-ellipsis-horizontal" />
                        <span>More</span>
                    </summary>
                    <div class="carbay-mobile-more-panel">
                        @foreach ($navigationGroups as $group)
                            @php($groupItems = collect($group->getItems())->filter(fn ($item) => $item->isVisible() && (filled($item->getUrl()) || filled($item->getChildItems()))))
                            @if ($groupItems->isNotEmpty())
                                @if (filled($group->getLabel()))
                                    <p class="carbay-mobile-more-heading">{{ $group->getLabel() }}</p>
                                @endif
                                @foreach ($groupItems as $item)
                                    @php($children = collect($item->getChildItems())->filter(fn ($child) => $child->isVisible() && filled($child->getUrl())))
                                    @if (filled($item->getUrl()))
                                        <a href="{{ $item->getUrl() }}">
                                            <x-filament::icon :icon="$item->getIcon() ?? 'heroicon-o-squares-2x2'" />
                                            <span>{{ $item->getLabel() }}</span>
                                        </a>
                                    @endif
                                    @foreach ($children as $child)
                                        <a href="{{ $child->getUrl() }}">
                                            <x-filament::icon :icon="$child->getIcon() ?? 'heroicon-o-squares-2x2'" />
                                            <span>{{ $child->getLabel() }}</span>
                                        </a>
                                    @endforeach
                                @endforeach
                            @endif
                        @endforeach
                    </div>
                </details>
            @endif
        </nav>
    @endif
@endauth
