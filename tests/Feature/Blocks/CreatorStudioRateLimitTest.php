<?php

namespace Tests\Feature\Blocks;

use App\Enums\DeveloperPermission;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Creator Studio limiters are keyed by User + Block / Template, so Draft autosaves never consume
 * the publishing allowance of the same Block, nor the Draft allowance of another Block.
 */
class CreatorStudioRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private DeveloperProfile $profile;

    private BlockDefinition $blockA;

    private BlockDefinition $blockB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = DeveloperProfile::factory()->withPermissions()->create();
        $this->profile->permissions()->create(['permission' => DeveloperPermission::ApproveNativeBlocks]);
        $this->blockA = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'promo-a']);
        $this->blockB = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'promo-b']);
    }

    public function test_many_draft_saves_do_not_consume_the_publish_allowance(): void
    {
        // More autosaves than the publish limit (30/min) of the same Block.
        for ($revision = 0; $revision < 40; $revision++) {
            $this->actingAs($this->profile->user)->put(route('developer.blocks.draft', $this->blockA), [
                'revision' => $revision,
                'sources' => [
                    'html' => '<h2>{{ title }}</h2>',
                    'css' => "h2 { margin: {$revision}px; }",
                    'js' => '',
                    'schema' => '{"fields":[{"key":"title","type":"text","label":"Заголовок"}]}',
                ],
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($this->profile->user)->post(route('developer.blocks.publish', $this->blockA), ['revision' => 40])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.blocks.show', $this->blockA));
        $this->assertSame('1.0.0', BlockVersion::query()->sole()->version);
    }

    public function test_draft_allowance_is_per_block(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->assertNotSame(429, $this->draft($this->blockA)->getStatusCode());
        }

        $this->draft($this->blockA)->assertStatus(429);
        $this->assertNotSame(429, $this->draft($this->blockB)->getStatusCode());
        $this->assertNotSame(429, $this->actingAs($this->profile->user)
            ->post(route('developer.blocks.publish', $this->blockA), ['revision' => 0])
            ->getStatusCode());
    }

    public function test_publish_limiter_still_returns_429_past_its_own_threshold(): void
    {
        $publish = fn () => $this->actingAs($this->profile->user)->post(route('developer.blocks.publish', $this->blockA), ['revision' => 0]);

        for ($i = 0; $i < 30; $i++) {
            $this->assertNotSame(429, $publish()->getStatusCode());
        }

        $publish()->assertStatus(429);
        // Another Block keeps its own publishing allowance.
        $this->assertNotSame(429, $this->actingAs($this->profile->user)
            ->post(route('developer.blocks.publish', $this->blockB), ['revision' => 0])
            ->getStatusCode());
    }

    public function test_limits_are_per_user(): void
    {
        $other = DeveloperProfile::factory()->withPermissions()->create();
        $foreign = (string) Str::ulid();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->profile->user)->post(route('developer.blocks.publish', $foreign), ['revision' => 0]);
        }

        $this->actingAs($this->profile->user)->post(route('developer.blocks.publish', $foreign), ['revision' => 0])->assertStatus(429);
        $this->assertNotSame(429, $this->actingAs($other->user)->post(route('developer.blocks.publish', $foreign), ['revision' => 0])->getStatusCode());
    }

    public function test_developer_and_platform_authoring_use_the_same_named_limiters(): void
    {
        $expected = [
            'developer.blocks.store' => 'throttle:block-create',
            'platform.blocks.store' => 'throttle:block-create',
            'developer.blocks.draft' => 'throttle:block-draft',
            'platform.blocks.draft' => 'throttle:block-draft',
            'developer.blocks.publish' => 'throttle:block-publish',
            'platform.blocks.publish' => 'throttle:block-publish',
            'developer.templates.store' => 'throttle:template-create',
            'platform.templates.store' => 'throttle:template-create',
            'studio.templates.publish' => 'throttle:template-publish',
        ];

        foreach ($expected as $name => $limiter) {
            $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];
            $this->assertContains($limiter, $middleware, $name);
            $this->assertEmpty(preg_grep('/^throttle:\d/', $middleware), "{$name} must not use a numeric throttle");
        }
    }

    /**
     * Throttling runs before validation, so an invalid payload still counts as an attempt.
     *
     * @return TestResponse<Response>
     */
    private function draft(BlockDefinition $block): TestResponse
    {
        return $this->actingAs($this->profile->user)->put(route('developer.blocks.draft', $block), ['revision' => 0]);
    }
}
