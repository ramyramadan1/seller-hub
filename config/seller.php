<?php

return [
    // Free credits granted to a new store (central credit ledger, kind "bonus").
    'trial_credits' => (int) env('SELLER_TRIAL_CREDITS', 10),

    'currencies' => ['SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'EGP', 'USD', 'EUR'],
    'locales' => ['ar', 'en'],
];
