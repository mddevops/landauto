<?php

namespace App\Http\Controllers\Platform;

use App\Catalog\AutoCatalog;
use App\Catalog\CatalogPresenter;
use App\Enums\MediaAngle;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only platform browser of the auto.ru catalog (ADR-010), page by page as on auto.ru:
 * marks → models → generations with series → series with photos, modifications and trims.
 * The catalog team fills the data; the platform manages only the Series photo library here.
 */
class CatalogBrowserController extends Controller
{
    public function __construct(
        private AutoCatalog $catalog,
        private CatalogPresenter $presenter,
    ) {}

    public function index(): Response
    {
        return Inertia::render('platform/catalog/index', [
            'marks' => $this->presenter->marks(),
        ]);
    }

    public function mark(string $mark): Response
    {
        $mark = $this->findMark($mark);

        return Inertia::render('platform/catalog/mark', [
            'mark' => [...$this->presenter->mark($mark), 'country' => $mark->country],
            'models' => $this->presenter->models($mark),
        ]);
    }

    public function model(string $mark, string $model): Response
    {
        $mark = $this->findMark($mark);
        $model = $this->findModel($mark, $model);

        return Inertia::render('platform/catalog/model', [
            'mark' => $this->presenter->mark($mark),
            'model' => [
                'url' => $model->url,
                'name' => $model->name,
                'years' => CatalogPresenter::years($model->year_from, $model->year_to),
                'class' => $model->class,
            ],
            'generations' => $this->presenter->generations($model, withMediaCounts: true),
        ]);
    }

    public function series(Request $request, string $series): Response
    {
        $row = $this->catalog->series($series) ?? abort(404);

        return Inertia::render('platform/catalog/series', [
            'series' => $this->presenter->series($row),
            'library' => $this->presenter->library((string) $row->ref(), activeOnly: false),
            'trims' => fn (): array => $this->presenter->trims($row),
            'characteristics' => fn (): array => $this->presenter->characteristics($row, $request->query('modification')),
            'options' => fn (): array => $this->presenter->options($row, $request->query('equipment')),
            'angles' => MediaAngle::options(),
            'can' => ['manageMedia' => Gate::allows(PlatformPermission::ManageCatalogMedia->value)],
        ]);
    }

    private function findMark(string $url): AutoMark
    {
        return $this->catalog->mark($url) ?? abort(404);
    }

    private function findModel(AutoMark $mark, string $url): AutoModel
    {
        return $this->catalog->model($mark, $url) ?? abort(404);
    }
}
