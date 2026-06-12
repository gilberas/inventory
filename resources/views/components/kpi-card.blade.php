@props([
    'title'    => '',
    'value'    => '0',
    'sub'      => null,
    'icon'     => 'chart-bar',
    'color'    => 'primary',
    'trend'    => null,
    'trendVal' => null,
    'href'     => null,
])
@php
$colorVars = [
    'primary' => 'var(--primary)',
    'green'   => 'var(--success)',
    'orange'  => 'var(--warning)',
    'red'     => 'var(--danger)',
    'blue'    => 'var(--info)',
    'gray'    => 'var(--muted)',
];
$cssColor    = $colorVars[$color] ?? 'var(--primary)';
$borderClass = match($color) {
    'green'  => 'success',
    'orange' => 'warning',
    'red'    => 'danger',
    'blue'   => 'sky',
    default  => '',
};
$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    {{ $href ? 'href="'.$href.'"' : '' }}
    class="kpi-card {{ $borderClass }}"
    {{ $attributes->except(['title','value','sub','icon','color','trend','trendVal','href']) }}
    @if($href) style="text-decoration:none;cursor:pointer" @endif>
    <span class="kpi-icon" style="color:{{ $cssColor }}">
        <i class="fas fa-{{ $icon }}"></i>
    </span>
    <span class="kpi-value" style="color:{{ $cssColor }}">{{ $value }}</span>
    <span class="kpi-label">{{ $title }}</span>
    @if($sub)
    <span class="kpi-sub">{{ $sub }}</span>
    @endif
    @if($trend && $trendVal)
    <span class="kpi-trend" style="color:{{ $trend === 'up' ? 'var(--success)' : 'var(--danger)' }}">
        {{ $trend === 'up' ? '▲' : '▼' }} {{ $trendVal }}
    </span>
    @endif
</{{ $tag }}>
