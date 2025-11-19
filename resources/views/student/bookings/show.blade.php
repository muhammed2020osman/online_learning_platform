@extends('layouts.app')

<!-- View: student/bookings/show -->
@section('content')
<div class="container py-4">
    <!-- Back Button & Header -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="{{ route('student.bookings.index') }}" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left"></i> {{ app()->getLocale() == 'ar' ? 'رجوع' : 'Back' }}
            </a>
            <h2>{{ app()->getLocale() == 'ar' ? 'تفاصيل الحجز' : 'Booking Details' }}</h2>
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

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Booking Reference & Status -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'رقم الحجز:' : 'Booking Reference:' }} #{{ $booking->booking_reference }}</h5>
                        @if($booking->status == 'pending_payment')
                            <span class="badge bg-warning">{{ app()->getLocale() == 'ar' ? 'قيد الانتظار' : 'Pending Payment' }}</span>
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
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>{{ app()->getLocale() == 'ar' ? 'تاريخ الحجز:' : 'Booking Date:' }}</strong><br>
                                {{ optional($booking->booking_date)->format('M d, Y H:i') ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>{{ app()->getLocale() == 'ar' ? 'نوع الجلسة:' : 'Session Type:' }}</strong><br>
                                {{ ucfirst($booking->session_type) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teacher Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'معلومات المعلم' : 'Teacher Information' }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <h6>{{ $booking->teacher->first_name }} {{ $booking->teacher->last_name }}</h6>
                            <p class="mb-2"><strong>{{ app()->getLocale() == 'ar' ? 'البريد الإلكتروني:' : 'Email:' }}</strong> {{ $booking->teacher->email }}</p>
                            <p class="mb-0"><strong>{{ app()->getLocale() == 'ar' ? 'الهاتف:' : 'Phone:' }}</strong> {{ $booking->teacher->phone ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'معلومات الدورة' : 'Course Information' }}</h5>
                </div>
                <div class="card-body">
                    <p><strong>{{ app()->getLocale() == 'ar' ? 'الموضوع:' : 'Subject:' }}</strong> {{ $booking->course->subject->name_en ?? 'N/A' }}</p>
                    <p><strong>{{ app()->getLocale() == 'ar' ? 'مستوى التعليم:' : 'Education Level:' }}</strong> {{ $booking->course->educationLevel->name_en ?? 'N/A' }}</p>
                    <p><strong>{{ app()->getLocale() == 'ar' ? 'مستوى الفصل:' : 'Class Level:' }}</strong> {{ $booking->course->classLevel->name_en ?? 'N/A' }}</p>
                    <p class="mb-0"><strong>{{ app()->getLocale() == 'ar' ? 'الوصف:' : 'Description:' }}</strong><br>
                        {{ $booking->course->description ?? 'N/A' }}</p>
                </div>
            </div>

            <!-- Session Schedule -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'جدول الجلسات' : 'Session Schedule' }}</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>{{ app()->getLocale() == 'ar' ? 'تاريخ الجلسة الأولى:' : 'First Session Date:' }}</strong><br>
                                {{ optional($booking->first_session_date)->format('M d, Y') ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>{{ app()->getLocale() == 'ar' ? 'الوقت:' : 'Time:' }}</strong><br>
                                {{ optional($booking->first_session_start_time)->format('H:i') ?? 'N/A' }} - {{ optional($booking->first_session_end_time)->format('H:i') ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>{{ app()->getLocale() == 'ar' ? 'مدة الجلسة:' : 'Session Duration:' }}</strong><br>
                                {{ $booking->session_duration }} {{ app()->getLocale() == 'ar' ? 'دقيقة' : 'minutes' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>{{ app()->getLocale() == 'ar' ? 'عدد الجلسات:' : 'Total Sessions:' }}</strong><br>
                                {{ $booking->sessions_count }}</p>
                        </div>
                    </div>

                    <!-- Sessions Progress -->
                    <div class="mb-3">
                        <strong>{{ app()->getLocale() == 'ar' ? 'تقدم الجلسات:' : 'Sessions Progress:' }}</strong>
                        <div class="progress mt-2" style="height: 20px;">
                            <div class="progress-bar bg-success" 
                                 style="width: {{ ($booking->sessions_completed / $booking->sessions_count) * 100 }}%">
                                {{ $booking->sessions_completed }}/{{ $booking->sessions_count }}
                            </div>
                        </div>
                    </div>

                    <!-- Sessions List -->
                    @if($booking->sessions->count() > 0)
                        <div class="mt-3">
                            <strong>{{ app()->getLocale() == 'ar' ? 'الجلسات:' : 'Sessions:' }}</strong>
                            <div class="list-group mt-2">
                                @foreach($booking->sessions as $session)
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    {{ app()->getLocale() == 'ar' ? 'الجلسة' : 'Session' }} #{{ $session->session_number }}
                                                </h6>
                                                <small class="text-muted">
                                                    {{ optional($session->session_date)->format('M d, Y') ?? 'N/A' }} - 
                                                    {{ optional($session->start_time)->format('H:i') ?? 'N/A' }}
                                                </small>
                                            </div>
                                            <span class="badge 
                                                @if($session->status == 'completed') bg-success
                                                @elseif($session->status == 'scheduled') bg-info
                                                @elseif($session->status == 'cancelled') bg-danger
                                                @else bg-secondary
                                                @endif">
                                                {{ ucfirst($session->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Special Requests -->
            @if($booking->special_requests)
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'طلبات خاصة' : 'Special Requests' }}</h5>
                    </div>
                    <div class="card-body">
                        {{ $booking->special_requests }}
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar - Pricing & Actions -->
        <div class="col-lg-4">
            <!-- Pricing Card -->
            <div class="card shadow-sm mb-4 sticky-top" style="top: 20px;">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'ملخص السعر' : 'Price Summary' }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ app()->getLocale() == 'ar' ? 'سعر الجلسة:' : 'Price per Session:' }}</span>
                            <strong>{{ number_format($booking->price_per_session, 2) }} {{ $booking->currency }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ app()->getLocale() == 'ar' ? 'عدد الجلسات:' : 'Sessions:' }}</span>
                            <strong>x {{ $booking->sessions_count }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                            <span>{{ app()->getLocale() == 'ar' ? 'الإجمالي الفرعي:' : 'Subtotal:' }}</span>
                            <strong>{{ number_format($booking->subtotal, 2) }} {{ $booking->currency }}</strong>
                        </div>

                        @if($booking->discount_percentage > 0)
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>{{ app()->getLocale() == 'ar' ? 'الخصم:' : 'Discount:' }} ({{ $booking->discount_percentage }}%)</span>
                                <strong>-{{ number_format($booking->discount_amount, 2) }} {{ $booking->currency }}</strong>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between mb-3 border-top pt-2">
                            <span class="h6">{{ app()->getLocale() == 'ar' ? 'الإجمالي:' : 'Total:' }}</span>
                            <strong class="h6 text-primary">{{ number_format($booking->total_amount, 2) }} {{ $booking->currency }}</strong>
                        </div>
                    </div>

                    <!-- Payment Status -->
                    @if($booking->payment)
                        <div class="alert alert-info mb-3">
                            <small>
                                <strong>{{ app()->getLocale() == 'ar' ? 'حالة الدفع:' : 'Payment Status:' }}</strong><br>
                                @if($booking->payment->status == 'pending')
                                    {{ app()->getLocale() == 'ar' ? 'قيد الانتظار' : 'Pending' }}
                                @elseif($booking->payment->status == 'paid')
                                    {{ app()->getLocale() == 'ar' ? 'مدفوع' : 'Paid' }}
                                @else
                                    {{ ucfirst($booking->payment->status) }}
                                @endif
                            </small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'الإجراءات' : 'Actions' }}</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    @if($booking->status == 'pending_payment')
                        <a href="{{ route('student.bookings.payment', $booking->id) }}" class="btn btn-success btn-lg">
                            <i class="fas fa-credit-card"></i>
                            {{ app()->getLocale() == 'ar' ? 'المتابعة للدفع' : 'Proceed to Payment' }}
                        </a>
                    @elseif($booking->status == 'confirmed' || $booking->status == 'in_progress')
                        <button class="btn btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#joinSessionModal">
                            <i class="fas fa-video"></i>
                            {{ app()->getLocale() == 'ar' ? 'الانضمام للجلسة' : 'Join Session' }}
                        </button>
                    @endif

                    @if(in_array($booking->status, ['confirmed', 'in_progress', 'pending_payment']))
                        <form action="{{ route('student.bookings.cancel', $booking->id) }}" method="POST" class="d-grid"
                              onsubmit="return confirm('{{ app()->getLocale() == 'ar' ? 'هل أنت متأكد من رغبتك في إلغاء هذا الحجز؟' : 'Are you sure you want to cancel this booking?' }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="fas fa-times"></i>
                                {{ app()->getLocale() == 'ar' ? 'إلغاء الحجز' : 'Cancel Booking' }}
                            </button>
                        </form>
                    @endif

                    @if($booking->status == 'completed')
                        <a href="{{ route('reviews.create', ['booking_id' => $booking->id]) }}" class="btn btn-warning">
                            <i class="fas fa-star"></i>
                            {{ app()->getLocale() == 'ar' ? 'ترك تقييم' : 'Leave Review' }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection