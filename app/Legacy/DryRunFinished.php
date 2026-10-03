<?php

namespace App\Legacy;

use RuntimeException;

/** Thrown at the end of a dry run to roll the import back. */
final class DryRunFinished extends RuntimeException {}
