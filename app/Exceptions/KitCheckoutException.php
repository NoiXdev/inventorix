<?php

namespace App\Exceptions;

use RuntimeException;

/** A kit could not be checked out and nothing was changed; the message names the item and why. */
class KitCheckoutException extends RuntimeException {}
