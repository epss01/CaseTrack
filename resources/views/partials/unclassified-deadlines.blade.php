@if ($unclassifiedCount > 0)
    <p class="alert alert-warning">{{ trans_choice('{1} The 60-day deadline is not evaluated for :count active case with unknown classification. Other deadline counts still apply.|[2,*] The 60-day deadline is not evaluated for :count active cases with unknown classification. Other deadline counts still apply.', $unclassifiedCount) }}</p>
@endif
