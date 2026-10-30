<nav class="section-nav" aria-label="{{ __('Reports navigation') }}">
    <a href="{{ route('reports.index') }}" @if (! request()->routeIs('reports.analytics')) aria-current="page" @endif>{{ __('Case reports') }}</a>
    <a href="{{ route('reports.analytics') }}" @if (request()->routeIs('reports.analytics')) aria-current="page" @endif>{{ __('Analytics') }}</a>
</nav>
