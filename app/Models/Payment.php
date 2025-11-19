<?php


// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;



class Payment extends Model
{
    
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'student_id',
        'teacher_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'transaction_reference',
        'gateway_reference',
        'gateway_response',
        'paid_at',
        'refund_amount',
        'refund_status',
        'refund_processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'refund_processed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    // Payment method constants
    const METHOD_CARD = 'card';
    const METHOD_WALLET = 'wallet';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_APPLE_PAY = 'apple_pay';
    const METHOD_STC_PAY = 'stc_pay';

    // Refund status constants
    const REFUND_NONE = 'none';
    const REFUND_PROCESSING = 'processing';
    const REFUND_COMPLETED = 'completed';
    const REFUND_FAILED = 'failed';

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRefunded($query)
    {
        return $query->where('refund_status', self::REFUND_COMPLETED);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    // Accessors & Mutators
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function getFormattedRefundAmountAttribute(): string
    {
        if (!$this->refund_amount) return '0.00 ' . $this->currency;
        return number_format($this->refund_amount, 2) . ' ' . $this->currency;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            self::METHOD_CARD => 'Credit/Debit Card',
            self::METHOD_WALLET => 'Digital Wallet',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_APPLE_PAY => 'Apple Pay',
            self::METHOD_STC_PAY => 'STC Pay',
            default => ucfirst(str_replace('_', ' ', $this->payment_method))
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REFUNDED => 'Refunded',
            default => ucfirst($this->status)
        };
    }

    public function getRefundStatusLabelAttribute(): string
    {
        return match($this->refund_status) {
            self::REFUND_NONE => 'No Refund',
            self::REFUND_PROCESSING => 'Processing Refund',
            self::REFUND_COMPLETED => 'Refund Completed',
            self::REFUND_FAILED => 'Refund Failed',
            default => ucfirst(str_replace('_', ' ', $this->refund_status))
        };
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getIsPendingAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getHasRefundAttribute(): bool
    {
        return $this->refund_amount > 0;
    }

    public function getIsRefundedAttribute(): bool
    {
        return $this->refund_status === self::REFUND_COMPLETED;
    }

    public function getIsRefundPendingAttribute(): bool
    {
        return $this->refund_status === self::REFUND_PROCESSING;
    }

    // Methods
    public function markAsCompleted(string $gatewayReference = null, array $gatewayResponse = []): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'paid_at' => now(),
            'gateway_reference' => $gatewayReference,
            'gateway_response' => $gatewayResponse,
        ]);
    }

    public function markAsFailed(array $gatewayResponse = []): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse),
        ]);
    }

    public function processRefund(float $refundAmount): bool
    {
        return $this->update([
            'refund_amount' => $refundAmount,
            'refund_status' => self::REFUND_PROCESSING,
        ]);
    }

    public function completeRefund(): bool
    {
        return $this->update([
            'refund_status' => self::REFUND_COMPLETED,
            'refund_processed_at' => now(),
            'status' => self::STATUS_REFUNDED,
        ]);
    }

    public function failRefund(): bool
    {
        return $this->update([
            'refund_status' => self::REFUND_FAILED,
        ]);
    }

    public function getNetAmount(): float
    {
        return $this->amount - ($this->refund_amount ?? 0);
    }

    public function generateInvoiceNumber(): string
    {
        return 'INV-' . $this->id . '-' . $this->created_at->format('Ymd');
    }

    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_COMPLETED 
               && $this->refund_status === self::REFUND_NONE
               && $this->paid_at
               && $this->paid_at->diffInDays(now()) <= 30; // 30 days refund policy
    }

    // Static methods
    public static function generateTransactionReference(): string
    {
        return 'TXN' . now()->format('YmdHis') . rand(1000, 9999);
    }

    public static function getPaymentMethods(): array
    {
        return [
            self::METHOD_CARD => 'Credit/Debit Card',
            self::METHOD_WALLET => 'Digital Wallet',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_APPLE_PAY => 'Apple Pay',
            self::METHOD_STC_PAY => 'STC Pay',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    // In app/Models/Payment.php - modify the boot method

protected static function boot()
{
    parent::boot();

    static::updated(function ($payment) {
        // When payment is completed, create Zoom meetings for sessions
        if ($payment->status === self::STATUS_COMPLETED && $payment->booking) {
            $payment->booking->update(['status' => Booking::STATUS_CONFIRMED]);
            
            // Create Zoom meetings for all sessions
            $payment->booking->createMeetingsForSessions();
        }
    });
}
}