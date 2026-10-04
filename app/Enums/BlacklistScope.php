<?php

namespace App\Enums;

enum BlacklistScope: string
{
    case Global = 'global';
    case Workspace = 'workspace';
    case Site = 'site';
}
