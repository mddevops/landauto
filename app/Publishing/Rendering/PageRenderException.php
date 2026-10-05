<?php

namespace App\Publishing\Rendering;

use RuntimeException;

/**
 * Rendering failed. The message is a short internal diagnostic; it never contains the manifest.
 */
final class PageRenderException extends RuntimeException {}
