@extends('layouts.app')

@section('title', app()->getLocale() == 'ar' ? 'تعديل الملف الشخصي' : 'Edit Profile')

@section('content')
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h3>{{ app()->getLocale() == 'ar' ? 'تعديل الملف الشخصي' : 'Edit Profile' }}</h3>
            </div>
        </div>

        <form action="{{ route('teacher.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @php
                $profilePic =
                    optional($teacherProfile->attachments->where('attached_to_type', 'profile_picture')->first())
                        ->file_path ?? asset('images/default-avatar.png');
                $profilePicUrl = Str::startsWith($profilePic, ['http://', 'https://', '/storage'])
                    ? url($profilePic)
                    : $profilePic;
            @endphp

            <div class="row">
                <div class="col-lg-4">
                    <div class="card text-center p-4">
                        <img id="profilePreview" src="{{ $profilePicUrl }}" alt="Profile Photo" class="rounded-circle mb-3"
                            style="width:160px;height:160px;object-fit:cover;">
                        <div class="mb-3">
                            <label class="btn btn-outline-secondary btn-sm">
                                {{ app()->getLocale() == 'ar' ? 'تغيير الصورة' : 'Change Photo' }}
                                <input type="file" name="profile_picture" id="profile_picture" accept="image/*"
                                    class="d-none">
                            </label>
                        </div>
                        @error('profile_picture')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><strong>{{ app()->getLocale() == 'ar' ? 'الخدمات' : 'Services' }}</strong>
                        </div>
                        <div class="card-body">
                            @if ($allServices->count())
                                <div class="row">
                                    @foreach ($allServices as $service)
                                        <div class="col-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="services[]"
                                                    value="{{ $service->id }}" id="service_{{ $service->id }}"
                                                    {{ in_array($service->id, old('services', $assignedServiceIds)) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="service_{{ $service->id }}">
                                                    {{ app()->getLocale() == 'ar' ? $service->name_ar ?? $service->name_en : $service->name_en ?? $service->name_ar }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted">
                                    {{ app()->getLocale() == 'ar' ? 'لا توجد خدمات مفعلة.' : 'No active services available.' }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card p-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label
                                        class="form-label">{{ app()->getLocale() == 'ar' ? 'الاسم الأول' : 'First Name' }}</label>
                                    <input type="text" name="first_name" class="form-control"
                                        value="{{ old('first_name', $teacherProfile->first_name) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label
                                        class="form-label">{{ app()->getLocale() == 'ar' ? 'اسم العائلة' : 'Last Name' }}</label>
                                    <input type="text" name="last_name" class="form-control"
                                        value="{{ old('last_name', $teacherProfile->last_name) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        value="{{ old('email', $teacherProfile->email) }}" required>
                                    @if ($teacherProfile->email_verified_at)
                                        <small
                                            class="text-success">{{ app()->getLocale() == 'ar' ? 'تم التحقق من البريد' : 'Email verified' }}</small>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ app()->getLocale() == 'ar' ? 'الهاتف' : 'Phone' }}</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="{{ old('phone', $teacherProfile->phone_number) }}">
                                    @if ($teacherProfile->phone_verified_at)
                                        <small
                                            class="text-success">{{ app()->getLocale() == 'ar' ? 'تم التحقق من الهاتف' : 'Phone verified' }}</small>
                                    @endif
                                </div>

                                <div class="col-12">
                                    <label
                                        class="form-label">{{ app()->getLocale() == 'ar' ? 'السيرة الذاتية' : 'Bio' }}</label>
                                    <textarea name="bio" class="form-control" rows="5">{{ old('bio', optional($teacherProfile->teacherInfo)->bio) }}</textarea>
                                </div>

                                <div class="col-6">
                                    {{-- FIX ✔ Hidden value for unchecked checkbox --}}
                                    <input type="hidden" name="teach_individual" value="0">

                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="teach_individual"
                                            name="teach_individual" value="1"
                                            {{ old('teach_individual', optional($teacherProfile->teacherInfo)->teach_individual) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="teach_individual">
                                            {{ app()->getLocale() == 'ar' ? 'أقدم تدريس فردي' : 'Offer Individual' }}
                                        </label>
                                    </div>

                                    <input type="number" step="0.01" name="individual_hour_price"
                                        class="form-control mt-2"
                                        placeholder="{{ app()->getLocale() == 'ar' ? 'سعر الساعة (فردي)' : 'Hourly (Individual)' }}"
                                        value="{{ old('individual_hour_price', optional($teacherProfile->teacherInfo)->individual_hour_price) }}">
                                </div>

                                <div class="col-6">
                                    {{-- FIX ✔ Hidden value for unchecked checkbox --}}
                                    <input type="hidden" name="teach_group" value="0">

                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="teach_group" name="teach_group"
                                            value="1"
                                            {{ old('teach_group', optional($teacherProfile->teacherInfo)->teach_group) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="teach_group">
                                            {{ app()->getLocale() == 'ar' ? 'أقدم تدريس جماعي' : 'Offer Group' }}
                                        </label>
                                    </div>

                                    <input type="number" step="0.01" name="group_hour_price"
                                        class="form-control mt-2"
                                        placeholder="{{ app()->getLocale() == 'ar' ? 'سعر الساعة (جماعي)' : 'Hourly (Group)' }}"
                                        value="{{ old('group_hour_price', optional($teacherProfile->teacherInfo)->group_hour_price) }}">

                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <input type="number" name="min_group_size" class="form-control"
                                                placeholder="{{ app()->getLocale() == 'ar' ? 'الحد الأدنى' : 'Min size' }}"
                                                value="{{ old('min_group_size', optional($teacherProfile->teacherInfo)->min_group_size) }}">
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="max_group_size" class="form-control"
                                                placeholder="{{ app()->getLocale() == 'ar' ? 'الحد الأقصى' : 'Max size' }}"
                                                value="{{ old('max_group_size', optional($teacherProfile->teacherInfo)->max_group_size) }}">
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="card-footer text-end">
                            <a href="{{ route('teacher.profile.show') }}" class="btn btn-outline-secondary">
                                {{ app()->getLocale() == 'ar' ? 'إلغاء' : 'Cancel' }}
                            </a>
                            <button class="btn btn-primary" type="submit">
                                {{ app()->getLocale() == 'ar' ? 'حفظ' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>

    @push('scripts')
        <script>
            document.getElementById('profile_picture')?.addEventListener('change', function(e) {
                const input = e.target;
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const img = document.getElementById('profilePreview');
                        if (img) img.src = ev.target.result;
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            });
        </script>
    @endpush
@endsection
