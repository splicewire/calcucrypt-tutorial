<?php

// Fixture-only prepared pricing: never a creator price editor or a payment/grant shortcut.
use Illuminate\Contracts\Console\Kernel;
use Lunar\Core\Models\Currency;
use Splicewire\Beam\Market\Models\Product;
use Splicewire\Beam\Market\Models\ProductVariant;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'Prepared pricing failed ('.$error::class.").\n");
    exit(1);
});
$id = $argv[1] ?? null;
$amount = $argv[2] ?? null;
$url = parse_url((string) config('app.url'));
$database = config('database.connections.sqlite.database');
if (! app()->environment('local') || ! config('local-marketplace.payment_sandbox')
    || config('commerce.driver') !== 'fake' || config('database.default') !== 'sqlite'
    || ! is_string($database) || realpath(dirname($database)) !== realpath(database_path())
    || basename($database) !== 'service-desk.sqlite' || ($url['host'] ?? null) !== '127.0.0.1'
    || ! isset($url['port']) || ! ctype_digit((string) $id) || ! in_array($amount, ['1900', '66602'], true)) {
    fwrite(STDERR, "Only the explicitly enabled fresh local paid sandbox accepts prepared USD1900/66602 pricing.\n");
    exit(1);
}
$listing = Product::findOrFail($id);
if ($listing->status->getValue() !== 'draft' || $listing->variants()->exists()) {
    throw new RuntimeException('Prepare pricing exactly once on a real draft before publication.');
}
$listing->getConnection()->transaction(function () use ($listing, $amount): void {
    $tier = ProductVariant::factory()->create(['product_id' => $listing->id, 'sku' => 'SANDBOX-'.$listing->id]);
    $tier->prices()->create(['currency_id' => Currency::where('code', 'USD')->firstOrFail()->id,
        'price' => (int) $amount, 'min_quantity' => 1]);
});
echo json_encode(['productId' => (int) $listing->id, 'currency' => 'USD', 'minorUnits' => (int) $amount, 'fixtureOnly' => true], JSON_THROW_ON_ERROR)."\n";
