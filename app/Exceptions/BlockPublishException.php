<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Publishing a Block Version was refused; the message is Russian and safe to show to the author.
 */
final class BlockPublishException extends RuntimeException {}
