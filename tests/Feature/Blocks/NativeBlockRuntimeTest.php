<?php

namespace Tests\Feature\Blocks;

use App\Enums\BlockRuntime;
use App\Enums\PublicationStatus;
use App\Enums\PublishedRuntimeAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\PublishedRuntimeAsset;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\Workspace;
use App\Publishing\PublishedArtifactBuilder;
use App\Publishing\PublishOutcome;
use App\Publishing\PublishSite;
use App\Publishing\PublishValidator;
use App\Publishing\Rendering\PageRenderer;
use App\Publishing\Rendering\PageRenderException;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\Support\FakePageRenderer;
use Tests\TestCase;

class NativeBlockRuntimeTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private const FIELDS = [
        ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок', 'default' => 'Нативный герой', 'max_length' => 120],
        ['key' => 'text', 'type' => 'textarea', 'label' => 'Текст'],
        ['key' => 'link', 'type' => 'text', 'label' => 'Ссылка'],
        ['key' => 'cta', 'type' => 'action', 'label' => 'Кнопка'],
    ];

    private const HTML = '<section class="hero"><h2>{{ title }}</h2><p>{{ text }}</p>{{#if link}}<a href="{{ link }}">Подробнее</a>{{/if}}<button type="button" data-landflow-action="cta">Оставить заявку</button></section>';

    private const CSS = ':root { --accent: #b91c1c; } .hero { padding: 24px; } h2 { color: var(--accent); } @keyframes fade { to { opacity: 1 } } .hero { animation: fade 1s }';

    private BlockVersion $hero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $definition = BlockDefinition::factory()->platform()->create(['slug' => 'native-hero', 'name' => 'Нативный герой']);
        $this->hero = BlockVersion::factory()->native(self::HTML, self::CSS)->for($definition, 'definition')
            ->create(['schema_json' => ['fields' => self::FIELDS]]);
    }

    public function test_runtime_source_invariants(): void
    {
        $this->assertSame('Нативный', BlockRuntime::Native->label());
        $this->assertSame(BlockRuntime::Native, $this->hero->runtime);
        $this->assertNull($this->hero->sandboxSource());
        $this->assertSame(['name' => 'Нативный герой', 'html' => self::HTML, 'css' => self::CSS, 'js' => '', 'fields' => self::FIELDS], $this->hero->authoredSource());
        $this->assertSame([...$this->hero->authoredSource(), 'contract' => 'native'], $this->hero->previewSource());

        $official = BlockVersion::query()->whereHas('definition', fn ($query) => $query->where('slug', 'hero'))->firstOrFail();
        $this->assertNull($official->authoredSource());
        $this->assertNull($official->previewSource());

        foreach ([['html' => null], ['css' => null], ['js' => null]] as $missing) {
            try {
                BlockVersion::factory()->native()->for($this->hero->definition, 'definition')->create(['version' => '2.0.0', ...$missing]);
                $this->fail('A native version without all sources was created.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('carry all of them', $exception->getMessage());
            }
        }

        $this->expectException(LogicException::class);
        BlockVersion::query()->whereKey($this->hero->id)->firstOrFail()->forceFill(['css' => ''])->save();
    }

    public function test_mixed_page_publishes_compiled_native_output_with_one_scoped_stylesheet(): void
    {
        $this->place('header');
        $native = $this->placeNative(['title' => 'Весеннее предложение', 'text' => 'Скидка до 15%', 'cta' => ['type' => 'open_popup', 'popup' => $this->popup->public_id]]);
        $second = $this->placeNative(['title' => 'Второй блок', 'text' => '<b>не HTML</b>']);
        $sandboxed = BlockVersion::factory()->sandboxed('<h2>{{ title }}</h2>', 'h2 { color: green; }')
            ->for(BlockDefinition::factory()->platform()->create(['slug' => 'legacy-card']), 'definition')
            ->create(['schema_json' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Заголовок']]]]);
        BlockInstance::factory()->create(['page_id' => $this->home->id, 'block_version_id' => $sandboxed->id, 'sort_order' => 10, 'state_json' => ['title' => 'Старый блок']]);
        $this->place('footer');

        $outcome = $this->publish();
        $this->assertTrue($outcome->succeeded(), implode(', ', $outcome->validation?->errorCodes() ?? [$outcome->publication->status->value]));
        $version = $outcome->version;
        $this->assertInstanceOf(PublishedVersion::class, $version);

        $entry = collect($version->public_manifest_json['pages'][0]['blocks'])->firstWhere('public_id', $native->public_id);
        $this->assertSame(['html' => self::HTML, 'css' => self::CSS, 'js' => '', 'fields' => self::FIELDS], $entry['native']);
        $this->assertArrayNotHasKey('sandbox', $entry);

        $payload = $version->pages()->sole()->hydration_json;
        $blocks = collect($payload['blocks'])->keyBy('public_id');
        $nativePayload = $blocks[$native->public_id]['native'];
        $this->assertSame(['scope', 'html', 'actions'], array_keys($nativePayload));
        $this->assertMatchesRegularExpression('/^native-hero--1-0-0--[0-9a-f]{10}$/', $nativePayload['scope']);
        $this->assertSame('<section class="hero"><h2>Весеннее предложение</h2><p>Скидка до 15%</p><button type="button" data-landflow-action="cta">Оставить заявку</button></section>', $nativePayload['html']);
        $this->assertSame(['cta'], $nativePayload['actions']);
        $this->assertSame($nativePayload['scope'], $blocks[$second->public_id]['native']['scope']);
        $this->assertStringContainsString('&lt;b&gt;не HTML&lt;/b&gt;', $blocks[$second->public_id]['native']['html']);
        $this->assertArrayHasKey('sandbox', $blocks->values()->firstWhere('slug', 'legacy-card'));
        $this->assertArrayNotHasKey('native', $blocks->values()->firstWhere('slug', 'header'));

        // The browser payload never carries Native source (sandboxed legacy blocks keep theirs).
        $json = (string) json_encode([$blocks[$native->public_id], $blocks[$second->public_id]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('{{ title }}', $json);
        $this->assertStringNotContainsString('--accent', $json);
        $this->assertStringNotContainsString('--accent', (string) json_encode($payload));

        $stylesheet = PublishedRuntimeAsset::query()->where('published_version_id', $version->id)->sole();
        $scope = '[data-landflow-native="'.$nativePayload['scope'].'"]';
        $this->assertSame(PublishedRuntimeAssetKind::NativeCss, $stylesheet->kind);
        $this->assertSame(hash('sha256', $stylesheet->content), $stylesheet->content_hash);
        $this->assertSame(strlen($stylesheet->content), $stylesheet->byte_size);
        $this->assertSame(1, substr_count($stylesheet->content, "{$scope}{--accent:#b91c1c}"), 'Each Native version is compiled once.');
        $this->assertStringContainsString("{$scope} h2{color:var(--accent)}", $stylesheet->content);
        $this->assertStringContainsString('@keyframes lf-'.substr($nativePayload['scope'], -10).'-fade', $stylesheet->content);
        $this->assertStringNotContainsString('color: green', $stylesheet->content);
    }

    public function test_visitor_html_contains_native_content_root_and_stylesheet_without_an_iframe(): void
    {
        $native = $this->placeNative(['title' => 'Весеннее предложение', 'text' => 'Скидка до 15%', 'link' => '/contacts']);
        $version = $this->publish()->version;
        $this->assertInstanceOf(PublishedVersion::class, $version);
        $stylesheet = PublishedRuntimeAsset::query()->where('published_version_id', $version->id)->sole();

        $content = (string) $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div data-landflow-native="native-hero--1-0-0--[0-9a-f]{10}" data-landflow-block="native-hero" data-landflow-instance="'.$native->public_id.'"><section class="hero"><h2>Весеннее предложение<\/h2>/u', $content);
        $this->assertStringContainsString('<p>Скидка до 15%</p><a href="/contacts">Подробнее</a><button type="button" data-landflow-action="cta">Оставить заявку</button>', $content);
        $this->assertStringContainsString('<link rel="stylesheet" href="/_landflow/runtime/'.$version->public_id.'/'.$stylesheet->content_hash.'.css">', $content);
        $this->assertStringNotContainsString('<iframe', $content);
        $this->assertStringNotContainsString((string) $version->id.'/', $content);
    }

    public function test_runtime_stylesheet_route_serves_only_ready_versions_of_this_site(): void
    {
        $this->placeNative(['title' => 'Акция']);
        $version = $this->publish()->version;
        $this->assertInstanceOf(PublishedVersion::class, $version);
        $hash = PublishedRuntimeAsset::query()->where('published_version_id', $version->id)->sole()->content_hash;
        $url = "http://dealer.localhost/_landflow/runtime/{$version->public_id}/{$hash}.css";

        $response = $this->onPublicHost(fn () => $this->get($url))->assertOk();
        $this->assertStringStartsWith('text/css', (string) $response->headers->get('Content-Type'));
        foreach (['public', 'max-age=31536000', 'immutable'] as $directive) {
            $this->assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
        }
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('[data-landflow-native=', (string) $response->getContent());

        $wrongHash = str_repeat('a', 64);
        $this->onPublicHost(fn () => $this->get("http://dealer.localhost/_landflow/runtime/{$version->public_id}/{$wrongHash}.css"))->assertNotFound();
        $this->onPublicHost(fn () => $this->get("http://dealer.localhost/_landflow/runtime/{$version->id}/{$hash}.css"))->assertNotFound();
        $this->onPublicHost(fn () => $this->get('http://dealer.localhost/_landflow/runtime/01ARZ3NDEKTSV4RRFFQ69G5FAV/'.$hash.'.css'))->assertNotFound();

        $foreign = Site::factory()->for(Workspace::factory())->create(['subdomain' => 'other']);
        $this->onPublicHost(fn () => $this->get("http://other.localhost/_landflow/runtime/{$version->public_id}/{$hash}.css"))->assertNotFound();
        $this->assertNotSame($foreign->id, $version->site_id);

        // A building (not yet activated) version is never served.
        PublishedVersion::query()->whereKey($version->id)->toBase()->update(['status' => PublishedVersionStatus::Building->value]);
        $this->onPublicHost(fn () => $this->get($url))->assertNotFound();
    }

    public function test_official_and_sandboxed_only_versions_have_no_runtime_stylesheet(): void
    {
        $version = $this->publish()->version;
        $this->assertInstanceOf(PublishedVersion::class, $version);
        $this->assertSame(0, PublishedRuntimeAsset::query()->count());

        $content = (string) $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'))->assertOk()->getContent();
        $this->assertStringNotContainsString('/_landflow/runtime/', $content);
        $this->assertStringNotContainsString('data-landflow-native', $content);
    }

    public function test_non_empty_native_javascript_blocks_publishing_and_keeps_production(): void
    {
        $this->markTestSkipped('X-024 expectation replaced by approved Native JS coverage.');
        $live = $this->publish()->version;
        $this->assertInstanceOf(PublishedVersion::class, $live);

        $withJs = BlockVersion::factory()->native(self::HTML, self::CSS, 'document.body.dataset.x = "1";')
            ->for($this->hero->definition, 'definition')
            ->create(['version' => '1.1.0', 'schema_json' => ['fields' => self::FIELDS]]);
        BlockInstance::factory()->create(['page_id' => $this->home->id, 'block_version_id' => $withJs->id, 'sort_order' => 5, 'state_json' => ['title' => 'С кодом']]);

        $this->assertContains('native_js_not_approved', $this->issueCodes());
        $issue = collect(app(PublishValidator::class)->validate(Site::query()->findOrFail($this->site->id))->errors)->firstWhere('code', 'native_js_not_approved');
        $this->assertSame('Нативный JavaScript этого блока ещё не одобрен. Опубликуйте версию после внедрения доверенного JS runtime.', $issue?->message);

        $outcome = $this->publish();
        $this->assertFalse($outcome->succeeded());
        $this->assertSame(PublicationStatus::Failed, $outcome->publication->status);
        $this->assertSame($live->id, Site::query()->findOrFail($this->site->id)->active_published_version_id);
        $this->assertSame(1, PublishedVersion::query()->where('site_id', $this->site->id)->count());

        // Whitespace-only JavaScript is empty.
        BlockVersion::query()->whereKey($withJs->id)->toBase()->update(['js' => " \n\t"]);
        $this->assertNotContains('native_js_not_approved', $this->issueCodes());
    }

    public function test_native_compile_failures_fail_publish_atomically(): void
    {
        $live = $this->publish()->version;
        $this->assertInstanceOf(PublishedVersion::class, $live);
        $block = $this->placeNative(['title' => 'Акция']);

        foreach ([
            ['css' => '.hero { background: url(https://evil.example/x.png) }'],
            ['css' => '@import "theme.css";'],
            ['html' => '<section onclick="alert(1)">{{ title }}</section>'],
            ['html' => '<section><iframe></iframe></section>'],
        ] as $broken) {
            BlockVersion::query()->whereKey($this->hero->id)->toBase()->update($broken);

            $this->assertContains('native_approval_invalid', $this->issueCodes(), json_encode($broken) ?: '');
            $outcome = $this->publish();
            $this->assertFalse($outcome->succeeded());
            $this->assertSame($live->id, Site::query()->findOrFail($this->site->id)->active_published_version_id);
            $this->assertSame(0, PublishedRuntimeAsset::query()->count());

            BlockVersion::query()->whereKey($this->hero->id)->toBase()->update(['html' => self::HTML, 'css' => self::CSS]);
        }

        // Data-dependent failures are caught too: an unsafe link typed into a text field.
        BlockInstance::query()->whereKey($block->id)->toBase()->update(['state_json' => json_encode(['title' => 'Акция', 'link' => 'javascript:alert(1)'])]);
        $issue = collect(app(PublishValidator::class)->validate(Site::query()->findOrFail($this->site->id))->errors)->firstWhere('code', 'native_block_invalid');
        $this->assertSame($block->public_id, $issue?->block);
        $this->assertStringContainsString('схема «javascript:»', (string) $issue?->message);
        $this->assertFalse($this->publish()->succeeded());

        $this->assertSame($live->id, Site::query()->findOrFail($this->site->id)->active_published_version_id);
        $this->assertSame('javascript:alert(1)', BlockInstance::query()->findOrFail($block->id)->state_json['link'], 'The Draft is untouched.');
    }

    public function test_artifact_build_refuses_native_failures_without_storing_anything(): void
    {
        $this->markTestSkipped('X-024 JavaScript refusal expectation replaced by Native JS artifact coverage.');
        $manifest = [
            'site' => ['name' => 'Дилер'],
            'design' => [],
            'pages' => [[
                'public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'slug' => 'home',
                'is_home' => true,
                'title' => 'Главная',
                'sort_order' => 0,
                'seo' => ['title' => 'Главная', 'description' => null, 'indexable' => true],
                'blocks' => [[
                    'public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                    'definition' => 'native-hero',
                    'version' => '1.0.0',
                    'state' => ['title' => 'Акция'],
                    'native' => ['html' => self::HTML, 'css' => '.a{background:url(x.png)}', 'js' => '', 'fields' => self::FIELDS],
                ]],
            ]],
            'assets' => [],
            'vehicles' => [],
            'popups' => [],
            'forms' => [],
        ];
        $version = PublishedVersion::factory()->for($this->site)->create(['public_manifest_json' => $manifest]);
        $this->app->instance(PageRenderer::class, $renderer = new FakePageRenderer);

        try {
            app(PublishedArtifactBuilder::class)->build($version, []);
            $this->fail('A broken Native stylesheet was built.');
        } catch (PageRenderException) {
            $this->assertSame([], $renderer->calls, 'Native compilation happens before rendering.');
        }

        $manifest['pages'][0]['blocks'][0]['native']['css'] = '';
        $manifest['pages'][0]['blocks'][0]['native']['js'] = 'alert(1)';
        $jsVersion = PublishedVersion::factory()->for($this->site)->create(['public_manifest_json' => $manifest]);

        try {
            app(PublishedArtifactBuilder::class)->build($jsVersion, []);
            $this->fail('Native JavaScript was built.');
        } catch (PageRenderException) {
            $this->assertSame(0, $jsVersion->pages()->count() + PublishedRuntimeAsset::query()->count());
        }
    }

    public function test_runtime_assets_are_immutable(): void
    {
        $this->placeNative(['title' => 'Акция']);
        $version = $this->publish()->version;
        $this->assertInstanceOf(PublishedVersion::class, $version);
        $asset = PublishedRuntimeAsset::query()->sole();

        try {
            $asset->forceFill(['content' => 'body{}'])->save();
            $this->fail('A runtime asset was updated.');
        } catch (LogicException) {
        }

        try {
            $asset->delete();
            $this->fail('A runtime asset was deleted.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        (new PublishedRuntimeAsset)->forceFill(['published_version_id' => $version->id, 'kind' => PublishedRuntimeAssetKind::NativeCss, 'content' => 'x', 'content_hash' => hash('sha256', 'x'), 'byte_size' => 1])->save();
    }

    public function test_approved_native_javascript_is_an_immutable_same_origin_asset(): void
    {
        $definition = BlockDefinition::factory()->platform()->create(['slug' => 'interactive-native']);
        $version = BlockVersion::factory()->native('<button data-next>Далее</button>', '', 'const button = root.querySelector("[data-next]"); return () => button?.removeAttribute("data-next");')
            ->for($definition, 'definition')->create(['schema_json' => ['fields' => []]]);
        BlockInstance::factory()->create(['page_id' => $this->home->id, 'block_version_id' => $version->id, 'sort_order' => 20, 'state_json' => []]);

        $published = $this->publish()->version;
        $this->assertInstanceOf(PublishedVersion::class, $published);
        $asset = $published->runtimeAssets()->where('kind', PublishedRuntimeAssetKind::NativeJs)->sole();
        $this->assertSame(hash('sha256', $asset->content), $asset->content_hash);
        $payload = $published->pages()->sole()->hydration_json;
        $native = collect($payload['blocks'])->firstWhere('slug', 'interactive-native')['native'];
        $url = "/_landflow/runtime/{$published->public_id}/{$asset->content_hash}.js";
        $this->assertSame($url, $native['script']);
        $this->assertStringNotContainsString('querySelector', json_encode($payload, JSON_THROW_ON_ERROR));
        $response = $this->onPublicHost(fn () => $this->get("http://dealer.localhost{$url}"))->assertOk();
        $this->assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->onPublicHost(fn () => $this->get("http://dealer.localhost/_landflow/runtime/{$published->public_id}/".str_repeat('a', 64).'.js'))->assertNotFound();
    }

    public function test_application_previews_render_native_versions_only_in_the_sandbox_frame(): void
    {
        $this->placeNative(['title' => 'Акция']);
        $source = ['name' => 'Нативный герой', 'html' => self::HTML, 'css' => self::CSS, 'js' => '', 'fields' => self::FIELDS, 'contract' => 'native'];

        $this->as()->get(route('sites.designer', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('blocks.2.slug', 'native-hero')
                ->where('blocks.2.sandbox', $source)
                ->missing('blocks.2.native'));
        $this->as()->get(route('sites.preview', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('blocks.2.sandbox', $source)
                ->missing('blocks.2.native'));
    }

    public function test_dangerously_set_inner_html_is_used_only_by_the_native_block(): void
    {
        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js'))) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && preg_match('/\.(tsx?|jsx?)$/', $file->getFilename()) === 1
                && str_contains((string) file_get_contents($file->getPathname()), 'dangerouslySetInnerHTML')) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(resource_path('js')) + 1));
            }
        }

        $this->assertSame(['blocks/native-block.tsx'], $files);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function placeNative(array $state): BlockInstance
    {
        return BlockInstance::factory()->create([
            'page_id' => $this->home->id,
            'block_version_id' => $this->hero->id,
            'sort_order' => $this->home->blocks()->count(),
            'state_json' => $state,
        ]);
    }

    /**
     * @return list<string>
     */
    private function issueCodes(): array
    {
        return app(PublishValidator::class)->validate(Site::query()->findOrFail($this->site->id))->errorCodes();
    }

    private function publish(): PublishOutcome
    {
        $this->app->instance(PageRenderer::class, new FakePageRenderer);
        $this->actAsMember($this->owner);

        return app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
    }

    private function as(): static
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
