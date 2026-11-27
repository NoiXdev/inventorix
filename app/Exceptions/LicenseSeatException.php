<?php

namespace App\Exceptions;

use RuntimeException;

/** A seat cannot be added, removed, checked out or checked in; the message says why. */
class LicenseSeatException extends RuntimeException {}
