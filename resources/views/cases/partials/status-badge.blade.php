{{--
    A case status, as a soft badge.

    $status — the raw cases.status value.

    Shared by the dashboard and the case list so the same field cannot render as a
    badge on one page and plain text on the other, one click apart.

    Colour comes from CaseModel::STATUS_COLOURS, the same ratified five-value map
    that drives partials/status-distribution.blade.php's bar and legend — a status
    used to collapse to one blue badge here while showing four colours on the
    dashboard, one click away. Anything outside the five still renders (status is
    an unconstrained string column), hashed to the same ramp the bar falls back to.
--}}
@php
    $ramp = ['#1d4ed8', '#3b82f6', '#93c5fd', '#1e3a8a'];

    $colour = \App\Models\CaseModel::STATUS_COLOURS[$status]
        ?? $ramp[crc32($status) % count($ramp)];
@endphp
<span class="badge" style="background-color: {{ $colour }}1a; color: {{ $colour }}; border: 1px solid {{ $colour }}66;">{{ $status }}</span>
