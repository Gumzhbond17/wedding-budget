<?php

namespace App\Enums;

/**
 * Who gave a gift envelope. A fixed list, so totals per group are comparable between
 * weddings and the frontend can translate every label.
 */
enum GuestGroup: string
{
    case GroomFamily = 'groom_family';
    case BrideFamily = 'bride_family';
    case Relatives = 'relatives';
    case Friends = 'friends';
    case Colleagues = 'colleagues';
    case Neighbors = 'neighbors';
    case Other = 'other';
}
