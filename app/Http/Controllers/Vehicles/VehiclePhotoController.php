<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\VehiclePhotos;
use App\Enums\MediaAngle;
use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteVehicle;
use App\Models\VehicleColor;
use App\Models\VehiclePhoto;
use App\Models\WorkspaceVehicle;
use App\Support\DesignerScope;
use App\Support\ImageUpload;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos of customer vehicles (ADR-010 §6) in the customer Catalog and on Sites: colours with
 * angle photos, the gallery, copies from the platform library and own uploads.
 */
class VehiclePhotoController extends Controller
{
    public function __construct(
        private WorkspaceContext $workspaceContext,
        private DesignerScope $scope,
        private VehiclePhotos $photos,
    ) {}

    public function show(VehiclePhoto $photo): StreamedResponse
    {
        abort_unless($photo->workspace_id === $this->workspaceContext->current()?->id, 404);

        if ($photo->site_vehicle_id !== null) {
            $site = SiteVehicle::query()->whereKey($photo->site_vehicle_id)->first()?->site;
            abort_unless($site instanceof Site && Gate::allows('viewVehicles', $site), 404);
        } else {
            abort_unless($photo->workspace_vehicle_id !== null && Gate::allows(WorkspacePermission::ManageWorkspaceVehicleLibrary->value), 404);
        }

        return Storage::disk(VehiclePhoto::DISK)->response($photo->path, basename($photo->path), [
            'Content-Type' => $photo->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    public function catalogUpload(Request $request, string $vehicle): RedirectResponse
    {
        return $this->upload($request, $this->catalogVehicle($vehicle));
    }

    public function siteUpload(Request $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        return $this->upload($request, $this->siteVehicle($site, $vehicle));
    }

    public function catalogLibrary(Request $request, string $vehicle): RedirectResponse
    {
        return $this->library($request, $this->catalogVehicle($vehicle));
    }

    public function siteLibrary(Request $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        return $this->library($request, $this->siteVehicle($site, $vehicle));
    }

    public function catalogColor(Request $request, string $vehicle): RedirectResponse
    {
        return $this->storeColor($request, $this->catalogVehicle($vehicle));
    }

    public function siteColor(Request $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        return $this->storeColor($request, $this->siteVehicle($site, $vehicle));
    }

    public function catalogUpdateColor(Request $request, string $vehicle, string $color): RedirectResponse
    {
        return $this->updateColor($request, $this->color($this->catalogVehicle($vehicle), $color));
    }

    public function siteUpdateColor(Request $request, Site $site, SiteVehicle $vehicle, string $color): RedirectResponse
    {
        return $this->updateColor($request, $this->color($this->siteVehicle($site, $vehicle), $color));
    }

    public function catalogDestroyColor(string $vehicle, string $color): RedirectResponse
    {
        $this->photos->removeColor($this->color($this->catalogVehicle($vehicle), $color));

        return back();
    }

    public function siteDestroyColor(Site $site, SiteVehicle $vehicle, string $color): RedirectResponse
    {
        $this->photos->removeColor($this->color($this->siteVehicle($site, $vehicle), $color));

        return back();
    }

    public function catalogDestroy(string $vehicle, string $photo): RedirectResponse
    {
        $this->photos->remove($this->photo($this->catalogVehicle($vehicle), $photo));

        return back();
    }

    public function siteDestroy(Site $site, SiteVehicle $vehicle, string $photo): RedirectResponse
    {
        $this->photos->remove($this->photo($this->siteVehicle($site, $vehicle), $photo));

        return back();
    }

    public function catalogAngle(Request $request, string $vehicle): RedirectResponse
    {
        return $this->angle($request, $this->catalogVehicle($vehicle));
    }

    public function siteAngle(Request $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        return $this->angle($request, $this->siteVehicle($site, $vehicle));
    }

    private function upload(Request $request, WorkspaceVehicle|SiteVehicle $vehicle): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ImageUpload::rules(),
            'color' => ['nullable', 'string', 'size:26'],
            'angle' => ['nullable', 'required_with:color', Rule::enum(MediaAngle::class)],
        ], [
            'files.required' => 'Выберите фото.',
            'files.max' => 'За один раз можно загрузить не больше 20 фото.',
            ...ImageUpload::messages('files.*'),
        ]);
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('files'), fn ($file): bool => $file instanceof UploadedFile));
        $validator = ValidatorFacade::make([], []);

        foreach ($files as $index => $file) {
            ImageUpload::checkDecoded($validator, $file, "files.{$index}");
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        $color = $request->filled('color') ? $this->color($vehicle, $request->string('color')->toString()) : null;
        $angle = $color === null ? null : MediaAngle::from($request->string('angle')->toString());

        if ($color !== null && count($files) !== 1) {
            throw ValidationException::withMessages(['files' => 'Для ракурса загрузите одно фото.']);
        }

        foreach ($files as $file) {
            $this->photos->upload($vehicle, $file, $color, $angle);
        }

        return back();
    }

    private function library(Request $request, WorkspaceVehicle|SiteVehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'colors' => ['nullable', 'array', 'max:30'],
            'colors.*' => ['string', 'size:26'],
            'gallery' => ['nullable', 'array', 'max:100'],
            'gallery.*' => ['string', 'size:26'],
        ]);
        $existing = $vehicle->colors()->pluck('name')->all();
        $colors = array_values(SeriesMediaSet::query()
            ->whereIn('public_id', array_map('strval', $data['colors'] ?? []))
            ->whereNotIn('name', $existing)
            ->get()
            ->map(fn (SeriesMediaSet $set): string => $set->public_id)
            ->all());
        $gallery = array_values(array_map('strval', $data['gallery'] ?? []));

        DB::transaction(fn () => $this->photos->copyFromLibrary($vehicle, $colors, $gallery));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Фото добавлены.']);

        return back();
    }

    private function storeColor(Request $request, WorkspaceVehicle|SiteVehicle $vehicle): RedirectResponse
    {
        $data = $this->colorData($request);
        $this->photos->addColor($vehicle, $data['name'], $data['swatch_hex']);

        return back();
    }

    private function updateColor(Request $request, VehicleColor $color): RedirectResponse
    {
        $color->update($this->colorData($request));

        return back();
    }

    private function angle(Request $request, WorkspaceVehicle|SiteVehicle $vehicle): RedirectResponse
    {
        $data = $request->validate(['primary_angle' => ['nullable', Rule::enum(MediaAngle::class)]]);
        $vehicle->update(['primary_angle' => $data['primary_angle'] ?? null]);

        return back();
    }

    /**
     * @return array{name: string, swatch_hex: string|null, sort_order?: int}
     */
    private function colorData(Request $request): array
    {
        $request->merge(['swatch_hex' => $request->filled('swatch_hex') ? mb_strtolower($request->string('swatch_hex')->toString()) : null]);

        /** @var array{name: string, swatch_hex: string|null, sort_order?: int} */
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'swatch_hex' => ['nullable', 'regex:'.SeriesMediaSet::SWATCH_PATTERN],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ], ['swatch_hex.regex' => 'Укажите цвет в формате #rrggbb.'], ['name' => 'Название цвета']);
    }

    private function catalogVehicle(string $publicId): WorkspaceVehicle
    {
        Gate::authorize(WorkspacePermission::ManageWorkspaceVehicleLibrary->value);
        $workspace = $this->workspaceContext->current() ?? abort(403);

        return $workspace->vehicleLibrary()->where('public_id', $publicId)->first() ?? abort(404);
    }

    private function siteVehicle(Site $site, SiteVehicle $vehicle): SiteVehicle
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('editVehicles', $site);

        return $vehicle;
    }

    private function color(WorkspaceVehicle|SiteVehicle $vehicle, string $publicId): VehicleColor
    {
        return $vehicle->colors()->where('public_id', $publicId)->first() ?? abort(404);
    }

    private function photo(WorkspaceVehicle|SiteVehicle $vehicle, string $publicId): VehiclePhoto
    {
        return $vehicle->photos()->where('public_id', $publicId)->first() ?? abort(404);
    }
}
