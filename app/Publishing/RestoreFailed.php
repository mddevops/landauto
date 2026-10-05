<?php

namespace App\Publishing;

use RuntimeException;

/**
 * A restore that cannot be applied safely. The message is Russian and safe to show.
 */
final class RestoreFailed extends RuntimeException {}
