<?php

namespace Tests\Feature\Sites;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class SiteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_receives_an_immutable_public_id(): void
    {
        $site = Site::factory()->create();

        $this->assertTrue(Str::isUlid($site->public_id));

        $site->public_id = (string) Str::ulid();

        $this->expectException(LogicException::class);
        $site->save();
    }

    public function test_route_binding_uses_public_id_and_rejects_numeric_id(): void
    {
        Route::middleware('web')->get(
            '/_site-binding/{site}',
            fn (Site $site) => response()->json(['public_id' => $site->public_id]),
        );
        $site = Site::factory()->create();

        $this->get("/_site-binding/{$site->public_id}")
            ->assertOk()
            ->assertJson(['public_id' => $site->public_id]);
        $this->get("/_site-binding/{$site->id}")->assertNotFound();
        $this->assertSame('public_id', $site->getRouteKeyName());
    }

    public function test_workspace_and_site_relations_preserve_tenant_ownership(): void
    {
        $workspace = Workspace::factory()->create();
        $foreignWorkspace = Workspace::factory()->create();
        $site = Site::factory()->for($workspace)->create();
        $foreignSite = Site::factory()->for($foreignWorkspace)->create();

        $this->assertTrue($site->workspace->is($workspace));
        $this->assertTrue($workspace->sites->contains($site));
        $this->assertFalse($workspace->sites->contains($foreignSite));
    }

    public function test_site_workspace_cannot_be_reassigned_without_a_transfer_workflow(): void
    {
        $site = Site::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $site->workspace_id = $otherWorkspace->id;

        $this->expectException(LogicException::class);
        $site->save();
    }

    public function test_site_uses_status_enum_without_changing_authorization_semantics(): void
    {
        $site = Site::factory()->archived()->create();

        $this->assertSame(SiteStatus::Archived, $site->status);
    }

    public function test_serialization_hides_internal_numeric_identifiers(): void
    {
        $site = Site::factory()->create();
        $serialized = $site->toArray();

        $this->assertArrayNotHasKey('id', $serialized);
        $this->assertArrayNotHasKey('workspace_id', $serialized);
        $this->assertSame($site->public_id, $serialized['public_id']);
        $this->assertSame($site->name, $serialized['name']);
    }
}
