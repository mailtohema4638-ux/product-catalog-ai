<?php

namespace App\Exceptions;

use RuntimeException;

/** The email provider could not deliver the message. Provider internals stay in `previous`. */
class EmailDeliveryException extends RuntimeException {}
