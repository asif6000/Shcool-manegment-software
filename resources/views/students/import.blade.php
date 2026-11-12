@extends('layouts.app')

@section('title', __('Import students'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-upload"></i>{{ __('Student Management') }}</span>
            <h1 class="page-title">{{ __('Import students') }}</h1>
            <p class="page-subtitle">{{ __('Upload a sheet, review every row, then confirm. Nothing is written until you confirm.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('students.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Students') }}</a>
            <a href="{{ route('student_import.template') }}" class="btn btn-light-soft"><i class="bi bi-filetype-csv"></i>{{ __('Download template') }}</a>
        </div>
    </div>

    @if ($errors->has('csv'))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>{{ $errors->first('csv') }}</div>
        </div>
    @endif

    <section class="ui-card p-lg mb-3">
        <h2 class="card-title">{{ __('Choose your sheet') }}</h2>
        <form method="POST" action="{{ route('student_import.preview') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
            @csrf
            <div class="col-12 col-md-7">
                <label class="form-label" for="import-csv">{{ __('CSV file') }}</label>
                <input class="form-control" type="file" id="import-csv" name="csv" accept=".csv,.txt,.tsv,text/csv" required>
                <div class="form-text">{{ __('Export it from Excel, Google Sheets or LibreOffice as CSV. Comma, semicolon and tab separated files are all read.') }}</div>
            </div>
            <div class="col-12 col-md-5">
                <button type="submit" class="btn btn-primary"><i class="bi bi-list-check"></i>{{ __('Preview rows') }}</button>
            </div>
        </form>
    </section>

    <section class="ui-card p-lg mb-3">
        <h2 class="card-title">{{ __('Columns the sheet can carry') }}</h2>
        <p class="page-subtitle mb-2">{{ __('The header row uses these names. Bengali headings such as নাম or শ্রেণি are accepted, and any column this system does not know is listed on the preview instead of being guessed at.') }}</p>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($fields as $field)
                <span class="badge-soft">{{ $field }}</span>
            @endforeach
        </div>
    </section>

    <section class="ui-card p-lg">
        <h2 class="card-title">{{ __('How the import behaves') }}</h2>
        <ul class="mb-0 ps-3">
            <li>{{ __('Only :max rows per file are read, so split a longer register into several sheets.', ['max' => $maxRows]) }}</li>
            <li>{{ __('Student ID and admission number follow your existing sequence unless the sheet supplies its own.') }}</li>
            <li>{{ __('Class, section and session are matched by name. A name that does not exist in this school blocks that row instead of creating a new class.') }}</li>
            <li>{{ __('A row repeating a name, date of birth and guardian who is already admitted is blocked, so nobody is enrolled twice.') }}</li>
            <li>{{ __('A blank roll number takes the next free roll in that class and section.') }}</li>
            <li>{{ __('Each row saves on its own. One bad row is reported and skipped; the good rows are not thrown away with it.') }}</li>
            <li>{{ __('Guardian details on the sheet are linked to the student exactly as the admission form does it.') }}</li>
            <li>{{ __('Photos are not imported. Add them from each student record afterwards.') }}</li>
        </ul>
    </section>
@endsection
