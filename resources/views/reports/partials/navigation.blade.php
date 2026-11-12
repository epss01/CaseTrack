<nav class="section-nav" aria-label="{{ __('Reports navigation') }}">
    <a href="{{ route('reports.index') }}" @if (request()->routeIs('reports.index', 'reports.show')) aria-current="page" @endif>{{ __('CHR monitoring worksheet') }}</a>
    <a href="{{ route('reports.analytics') }}" @if (request()->routeIs('reports.analytics')) aria-current="page" @endif>{{ __('Analytics') }}</a>
    <a href="{{ route('reports.case-profiles') }}" @if (request()->routeIs('reports.case-profiles')) aria-current="page" @endif>{{ __('Case profiles') }}</a>
</nav>
