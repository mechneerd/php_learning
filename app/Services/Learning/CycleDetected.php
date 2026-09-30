<?php

namespace App\Services\Learning;

/**
 * Thrown when a prerequisite edge would close a loop in the concept graph.
 */
final class CycleDetected extends \RuntimeException {}
