@php
    $barClasses = fn (string $style): string => match ($style) {
        'confirmed' => 'bg-primary-600 text-white dark:bg-primary-500',
        'in_shop' => 'bg-info-600 text-white dark:bg-info-500 dark:text-gray-950',
        'ready' => 'bg-success-600 text-white dark:bg-success-500 dark:text-gray-950',
        'done' => 'bg-gray-200 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        'review' => 'bg-warning-100 text-warning-900 ring-1 ring-warning-400 dark:bg-warning-400/15 dark:text-warning-200',
        'hold' => 'border border-dashed border-info-500 bg-info-50 text-info-900 dark:bg-info-400/10 dark:text-info-200',
        'overdue' => 'bg-warning-500 text-gray-950',
        'block' => 'bg-gray-300 text-gray-700 dark:bg-white/15 dark:text-gray-200',
        'closed' => 'bg-[repeating-linear-gradient(45deg,transparent,transparent_6px,rgb(0_0_0/0.06)_6px,rgb(0_0_0/0.06)_12px)] text-gray-500 dark:bg-[repeating-linear-gradient(45deg,transparent,transparent_6px,rgb(255_255_255/0.06)_6px,rgb(255_255_255/0.06)_12px)] dark:text-gray-400',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<x-filament-panels::page>
    @if (! $shop)
        <div class="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10">
            @if ($branches === [])
                You’re not assigned to a branch yet. Ask an owner to add you to one in Settings → Users.
            @else
                Pick a branch.
            @endif
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @if (count($branches) > 1)
                    <x-filament::input.wrapper class="min-w-48">
                        <x-filament::input.select wire:model.live="branch" aria-label="Branch">
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @else
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $shop->name }}</span>
                @endif

                <div class="flex items-center gap-1">
                    <x-filament::icon-button icon="heroicon-m-chevron-left" color="gray" label="Earlier" wire:click="previous" />
                    <h2 class="min-w-44 text-center text-base font-semibold text-gray-950 dark:text-white" aria-live="polite">{{ $heading }}</h2>
                    <x-filament::icon-button icon="heroicon-m-chevron-right" color="gray" label="Later" wire:click="next" />
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-calendar" wire:click="today">Today</x-filament::button>
                </div>
            </div>

            <x-filament::tabs>
                <x-filament::tabs.item :active="$range === 'day'" wire:click="showRange('day')" icon="heroicon-m-sun">Day</x-filament::tabs.item>
                <x-filament::tabs.item :active="$range === 'week'" wire:click="showRange('week')" icon="heroicon-m-calendar-days">Week</x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        <ul class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-primary-600"></span> Reserved</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm border border-dashed border-info-500 bg-info-50"></span> Unpaid hold</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-warning-100 ring-1 ring-warning-400"></span> Screenshot to check</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-info-600"></span> In the shop</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-success-600"></span> Ready</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-warning-500"></span> Past ready-by</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-gray-300"></span> Bay out</li>
        </ul>

        @if ($overCapacity)
            <div class="flex items-start gap-2 rounded-xl bg-danger-50 px-4 py-3 text-sm text-danger-800 ring-1 ring-danger-200 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/30" role="status">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
                <p>More cars than bays at some point this {{ $range }}. They’re in the red lanes: move one, or make room.</p>
            </div>
        @endif

        @if ($shop->bays < 1)
            <div class="rounded-xl bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-warning-200 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/30">
                This branch has no bays set, so it takes no onsite bookings. Set its bays in Shop → Branches.
            </div>
        @endif

        {{-- The bays (tablet and up) --}}
        <div class="hidden overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 md:block dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full min-w-[56rem] table-fixed border-collapse text-xs">
                <colgroup>
                    <col class="w-28" />
                    @foreach ($days as $day)
                        <col @class(['w-14' => ! $day['open']]) />
                    @endforeach
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th scope="col" class="px-2 py-2 text-start font-medium text-gray-500 dark:text-gray-400"><span class="sr-only">Lane</span></th>
                        @foreach ($days as $day)
                            <th scope="col" @class([
                                'border-s border-gray-200 px-2 py-2 text-start align-bottom dark:border-white/10',
                                'bg-primary-50/60 dark:bg-primary-400/5' => $day['isToday'],
                            ])>
                                <span class="block font-semibold text-gray-900 dark:text-white">{{ $day['date']->format($range === 'day' ? 'l, M j' : 'D j') }}</span>
                                <span class="block font-normal text-gray-500 dark:text-gray-400">
                                    {{ $day['open'] ? $day['open']->format('g:i A').' – '.$day['close']->format('g:i A') : 'Closed' }}
                                </span>
                                @if ($range === 'day' && $day['open'])
                                    @php($hours = (int) ceil($day['open']->diffInHours($day['close'])))
                                    <span class="relative mt-1 block h-4 text-[10px] text-gray-400" aria-hidden="true">
                                        @for ($h = 0; $h <= $hours; $h += 2)
                                            <span class="absolute -translate-x-1/2" style="left: {{ min(100, round($h / max(1, $hours) * 100, 2)) }}%">{{ $day['open']->addHours($h)->format('ga') }}</span>
                                        @endfor
                                    </span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class(['border-b border-gray-100 dark:border-white/5', 'bg-danger-50/50 dark:bg-danger-400/5' => $row['overflow']])>
                            <th scope="row" @class(['px-2 py-1 text-start font-medium', 'text-danger-700 dark:text-danger-300' => $row['overflow'], 'text-gray-700 dark:text-gray-200' => ! $row['overflow']])>{{ $row['label'] }}</th>
                            @foreach ($days as $index => $day)
                                <td @class(['relative h-12 border-s border-gray-200 p-0 dark:border-white/10', 'bg-gray-50 dark:bg-white/[0.02]' => ! $day['open']])>
                                    @foreach ($closureSegments[$index] as $segment)
                                        <span class="absolute inset-y-0 {{ $barClasses('closed') }}" style="left: {{ $segment['left'] }}%; width: {{ $segment['width'] }}%" title="Closed: {{ $segment['label'] }}"></span>
                                    @endforeach
                                    @foreach ($row['segments'][$index] as $segment)
                                        @php($tag = $segment['url'] ? 'a' : 'span')
                                        <{{ $tag }}
                                            @if ($segment['url']) href="{{ $segment['url'] }}" wire:navigate @endif
                                            @class([
                                                'absolute inset-y-1 flex min-w-0 flex-col justify-center overflow-hidden px-1.5 leading-tight shadow-sm',
                                                'rounded-s-md' => $segment['startsHere'],
                                                'rounded-e-md' => $segment['endsHere'],
                                                $barClasses($segment['style']),
                                            ])
                                            style="left: {{ $segment['left'] }}%; width: {{ $segment['width'] }}%"
                                            title="{{ $segment['label'] }}{{ isset($segment['time']) ? ' · '.$segment['time'] : '' }}{{ $segment['sub'] ? ' · '.$segment['sub'] : '' }}{{ ! empty($segment['note']) ? ' · '.$segment['note'] : '' }}"
                                        >
                                            <span class="truncate font-semibold">{{ $segment['label'] }}</span>
                                            <span class="truncate opacity-80">{{ ! empty($segment['note']) ? $segment['note'] : $segment['sub'] }}</span>
                                        </{{ $tag }}>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <th scope="row" class="px-2 py-1 text-start font-medium text-gray-700 dark:text-gray-200">
                            Home service
                            @if ($unscheduledHome)
                                <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('index', ['activeTab' => 'home']) }}" class="mt-0.5 block font-normal text-warning-700 underline dark:text-warning-300">{{ $unscheduledHome }} to schedule</a>
                            @endif
                        </th>
                        @foreach ($days as $index => $day)
                            <td @class(['relative h-12 border-s border-gray-200 p-0 dark:border-white/10', 'bg-gray-50 dark:bg-white/[0.02]' => ! $day['open']])>
                                @foreach ($homeSegments[$index] as $segment)
                                    <a href="{{ $segment['url'] }}" wire:navigate @class([
                                        'absolute inset-y-1 flex min-w-0 flex-col justify-center overflow-hidden rounded-md px-1.5 leading-tight shadow-sm',
                                        $barClasses($segment['style']),
                                    ]) style="left: {{ $segment['left'] }}%; width: {{ $segment['width'] }}%" title="{{ $segment['label'] }} · {{ $segment['time'] }}">
                                        <span class="truncate font-semibold">{{ $segment['label'] }}</span>
                                        <span class="truncate opacity-80">{{ $segment['sub'] }}</span>
                                    </a>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Phones: one list, in drop-off order --}}
        <div class="space-y-2 md:hidden">
            @forelse ($agenda as $item)
                <a href="{{ $item['url'] }}" wire:navigate class="flex items-start gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <span class="mt-1 size-3 shrink-0 rounded-sm {{ $barClasses($item['style']) }}"></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $item['label'] }}</span>
                        <span class="block text-xs text-gray-600 dark:text-gray-400">{{ $item['time'] }}</span>
                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['note'] ?? $item['sub'] }}</span>
                    </span>
                </a>
            @empty
                <p class="rounded-xl bg-white p-4 text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10">Nothing booked this {{ $range }}.</p>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
