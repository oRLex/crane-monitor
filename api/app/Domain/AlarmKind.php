<?php

namespace App\Domain;

enum AlarmKind: string
{
    case Wind = 'wind';
    case Overload = 'overload';
    case Offline = 'offline';
}
