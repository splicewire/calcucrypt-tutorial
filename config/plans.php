<?php

/*
| The plans a team can buy, and what each costs. A plan's slug is a bundle in
| `config/beam/accounts.php` (`entitlements.bundles`), which lists the calculators it unlocks; this
| file only names and prices it. `free` is not here: it is what a team has before it buys anything.
|
| Prices are in minor units (cents) of the currency, which is `commerce.currency` (USD). A plan is a
| one-time purchase for the team.
*/
return [

    'trader' => [
        'name' => 'Trader',
        'summary' => 'Position sizing from your risk budget and stop.',
        'price' => 900,
    ],

    'pro' => [
        'name' => 'Pro',
        'summary' => 'Everything in Trader, plus capital gains.',
        'price' => 1900,
    ],

];
