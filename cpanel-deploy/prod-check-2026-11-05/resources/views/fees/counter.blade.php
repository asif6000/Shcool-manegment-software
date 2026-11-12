@extends('layouts.app')

@section('title', __('Accountant fee counter'))

@section('content')
    @php
        $monthNames = collect(range(1, 12))->mapWithKeys(
            fn (int $number): array => [$number => \Carbon\Carbon::create(2000, $number, 1)->translatedFormat('F')]
        );
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calculator"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('Accountant fee counter') }}</h1>
            <p class="page-subtitle">{{ __('Choose the class, student, month and fee type, then record the money actually received.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.receipts') }}" class="btn btn-light-soft"><i class="bi bi-upc-scan"></i>{{ __('Receipts') }}</a>
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-journal-text"></i>{{ __('Invoices') }}</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-600">{{ __('The payment was not recorded.') }}</div>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="ui-card p-lg mb-3">
        <form method="GET" action="{{ route('fees.counter') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="counter-session">{{ __('Academic session') }}</label>
                <select id="counter-session" name="session_id" class="form-select" required>
                    @foreach ($sessions as $option)
                        <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="counter-class">{{ __('Class') }}</label>
                <select id="counter-class" name="class_id" class="form-select" required>
                    <option value="">{{ __('Select a class') }}</option>
                    @foreach ($classes as $option)
                        <option value="{{ $option->id }}" @selected((string) $classId === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="counter-student">{{ __('Student') }}</label>
                <select id="counter-student" name="student_id" class="form-select" @disabled($classId === null)>
                    <option value="">{{ $classId === null ? __('Choose a class first') : __('Select a student') }}</option>
                    @foreach ($students as $option)
                        <option value="{{ $option->id }}" @selected((string) $student?->id === (string) $option->id)>
                            {{ $option->full_name }} ({{ $option->student_id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="counter-fee-type">{{ __('Fee type') }}</label>
                <select id="counter-fee-type" name="fee_type_id" class="form-select" @disabled($classId === null)>
                    <option value="">{{ __('Select a fee type') }}</option>
                    @foreach ($feeTypes as $option)
                        <option value="{{ $option->id }}" @selected((string) $feeType?->id === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="counter-month">{{ __('Month') }}</label>
                <select id="counter-month" name="month" class="form-select" required>
                    @foreach ($monthNames as $number => $name)
                        <option value="{{ $number }}" @selected((int) $month === $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="counter-year">{{ __('Year') }}</label>
                <select id="counter-year" name="year" class="form-select" required>
                    @php
                        $firstYear = min(2000, $year);
                        $lastYear = max((int) now()->year + 1, $year);
                    @endphp
                    @for ($option = $firstYear; $option <= $lastYear; $option++)
                        <option value="{{ $option }}" @selected((int) $year === $option)>{{ $option }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i>{{ __('Load fee') }}</button>
                <a href="{{ route('fees.counter') }}" class="btn btn-light-soft">{{ __('Reset') }}</a>
            </div>
        </form>
    </section>

    <div class="row g-3">
        <div class="col-lg-7">
            @if ($classId === null)
                <section class="ui-card p-lg">
                    <div class="empty-state py-5">
                        <i class="bi bi-mortarboard"></i>
                        <h2 class="card-title mt-2">{{ __('Choose a class to begin') }}</h2>
                        <p class="text-muted-2 mb-0">{{ __('The student list and the fee types available to that class appear here.') }}</p>
                    </div>
                </section>
            @elseif ($student === null)
                <section class="ui-card p-lg">
                    <div class="empty-state py-5">
                        <i class="bi bi-person-lines-fill"></i>
                        <h2 class="card-title mt-2">{{ __('Select a student') }}</h2>
                        <p class="text-muted-2 mb-0">{{ __('Pick one learner, then choose the fee type and month.') }}</p>
                    </div>
                </section>
            @elseif ($feeTypes->isEmpty())
                <section class="ui-card p-lg">
                    <div class="empty-state py-5">
                        <i class="bi bi-exclamation-circle"></i>
                        <h2 class="card-title mt-2">{{ __('No fee type is configured for this class') }}</h2>
                        <p class="text-muted-2 mb-0">{{ __('Ask an administrator to add a fee structure or student assignment for :class.', ['class' => \App\Models\SchoolClass::find($classId)?->name ?? __('this class')]) }}</p>
                    </div>
                </section>
            @elseif ($feeType === null || $snapshot === null)
                <section class="ui-card p-lg">
                    <div class="empty-state py-5">
                        <i class="bi bi-tags"></i>
                        <h2 class="card-title mt-2">{{ __('Select a fee type') }}</h2>
                        <p class="text-muted-2 mb-0">{{ __('The scheduled amount, payments already received and the balance will show here.') }}</p>
                    </div>
                </section>
            @else
                <section class="ui-card p-lg">
                    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                        <div>
                            <h2 class="card-title mb-1">{{ $student->full_name }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                                {{ $student->student_id }}
                                &middot; {{ $snapshot['enrollment']?->schoolClass?->name }}
                                &middot; {{ __('Roll') }} {{ $snapshot['enrollment']?->roll_no }}
                                &middot; {{ $snapshot['enrollment']?->section?->name }}
                            </p>
                        </div>
                        <span class="badge-soft is-warning">{{ $monthNames[$month] }} {{ $year }}</span>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-sm-4">
                            <div class="text-muted-2" style="font-size:.75rem;">{{ __('Fee type') }}</div>
                            <div class="fw-600">{{ $feeType->name }}</div>
                            <div class="text-muted-2" style="font-size:.72rem;">{{ $snapshot['description'] }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-muted-2" style="font-size:.75rem;">{{ __('Scheduled fee') }}</div>
                            <div class="fw-700">{{ \App\Support\Money::format($snapshot['scheduled']) }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-muted-2" style="font-size:.75rem;">{{ __('Already received') }}</div>
                            <div class="fw-600">{{ \App\Support\Money::format($snapshot['paid']) }}</div>
                            @if ($snapshot['waived'] > 0)
                                <div class="text-muted-2" style="font-size:.72rem;">{{ __('Waived') }}: {{ \App\Support\Money::format($snapshot['waived']) }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mt-3 p-3" style="background:var(--bs-tertiary-bg);border-radius:.75rem;">
                        <div>
                            <div class="text-muted-2" style="font-size:.75rem;">{{ __('Balance waiting') }}</div>
                            <div class="fs-4 fw-700">{{ \App\Support\Money::format($snapshot['balance']) }}</div>
                        </div>
                        @if ($snapshot['invoice'] !== null)
                            <a href="{{ route('fees.show', $snapshot['invoice']) }}" class="btn btn-light-soft btn-sm">
                                <i class="bi bi-receipt"></i>{{ $snapshot['invoice']->invoice_no }}
                            </a>
                        @endif
                    </div>

                    @if ($snapshot['enrollment'] === null)
                        <div class="alert alert-warning mt-3 mb-0">{{ __('This student is not actively enrolled in the selected class and session.') }}</div>
                    @elseif ($snapshot['balance'] <= 0)
                        <div class="alert alert-success mt-3 mb-0">
                            <i class="bi bi-check-circle"></i>
                            {{ __('This fee is fully settled for the selected month. Use the history on the right to reprint a receipt.') }}
                        </div>
                    @else
                        <hr class="my-4">

                        <form method="POST" action="{{ route('fees.counter.store') }}" id="counter-form">
                            @csrf
                            <input type="hidden" name="academic_session_id" value="{{ $session?->id }}">
                            <input type="hidden" name="class_id" value="{{ $classId }}">
                            <input type="hidden" name="student_id" value="{{ $student->id }}">
                            <input type="hidden" name="fee_type_id" value="{{ $feeType->id }}">
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="counter-amount">{{ __('Actual amount received') }}</label>
                                    <input id="counter-amount" name="amount" type="number" step="0.01" min="0.01"
                                           max="{{ number_format($snapshot['balance'], 2, '.', '') }}"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount', number_format($snapshot['balance'], 2, '.', '')) }}" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="counter-method">{{ __('Payment method') }}</label>
                                    <select id="counter-method" name="method" class="form-select" required>
                                        @foreach ($methods as $method)
                                            <option value="{{ $method }}" @selected(old('method', 'cash') === $method)>{{ __(ucfirst($method)) }}</option>
                                        @endforeach
                                    </select>
                                    @error('method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="counter-paid-at">{{ __('Payment date') }}</label>
                                    <input id="counter-paid-at" name="paid_at" type="date" class="form-control @error('paid_at') is-invalid @enderror"
                                           value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                                    @error('paid_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="counter-reference">{{ __('Transaction reference') }}</label>
                                    <input id="counter-reference" name="transaction_ref" class="form-control @error('transaction_ref') is-invalid @enderror"
                                           maxlength="100" value="{{ old('transaction_ref') }}" placeholder="{{ __('bKash TrxID, cheque no, etc.') }}">
                                    @error('transaction_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="counter-bank">{{ __('Received into') }}</label>
                                    <select id="counter-bank" name="bank_account_id" class="form-select">
                                        <option value="">{{ __('Not a bank account') }}</option>
                                        @foreach ($bankAccounts as $account)
                                            <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="counter-notes">{{ __('Remarks') }}</label>
                                    <input id="counter-notes" name="notes" class="form-control" maxlength="500" value="{{ old('notes') }}">
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mt-4">
                                <p class="text-muted-2 mb-0" style="font-size:.76rem;">
                                    {{ __('The invoice is created automatically and the receipt is issued immediately. The admin reports update at once.') }}
                                </p>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-printer"></i>{{ __('Collect & print receipt') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </section>
            @endif
        </div>

        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h2 class="card-title mb-0">{{ __('My previous payments') }}</h2>
                    <a href="{{ route('fees.receipts') }}" class="btn btn-light-soft btn-sm">{{ __('All receipts') }}</a>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-aligned align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Fee') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $payment)
                                <tr>
                                    <td>
                                        <div>{{ $payment->paid_at?->format('d M Y') }}</div>
                                        <div class="text-muted-2" style="font-size:.72rem;">{{ $payment->invoice_no }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-600">{{ $payment->student?->full_name }}</div>
                                        <div class="text-muted-2" style="font-size:.72rem;">
                                            {{ $payment->schoolClass?->name }}
                                            @if ($payment->billing_month)
                                                &middot; {{ \Carbon\Carbon::createFromFormat('Y-m-d', $payment->billing_month.'-01')->translatedFormat('M Y') }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $payment->feeType?->name ?? __('Fee') }}</div>
                                        <div class="text-muted-2" style="font-size:.72rem;">{{ __(ucfirst($payment->method)) }}</div>
                                    </td>
                                    <td class="text-end fw-600">{{ \App\Support\Money::format($payment->amount) }}</td>
                                    <td class="text-end">
                                        @if ($payment->receipt !== null)
                                            <a href="{{ route('fees.receipt', $payment) }}" class="btn btn-light-soft btn-sm" title="{{ __('Reprint receipt') }}">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <a href="{{ route('fees.receipt', ['payment' => $payment, 'print' => 1]) }}" class="btn btn-light-soft btn-sm" title="{{ __('Open print view') }}">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('No payment has been recorded by you yet.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const method = document.getElementById('counter-method');
                const reference = document.getElementById('counter-reference');
                const requiredMethods = @json(array_values($referenceRequired));

                if (! method || ! reference) return;

                function refresh() {
                    const needed = requiredMethods.includes(method.value);
                    reference.required = needed;
                    reference.placeholder = needed
                        ? @json(__('Required for this payment method'))
                        : @json(__('bKash TrxID, cheque no, etc.'));
                }

                method.addEventListener('change', refresh);
                refresh();
            })();
        </script>
    @endpush
@endsection
