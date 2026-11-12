@extends('layouts.app')

@section('title', __('Grading scale'))

@section('content')
    @php
        $rows = old('items');

        if (! is_array($rows)) {
            $rows = $items->map(fn ($item): array => [
                'grade_letter' => $item->grade_letter,
                'min_percentage' => (float) $item->min_percentage,
                'max_percentage' => (float) $item->max_percentage,
                'grade_point' => (float) $item->grade_point,
                'remarks' => $item->remarks,
            ])->all();
        }
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-sliders"></i>{{ __('Examinations') }}</span>
            <h1 class="page-title">{{ __('Grading scale') }}</h1>
            <p class="page-subtitle">{{ __('Percentage ranges convert paper marks into grades and grade points.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('exams.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Exams') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('exams.grade-scale.update') }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-lg-9">
                <section class="ui-card table-card">
                    <div class="table-toolbar">
                        <div>
                            <h2 class="card-title mb-1">{{ $scale->name }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Ranges must run continuously from 0 to 100 without gaps or overlaps.') }}</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-light-soft" data-add-grade>
                            <i class="bi bi-plus-lg"></i>{{ __('Add grade') }}
                        </button>
                    </div>

                    @error('items')<div class="alert alert-danger py-2 mx-3 mt-3 mb-0" style="font-size:.82rem;">{{ $message }}</div>@enderror

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" style="width:110px;">{{ __('Grade') }}</th>
                                    <th scope="col" class="text-end">{{ __('Min %') }}</th>
                                    <th scope="col" class="text-end">{{ __('Max %') }}</th>
                                    <th scope="col" class="text-end">{{ __('Point') }}</th>
                                    <th scope="col">{{ __('Remarks') }}</th>
                                    <th scope="col" style="width:60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="grade-rows">
                                @foreach ($rows as $index => $row)
                                    <tr data-grade-row>
                                        <td>
                                            <label class="visually-hidden" for="grade-letter-{{ $index }}">{{ __('Grade letter') }}</label>
                                            <input id="grade-letter-{{ $index }}" type="text" name="items[{{ $index }}][grade_letter]" maxlength="5"
                                                class="form-control form-control-sm text-uppercase" value="{{ $row['grade_letter'] ?? '' }}" required>
                                        </td>
                                        <td>
                                            <label class="visually-hidden" for="grade-min-{{ $index }}">{{ __('Minimum percentage') }}</label>
                                            <input id="grade-min-{{ $index }}" type="number" step="0.01" min="0" max="100"
                                                name="items[{{ $index }}][min_percentage]" class="form-control form-control-sm text-end"
                                                value="{{ $row['min_percentage'] ?? '' }}" required>
                                        </td>
                                        <td>
                                            <label class="visually-hidden" for="grade-max-{{ $index }}">{{ __('Maximum percentage') }}</label>
                                            <input id="grade-max-{{ $index }}" type="number" step="0.01" min="0" max="100"
                                                name="items[{{ $index }}][max_percentage]" class="form-control form-control-sm text-end"
                                                value="{{ $row['max_percentage'] ?? '' }}" required>
                                        </td>
                                        <td>
                                            <label class="visually-hidden" for="grade-point-{{ $index }}">{{ __('Grade point') }}</label>
                                            <input id="grade-point-{{ $index }}" type="number" step="0.01" min="0" max="5"
                                                name="items[{{ $index }}][grade_point]" class="form-control form-control-sm text-end"
                                                value="{{ $row['grade_point'] ?? '' }}" required>
                                        </td>
                                        <td>
                                            <label class="visually-hidden" for="grade-remarks-{{ $index }}">{{ __('Remarks') }}</label>
                                            <input id="grade-remarks-{{ $index }}" type="text" maxlength="80"
                                                name="items[{{ $index }}][remarks]" class="form-control form-control-sm"
                                                value="{{ $row['remarks'] ?? '' }}">
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="icon-action is-danger" data-remove-grade aria-label="{{ __('Remove grade') }}">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <span class="text-muted-2" style="font-size:.78rem;">{{ __('Changes apply to new marks and draft results. Published snapshots keep their original grades.') }}</span>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Save grading scale') }}</button>
                    </div>
                </section>
            </div>

            <div class="col-lg-3">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('GPA rules') }}</h2>
                    <ul class="text-muted-2 mt-3 mb-0" style="font-size:.82rem;padding-left:1.1rem;">
                        <li>{{ __('Each paper earns the grade point of its percentage range.') }}</li>
                        <li>{{ __('Overall GPA is the average of paper grade points.') }}</li>
                        <li>{{ __('Any F grade makes the overall GPA 0.00.') }}</li>
                        <li>{{ __('Positions rank students within the same class.') }}</li>
                    </ul>
                </section>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            const body = document.getElementById('grade-rows');
            const template = document.createElement('template');

            template.innerHTML = `
                <tr data-grade-row>
                    <td><input type="text" name="items[__INDEX__][grade_letter]" maxlength="5" class="form-control form-control-sm text-uppercase" required aria-label="{{ __('Grade letter') }}"></td>
                    <td><input type="number" step="0.01" min="0" max="100" name="items[__INDEX__][min_percentage]" class="form-control form-control-sm text-end" required aria-label="{{ __('Minimum percentage') }}"></td>
                    <td><input type="number" step="0.01" min="0" max="100" name="items[__INDEX__][max_percentage]" class="form-control form-control-sm text-end" required aria-label="{{ __('Maximum percentage') }}"></td>
                    <td><input type="number" step="0.01" min="0" max="5" name="items[__INDEX__][grade_point]" class="form-control form-control-sm text-end" required aria-label="{{ __('Grade point') }}"></td>
                    <td><input type="text" maxlength="80" name="items[__INDEX__][remarks]" class="form-control form-control-sm" aria-label="{{ __('Remarks') }}"></td>
                    <td class="text-end"><button type="button" class="icon-action is-danger" data-remove-grade aria-label="{{ __('Remove grade') }}"><i class="bi bi-trash3"></i></button></td>
                </tr>`;

            document.querySelector('[data-add-grade]')?.addEventListener('click', function () {
                const index = 'n' + Date.now();
                const html = template.innerHTML.replaceAll('__INDEX__', index);
                body.insertAdjacentHTML('beforeend', html);
            });

            body?.addEventListener('click', function (event) {
                const button = event.target.closest('[data-remove-grade]');

                if (!button) {
                    return;
                }

                const rows = body.querySelectorAll('[data-grade-row]');

                if (rows.length <= 1) {
                    return;
                }

                button.closest('[data-grade-row]').remove();
            });
        })();
    </script>
@endpush
