<?php

namespace App\Http\Controllers;

use App\Data\Pages\RequestApprovalInspectionPageData;
use App\Inspection\RequestApprovalHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Splicewire\Beam\Ux\Frame\RouteContextProjector;

final class RequestApprovalInspectionController
{
    #[ResponseFromData(RequestApprovalInspectionPageData::class)]
    public function __invoke(int $id, Request $request, RequestApprovalHistory $reader): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $subject = $reader->subject($id, $actor);
        abort_if($request->query->count() !== 0, 422);
        $path = app(RouteContextProjector::class)->hrefs('tenant')['service-requests.index'];

        return Inertia::render('service-requests/inspection', new RequestApprovalInspectionPageData(
            requestId: $subject->id,
            cacheScope: $actor->getKey().':'.$subject->team_id,
            backHref: route('frame.console', ['frameRoute' => trim($path, '/').'/'.$subject->id]),
        ));
    }
}
