<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property EnquiryStatus $status
 * @property string|null $failure_reason
 *
 * A customer enquiry. Product title and price are snapshots, so later catalogue changes
 * never rewrite what the customer actually asked about.
 */
class Enquiry extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id',
        'product_title',
        'product_price_minor',
        'product_currency',
        'name',
        'email',
        'phone',
        'message',
        'status',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'product_price_minor' => 'integer',
            'status' => EnquiryStatus::class,
        ];
    }

    public function markSent(): void
    {
        $this->update(['status' => EnquiryStatus::Sent, 'failure_reason' => null]);
    }

    public function markFailed(string $reason): void
    {
        $this->update(['status' => EnquiryStatus::Failed, 'failure_reason' => mb_substr($reason, 0, 500)]);
    }
}
