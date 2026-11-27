<?php

namespace App\Exceptions;

use RuntimeException;

/** A consumable cannot be checked out; the message says why. */
class ConsumableCheckoutException extends RuntimeException {}
