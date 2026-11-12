@extends('layouts.public')

@section('title', __('Student card check'))
@section('page-banner', __('Student card check'))

@php
    $isCurrent = $enrollment !== null && $student->status === 'active';
@endphp

@section('content')
    <section class="pub-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="pub-panel">
                        <div class="pub-panel-head {{ $isCurrent ? 'pub-panel-blue' : '' }}">
                            <h2 class="pub-h3 mb-0"><i class="bi bi-shield-check"></i>
                                {{ $isCurrent ? __('This card is valid') : __('This card needs a check') }}
                            </h2>
                        </div>
                        <div class="pub-panel-body">
                            <div class="d-flex gap-3 align-items-start flex-wrap">
                                <div style="width:96px;height:120px;flex:0 0 auto;border-radius:12px;overflow:hidden;background:#e8eef8;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0b2850;">
                                    @if ($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                                    @else
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1)) }}
                                    @endif
                                </div>

                                <div class="flex-grow-1" style="min-width:220px;">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <th scope="row">{{ __('Name') }}</th>
                                                <td>{{ $student->full_name }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">{{ __('Student ID') }}</th>
                                                <td>{{ $student->student_id }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">{{ __('Class') }}</th>
                                                <td>
                                                    {{ $enrollment?->schoolClass?->name ?? __('Not enrolled in a class') }}
                                                    @if ($enrollment?->section)
                                                        · {{ $enrollment->section->name }}
                                                    @endif
                                                    @if ($enrollment?->roll_no)
                                                        · {{ __('Roll') }} {{ $enrollment->roll_no }}
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">{{ __('Session') }}</th>
                                                <td>{{ $enrollment?->academicSession?->name ?? '—' }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">{{ __('Status') }}</th>
                                                <td>{{ __(ucfirst($student->status)) }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">{{ __('Date of birth') }}</th>
                                                <td>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <p class="pub-muted mt-3 mb-0">
                                {{ __(
                                    $isCurrent
                                        ? 'This record matches the card issued by the school. Only the details printed on the card are shown here.'
                                        : 'The card is not matched to a current class enrollment. Speak to the school office before treating it as valid.'
                                ) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
