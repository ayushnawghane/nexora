<?php

namespace App\Services\EInvoice;

use RuntimeException;

/** The IRP rejected the invoice or couldn't be reached. The message is shown to the user. */
class EInvoiceFailed extends RuntimeException {}
