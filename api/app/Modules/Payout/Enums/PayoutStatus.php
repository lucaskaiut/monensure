<?php

namespace App\Modules\Payout\Enums;

/**
 * Ciclo de vida de uma ordem de pagamento.
 *
 * scheduled → submitting → processing → paid
 *                ↘ failed      ↘ failed
 *                ↘ needs_review (resultado incerto; exige conferência manual)
 * scheduled → cancelled
 */
enum PayoutStatus: string
{
    case Scheduled = 'scheduled';
    case Submitting = 'submitting';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Submitting, self::Processing, self::NeedsReview], true);
    }

    /**
     * Estados que já consumiram (ou podem ter consumido) saldo.
     *
     * @return list<self>
     */
    public static function committed(): array
    {
        return [self::Submitting, self::Processing, self::NeedsReview, self::Paid];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
