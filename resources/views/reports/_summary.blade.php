@use('App\Support\Money')

<section class="stats-grid mb-2" aria-label="{{ __('Report totals') }}">
    <article class="stat-card">
        <div class="stat-top">
            <div>
                <p class="stat-label">{{ __('Standard fee') }}</p>
                <p class="stat-value">{{ Money::format($summary['standard_fee']) }}</p>
            </div>
            <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
        </div>
        <div class="stat-foot">
            <span>{{ __('Fee as issued on :count invoices', ['count' => $summary['invoices']]) }}</span>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <div>
                <p class="stat-label">{{ __('Cash received') }}</p>
                <p class="stat-value">{{ Money::format($summary['received']) }}</p>
            </div>
            <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
        </div>
        <div class="stat-foot">
            <span>{{ trans_choice(':count payment|:count payments', $summary['payments'], ['count' => $summary['payments']]) }}</span>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <div>
                <p class="stat-label">{{ __('Cashbook inflow') }}</p>
                <p class="stat-value">{{ Money::format($summary['cashbook_gross']) }}</p>
            </div>
            <span class="stat-icon" aria-hidden="true"><i class="bi bi-arrow-down-left-circle"></i></span>
        </div>
        <div class="stat-foot">
            <span>{{ __('Posted receipts only, after reversals :amount', ['amount' => Money::format($summary['cashbook_net'])]) }}</span>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <div>
                <p class="stat-label">{{ __('Reversed in range') }}</p>
                <p class="stat-value">{{ Money::format($summary['reversed_amount']) }}</p>
            </div>
            <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
        </div>
        <div class="stat-foot">
            <span>{{ trans_choice(':count refund|:count refunds', $summary['reversed_count'], ['count' => $summary['reversed_count']]) }}</span>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <div>
                <p class="stat-label">{{ __('Waiting verification') }}</p>
                <p class="stat-value">{{ Money::format($summary['held_amount']) }}</p>
            </div>
            <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
        </div>
        <div class="stat-foot">
            <span>{{ trans_choice(':count payment|:count payments', $summary['held_count'], ['count' => $summary['held_count']]) }}</span>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-top">
            <div>
                <p class="stat-label">{{ __('Students paid') }}</p>
                <p class="stat-value">{{ number_format($summary['students']) }}</p>
            </div>
            <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-people"></i></span>
        </div>
        <div class="stat-foot">
            <span>{{ __('Collection rate :rate% of the standard fee of these invoices', ['rate' => number_format($summary['rate'], 1)]) }}</span>
        </div>
    </article>
</section>

<p class="text-muted-2 mb-3" style="font-size:.8rem;">
    <i class="bi bi-info-circle"></i>
    {{ __('Standard fee is the fee as issued; cash received is the money actually taken. They are two separate figures, and no report here keeps a balance between them. The rate divides cash received by the standard fee of the invoices these payments touched.') }}
</p>
