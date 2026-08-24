{{--
    A status mix, as a stacked bar with a legend beneath it.

    $counts — status => count, rendered in the order given.
    $total  — the denominator for the bar widths.

    The bar is decoration; the legend carries the actual figures. The card and
    heading around it belong to the caller, so each page can title its own.

    Shared by both dashboards. The colour map below is the reason this is a
    partial rather than markup copied twice: the whole point of it is that a
    status renders the same colour wherever it appears, which a second copy
    would break the first time someone edited only one of them.
--}}
@php
    use App\Models\CaseModel;

    /*
     * A colour per status, stable everywhere.
     *
     * The five-value vocabulary is ratified (CHR-Answers-2026-08-01, item 2)
     * and its colours now live on CaseModel::STATUS_COLOURS, shared with
     * cases/partials/status-badge.blade.php so a status can't render one way
     * here and another way one click away. Anything outside those five
     * (status is still an unconstrained string column) is hashed to the ramp
     * so it is at least consistent from one day to the next.
     */
    // Steps are two rungs apart, not adjacent: #3b82f6 beside #60a5fa rendered as
    // one solid blue bar, which defeats the only thing the bar is for.
    $ramp = ['#1d4ed8', '#3b82f6', '#93c5fd', '#1e3a8a'];

    $fixed = CaseModel::STATUS_COLOURS;

    $colourFor = fn (string $status) => $fixed[$status]
        ?? $ramp[crc32($status) % count($ramp)];

    /*
     * The counts arrive from a GROUP BY, whose result order is unspecified —
     * MySQL happened to return them alphabetically and SQLite need not agree, so
     * the legend read as an arbitrary list rather than a lifecycle.
     *
     * Sorting by position in $fixed does not ratify a status vocabulary: it
     * orders the five values already circulating and sends anything else to the
     * end, exactly as $colourFor already handles an unlisted status. A new
     * status still renders, still gets a stable colour, and simply sorts last.
     */
    $order = array_flip(array_keys($fixed));

    $counts = $counts->sortBy(fn ($count, $status) => $order[$status] ?? PHP_INT_MAX);
@endphp

<div class="progress-stacked mb-3" style="height: .5rem;" aria-hidden="true">
    @foreach ($counts as $status => $count)
        <div class="progress" style="width: {{ round($count / max($total, 1) * 100, 2) }}%; height: .5rem;">
            <div class="progress-bar" style="background-color: {{ $colourFor($status) }};"></div>
        </div>
    @endforeach
</div>

<ul class="list-inline mb-0 small">
    @foreach ($counts as $status => $count)
        <li class="list-inline-item me-4">
            <span class="legend-dot" style="background-color: {{ $colourFor($status) }};" aria-hidden="true"></span>
            {{ $status }} <strong>{{ $count }}</strong>
        </li>
    @endforeach
</ul>
