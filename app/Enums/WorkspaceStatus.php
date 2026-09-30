<?php

namespace App\Enums;

enum WorkspaceStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
