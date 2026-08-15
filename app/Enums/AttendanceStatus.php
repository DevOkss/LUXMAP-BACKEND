<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PENDING = 'pending';
    case PRESENT = 'present';
    case LATE = 'late';
    case ABSENT = 'absent';
}
