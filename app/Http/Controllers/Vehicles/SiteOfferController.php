<?php

namespace App\Http\Controllers\Vehicles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\SaveSiteOfferRequest;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SiteOfferController extends Controller
{
    public function store(SaveSiteOfferRequest $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        DB::transaction(function () use ($request, $vehicle): void {
            $offer = new SiteOffer($request->offerAttributes());
            $offer->vehicle()->associate($vehicle)->save();
            $offer->replaceBenefits($request->benefits());
        });

        return back();
    }

    public function update(SaveSiteOfferRequest $request, Site $site, SiteOffer $offer): RedirectResponse
    {
        DB::transaction(function () use ($request, $offer): void {
            $offer->update($request->offerAttributes());
            $offer->replaceBenefits($request->benefits());
        });

        return back();
    }

    public function destroy(Site $site, SiteOffer $offer, DesignerScope $scope): RedirectResponse
    {
        $scope->offer($site, $offer);
        Gate::authorize('editPrices', $site);

        $offer->delete();

        return back();
    }
}
