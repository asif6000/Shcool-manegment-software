@extends('layouts.app')

@section('title', __('Audit trail'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clock-history"></i>{{ __('Accountability') }}</span>
            <h1 class="page-title">{{ __('Audit trail') }}</h1>
            <p class="page-subtitle">{{ __('An immutable record of sign-ins, account changes, permission updates and school profile edits.') }}</p>
        </div>
        <div class="page-actions">
            <span class="badge-soft is-info"><i class="bi bi-shield-check"></i>{{ __('Read only') }}</span>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('audit.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="audit-search">{{ __('Search audit trail') }}</label>
                    <input id="audit-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search description') }}">
                </div>
                <label class="visually-hidden" for="audit-event">{{ __('Event') }}</label>
                <select id="audit-event" name="event" class="form-select" data-auto-submit>
                    <option value="">{{ __('All events') }}</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ \Illuminate\Support\Str::headline(str_replace(['.', '_'], ' ', $event)) }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="audit-from">{{ __('From date') }}</label>
                <input id="audit-from" type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}" aria-label="{{ __('From date') }}">
                <label class="visually-hidden" for="audit-to">{{ __('To date') }}</label>
                <input id="audit-to" type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}" aria-label="{{ __('To date') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('audit.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count entry|:count entries', $logs->total(), ['count' => $logs->total()]) }}</span>
        </div>

        @if ($logs->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-text"></i></div>
                <h3>{{ __('No audit entries found') }}</h3>
                <p>{{ __('Adjust the filters, or continue using the system — important actions will appear here.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Event') }}</th>
                            <th scope="col">{{ __('Actor') }}</th>
                            <th scope="col">{{ __('Description') }}</th>
                            <th scope="col">{{ __('When') }}</th>
                            <th scope="col" class="text-end">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td><span class="audit-event">{{ $log->event }}</span></td>
                                <td>
                                    @if ($log->user)
                                        <div class="cell-person" style="min-width:150px;">
                                            <span class="avatar avatar-sm">{{ $log->user->initials() }}</span>
                                            <span>
                                                <span class="name" style="font-size:.8rem;">{{ $log->user->name }}</span>
                                                <span class="email">{{ $log->user->email }}</span>
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-muted-2">{{ __('System') }}</span>
                                    @endif
                                </td>
                                <td style="max-width:360px;">{{ $log->description }}</td>
                                <td style="white-space:nowrap;">
                                    <span data-bs-toggle="tooltip" title="{{ $log->created_at?->format('j M Y, h:i:s A') }}">
                                        {{ $log->created_at?->diffForHumans() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($log->old_values || $log->new_values || $log->ip_address)
                                        <button class="icon-action" type="button" data-bs-toggle="collapse" data-bs-target="#audit-detail-{{ $log->id }}" aria-expanded="false" aria-controls="audit-detail-{{ $log->id }}" aria-label="{{ __('Show details') }}">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    @else
                                        <span class="text-muted-2">—</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($log->old_values || $log->new_values || $log->ip_address)
                                <tr class="collapse" id="audit-detail-{{ $log->id }}">
                                    <td colspan="5" style="background:#fbfdff;">
                                        <div class="row g-3">
                                            @if ($log->old_values)
                                                <div class="col-md-6">
                                                    <div class="audit-meta mb-1"><strong>{{ __('Before') }}</strong></div>
                                                    <pre>{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            @endif
                                            @if ($log->new_values)
                                                <div class="col-md-6">
                                                    <div class="audit-meta mb-1"><strong>{{ __('After') }}</strong></div>
                                                    <pre>{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            @endif
                                            <div class="col-12">
                                                <div class="audit-meta">
                                                    @if ($log->ip_address)<span><i class="bi bi-hdd-network"></i> {{ $log->ip_address }}</span>@endif
                                                    @if ($log->user_agent)<span><i class="bi bi-window"></i> {{ $log->user_agent }}</span>@endif
                                                    @if ($log->auditable_type)<span><i class="bi bi-link-45deg"></i> {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>@endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $logs->firstItem(), 'to' => $logs->lastItem(), 'total' => $logs->total()]) }}</span>
                {{ $logs->links() }}
            </div>
        @endif
    </section>
@endsection
