{{-- Print output is the PDF path: the browser's own Save as PDF keeps the
     numbers on screen, so no export step can drift from the report. --}}
@push('styles')
    <style>
        @media print {
            .app-sidebar,
            .app-topbar,
            .app-footer,
            .page-actions,
            .pagination,
            form,
            .no-print {
                display: none !important;
            }

            body.admin-shell {
                background: #fff !important;
            }

            .app-shell,
            .app-main,
            .app-content {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .ui-card,
            .stat-card {
                break-inside: avoid;
                box-shadow: none !important;
            }

            .table-responsive {
                max-height: none !important;
                overflow: visible !important;
            }

            .table {
                font-size: .78rem !important;
            }

            @page {
                size: A4;
                margin: 12mm;
            }
        }
    </style>
@endpush
