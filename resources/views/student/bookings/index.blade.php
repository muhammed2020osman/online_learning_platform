
@extends('layouts.app')

@section('title' , 'booking')
<!-- View: student/bookings/index -->


@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">
                {{ app()->getLocale() == 'ar' ? 'حجوزاتي' : 'My Bookings' }}
            </h2>
            <p class="text-muted">{{ app()->getLocale() == 'ar' ? 'إدارة جميع حجوزاتك' : 'Manage all your bookings' }}</p>
        </div>
    </div>

    <!-- Status Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('bookings.index', ['status' => 'all']) }}" 
                   class="btn btn-outline-primary {{ $status == 'all' ? 'active' : '' }}">
                    {{ app()->getLocale() == 'ar' ? 'جميع' : 'All' }}
                </a>
                <a href="{{ route('bookings.index', ['status' => 'upcoming']) }}" 
                   class="btn btn-outline-primary {{ $status == 'upcoming' ? 'active' : '' }}">
                    {{ app()->getLocale() == 'ar' ? 'قادمة' : 'Upcoming' }}
                </a>
                <a href="{{ route('bookings.index', ['status' => 'active']) }}" 
                   class="btn btn-outline-primary {{ $status == 'active' ? 'active' : '' }}">
                    {{ app()->getLocale() == 'ar' ? 'نشطة' : 'Active' }}
                </a>
                <a href="{{ route('bookings.index', ['status' => 'completed']) }}" 
                   class="btn btn-outline-primary {{ $status == 'completed' ? 'active' : '' }}">
                    {{ app()->getLocale() == 'ar' ? 'مكتملة' : 'Completed' }}
                </a>
                <a href="{{ route('bookings.index', ['status' => 'cancelled']) }}" 
                   class="btn btn-outline-primary {{ $status == 'cancelled' ? 'active' : '' }}">
                    {{ app()->getLocale() == 'ar' ? 'ملغاة' : 'Cancelled' }}
                </a>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Bookings Grid -->
    @if($bookings->count() > 0)
        <div class="row">
            @foreach($bookings as $booking)
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card h-100 shadow-sm border-0 booking-card">
                        <!-- Status Badge -->
                        <div class="position-absolute top-0 end-0 p-2">
                            @if($booking->status == 'pending_payment')
                                <span class="badge bg-warning">{{ app()->getLocale() == 'ar' ? 'قيد الانتظار' : 'Pending' }}</span>
                            @elseif($booking->status == 'confirmed')
                                <span class="badge bg-success">{{ app()->getLocale() == 'ar' ? 'مؤكد' : 'Confirmed' }}</span>
                            @elseif($booking->status == 'in_progress')
                                <span class="badge bg-info">{{ app()->getLocale() == 'ar' ? 'جارٍ' : 'In Progress' }}</span>
                            @elseif($booking->status == 'completed')
                                <span class="badge bg-primary">{{ app()->getLocale() == 'ar' ? 'مكتمل' : 'Completed' }}</span>
                            @else
                                <span class="badge bg-danger">{{ app()->getLocale() == 'ar' ? 'ملغى' : 'Cancelled' }}</span>
                            @endif
                        </div>

                        <div class="card-body">
                            <!-- Teacher Info -->
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ $booking->teacher->first_name }} {{ $booking->teacher->last_name }}</h6>
                                    <small class="text-muted">{{ $booking->course->subject->name_en ?? 'N/A' }}</small>
                                </div>
                            </div>

                            <!-- Booking Reference -->
                            <p class="mb-2">
                                <small><strong>{{ app()->getLocale() == 'ar' ? 'رقم الحجز:' : 'Booking Ref:' }}</strong></small>
                                <br>
                                <small class="text-muted">#{{ $booking->booking_reference }}</small>
                            </p>

                            <!-- Session Info -->
                            <div class="bg-light p-2 rounded mb-2">
                                <small>
                                    <strong>{{ app()->getLocale() == 'ar' ? 'الجلسات:' : 'Sessions:' }}</strong>
                                    {{ $booking->sessions_completed }}/{{ $booking->sessions_count }}
                                </small>
                                <div class="progress mt-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: {{ ($booking->sessions_completed / $booking->sessions_count) * 100 }}%"></div>
                                </div>
                            </div>

                            <!-- Date & Time -->
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i>
                                    {{ optional($booking->first_session_date)->format('M d, Y') ?? 'N/A' }}
                                    <br>
                                    <i class="fas fa-clock"></i>
                                    {{ optional($booking->first_session_start_time)->format('H:i') ?? 'N/A' }} - {{ optional($booking->first_session_end_time)->format('H:i') ?? 'N/A' }}
                                </small>
                            </div>

                            <!-- Price -->
                            <div class="border-top pt-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>
                                        <strong>{{ app()->getLocale() == 'ar' ? 'الإجمالي:' : 'Total:' }}</strong>
                                    </small>
                                    <strong class="text-primary">
                                        {{ number_format($booking->total_amount, 2) }} {{ $booking->currency }}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer Actions -->
                        <div class="card-footer bg-white border-top">
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-outline-primary">
                                    <i class="fas fa-eye"></i> {{ app()->getLocale() == 'ar' ? 'عرض' : 'View' }}
                                </a>
                                @if($booking->status == 'pending_payment')
                                    <a href="{{ route('bookings.payment', $booking->id) }}" class="btn btn-outline-success">
                                        <i class="fas fa-credit-card"></i> {{ app()->getLocale() == 'ar' ? 'دفع' : 'Pay' }}
                                    </a>
                                @endif
                                @if(in_array($booking->status, ['confirmed', 'in_progress']) && now()->diffInHours(Carbon\Carbon::parse($booking->first_session_date . ' ' . $booking->first_session_start_time)) <= 24)
                                    <form action="{{ route('bookings.cancel', $booking->id) }}" method="POST" class="d-inline" 
                                          onsubmit="return confirm('{{ app()->getLocale() == 'ar' ? 'هل أنت متأكد؟' : 'Are you sure?' }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i class="fas fa-times"></i> {{ app()->getLocale() == 'ar' ? 'ألغِ' : 'Cancel' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="row mt-4">
            <div class="col-12 d-flex justify-content-center">
                {{ $bookings->links() }}
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">
                            {{ app()->getLocale() == 'ar' ? 'لا توجد حجوزات' : 'No bookings found' }}
                        </h5>
                        <p class="text-muted">
                            {{ app()->getLocale() == 'ar' ? 'ابدأ بحجز جلسة مع معلم' : 'Start by booking a session with a teacher' }}
                        </p>
                        <a href="{{ route('courses.index') }}" class="btn btn-primary mt-3">
                            <i class="fas fa-search"></i> {{ app()->getLocale() == 'ar' ? 'استكشف الدورات' : 'Explore Courses' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
.booking-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.booking-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
}
</style>
@endsection