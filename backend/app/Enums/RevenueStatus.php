<?php

namespace App\Enums;

enum RevenueStatus: string
{
    case Expected = 'expected';
    case Received = 'received';
}
