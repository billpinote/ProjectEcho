@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $hasChildItems = filled($childItems);
    $canToggleChildItems = $hasChildItems && blank($url);
    $shouldRenderChildItems = $canToggleChildItems || $active || $activeChildItems;
@endphp

<li
    @if ($hasChildItems)
        x-data="{ isExpanded: @js($active || $activeChildItems) }"
    @endif
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-children' => $hasChildItems,
            'fi-sidebar-item-has-url' => filled($url),
            'fi-sidebar-item-is-toggle' => $canToggleChildItems,
        ])
    }}
>
    <a
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        @if ($canToggleChildItems)
            x-on:click.prevent="isExpanded = ! isExpanded"
            x-bind:aria-expanded="isExpanded.toString()"
            role="button"
        @else
            x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
        @endif
        @if ($sidebarCollapsible && (! $subNavigation))
            x-data="{ tooltip: false }"
            x-effect="
                tooltip = $store.sidebar.isOpen
                    ? false
                    : {
                          content: @js($slot->toHtml()),
                          placement: document.dir === 'rtl' ? 'left' : 'right',
                          theme: $store.theme,
                      }
            "
            x-tooltip.html="tooltip"
        @endif
        class="fi-sidebar-item-btn"
    >
        @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
            {{
                \Filament\Support\generate_icon_html(
                    ($active && $activeIcon) ? $activeIcon : $icon,
                    attributes: (new \Illuminate\View\ComponentAttributeBag())->class(['fi-sidebar-item-icon']),
                    size: \Filament\Support\Enums\IconSize::Large,
                )
            }}
        @endif

        @if ((blank($icon) && $grouped) || ($subGrouped && blank($icon)))
            <div
                @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-sidebar-item-grouped-border"
            >
                @if (! $first)
                    <div class="fi-sidebar-item-grouped-border-part-not-first"></div>
                @endif

                @if (! $last)
                    <div class="fi-sidebar-item-grouped-border-part-not-last"></div>
                @endif

                <div class="fi-sidebar-item-grouped-border-part"></div>
            </div>
        @endif

        <span
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-item-label"
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="fi-sidebar-item-badge-ctn"
            >
                <x-filament::badge
                    :color="$badgeColor"
                    :tooltip="$badgeTooltip"
                >
                    {{ $badge }}
                </x-filament::badge>
            </span>
        @endif

        @if ($hasChildItems)
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                x-bind:class="{ 'is-expanded': isExpanded }"
                class="fi-sidebar-item-toggle-icon"
            >
                {{
                    \Filament\Support\generate_icon_html(
                        \Filament\Support\Icons\Heroicon::ChevronDown,
                        attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fi-icon']),
                        size: \Filament\Support\Enums\IconSize::Small,
                    )
                }}
            </span>
        @endif
    </a>

    @if ($shouldRenderChildItems)
        <ul
            @if ($hasChildItems)
                x-show="isExpanded"
                x-collapse.duration.250ms
            @endif
            class="fi-sidebar-sub-group-items echo-sidebar-submenu"
        >
            @foreach ($childItems as $childItem)
                @php
                    $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                    $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                    $childItemActiveIcon = $childItem->getActiveIcon();
                    $childItemBadge = $childItem->getBadge();
                    $childItemBadgeColor = $childItem->getBadgeColor();
                    $childItemBadgeTooltip = $childItem->getBadgeTooltip();
                    $childItemIcon = $childItem->getIcon();
                    $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                    $childItemUrl = $childItem->getUrl();
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                @endphp

                <x-filament-panels::sidebar.item
                    :active="$isChildActive"
                    :active-child-items="$isChildItemChildItemsActive"
                    :active-icon="$childItemActiveIcon"
                    :badge="$childItemBadge"
                    :badge-color="$childItemBadgeColor"
                    :badge-tooltip="$childItemBadgeTooltip"
                    :child-items="$childItem->getChildItems()"
                    :first="$loop->first"
                    grouped
                    :icon="$childItemIcon"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                    sub-grouped
                    :sub-navigation="$subNavigation"
                    :url="$childItemUrl"
                    :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                >
                    {{ $childItem->getLabel() }}
                </x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
