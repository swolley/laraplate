<?php

namespace Modules\Cms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Cms\Services\NominatimService;
use Modules\Cms\Http\Requests\GeocodeRequest;

class LocationsController extends Controller
{
    public function geocode(GeocodeRequest $request, NominatimService $nominatimService): JsonResponse
    {
        $response = new ResponseBuilder($request);
        $location = $nominatimService->search($request->get('q'), $request->get('city'), $request->get('province'), $request->get('country'));
        $response->setData($location);
        return $response->json();
    }
}