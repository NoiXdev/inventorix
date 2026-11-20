<?php

namespace App\Exceptions;

use RuntimeException;

/** An asset cannot be checked out (or not to this target); the message says why. */
class CheckoutNotAllowedException extends RuntimeException {}
