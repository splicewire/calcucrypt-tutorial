<?php

namespace App\Wallet\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Splicewire\Beam\Data\BeamData;

#[Title('Add sandbox funds')]
#[Description('Local fake rail, not settlement. If an error or timeout leaves the outcome unknown, close this form and reload the wallet before any new deliberate purchase. No automatic retry.')]
class WalletTopupInputData extends BeamData
{
    public function __construct(
        #[MapInputName('amount_usd'), Title('Amount (USD)')]
        #[Description('Sandbox funds, 1–10000 USD, up to two decimal places. A new submission is a new purchase.')]
        public float $amountUsd,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(ValidationContext $context): array
    {
        return ['amount_usd' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:10000']];
    }
}
