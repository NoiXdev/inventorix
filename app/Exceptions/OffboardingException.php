<?php

namespace App\Exceptions;

use RuntimeException;

/** An offboarding could not be completed and nothing was changed; the message names the item and why. */
class OffboardingException extends RuntimeException {}
