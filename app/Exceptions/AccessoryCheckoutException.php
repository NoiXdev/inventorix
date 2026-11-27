<?php

namespace App\Exceptions;

use RuntimeException;

/** An accessory cannot be checked out or in; the message says why. */
class AccessoryCheckoutException extends RuntimeException {}
