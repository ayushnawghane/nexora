<?php

return [
    /*
    | Decimal places fee schedule amounts are rounded to (half up). 0 = whole rupees, which is what
    | most legacy DT schedules used; legacy was inconsistent, so this awaits business confirmation
    | (docs/PLAN.md §6). Change it here, never per calculation.
    */
    'rounding_scale' => 0,
];
