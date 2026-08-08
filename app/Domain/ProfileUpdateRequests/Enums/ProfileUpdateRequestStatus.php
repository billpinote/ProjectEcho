<?php

namespace App\Domain\ProfileUpdateRequests\Enums;

enum ProfileUpdateRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
