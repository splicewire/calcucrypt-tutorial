<?php

namespace App\Console\Commands;

use App\Data\MigrateLegacySavedInputsFailureData;
use App\Particle\Operations\MigrateLegacySavedInputs as Conversion;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Console adapter to the declared operation; no migration HTTP route is mounted. */
class MigrateLegacySavedInputs extends Command
{
    protected $signature = 'calcucrypt:migrate-saved-inputs {--apply : Convert records in a pre-cutover maintenance window}';

    protected $description = 'Preflight legacy Saved Inputs; --apply converts atomically and retains the source table.';

    public function handle(): int
    {
        try {
            $result = Conversion::handle(null, Request::create('/legacy-saved-inputs', 'POST', ['apply' => (bool) $this->option('apply')]), null);
            $this->line($result->toJson());

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->line((new MigrateLegacySavedInputsFailureData($exception->getMessage(), $exception->errors()))->toJson());

            return self::FAILURE;
        }
    }
}
