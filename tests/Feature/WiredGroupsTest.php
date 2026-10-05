<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\Prompt;
use App\Models\WorkspaceRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WiredGroupsTest extends TestCase
{
    public function test_media_upload_flow(): void
    {
        Storage::fake('public');
        $this->getJson('/api/v1/media')->assertStatus(401);

        $user = $this->actingAsUser();
        $file = UploadedFile::fake()->image('photo.jpg');

        $res = $this->postJson('/api/v1/media', ['file' => $file]);
        $res->assertStatus(201);
        $id = $res->json('data.id');
        Storage::disk('public')->assertExists($res->json('data.path'));

        $this->postJson('/api/v1/media', [])->assertStatus(422);

        // Foreign owner's record must be created BEFORE switching auth context.
        $other = User::factory()->create();
        $foreign = Media::factory()->create(['user_id' => $other->id]);

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($user));
        $this->deleteJson("/api/v1/media/{$foreign->id}")->assertStatus(403);

        $this->deleteJson("/api/v1/media/{$id}")->assertStatus(200);
    }

    public function test_prompts_flow(): void
    {
        $this->getJson('/api/v1/prompts')->assertStatus(401);

        $user = $this->actingAsUser();
        $this->postJson('/api/v1/prompts', [])->assertStatus(422);

        $res = $this->postJson('/api/v1/prompts', ['title' => 'A', 'prompt' => 'do X']);
        $res->assertStatus(201);
        $id = $res->json('data.id');

        $this->getJson('/api/v1/prompts')->assertStatus(200);

        // Foreign owner's record must be created BEFORE switching auth context.
        $other = User::factory()->create();
        $foreign = Prompt::factory()->create(['user_id' => $other->id]);

        $this->patchJson("/api/v1/prompts/{$foreign->id}", ['title' => 'x'])->assertStatus(403);
        $this->deleteJson("/api/v1/prompts/{$foreign->id}")->assertStatus(403);

        $this->deleteJson("/api/v1/prompts/{$id}")->assertStatus(200);
    }

    public function test_drafts_flow(): void
    {
        $this->getJson('/api/v1/drafts/foo')->assertStatus(401);

        $user = $this->actingAsUser();
        $this->getJson('/api/v1/drafts/foo')->assertStatus(404);

        $this->putJson('/api/v1/drafts/foo', [])->assertStatus(422);
        $this->putJson('/api/v1/drafts/foo', ['data' => ['a' => 1]])->assertStatus(200);
        $this->getJson('/api/v1/drafts/foo')->assertStatus(200)->assertJsonPath('data.data.a', 1);

        // Switch to a different user: draft is not visible to them.
        $this->actingAsUser();
        $this->getJson('/api/v1/drafts/foo')->assertStatus(404);

        // Switch back to the original owner and delete their draft.
        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($user));
        $this->deleteJson('/api/v1/drafts/foo')->assertStatus(200);
    }

    public function test_activity_flow(): void
    {
        $this->getJson('/api/v1/activity')->assertStatus(401);

        $this->actingAsUser();
        $this->postJson('/api/v1/activity', [])->assertStatus(422);
        $this->postJson('/api/v1/activity', ['action' => 'did-thing'])->assertStatus(201);
        $this->getJson('/api/v1/activity')->assertStatus(200);
    }

    public function test_usage_requires_auth(): void
    {
        $this->getJson('/api/v1/usage')->assertStatus(401);

        $this->actingAsUser();
        $this->getJson('/api/v1/usage')->assertStatus(200);
    }

    public function test_settings_flow(): void
    {
        $this->getJson('/api/v1/settings')->assertStatus(401);

        $this->actingAsUser();
        $this->getJson('/api/v1/settings')->assertStatus(200);
        $this->patchJson('/api/v1/settings', ['active_provider' => 'not-a-real-provider'])->assertStatus(422);
        $this->patchJson('/api/v1/settings', ['locale' => 'ar'])->assertStatus(200)->assertJsonPath('data.locale', 'ar');
    }

    public function test_onboarding_flow(): void
    {
        $this->postJson('/api/v1/onboarding/setup', [])->assertStatus(401);

        $this->actingAsUser();

        // Goal entries require a title; omitting it genuinely violates validation.
        $this->postJson('/api/v1/onboarding/setup', [
            'goals' => [['metric' => 'followers']],
        ])->assertStatus(422);

        $res = $this->postJson('/api/v1/onboarding/setup', [
            'full_name' => 'Jane',
            'brand' => ['name' => 'Acme'],
            'goals' => [['title' => 'Grow followers']],
        ]);
        $res->assertStatus(200);
        $this->assertDatabaseHas('brands', ['name' => 'Acme']);
        $this->assertDatabaseHas('goals', ['title' => 'Grow followers']);
    }
}
