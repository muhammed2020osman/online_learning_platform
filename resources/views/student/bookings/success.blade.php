@extends('layouts.app')

@section('content')

<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Main Success Card -->
            <div class="card shadow-lg border-0 mb-4">
                <div class="card-body p-5">
                    <!-- Success Icon Animation -->
                    <div class="text-center mb-4">
                        <div class="success-icon-container d-inline-flex align-items-center justify-content-center" style="width: 140px; height: 140px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; animation: scaleIn 0.6s ease-out;">
                            <i class="fas fa-check text-white" style="font-size: 70px;"></i>
                        </div>
                    </div>

                    <!-- Success Messages -->
                    <div class="text-center mb-5">
                        <h1 class="mb-2" style="color: #059669; font-weight: 700;">
                            {{ app()->getLocale() == 'ar' ? 'تم الدفع بنجاح!' : 'Payment Successful!' }}
                        </h1>
                        <p class="text-muted fs-5 mb-0">
                            {{ app()->getLocale() == 'ar' ? 'حجزك قد تم تأكيده وجاهز للبدء' : 'Your booking has been confirmed and is ready to begin' }}
                        </p>
                    </div>

                    <!-- Booking Summary Card -->
                    <div class="row mb-4">
                        <!-- Booking Info -->
                        <div class="col-lg-6 mb-3 mb-lg-0">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted mb-3">{{ app()->getLocale() == 'ar' ? 'معلومات الحجز' : 'Booking Information' }}</h6>
                                    
                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'رقم الحجز' : 'Booking Reference' }}</small>
                                        <h6 class="mb-0" style="color: #059669; font-weight: 600;">
                                            #{{ $booking->booking_reference }}
                                        </h6>
                                    </div>

                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'الحالة' : 'Status' }}</small>
                                        <span class="badge bg-success" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                            <i class="fas fa-check-circle me-1"></i>
                                            {{ app()->getLocale() == 'ar' ? 'مؤكد' : 'Confirmed' }}
                                        </span>
                                    </div>

                                    <div class="mb-0">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'تاريخ الحجز' : 'Booking Date' }}</small>
                                        <small>{{ optional($booking->booking_date)->format('M d, Y H:i') ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Info -->
                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted mb-3">{{ app()->getLocale() == 'ar' ? 'معلومات المعلم' : 'Teacher Information' }}</h6>
                                    
                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'الاسم' : 'Name' }}</small>
                                        <h6 class="mb-0">{{ $booking->teacher->first_name }} {{ $booking->teacher->last_name }}</h6>
                                    </div>

                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'الموضوع' : 'Subject' }}</small>
                                        <small>{{ $booking->course->subject->name_en ?? 'N/A' }}</small>
                                    </div>

                                    <div class="mb-0">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'البريد الإلكتروني' : 'Email' }}</small>
                                        <small>{{ $booking->teacher->email }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Session Details -->
                    <div class="row mb-4">
                        <div class="col-lg-6 mb-3 mb-lg-0">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted mb-3">{{ app()->getLocale() == 'ar' ? 'تفاصيل الجلسة' : 'Session Details' }}</h6>
                                    
                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'تاريخ الجلسة الأولى' : 'First Session Date' }}</small>
                                        <h6 class="mb-0">{{ optional($booking->first_session_date)->format('M d, Y') ?? 'N/A' }}</h6>
                                    </div>

                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'الوقت' : 'Time' }}</small>
                                        <small>{{ optional($booking->first_session_start_time)->format('H:i') ?? 'N/A' }} - {{ optional($booking->first_session_end_time)->format('H:i') ?? 'N/A' }}</small>
                                    </div>

                                    <div class="mb-0">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'مدة الجلسة' : 'Duration' }}</small>
                                        <small>{{ $booking->session_duration }} {{ app()->getLocale() == 'ar' ? 'دقيقة' : 'minutes' }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted mb-3">{{ app()->getLocale() == 'ar' ? 'عدد الجلسات' : 'Sessions' }}</h6>
                                    
                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'إجمالي الجلسات' : 'Total Sessions' }}</small>
                                        <h6 class="mb-0">{{ $booking->sessions_count }}</h6>
                                    </div>

                                    <div class="mb-3 pb-3 border-bottom">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'النوع' : 'Type' }}</small>
                                        <small>
                                            @if($booking->session_type == 'package')
                                                <span class="badge bg-info">{{ app()->getLocale() == 'ar' ? 'باقة' : 'Package' }}</span>
                                            @else
                                                <span class="badge bg-warning">{{ app()->getLocale() == 'ar' ? 'جلسة واحدة' : 'Single Session' }}</span>
                                            @endif
                                        </small>
                                    </div>

                                    <div class="mb-0">
                                        <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'الخصم' : 'Discount' }}</small>
                                        <small>
                                            @if($booking->discount_percentage > 0)
                                                <span class="badge bg-success">{{ $booking->discount_percentage }}% {{ app()->getLocale() == 'ar' ? 'خصم' : 'OFF' }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ app()->getLocale() == 'ar' ? 'بدون خصم' : 'No discount' }}</span>
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="alert alert-info border-0 mb-4" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%); border-left: 4px solid #3b82f6;">
                        <h6 class="mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            {{ app()->getLocale() == 'ar' ? 'الخطوات التالية' : 'What Happens Next' }}
                        </h6>
                        <ul class="mb-0 ps-4">
                            <li class="mb-2">
                                <strong>1.</strong> 
                                {{ app()->getLocale() == 'ar' ? 'ستتلقى رسالة تأكيد عبر البريد الإلكتروني في غضون دقائق' : 'You\'ll receive a confirmation email shortly' }}
                            </li>
                            <li class="mb-2">
                                <strong>2.</strong>
                                {{ app()->getLocale() == 'ar' ? 'سيتم إخطار معلمك بالحجز الجديد' : 'Your teacher will be notified of your booking' }}
                            </li>
                            <li class="mb-2">
                                <strong>3.</strong>
                                {{ app()->getLocale() == 'ar' ? 'يمكنك الانضمام 15 دقيقة قبل بدء الجلسة' : 'You can join 15 minutes before the session starts' }}
                            </li>
                            <li>
                                <strong>4.</strong>
                                {{ app()->getLocale() == 'ar' ? 'ستتمكن من ترك تقييم بعد انتهاء الجلسة' : 'You can leave a review after the session ends' }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Payment Receipt Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            {{ app()->getLocale() == 'ar' ? 'إيصال الدفع' : 'Payment Receipt' }}
                        </h6>
                        <a href="#" class="btn btn-sm btn-outline-primary" onclick="window.print(); return false;">
                            <i class="fas fa-download me-1"></i> {{ app()->getLocale() == 'ar' ? 'تحميل' : 'Print' }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'تاريخ الدفع' : 'Payment Date' }}</small>
                            <strong>{{ $booking->payment->paid_at?->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'طريقة الدفع' : 'Payment Method' }}</small>
                            <strong>{{ ucfirst($booking->payment->payment_method ?? 'Card') }}</strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block mb-1">{{ app()->getLocale() == 'ar' ? 'رقم المعاملة' : 'Transaction ID' }}</small>
                            <strong style="color: #059669;">{{ $booking->payment->transaction_reference ?? 'N/A' }}</strong>
                        </div>
                    </div>

                    <hr>

                    <!-- Pricing Table -->
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-end" style="font-weight: 500;">
                                        {{ $booking->sessions_count }} × {{ number_format($booking->price_per_session, 2) }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($booking->subtotal, 2) }} {{ $booking->currency }}
                                    </td>
                                </tr>
                                @if($booking->discount_amount > 0)
                                    <tr style="background-color: rgba(16, 185, 129, 0.05);">
                                        <td class="text-end text-success" style="font-weight: 500;">
                                            {{ app()->getLocale() == 'ar' ? 'الخصم' : 'Discount' }} ({{ $booking->discount_percentage }}%)
                                        </td>
                                        <td class="text-end text-success">
                                            -{{ number_format($booking->discount_amount, 2) }} {{ $booking->currency }}
                                        </td>
                                    </tr>
                                @endif
                                <tr style="background-color: rgba(5, 150, 105, 0.1); border-top: 2px solid #e5e7eb;">
                                    <td class="text-end" style="font-weight: 700; color: #059669;">
                                        {{ app()->getLocale() == 'ar' ? 'الإجمالي' : 'Total' }}
                                    </td>
                                    <td class="text-end" style="font-weight: 700; color: #059669; font-size: 1.1rem;">
                                        {{ number_format($booking->total_amount, 2) }} {{ $booking->currency }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3 mb-md-0">
                    <a href="{{ route('student.bookings.show', $booking->id) }}" class="btn btn-lg btn-primary w-100" style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); border: none;">
                        <i class="fas fa-eye me-2"></i>
                        {{ app()->getLocale() == 'ar' ? 'عرض تفاصيل الحجز' : 'View Booking Details' }}
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="{{ route('student.bookings.index') }}" class="btn btn-lg btn-outline-secondary w-100">
                        <i class="fas fa-list me-2"></i>
                        {{ app()->getLocale() == 'ar' ? 'حجوزاتي' : 'My Bookings' }}
                    </a>
                </div>
            </div>

            <!-- Support Section -->
            <div class="card border-0 bg-light">
                <div class="card-body text-center py-4">
                    <h6 class="mb-3">{{ app()->getLocale() == 'ar' ? 'هل لديك أسئلة؟' : 'Need Help?' }}</h6>
                    <p class="text-muted mb-3">
                        {{ app()->getLocale() == 'ar' ? 'تواصل مع فريق الدعم لدينا' : 'Contact our support team if you need any assistance' }}
                    </p>
                    <a href="mailto:support@example.com" class="btn btn-sm btn-outline-primary me-2">
                        <i class="fas fa-envelope me-1"></i>
                        {{ app()->getLocale() == 'ar' ? 'البريد الإلكتروني' : 'Email' }}
                    </a>
                    <a href="tel:+966555555555" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-phone me-1"></i>
                        {{ app()->getLocale() == 'ar' ? 'اتصل بنا' : 'Call Us' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.success-icon-container {
    animation: scaleIn 0.6s ease-out;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
}

@media print {
    .btn, .alert, .row > [class*='col'] > .btn,
    a[onclick*='print'] {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
}

@media (max-width: 768px) {
    .card-body {
        padding: 1.5rem !important;
    }
    
    h1 {
        font-size: 1.75rem !important;
    }
}
</style>
@endsection