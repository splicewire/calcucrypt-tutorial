<?php

namespace Database\Seeders;

use App\Workflows\ServiceRequestApproval;
use Illuminate\Database\Seeder;
use Splicewire\Beam\Workflows\Blueprint\BlueprintValidator;
use Splicewire\Beam\Workflows\Definition\DefinitionStore;

class ServiceRequestApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $blueprint = ServiceRequestApproval::blueprint();
        app(BlueprintValidator::class)->validate($blueprint);
        app(DefinitionStore::class)->ensureSystemLineage(ServiceRequestApproval::LINEAGE, 'Service request approval', $blueprint);
    }
}
