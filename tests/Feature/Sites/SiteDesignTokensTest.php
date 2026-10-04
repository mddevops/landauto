<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SiteDesignTokens;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteDesignTokensTest extends TestCase
{
    use RefreshDatabase;

    private const TOKENS = [
        'primary_color' => '#C8102E',
        'secondary_color' => '#1f2937',
        'font_family' => 'serif',
        'radius' => 'large',
        'container' => 'wide',
        'button_style' => 'outline',
    ];

    public function test_designer_saves_tokens_and_designer_receives_them(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Designer);

        $this->as($user, $workspace)->get(route('sites.designer', $site))
            ->assertInertia(fn (Assert $page) => $page->where('design', SiteDesignTokens::DEFAULTS));

        $this->as($user, $workspace)->from(route('sites.designer', $site))
            ->patch(route('sites.design.update', $site), [...self::TOKENS, 'custom_css' => 'body{}'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.designer', $site));

        $expected = [...self::TOKENS, 'primary_color' => '#c8102e'];
        $this->assertSame($expected, $site->fresh()?->design_tokens);
        $this->as($user, $workspace)->get(route('sites.designer', $site))
            ->assertInertia(fn (Assert $page) => $page->where('design', $expected));
    }

    public function test_invalid_tokens_are_rejected_with_russian_errors(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Owner);

        $this->as($user, $workspace)->patch(route('sites.design.update', $site), [
            ...self::TOKENS,
            'primary_color' => 'red',
            'secondary_color' => '#12345',
            'radius' => 'huge',
            'button_style' => null,
        ])->assertSessionHasErrors([
            'primary_color' => 'Укажите цвет в формате #RRGGBB.',
            'secondary_color',
            'radius',
            'button_style',
        ]);

        $this->assertNull($site->fresh()?->design_tokens);
    }

    public function test_stored_garbage_falls_back_to_defaults(): void
    {
        $this->assertSame(
            [...SiteDesignTokens::DEFAULTS, 'radius' => 'small'],
            SiteDesignTokens::resolve(['radius' => 'small', 'container' => 'huge', 'primary_color' => 'url(x)', 'extra' => '1']),
        );
    }

    public function test_content_editor_is_forbidden_and_foreign_site_is_not_found(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::ContentEditor);
        $foreign = Site::factory()->create();

        $this->as($user, $workspace)->patch(route('sites.design.update', $site), self::TOKENS)->assertForbidden();
        $this->as($user, $workspace)->patch(route('sites.design.update', $foreign), [])->assertNotFound();
        $this->assertNull($site->fresh()?->design_tokens);
        $this->assertNull($foreign->fresh()?->design_tokens);
    }

    /**
     * @return array{User, Workspace, Site}
     */
    private function siteFor(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, $role);
        $site = Site::factory()->for($workspace)->create();
        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($site)->save();

        return [$user, $workspace, $site];
    }

    private function as(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);
    }
}
