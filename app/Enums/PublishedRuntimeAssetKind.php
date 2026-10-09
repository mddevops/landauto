<?php

namespace App\Enums;

/**
 * Compiled runtime artifacts of a Published Version (ADR-009). `native_js` is reserved for the
 * future trusted Native JavaScript runtime and is not produced yet.
 */
enum PublishedRuntimeAssetKind: string
{
    case NativeCss = 'native_css';
}
