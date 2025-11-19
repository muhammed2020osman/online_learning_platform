@extends('layouts.app')

@section('title', app()->getLocale() == 'ar' ? 'إدارة أوقات التوفر' : 'Manage Availability')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="mb-1">{{ app()->getLocale() == 'ar' ? 'إدارة أوقات التوفر' : 'Manage Your Availability' }}</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">{{ app()->getLocale() == 'ar' ? 'لوحة التحكم' : 'Dashboard' }}</a></li>
                    <li class="breadcrumb-item active">{{ app()->getLocale() == 'ar' ? 'أوقات التوفر' : 'Availability' }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Alert Messages -->
    @if($message = Session::get('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($message = Session::get('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        {{ app()->getLocale() == 'ar' ? 'أوقات التوفر الأسبوعية' : 'Weekly Availability Schedule' }}
                    </h5>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th>{{ app()->getLocale() == 'ar' ? 'اليوم' : 'Day' }}</th>
                                    <th>{{ app()->getLocale() == 'ar' ? 'أوقات التوفر' : 'Available Times' }}</th>
                                    <th class="text-center">{{ app()->getLocale() == 'ar' ? 'الإجراءات' : 'Actions' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($daysOfWeek as $dayNumber => $dayName)
                                    <tr>
                                        <td>
                                            <strong>{{ $dayName }}</strong>
                                        </td>
                                        <td>
                                            @if(isset($availabilitySlots[$dayNumber]))
                                                <div class="d-flex flex-wrap gap-2">
                                                    @foreach($availabilitySlots[$dayNumber] as $slot)
                                                        <span class="badge bg-info">
                                                            {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }} - 
                                                            {{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                                                            @if($slot->is_booked)
                                                                <i class="bi bi-lock-fill ms-1"></i>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">{{ app()->getLocale() == 'ar' ? 'لا توجد أوقات محددة' : 'No times set' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSlotModal{{ $dayNumber }}">
                                                <i class="bi bi-plus-circle"></i>
                                                {{ app()->getLocale() == 'ar' ? 'إضافة' : 'Add' }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Day Overview Cards -->
    <div class="row mt-4">
        @foreach($daysOfWeek as $dayNumber => $dayName)
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card shadow-sm border-primary">
                    <div class="card-header bg-light border-primary">
                        <h6 class="mb-0 text-primary">{{ $dayName }}</h6>
                    </div>
                    <div class="card-body">
                        @if(isset($availabilitySlots[$dayNumber]))
                            <div class="d-flex flex-column gap-2">
                                @foreach($availabilitySlots[$dayNumber] as $slot)
                                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                        <div>
                                            <strong>{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}</strong> - 
                                            <strong>{{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}</strong>
                                            @if($slot->is_booked)
                                                <span class="badge bg-warning ms-2">{{ app()->getLocale() == 'ar' ? 'محجوز' : 'Booked' }}</span>
                                            @else
                                                <span class="badge bg-success ms-2">{{ app()->getLocale() == 'ar' ? 'متاح' : 'Available' }}</span>
                                            @endif
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('teacher.availability.edit', $slot->id) }}" class="btn btn-warning" title="{{ app()->getLocale() == 'ar' ? 'تعديل' : 'Edit' }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('teacher.availability.destroy', $slot->id) }}" class="d-inline" onsubmit="return confirm('{{ app()->getLocale() == 'ar' ? 'هل أنت متأكد من الحذف؟' : 'Are you sure?' }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger" title="{{ app()->getLocale() == 'ar' ? 'حذف' : 'Delete' }}" {{ $slot->is_booked ? 'disabled' : '' }}>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center mb-0">{{ app()->getLocale() == 'ar' ? 'لم تحدد أي أوقات لهذا اليوم' : 'No times set for this day' }}</p>
                        @endif
                    </div>
                    <div class="card-footer bg-light">
                        <button type="button" class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addSlotModal{{ $dayNumber }}">
                            <i class="bi bi-plus me-1"></i>{{ app()->getLocale() == 'ar' ? 'إضافة وقت' : 'Add Time' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Slot Modal for this day -->
            <div class="modal fade" id="addSlotModal{{ $dayNumber }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h6 class="modal-title">{{ app()->getLocale() == 'ar' ? 'إضافة وقت متاح' : 'Add Available Time' }} - {{ $dayName }}</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="{{ route('teacher.availability.store') }}">
                            @csrf
                            <div class="modal-body">
                                <input type="hidden" name="day_number" value="{{ $dayNumber }}">
                                
                                <div class="mb-3">
                                    <label for="startTime{{ $dayNumber }}" class="form-label">
                                        {{ app()->getLocale() == 'ar' ? 'وقت البداية (يتم إضافة ساعة واحدة تلقائياً)' : 'Start Time (1 hour duration auto-added)' }}
                                    </label>
                                    <select class="form-select" id="startTime{{ $dayNumber }}" name="start_time" required>
                                        <option value="">{{ app()->getLocale() == 'ar' ? 'اختر الوقت' : 'Select Time' }}</option>
                                        @foreach($timeSlots as $time)
                                            <option value="{{ $time }}">
                                                {{ \Carbon\Carbon::createFromFormat('H:i', $time)->format('H:i') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    {{ app()->getLocale() == 'ar' ? 'سيتم إضافة ساعة واحدة تلقائياً كوقت انتهاء' : 'An additional hour will be automatically added as the end time' }}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    {{ app()->getLocale() == 'ar' ? 'إلغاء' : 'Cancel' }}
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>{{ app()->getLocale() == 'ar' ? 'إضافة' : 'Add' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<style>
.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>
@endsection
