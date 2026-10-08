<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The Draft was saved elsewhere after the editor loaded it; the newer save is never overwritten.
 */
class BlockDraftConflictException extends RuntimeException {}
