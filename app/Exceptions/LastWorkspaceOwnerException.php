<?php

namespace App\Exceptions;

use RuntimeException;

class LastWorkspaceOwnerException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('A workspace must keep at least one owner.'));
    }
}
