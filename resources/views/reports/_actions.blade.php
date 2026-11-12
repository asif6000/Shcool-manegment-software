<div class="page-actions">
    <a href="{{ route('reports.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Reports') }}</a>
    @can('reports.export')
        <a href="{{ route('reports.export', array_merge(request()->query(), ['report' => $export])) }}" class="btn btn-light-soft">
            <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
        </a>
    @endcan
    <button type="button" class="btn btn-light-soft" onclick="window.print()">
        <i class="bi bi-printer"></i>{{ __('Print / Save as PDF') }}
    </button>
</div>
