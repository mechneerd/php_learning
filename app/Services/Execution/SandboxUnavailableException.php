<?php

namespace App\Services\Execution;

/**
 * The runner could not be reached or answered at the transport level
 * (connect failure, HTTP timeout, non-2xx, unreadable body).
 */
final class SandboxUnavailableException extends \RuntimeException {}
