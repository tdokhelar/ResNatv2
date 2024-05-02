<?php

declare(strict_types=1);

namespace App\Enum;

final class UserInteractionType
{
    const Deleted = -1;
    const Add = 0;
    const Edit = 1;
    const Vote = 2;
    const Report = 3;
    const Import = 4;
    const Restored = 5;
    const ModerationResolved = 6;
    const PendingAdd = 7;
    const PendingEdit = 8;
}
