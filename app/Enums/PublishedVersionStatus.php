<?php

namespace App\Enums;

enum PublishedVersionStatus: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
}
