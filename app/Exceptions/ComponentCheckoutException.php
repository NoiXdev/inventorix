<?php

namespace App\Exceptions;

use RuntimeException;

/** A component cannot be checked out or in; the message says why. */
class ComponentCheckoutException extends RuntimeException {}
