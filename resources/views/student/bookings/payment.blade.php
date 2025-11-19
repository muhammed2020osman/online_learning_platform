@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <a href="{{ route('student.bookings.index') }}" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left"></i> {{ app()->getLocale() == 'ar' ? 'رجوع' : 'Back' }}
            </a>
            <h2>{{ app()->getLocale() == 'ar' ? 'الدفع' : 'Payment' }}</h2>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Payment Form -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'تفاصيل الدفع' : 'Payment Details' }}</h5>
                </div>
                <div class="card-body">
                    <form id="paymentForm" method="POST" action="{{ route('student.bookings.payment.process', $booking->id) }}">
                        @csrf

                        <!-- ensure customer and billing fields are submitted as nested arrays -->
                        <input type="hidden" name="customer[email]" value="{{ $student->email }}">
                        @php
                            $full = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
                            $parts = explode(' ', $full, 2);
                            $given = $parts[0] ?? $full;
                            $surname = $parts[1] ?? '';
                        @endphp
                        <input type="hidden" name="customer[givenName]" value="{{ old('customer.givenName', $given) }}">
                        <input type="hidden" name="customer[surname]" value="{{ old('customer.surname', $surname) }}">

                        <!-- billing (use bracket notation so Laravel validates billing.street1, etc) -->
                        <input type="hidden" name="billing[street1]" value="{{ old('billing.street1', $student->address ?? 'N/A') }}">
                        <input type="hidden" name="billing[city]" value="{{ old('billing.city', $student->city ?? 'Riyadh') }}">
                        <input type="hidden" name="billing[state]" value="{{ old('billing.state', $student->state ?? 'Riyadh') }}">
                        <input type="hidden" name="billing[postcode]" value="{{ old('billing.postcode', $student->postcode ?? '00000') }}">
                        <input type="hidden" name="billing[country]" value="{{ old('billing.country', $student->country ?? 'SA') }}">

                        <!-- Payment Method Selection -->
                        <div class="mb-4">
                            <label class="form-label"><strong>{{ app()->getLocale() == 'ar' ? 'طريقة الدفع' : 'Payment Method' }}</strong></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="payment_method_type" id="new_card" value="new_card" checked>
                                <label class="btn btn-outline-primary" for="new_card">
                                    <i class="fas fa-credit-card"></i> {{ app()->getLocale() == 'ar' ? 'بطاقة جديدة' : 'New Card' }}
                                </label>

                                @if($paymentMethods->count() > 0)
                                    <input type="radio" class="btn-check" name="payment_method_type" id="saved_card" value="saved_card">
                                    <label class="btn btn-outline-primary" for="saved_card">
                                        <i class="fas fa-wallet"></i> {{ app()->getLocale() == 'ar' ? 'بطاقة محفوظة' : 'Saved Card' }}
                                    </label>
                                @endif
                            </div>
                        </div>

                        <!-- New Card Section -->
                        <div id="newCardSection">
                            <h6 class="mb-3">{{ app()->getLocale() == 'ar' ? 'معلومات البطاقة' : 'Card Information' }}</h6>

                            <div class="mb-3">
                                <label for="card_holder" class="form-label">
                                    {{ app()->getLocale() == 'ar' ? 'اسم حامل البطاقة' : 'Card Holder Name' }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control @error('card_holder') is-invalid @enderror" 
                                       id="card_holder" name="card_holder" value="{{ old('card_holder', $student->first_name . ' ' . $student->last_name) }}"
                                       placeholder="{{ app()->getLocale() == 'ar' ? 'الاسم الكامل' : 'Full Name' }}">
                                @error('card_holder')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="card_number" class="form-label">
                                    {{ app()->getLocale() == 'ar' ? 'رقم البطاقة' : 'Card Number' }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control @error('card_number') is-invalid @enderror" 
                                       id="card_number" name="card_number" placeholder="1234 5678 9012 3456"
                                       maxlength="19" inputmode="numeric">
                                <small class="text-muted">
                                    {{ app()->getLocale() == 'ar' ? 'يدعم VISA و MASTER و MADA' : 'Supports VISA, MASTER, MADA' }}
                                </small>
                                @error('card_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="expiry_month" class="form-label">
                                        {{ app()->getLocale() == 'ar' ? 'الشهر' : 'Month' }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('expiry_month') is-invalid @enderror" 
                                            id="expiry_month" name="expiry_month">
                                        <option value="">{{ app()->getLocale() == 'ar' ? '-- اختر --' : '-- Select --' }}</option>
                                        @for($m = 1; $m <= 12; $m++)
                                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">
                                                {{ str_pad($m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                    @error('expiry_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="expiry_year" class="form-label">
                                        {{ app()->getLocale() == 'ar' ? 'السنة' : 'Year' }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select @error('expiry_year') is-invalid @enderror" 
                                            id="expiry_year" name="expiry_year">
                                        <option value="">{{ app()->getLocale() == 'ar' ? '-- اختر --' : '-- Select --' }}</option>
                                        @for($y = now()->year; $y <= now()->year + 20; $y++)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                    @error('expiry_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="cvv" class="form-label">
                                    {{ app()->getLocale() == 'ar' ? 'CVV' : 'CVV' }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control @error('cvv') is-invalid @enderror" 
                                       id="cvv" name="cvv" placeholder="123"
                                       maxlength="4" inputmode="numeric">
                                <small class="text-muted">
                                    {{ app()->getLocale() == 'ar' ? '3 أو 4 أرقام على ظهر البطاقة' : '3 or 4 digits on back of card' }}
                                </small>
                                @error('cvv')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="save_card" name="save_card" value="1">
                                <label class="form-check-label" for="save_card">
                                    {{ app()->getLocale() == 'ar' ? 'حفظ هذه البطاقة لاستخدام لاحق' : 'Save this card for future use' }}
                                </label>
                            </div>
                        </div>

                        <!-- Saved Cards Section -->
                        @if($paymentMethods->count() > 0)
                            <div id="savedCardSection" style="display: none;">
                                <h6 class="mb-3">{{ app()->getLocale() == 'ar' ? 'البطاقات المحفوظة' : 'Saved Cards' }}</h6>
                                
                                <div class="row mb-3">
                                    @foreach($paymentMethods as $method)
                                        <div class="col-md-6 mb-3">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <input type="radio" class="form-check-input" name="saved_payment_id" 
                                                           id="card_{{ $method->id }}" value="{{ $method->id }}"
                                                           {{ $loop->first ? 'checked' : '' }}>
                                                    <label class="form-check-label ms-2" for="card_{{ $method->id }}">
                                                        <div>
                                                            <strong>{{ $method->card_holder_name }}</strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                •••• {{ substr($method->card_number, -4) }}
                                                            </small>
                                                            <br>
                                                            <small class="text-muted">
                                                                {{ $method->expiry_month }}/{{ $method->expiry_year }}
                                                            </small>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Terms & Conditions -->
                        <div class="alert alert-info mb-3">
                            <small>
                                <strong>{{ app()->getLocale() == 'ar' ? 'ملاحظة:' : 'Note:' }}</strong>
                                {{ app()->getLocale() == 'ar' ? 'تم تشفير جميع معلومات البطاقة وتتم معالجتها بشكل آمن من خلال HyperPay.' : 'All card information is encrypted and processed securely through HyperPay.' }}
                            </small>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                {{ app()->getLocale() == 'ar' ? 'أوافق على شروط الدفع والخصوصية' : 'I agree to payment terms and privacy policy' }}
                            </label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                <i class="fas fa-lock"></i> 
                                {{ app()->getLocale() == 'ar' ? 'دفع الآن' : 'Pay Now' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">{{ app()->getLocale() == 'ar' ? 'ملخص الطلب' : 'Order Summary' }}</h5>
                </div>
                <div class="card-body">
                    <!-- Booking Info -->
                    <div class="mb-3 pb-3 border-bottom">
                        <h6>{{ $booking->course->subject->name_en ?? 'Course' }}</h6>
                        <small class="text-muted">
                            {{ $booking->teacher->first_name }} {{ $booking->teacher->last_name }}<br>
                            {{ optional($booking->first_session_date)->format('M d, Y') ?? 'N/A' }} 
                            {{ optional($booking->first_session_start_time)->format('H:i') ?? 'N/A' }}
                        </small>
                    </div>

                    <!-- Pricing Breakdown -->
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

                        <div class="d-flex justify-content-between mt-3 pt-2 border-top">
                            <span class="h6">{{ app()->getLocale() == 'ar' ? 'المبلغ المستحق:' : 'Total Amount Due:' }}</span>
                            <strong class="h6 text-primary">{{ number_format($booking->total_amount, 2) }} {{ $booking->currency }}</strong>
                        </div>
                    </div>

                    <!-- Session Details -->
                    <div class="mb-3 pb-3 border-top">
                        <h6 class="mt-3">{{ app()->getLocale() == 'ar' ? 'تفاصيل الجلسات' : 'Session Details' }}</h6>
                        <small class="text-muted">
                            <p class="mb-2">
                                <strong>{{ app()->getLocale() == 'ar' ? 'النوع:' : 'Type:' }}</strong><br>
                                {{ ucfirst($booking->session_type) }}
                            </p>
                            <p class="mb-2">
                                <strong>{{ app()->getLocale() == 'ar' ? 'المدة:' : 'Duration:' }}</strong><br>
                                {{ $booking->session_duration }} {{ app()->getLocale() == 'ar' ? 'دقيقة' : 'minutes' }}
                            </p>
                            <p class="mb-0">
                                <strong>{{ app()->getLocale() == 'ar' ? 'التاريخ:' : 'Date:' }}</strong><br>
                                {{ optional($booking->first_session_date)->format('M d, Y') }}
                            </p>
                        </small>
                    </div>

                    <!-- Security Badge -->
                    <div class="alert alert-success alert-sm mb-0">
                        <small>
                            <i class="fas fa-shield-alt"></i>
                            {{ app()->getLocale() == 'ar' ? 'دفع آمن 100%' : '100% Secure Payment' }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner for Payment Processing -->
<div id="loadingSpinner" class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-none d-flex align-items-center justify-content-center" style="z-index: 9999;">
    <div class="text-center text-white">
        <div class="spinner-border mb-3" role="status">
            <span class="visually-hidden">{{ app()->getLocale() == 'ar' ? 'جاري المعالجة...' : 'Processing...' }}</span>
        </div>
        <p>{{ app()->getLocale() == 'ar' ? 'جاري معالجة الدفع...' : 'Processing your payment...' }}</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle between new and saved cards
    const newCardRadio = document.getElementById('new_card');
    const savedCardRadio = document.getElementById('saved_card');
    const newCardSection = document.getElementById('newCardSection');
    const savedCardSection = document.getElementById('savedCardSection');

    if (newCardRadio) {
        newCardRadio.addEventListener('change', function() {
            if (this.checked) {
                newCardSection.style.display = 'block';
                if (savedCardSection) savedCardSection.style.display = 'none';
            }
        });
    }

    if (savedCardRadio) {
        savedCardRadio.addEventListener('change', function() {
            if (this.checked) {
                newCardSection.style.display = 'none';
                savedCardSection.style.display = 'block';
            }
        });
    }

    // Format card number with spaces
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '');
            let formattedValue = value.replace(/(\d{4})/g, '$1 ').trim();
            this.value = formattedValue;
        });
    }

    // Form submission with loading spinner
    const paymentForm = document.getElementById('paymentForm');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const submitBtn = document.getElementById('submitBtn');

    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        loadingSpinner.classList.remove('d-none');
        submitBtn.disabled = true;

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: formData
        })
        .then(async response => {
            loadingSpinner.classList.add('d-none');
            submitBtn.disabled = false;

            const json = await response.json().catch(() => null);

            if (response.status === 422 && json && json.errors) {
                // show validation errors - join messages
                const messages = Object.values(json.errors).flat().join('\n');
                alert('{{ app()->getLocale() == "ar" ? "خطأ في المدخلات:" : "Validation error:" }}\n' + messages);
                return;
            }

            if (!response.ok) {
                const msg = (json && (json.message || json.error)) ? (json.message || json.error) : 'Server error';
                alert('{{ app()->getLocale() == "ar" ? "خطأ في الدفع:" : "Payment Error:" }} ' + msg);
                return;
            }

            // success handling (same as before)
            if (json.success) {
                if (json.requires_3ds && json.redirect_url) {
                    window.location.href = json.redirect_url;
                } else {
                    window.location.href = json.redirect_url || '{{ route("student.bookings.index") }}';
                }
            } else {
                alert('{{ app()->getLocale() == "ar" ? "خطأ في الدفع:" : "Payment Error:" }} ' + (json.message || ''));
            }
        })
        .catch(error => {
            loadingSpinner.classList.add('d-none');
            submitBtn.disabled = false;
            console.error('Error:', error);
            alert('{{ app()->getLocale() == "ar" ? "حدث خطأ أثناء معالجة الدفع" : "An error occurred while processing payment" }}');
        });
    });
});
</script>

<style>
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
}

.alert-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}
</style>
@endsection