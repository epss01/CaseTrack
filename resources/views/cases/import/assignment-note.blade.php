@if (Auth::user()->isInvestigator())
    <p>{{ __('Import into your own caseload. Leave Investigator blank or enter your username (:username). Cases naming another investigator will be rejected.', ['username' => Auth::user()->username]) }}</p>
@endif
