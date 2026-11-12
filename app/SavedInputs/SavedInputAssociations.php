<?php

namespace App\SavedInputs;

use App\Models\Calculator;
use App\Models\User;
use Illuminate\Http\Request;
use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Particle\Backing\ActorOwnedParticleBacking;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Schema\SchemaId;

/** Product associations remain application behavior over the package's shared particle store. */
class SavedInputAssociations
{
    public function deletingCalculator(Calculator $calculator): void
    {
        $resource = app(ParticleResourceRegistry::class)->get('saved-inputs');
        BeamParticle::query()->where('schema_ref', SchemaId::from($resource->schemaRef)->recordType())
            ->where('source_tier', 'local')->where('payload->calculator', $calculator->getKey())
            ->eachById(fn (BeamParticle $particle) => $particle->delete());
    }

    public function deletingUser(User $user): void
    {
        $request = Request::create('/saved-inputs-owner-deletion');
        $request->setUserResolver(fn () => $user);
        $backing = app(ParticleResourceRegistry::class)->get('saved-inputs')->backing($request);
        if (! $backing instanceof ActorOwnedParticleBacking) {
            throw new \LogicException('Saved Inputs must use its declared actor-owned backing.');
        }
        $backing->query([])->eachById(fn (BeamParticle $particle) => $particle->delete());
    }
}
