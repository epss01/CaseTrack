@extends('layouts.app')

@section('title', __('Upload too large'))

@section('content')
<div class="container">
    <h1>{{ __('Upload too large') }}</h1>
    <p>{{ __('Choose an import file up to :size MB. If your file is smaller, ask the administrator to check the server upload limit.', ['size' => \App\Http\Requests\UploadCaseImportRequest::MAX_SIZE_KB / 1024]) }}</p>
    <a href="{{ route('cases.import.create') }}" class="btn btn-primary">{{ __('Return to import') }}</a>
</div>
@endsection
