<?php

namespace App\Exceptions;

use RuntimeException;

class DeveloperProfileBlocksDeletionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Учётную запись с профилем разработчика нельзя удалить самостоятельно. Обратитесь в поддержку Landflow.');
    }
}
