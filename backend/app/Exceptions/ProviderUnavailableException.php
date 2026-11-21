<?php

namespace App\Exceptions;

use RuntimeException;

/** The product source could not be reached or answered with an error. Transport details never leak past this type. */
class ProviderUnavailableException extends RuntimeException {}
