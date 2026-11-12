@extends('layouts.app')

@section('title', __('Edit school profile'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-building-gear"></i>{{ __('School profile') }}</span>
            <h1 class="page-title">{{ __('Edit school profile') }}</h1>
            <p class="page-subtitle">{{ __('Keep official details accurate — they appear on receipts, report cards and exports.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('schools.show') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to profile') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('schools.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        <div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(270px,1fr);gap:20px;align-items:start;" class="school-edit-grid">
            <div class="ui-card form-card">
                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Identity') }}</h2>
                        <p>{{ __('The name and code shown throughout the system.') }}</p>
                    </div>
                    <div class="form-grid">
                        <div class="span-2">
                            <label for="name" class="form-label">{{ __('School name') }}<span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $school->name) }}" class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="short_name" class="form-label">{{ __('Short name') }}</label>
                            <input type="text" id="short_name" name="short_name" value="{{ old('short_name', $school->short_name) }}" class="form-control @error('short_name') is-invalid @enderror">
                            @error('short_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="code" class="form-label">{{ __('School code') }}<span class="required">*</span></label>
                            <input type="text" id="code" name="code" value="{{ old('code', $school->code) }}" class="form-control @error('code') is-invalid @enderror" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">{{ __('Letters, numbers, dashes and underscores. Used in identifiers.') }}</div>
                        </div>
                        <div>
                            <label for="eiin" class="form-label">{{ __('EIIN') }}</label>
                            <input type="text" id="eiin" name="eiin" value="{{ old('eiin', $school->eiin) }}" class="form-control @error('eiin') is-invalid @enderror">
                            @error('eiin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="established_year" class="form-label">{{ __('Established year') }}</label>
                            <input type="number" id="established_year" name="established_year" value="{{ old('established_year', $school->established_year) }}" class="form-control @error('established_year') is-invalid @enderror" min="1800" max="{{ now()->year + 1 }}">
                            @error('established_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="span-2">
                            <label for="motto" class="form-label">{{ __('Motto') }}</label>
                            <input type="text" id="motto" name="motto" value="{{ old('motto', $school->motto) }}" class="form-control @error('motto') is-invalid @enderror" maxlength="255">
                            @error('motto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Contact') }}</h2>
                        <p>{{ __('How guardians, staff and the board can reach the school.') }}</p>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $school->email) }}" class="form-control @error('email') is-invalid @enderror">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="website" class="form-label">{{ __('Website') }}</label>
                            <input type="url" id="website" name="website" value="{{ old('website', $school->website) }}" class="form-control @error('website') is-invalid @enderror" placeholder="https://">
                            @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="phone" class="form-label">{{ __('Phone') }}</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $school->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="phone_alt" class="form-label">{{ __('Alternate phone') }}</label>
                            <input type="tel" id="phone_alt" name="phone_alt" value="{{ old('phone_alt', $school->phone_alt) }}" class="form-control @error('phone_alt') is-invalid @enderror">
                            @error('phone_alt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Location') }}</h2>
                        <p>{{ __('Used on reports and official documents.') }}</p>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label for="division" class="form-label">{{ __('Division') }}</label>
                            <select id="division" name="division" class="form-select @error('division') is-invalid @enderror">
                                <option value="">{{ __('Select division') }}</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division }}" @selected(old('division', $school->division) === $division)>{{ $division }}</option>
                                @endforeach
                            </select>
                            @error('division')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="district" class="form-label">{{ __('District') }}</label>
                            <input type="text" id="district" name="district" value="{{ old('district', $school->district) }}" class="form-control @error('district') is-invalid @enderror">
                            @error('district')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="upazila" class="form-label">{{ __('Upazila / Thana') }}</label>
                            <input type="text" id="upazila" name="upazila" value="{{ old('upazila', $school->upazila) }}" class="form-control @error('upazila') is-invalid @enderror">
                            @error('upazila')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="postcode" class="form-label">{{ __('Postcode') }}</label>
                            <input type="text" id="postcode" name="postcode" value="{{ old('postcode', $school->postcode) }}" class="form-control @error('postcode') is-invalid @enderror">
                            @error('postcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="span-2">
                            <label for="address" class="form-label">{{ __('Address') }}</label>
                            <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $school->address) }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Regional defaults') }}</h2>
                        <p>{{ __('Used for dates, money formatting and interface language.') }}</p>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label for="timezone" class="form-label">{{ __('Timezone') }}<span class="required">*</span></label>
                            @php($timezones = ['Asia/Dhaka', 'Asia/Kolkata', 'Asia/Karachi', 'Asia/Dubai', 'UTC'])
                            <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', $school->timezone) === $timezone)>{{ $timezone }}</option>
                                @endforeach
                                @if (! in_array(old('timezone', $school->timezone), $timezones, true))
                                    <option value="{{ old('timezone', $school->timezone) }}" selected>{{ old('timezone', $school->timezone) }}</option>
                                @endif
                            </select>
                            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="currency" class="form-label">{{ __('Currency code') }}<span class="required">*</span></label>
                            <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                                @foreach (['BDT', 'USD', 'GBP', 'EUR', 'INR'] as $currency)
                                    <option value="{{ $currency }}" @selected(old('currency', $school->currency) === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="locale" class="form-label">{{ __('Default language') }}<span class="required">*</span></label>
                            <select id="locale" name="locale" class="form-select @error('locale') is-invalid @enderror" required>
                                <option value="en" @selected(old('locale', $school->locale) === 'en')>{{ __('English') }}</option>
                                <option value="bn" @selected(old('locale', $school->locale) === 'bn')>{{ __('বাংলা') }}</option>
                            </select>
                            @error('locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('schools.show') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Save profile') }}</button>
                </div>
            </div>

            <aside class="dashboard-stack">
                <section class="ui-card p-lg">
                    <h2 class="card-title mb-2">{{ __('School logo') }}</h2>
                    <p class="card-subtitle mb-3">{{ __('Square images work best. JPEG, PNG or WebP up to 2 MB.') }}</p>

                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar avatar-lg">
                            @if ($school->logoUrl())
                                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}">
                            @else
                                {{ $school->initials() }}
                            @endif
                        </span>
                        <div class="text-muted-2" style="font-size:.76rem;">
                            {{ __('Current logo') }}<br>
                            <span style="font-size:.7rem;">{{ $school->logo_path ? __('Stored securely') : __('Using initials') }}</span>
                        </div>
                    </div>

                    <label for="logo" class="form-label">{{ __('Replace logo') }}</label>
                    <input type="file" id="logo" name="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">{{ __('Uploaded images are validated by MIME type and stored outside executable paths.') }}</div>
                </section>

                <section class="ui-card p-lg">
                    <h2 class="card-title mb-2">{{ __('Before you change') }}</h2>
                    <ul class="text-muted-2 ps-3 mb-0" style="font-size:.78rem;line-height:1.7;">
                        <li>{{ __('The school code appears in generated identifiers — avoid casual changes.') }}</li>
                        <li>{{ __('Timezone changes affect new timestamps only.') }}</li>
                        <li>{{ __('Every update is audited with old and new values.') }}</li>
                    </ul>
                </section>
            </aside>
        </div>
    </form>
@endsection
