<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\VehiclePhotos;
use App\Enums\SiteType;
use App\Enums\StockVehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteStockListing;
use App\Models\StockVehicle;
use App\Models\VehiclePhoto;
use App\Stock\SiteStockPresenter;
use App\Stock\StockPresenter;
use App\Support\DesignerScope;
use App\Support\ImageUpload;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock vehicles chosen for one Site. The Workspace Stock card stays shared;
 * price and photos saved here apply only to this Site.
 */
class SiteStockListingController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private SiteStockPresenter $listings,
        private StockPresenter $stock,
        private VehiclePhotos $photos,
    ) {}

    public function index(Site $site): Response
    {
        $this->authorizeSite($site);

        $rows = $site->stockListings()
            ->with(['stockVehicle.photos', 'photos'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (SiteStockListing $listing): array => $this->listings->card($listing))
            ->values()
            ->all();

        return Inertia::render('sites/stock/index', [
            'site' => $this->site($site),
            'listings' => $rows,
            'can' => $this->can($site),
        ]);
    }

    public function create(Site $site): Response
    {
        $this->authorizeSite($site);
        Gate::authorize('editVehicles', $site);

        $placed = $site->stockListings()->pluck('stock_vehicle_id');
        $available = $site->workspace->stockVehicles()
            ->ready()
            ->whereNotIn('id', $placed)
            ->with('photos')
            ->ordered()
            ->get()
            ->map(fn (StockVehicle $vehicle): array => $this->stock->card($vehicle))
            ->values()
            ->all();

        return Inertia::render('sites/stock/create', [
            'site' => $this->site($site),
            'vehicles' => $available,
        ]);
    }

    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($site);
        Gate::authorize('editVehicles', $site);

        $publicId = $request->validate([
            'stock_vehicle' => ['required', 'string', 'size:26'],
        ], [
            'stock_vehicle.required' => 'Выберите автомобиль склада.',
            'stock_vehicle.size' => 'Выберите автомобиль склада.',
        ])['stock_vehicle'];

        $vehicle = $site->workspace->stockVehicles()->where('public_id', $publicId)->first();
        abort_unless($vehicle instanceof StockVehicle, 404);

        if ($vehicle->status !== StockVehicleStatus::Ready) {
            throw ValidationException::withMessages([
                'stock_vehicle' => 'На сайт можно добавить только автомобиль со статусом «Готов».',
            ]);
        }

        if ($site->stockListings()->where('stock_vehicle_id', $vehicle->id)->exists()) {
            throw ValidationException::withMessages([
                'stock_vehicle' => 'Этот автомобиль уже есть на сайте.',
            ]);
        }

        $listing = new SiteStockListing;
        $listing->site_id = $site->id;
        $listing->stock_vehicle_id = $vehicle->id;
        $listing->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль добавлен на сайт.']);

        return to_route('sites.stock.show', [$site, $listing]);
    }

    public function show(Site $site, SiteStockListing $listing): Response
    {
        $this->scope->stockListing($site, $listing);
        $this->authorizeSite($site);
        $listing->load(['stockVehicle.photos', 'photos']);

        return Inertia::render('sites/stock/show', [
            'site' => $this->site($site),
            'listing' => $this->listings->detail($listing),
            'can' => $this->can($site),
        ]);
    }

    public function update(Request $request, Site $site, SiteStockListing $listing): RedirectResponse
    {
        $this->scope->stockListing($site, $listing);
        $this->authorizeSite($site);
        Gate::authorize('editPrices', $site);

        $request->validate([
            'price' => ['nullable', 'string', 'max:32'],
        ], [
            'price.max' => 'Укажите цену короче.',
        ]);

        $price = $request->string('price')->trim()->value();

        if ($price === '') {
            $listing->forceFill(['price_minor' => null, 'currency' => null])->save();
        } else {
            $minor = Money::parse($price);

            if ($minor === null) {
                throw ValidationException::withMessages(['price' => 'Укажите цену, например 1 850 000.']);
            }

            $listing->forceFill([
                'price_minor' => $minor,
                'currency' => Money::DEFAULT_CURRENCY,
            ])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Цена на сайте сохранена.']);

        return back();
    }

    public function destroy(Site $site, SiteStockListing $listing): RedirectResponse
    {
        $this->scope->stockListing($site, $listing);
        $this->authorizeSite($site);
        Gate::authorize('editVehicles', $site);

        $listing->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль снят с сайта. На складе он остался.']);

        return to_route('sites.stock.index', $site);
    }

    public function storePhotos(Request $request, Site $site, SiteStockListing $listing): RedirectResponse
    {
        $this->scope->stockListing($site, $listing);
        $this->authorizeSite($site);
        Gate::authorize('editVehicles', $site);
        $listing->load('site');

        $validator = Validator::make($request->all(), [
            'uploads' => ['required', 'array', 'min:1', 'max:20'],
            'uploads.*' => ImageUpload::rules(),
        ], [
            'uploads.required' => 'Выберите хотя бы одно фото.',
            ...ImageUpload::messages('uploads.*'),
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $files = $request->file('uploads');
        $files = is_array($files) ? array_values($files) : [];

        foreach ($files as $index => $file) {
            ImageUpload::checkDecoded($validator, $file, "uploads.{$index}");
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        foreach ($files as $file) {
            $this->photos->upload($listing, $file);
        }

        $listing->forceFill(['photos_custom' => true])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Фото для этого сайта сохранены.']);

        return back();
    }

    public function destroyPhoto(Site $site, SiteStockListing $listing, VehiclePhoto $photo): RedirectResponse
    {
        $this->scope->stockListing($site, $listing);
        $this->authorizeSite($site);
        Gate::authorize('editVehicles', $site);
        abort_unless($photo->site_stock_listing_id === $listing->id, 404);

        $this->photos->remove($photo);

        if (! $listing->photos()->exists()) {
            $listing->forceFill(['photos_custom' => false])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Фото удалено.']);

        return back();
    }

    public function resetPhotos(Site $site, SiteStockListing $listing): RedirectResponse
    {
        $this->scope->stockListing($site, $listing);
        $this->authorizeSite($site);
        Gate::authorize('editVehicles', $site);

        $listing->photos()->get()->each(fn (VehiclePhoto $photo) => $this->photos->remove($photo));
        $listing->forceFill(['photos_custom' => false])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Сайт снова показывает фото склада.']);

        return back();
    }

    private function authorizeSite(Site $site): void
    {
        $this->scope->site($site);
        abort_unless($site->site_type === SiteType::MultiPage, 404);
        Gate::authorize('viewVehicles', $site);
    }

    /**
     * @return array{editVehicles: bool, editPrices: bool}
     */
    private function can(Site $site): array
    {
        return [
            'editVehicles' => Gate::allows('editVehicles', $site),
            'editPrices' => Gate::allows('editPrices', $site),
        ];
    }

    /**
     * @return array{public_id: string, name: string}
     */
    private function site(Site $site): array
    {
        return ['public_id' => $site->public_id, 'name' => $site->name];
    }
}
