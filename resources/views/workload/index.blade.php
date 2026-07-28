@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    {{ __('Workload') }} &mdash; {{ Auth::user()->office_region }}
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <p class="text-muted small">
                        {{ __('WCS = sum of active case complexity weights × (2 − performance rating). Lower scores are suggested for the next assignment.') }}
                    </p>

                    @if ($investigators->isEmpty())
                        <p class="mb-0">{{ __('No investigators on record.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Investigator') }}</th>
                                        <th class="text-end">{{ __('Active Cases') }}</th>
                                        <th class="text-end">{{ __('Complexity (ΣC)') }}</th>
                                        <th>{{ __('Rating (P)') }}</th>
                                        <th class="text-end">{{ __('WCS') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($investigators as $investigator)
                                        <tr>
                                            <td>
                                                {{ $investigator->full_name }}
                                                @if ($loop->first)
                                                    <span class="badge bg-success">{{ __('suggested') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $investigator->active_cases_count }}</td>
                                            <td class="text-end">{{ $investigator->active_complexity_sum ?? 0 }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('workload.update', $investigator) }}" class="d-flex gap-2 flex-nowrap">
                                                    @csrf
                                                    @method('PUT')

                                                    <label for="performance_rating_{{ $investigator->id }}" class="visually-hidden">
                                                        {{ __('Performance rating for :name', ['name' => $investigator->full_name]) }}
                                                    </label>
                                                    <input id="performance_rating_{{ $investigator->id }}"
                                                           type="number" step="0.1" min="0.1" max="1.0"
                                                           class="form-control form-control-sm flex-shrink-0" style="width: 5rem;"
                                                           name="performance_rating"
                                                           value="{{ number_format($investigator->performance_rating, 1) }}" required>

                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        {{ __('Save') }}
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-end">{{ number_format($investigator->workloadCapacityScore(), 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
