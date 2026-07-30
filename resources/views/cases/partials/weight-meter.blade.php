{{-- complexity_weight (1-5) as a segmented meter. Expects $weight. --}}
<span class="weight-meter" aria-hidden="true">
    @for ($i = 1; $i <= 5; $i++)
        <span @class(['weight-meter__seg', 'is-filled' => $i <= $weight])></span>
    @endfor
</span>
<span class="text-muted small ms-1" aria-hidden="true">{{ $weight }}</span>
<span class="visually-hidden">{{ __('Complexity :n of 5', ['n' => $weight]) }}</span>
