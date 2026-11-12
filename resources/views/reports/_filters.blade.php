{{-- Every fee report reads the same filters so the figures mean the same thing
     on each page. Dates are payment dates; the month is the fee month the money
     was for, which is a different dimension. --}}
<section class="ui-card p-lg mb-3">
    <form method="GET" action="{{ route($route) }}" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="filter-from">{{ __('Paid from') }}</label>
            <input id="filter-from" type="date" name="from" class="form-control" value="{{ $filters['from']->toDateString() }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-to">{{ __('Paid to') }}</label>
            <input id="filter-to" type="date" name="to" class="form-control" value="{{ $filters['to']->toDateString() }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-month">{{ __('Fee month') }}</label>
            <input id="filter-month" type="month" name="month" class="form-control" value="{{ $filters['fee_month'] }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-session">{{ __('Academic year') }}</label>
            <select id="filter-session" name="session_id" class="form-select">
                <option value="">{{ __('All years') }}</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}" {{ (int) $filters['session_id'] === $session->id ? 'selected' : '' }}>{{ $session->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="filter-class">{{ __('Class') }}</label>
            <select id="filter-class" name="class_id" class="form-select">
                <option value="">{{ __('All classes') }}</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" {{ (int) $filters['class_id'] === $class->id ? 'selected' : '' }}>{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-section">{{ __('Section') }}</label>
            <select id="filter-section" name="section_id" class="form-select">
                <option value="">{{ __('All sections') }}</option>
                @foreach ($sections as $section)
                    <option value="{{ $section->id }}" {{ (int) $filters['section_id'] === $section->id ? 'selected' : '' }}>
                        {{ ($section->schoolClass?->name ?? __('Class')).' · '.$section->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-student">{{ __('Student') }}</label>
            <select id="filter-student" name="student_id" class="form-select">
                <option value="">{{ __('All students') }}</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" {{ (int) $filters['student_id'] === $student->id ? 'selected' : '' }}>
                        {{ $student->full_name }} ({{ $student->student_id }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-fee-type">{{ __('Fee type') }}</label>
            <select id="filter-fee-type" name="fee_type_id" class="form-select">
                <option value="">{{ __('All fee types') }}</option>
                @foreach ($feeTypes as $feeType)
                    <option value="{{ $feeType->id }}" {{ (int) $filters['fee_type_id'] === $feeType->id ? 'selected' : '' }}>{{ $feeType->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="filter-method">{{ __('Payment method') }}</label>
            <select id="filter-method" name="method" class="form-select">
                <option value="">{{ __('All methods') }}</option>
                @foreach ($methods as $key)
                    <option value="{{ $key }}" {{ $filters['method'] === $key ? 'selected' : '' }}>{{ __(ucfirst($key)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-collector">{{ __('Collector') }}</label>
            <select id="filter-collector" name="collector_id" class="form-select">
                <option value="">{{ __('Anyone') }}</option>
                @foreach ($collectors as $collector)
                    <option value="{{ $collector->id }}" {{ (int) $filters['collector_id'] === $collector->id ? 'selected' : '' }}>{{ $collector->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 d-flex gap-2">
            <button type="submit" class="btn btn-light-soft flex-grow-1"><i class="bi bi-funnel"></i>{{ __('Apply filters') }}</button>
            @if (collect($filters)->except(['from', 'to'])->contains(fn ($value) => $value !== null))
                <a href="{{ route($route, ['from' => $filters['from']->toDateString(), 'to' => $filters['to']->toDateString()]) }}" class="btn btn-light">{{ __('Clear') }}</a>
            @endif
        </div>
    </form>
</section>
