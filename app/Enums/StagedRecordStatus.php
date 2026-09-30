<?php

namespace App\Enums;

enum StagedRecordStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
