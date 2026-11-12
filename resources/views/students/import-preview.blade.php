@extends('layouts.app')

@section('title', __('Import preview'))

@php
    $previewRows = array_slice($batch['rows'], 0, 100);
@endphp

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-list-check"></i>{{ __('Student Management') }}</span>
            <h1 class="page-title">{{ __('Review before importing') }}</h1>
            <p class="page-subtitle">{{ $fileName }} &middot; {{ __('Nothing has been saved yet.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student_import.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Choose another file') }}</a>
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Row counts') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Rows read') }}</p>
                    <p class="stat-value">{{ number_format(count($batch['rows'])) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-table"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('From the header down') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Ready to import') }}</p>
                    <p class="stat-value">{{ number_format($batch['valid']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Passes every admission rule') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Blocked') }}</p>
                    <p class="stat-value">{{ number_format($batch['invalid']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Skipped, the rest still import') }}</span></div>
        </article>
    </section>

    @if ($batch['fatal'] !== [])
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                <strong>{{ __('This sheet cannot be imported.') }}</strong>
                <ul class="mb-0 ps-3 mt-1">
                    @foreach ($batch['fatal'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($batch['unknown'] !== [])
        <div class="alert alert-warning">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <strong>{{ __('Columns ignored:') }}</strong>
                {{ implode(', ', $batch['unknown']) }}
            </div>
        </div>
    @endif

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Every row this sheet carries') }}</h2>
            @if (count($batch['rows']) > count($previewRows))
                <span class="text-muted-2" style="font-size:.78rem;">{{ __('Showing the first :count rows; all :total are checked the same way.', [
                    'count' => count($previewRows),
                    'total' => count($batch['rows']),
                ]) }}</span>
            @endif
        </div>

        @if ($batch['rows'] === [])
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <h3>{{ __('No rows found') }}</h3>
                <p>{{ __('The file has a header line but nothing under it.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Line') }}</th>
                            <th scope="col">{{ __('Name') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col">{{ __('Section') }}</th>
                            <th scope="col">{{ __('Roll') }}</th>
                            <th scope="col">{{ __('Guardian') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('What needs fixing') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr>
                                <td class="text-muted-2">{{ $row['line'] }}</td>
                                <td>
                                    <span class="name">{{ $row['name'] !== '' ? $row['name'] : __('(no name)') }}</span>
                                    @if ($row['student_id'])
                                        <span class="d-block text-muted-2" style="font-size:.75rem;">{{ $row['student_id'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['class'] ? ($classNames[$row['class']] ?? '-') : '-' }}</td>
                                <td>{{ $row['section'] ? ($sectionNames[$row['section']] ?? '-') : '-' }}</td>
                                <td>{{ $row['roll'] ?? __('auto') }}</td>
                                <td>{{ $row['data']['guardian_name'] ?? '-' }}</td>
                                <td>{{ $row['data']['status'] ?? '-' }}</td>
                                <td>
                                    @if ($row['valid'])
                                        <span class="badge-soft is-success"><span class="dot"></span>{{ __('Ready') }}</span>
                                    @else
                                        <span class="badge-soft is-danger"><span class="dot"></span>{{ __('Blocked') }}</span>
                                        <ul class="mb-0 ps-3 mt-1" style="font-size:.76rem;">
                                            @foreach ($row['errors'] as $messages)
                                                @foreach ((array) $messages as $message)
                                                    <li>{{ $message }}</li>
                                                @endforeach
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($batch['fatal'] === [] && $batch['valid'] > 0)
        <section class="ui-card p-lg">
            <h2 class="card-title">{{ __('Confirm the import') }}</h2>
            <p class="page-subtitle">{{ __(':count students will be admitted now.', ['count' => $batch['valid']]) }}
                @if ($batch['invalid'] > 0)
                    {{ __(':count rows stay out of the database.', ['count' => $batch['invalid']]) }}
                @endif
            </p>

            <form method="POST" action="{{ route('student_import.store') }}" class="mt-2"
                  data-confirm="{{ __('Import :count students into the register now?', ['count' => $batch['valid']]) }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="import-confirm" name="confirm" @checked(old('confirm'))>
                    <label class="form-check-label" for="import-confirm">
                        {{ __('I have checked these rows and they are real students of this school.') }}
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-download"></i>{{ __('Import students') }}
                </button>
                <a href="{{ route('student_import.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
            </form>
        </section>
    @elseif ($batch['fatal'] === [])
        <section class="ui-card p-lg">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-shield-exclamation"></i></div>
                <h3>{{ __('Nothing to import') }}</h3>
                <p>{{ __('Every row in this sheet is blocked. Fix the sheet and upload it again.') }}</p>
                <a href="{{ route('student_import.index') }}" class="btn btn-light-soft mt-2">{{ __('Upload again') }}</a>
            </div>
        </section>
    @endif
@endsection
