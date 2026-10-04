<?php

namespace App\Forms\Captcha;

enum CaptchaVerdict
{
    case Passed;

    case Failed;

    /** The provider could not be reached; owner-approved policy is to accept (fail open). */
    case Unavailable;
}
