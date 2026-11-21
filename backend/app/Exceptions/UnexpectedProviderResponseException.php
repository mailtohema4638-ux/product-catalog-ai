<?php

namespace App\Exceptions;

/** The source answered, but not with a payload we can trust. */
class UnexpectedProviderResponseException extends ProviderUnavailableException {}
