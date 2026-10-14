<?php

namespace Tests\Feature\Blocks;

use App\Enums\BlockRuntime;
use App\Enums\DeveloperPermission;
use App\Enums\PlatformRole;
use App\Models\BlockDefinition;
use App\Models\BlockDraft;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BlockPublishingTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEMA = '{"fields":[{"key":"title","type":"text","label":"Заголовок"}]}';

    private DeveloperProfile $profile;

    private BlockDefinition $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = DeveloperProfile::factory()->withPermissions()->create();
        $this->profile->permissions()->create(['permission' => DeveloperPermission::ApproveNativeBlocks]);
        $this->block = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'promo']);
    }

    /**
     * @param  array<string, string>  $sources
     */
    private function saveDraft(User $user, BlockDefinition $block, int $revision, array $sources = [], string $scope = 'developer'): void
    {
        $this->actingAs($user)->put(route("{$scope}.blocks.draft", $block), ['revision' => $revision, 'sources' => [
            'html' => '<h2>{{ title }}</h2>',
            'css' => 'h2 { color: red; }',
            'js' => '',
            'schema' => self::SCHEMA,
            ...$sources,
        ]])->assertSessionHasNoErrors();
    }

    /**
     * @return TestResponse<Response>
     */
    private function publish(User $user, BlockDefinition $block, int $revision, string $scope = 'developer'): TestResponse
    {
        return $this->actingAs($user)->post(route("{$scope}.blocks.publish", $block), ['revision' => $revision]);
    }

    public function test_studio_publish_creates_native_versions_after_approval(): void
    {
        $user = $this->profile->user;
        $this->saveDraft($user, $this->block, 0);

        $this->actingAs($user)->post(route('developer.blocks.publish', $this->block), ['revision' => 1, 'runtime' => 'native'])
            ->assertSessionHasNoErrors();

        $this->assertSame(BlockRuntime::Native, BlockVersion::query()->sole()->runtime);
        $this->assertSame(1, BlockVersion::query()->where('runtime', BlockRuntime::Native->value)->count());
    }

    public function test_author_approves_the_saved_draft_as_an_immutable_native_version(): void
    {
        Log::spy();
        $user = $this->profile->user;
        $this->saveDraft($user, $this->block, 0, ['js' => 'return () => {};']);

        $this->publish($user, $this->block, 1)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.blocks.show', $this->block));

        $version = BlockVersion::query()->sole();
        $this->assertSame(['1.0.0', BlockRuntime::Native], [$version->version, $version->runtime]);
        $this->assertSame(['<h2>{{ title }}</h2>', 'h2 { color: red; }', 'return () => {};'], [$version->html, $version->css, $version->js]);
        $this->assertSame(json_decode(self::SCHEMA, true), $version->schema_json);
        $this->assertSame($user->id, $version->published_by_user_id);
        Log::shouldHaveReceived('info')->with('developer.block_published', Mockery::on(fn (array $context): bool => $context['block'] === $this->block->public_id
            && $context['version'] === '1.0.0' && $context['developer_profile'] === $this->profile->public_id
            && $context['actor_user_id'] === $user->id && preg_match('/^[a-f0-9]{64}$/', $context['source_hash']) === 1));

        $this->actingAs($user)->get(route('developer.blocks.show', $this->block))
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.versions_count', 1)
                ->where('publishBlockedReason', null)
                ->has('versions', 1)
                ->where('versions.0.version', '1.0.0')
                ->where('versions.0.runtime_label', 'Нативный')
                ->missing('versions.0.html')
                ->missing('versions.0.published_by_user_id'));
    }

    public function test_versions_increment_semantically_and_earlier_versions_never_change(): void
    {
        $user = $this->profile->user;
        $this->saveDraft($user, $this->block, 0);
        $this->publish($user, $this->block, 1)->assertSessionHasNoErrors();

        // Unchanged Draft: nothing new to publish.
        $this->publish($user, $this->block, 1)->assertSessionHasErrors(['publish' => 'Изменений с версии 1.0.0 нет.']);

        // Source-only change: patch. The Draft keeps changing; the published version does not.
        $this->saveDraft($user, $this->block, 1, ['css' => 'h2 { color: blue; }']);
        $this->publish($user, $this->block, 2)->assertSessionHasNoErrors();

        // Added field: minor.
        $this->saveDraft($user, $this->block, 2, ['schema' => '{"fields":[{"key":"title","type":"text","label":"Заголовок"},{"key":"lead","type":"textarea","label":"Текст"}]}']);
        $this->publish($user, $this->block, 3)->assertSessionHasNoErrors();

        // Removed field: major.
        $this->saveDraft($user, $this->block, 3, ['html' => '<p>{{ lead }}</p>', 'schema' => '{"fields":[{"key":"lead","type":"textarea","label":"Текст"}]}']);
        $this->publish($user, $this->block, 4)->assertSessionHasNoErrors();

        // Autosave after publishing never publishes.
        $this->saveDraft($user, $this->block, 4, ['css' => 'p { margin: 0; }']);

        $this->assertSame(['1.0.0', '1.0.1', '1.1.0', '2.0.0'], BlockVersion::query()->orderBy('id')->pluck('version')->all());
        $first = BlockVersion::query()->where('version', '1.0.0')->sole();
        $this->assertSame(['<h2>{{ title }}</h2>', 'h2 { color: red; }'], [$first->html, $first->css]);
        $this->assertSame('p { margin: 0; }', BlockDraft::query()->sole()->css);

        $this->expectException(LogicException::class);
        $first->update(['version' => '9.9.9']);
    }

    public function test_failed_checks_stale_or_missing_drafts_publish_nothing(): void
    {
        $user = $this->profile->user;

        $this->publish($user, $this->block, 0)->assertSessionHasErrors(['publish' => 'Сначала сохраните черновик блока.']);

        $this->saveDraft($user, $this->block, 0, ['html' => "<h2>{{ title }}</h2>\n<script>alert(1)</script>", 'css' => '@import "x.css";']);
        $this->publish($user, $this->block, 1)
            ->assertSessionHasErrors(['publish' => 'Публикация остановлена: исправьте проблемы из панели «Проверки перед публикацией» (2).']);

        $this->saveDraft($user, $this->block, 1);
        $this->publish($user, $this->block, 1)
            ->assertSessionHasErrors(['publish' => 'Черновик изменился после последней проверки. Обновите страницу и опубликуйте снова.']);
        $this->publish($user, $this->block, -1)->assertSessionHasErrors('revision');

        $this->assertSame(0, BlockVersion::query()->count());
    }

    public function test_foreign_suspended_and_unpermitted_authors_cannot_publish(): void
    {
        $user = $this->profile->user;
        $this->saveDraft($user, $this->block, 0);

        $other = DeveloperProfile::factory()->withPermissions()->create();
        $this->publish($other->user, $this->block, 1)->assertNotFound();

        $superAdmin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $superAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
        $this->publish($superAdmin, $this->block, 1, 'platform')->assertNotFound();

        $this->profile->permissions()->delete();
        $this->publish($user, $this->block, 1)->assertForbidden();

        $this->assertSame(0, BlockVersion::query()->count());
    }

    public function test_super_admin_publishes_platform_blocks_but_never_over_an_official_renderer(): void
    {
        Log::spy();
        $superAdmin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $superAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
        $platform = BlockDefinition::factory()->platform()->create();
        $this->saveDraft($superAdmin, $platform, 0, [], 'platform');
        $this->publish($superAdmin, $platform, 1, 'platform')->assertSessionHasNoErrors();
        $this->assertSame(BlockRuntime::Native, $platform->versions()->sole()->runtime);
        Log::shouldHaveReceived('info')->with('platform.block_published', Mockery::any())->once();
        $this->publish($this->profile->user, $platform, 1, 'platform')->assertForbidden();

        $instance = BlockInstance::factory()->create();
        $official = $instance->version->definition;
        $before = [$instance->version->fresh()?->toArray(), $instance->fresh()?->toArray()];
        $this->saveDraft($superAdmin, $official, 0, [], 'platform');

        $this->actingAs($superAdmin)->get(route('platform.blocks.show', $official))
            ->assertInertia(fn (Assert $page) => $page->where('publishBlockedReason', 'Этот блок отображается встроенным компонентом Landflow и не публикуется из студии.'));
        $this->publish($superAdmin, $official, 1, 'platform')
            ->assertSessionHasErrors(['publish' => 'Этот блок отображается встроенным компонентом Landflow и не публикуется из студии.']);
        $this->assertSame($before, [$instance->version->fresh()?->toArray(), $instance->fresh()?->toArray()]);
        $this->assertSame(1, $official->versions()->count());
    }

    public function test_version_sources_must_match_the_runtime(): void
    {
        $this->expectException(LogicException::class);

        BlockVersion::factory()->create(['html' => '<p>x</p>']);
    }
}
