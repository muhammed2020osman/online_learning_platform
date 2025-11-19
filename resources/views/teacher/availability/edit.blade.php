@extends('layouts.app')

@section('title', app()->getLocale() == 'ar' ? 'تعديل وقت التوفر' : 'Edit Availability')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <h2 class="mb-4">
                {{ app()->getLocale() == 'ar' ? 'تعديل وقت التوفر' : 'Edit Availability Slot' }}
            </h2>

            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="bi bi-pencil me-2"></i>
                        {{ app()->getLocale() == 'ar' ? 'تحديث معلومات الوقت' : 'Update Time Details' }}
                    </h6>
                </div>

                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($slot->is_booked)
                        <div class="alert alert-warning" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            {{ app()->getLocale() == 'ar' ? 'هذا الوقت محجوز بالفعل. قد لا تتمكن من تعديل جميع الحقول.' : 'This slot is already booked. You may not be able to modify all fields.' }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('teacher.availability.update', $slot->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="dayNumber" class="form-label">
                                {{ app()->getLocale() == 'ar' ? 'اختر اليوم' : 'Select Day' }}
                            </label>
                            <select class="form-select @error('day_number') is-invalid @enderror" 
                                    id="dayNumber" name="day_number" required {{ $slot->is_booked ? 'disabled' : '' }}>
                                @foreach($daysOfWeek as $dayNum => $dayName)
                                    <option value="{{ $dayNum }}" {{ $slot->day_number == $dayNum ? 'selected' : '' }}>
                                        {{ $dayName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('day_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="startTime" class="form-label">
                                {{ app()->getLocale() == 'ar' ? 'وقت البداية' : 'Start Time' }}
                            </label>
                            <select class="form-select @error('start_time') is-invalid @enderror" 
                                    id="startTime" name="start_time" required {{ $slot->is_booked ? 'disabled' : '' }}>
                                @foreach($timeSlots as $time)
                                    <option value="{{ $time }}" 
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') == $time ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $time)->format('h:i A') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('start_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="endTime" class="form-label">
                                {{ app()->getLocale() == 'ar' ? 'وقت الانتهاء (يُحدَّث تلقائياً)' : 'End Time (Auto-calculated)' }}
                            </label>
                            <input type="text" class="form-control" id="endTime" 
                                   value="{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}" disabled>
                            <small class="text-muted">
                                {{ app()->getLocale() == 'ar' ? 'سيتم إضافة ساعة واحدة تلقائياً' : 'One hour will be automatically added' }}
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            {{ app()->getLocale() == 'ar' ? 'مدة كل جلسة هي ساعة واحدة بالضبط' : 'Each session is exactly 1 hour' }}
                        </div>

                        <div class="row g-2 mt-4">
                            <div class="col">
                                <a href="{{ route('teacher.availability.index') }}" class="btn btn-secondary w-100">
                                    <i class="bi bi-arrow-left me-1"></i>
                                    {{ app()->getLocale() == 'ar' ? 'الرجوع' : 'Back' }}
                                </a>
                            </div>
                            <div class="col">
                                <button type="submit" class="btn btn-primary w-100" {{ $slot->is_booked ? 'disabled' : '' }}>
                                    <i class="bi bi-check-circle me-1"></i>
                                    {{ app()->getLocale() == 'ar' ? 'تحديث' : 'Update' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Slot Info Card -->
            <div class="card shadow-sm mt-4 border-secondary">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        {{ app()->getLocale() == 'ar' ? 'معلومات الجلسة' : 'Slot Information' }}
                    </h6>
                </div>
                <div class="card-body">
                    <p>
                        <strong>{{ app()->getLocale() == 'ar' ? 'الحالة:' : 'Status:' }}</strong>
                        @if($slot->is_booked)
                            <span class="badge bg-warning">{{ app()->getLocale() == 'ar' ? 'محجوز' : 'Booked' }}</span>
                        @else
                            <span class="badge bg-success">{{ app()->getLocale() == 'ar' ? 'متاح' : 'Available' }}</span>
                        @endif
                    </p>
                    <p>
                        <strong>{{ app()->getLocale() == 'ar' ? 'المدة:' : 'Duration:' }}</strong> 
                        {{ $slot->duration }} {{ app()->getLocale() == 'ar' ? 'دقيقة' : 'minutes' }}
                    </p>
                    <p class="mb-0">
                        <strong>{{ app()->getLocale() == 'ar' ? 'تاريخ الإنشاء:' : 'Created:' }}</strong> 
                        {{ $slot->created_at->format('M d, Y h:i A') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
