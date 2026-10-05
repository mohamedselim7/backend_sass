<?php

namespace Tests\Unit\Ai;

use App\Models\ProviderKey;
use App\Models\User;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use App\Services\Ai\Providers\OpenRouterProvider;
use App\Services\Ai\ProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    private ProviderResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ProviderResolver;

        config([
            'ai.default_provider' => 'openai',
            'ai.default_model' => 'gpt-4o-mini',
            'ai.providers.openai.api_key' => 'env-openai-key',
            'ai.providers.openrouter.api_key' => 'env-openrouter-key',
            'ai.providers.gemini.api_key' => 'env-gemini-key',
        ]);
    }

    public function test_explicit_options_take_priority_over_user_and_defaults(): void
    {
        $user = User::factory()->create(['active_provider' => 'gemini', 'active_model' => 'gemini-1.5-pro']);

        $provider = $this->resolver->text($user, ['provider' => 'openrouter', 'model' => 'openai/gpt-4o-mini']);

        $this->assertInstanceOf(OpenRouterProvider::class, $provider);
    }

    public function test_user_active_provider_is_used_when_no_explicit_option_given(): void
    {
        $user = User::factory()->create(['active_provider' => 'gemini', 'active_model' => 'gemini-1.5-pro']);

        $provider = $this->resolver->text($user, []);

        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }

    public function test_config_default_is_used_when_no_options_or_user_preference_exist(): void
    {
        $provider = $this->resolver->text(null, []);

        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function test_gemini_plan_provider_is_used_for_images_without_openai_fallback(): void
    {
        config([
            'ai.default_provider' => 'gemini',
            'ai.providers.gemini.image_model' => 'gemini-3.1-flash-image',
        ]);

        $provider = $this->resolver->image(null, ['provider' => 'openai', 'model' => 'dall-e-3']);

        $this->assertInstanceOf(GeminiProvider::class, $provider);

        $imageModel = new \ReflectionProperty($provider, 'imageModel');
        $this->assertSame('gemini-3.1-flash-image', $imageModel->getValue($provider));
    }

    public function test_active_provider_key_row_takes_priority_over_env_key(): void
    {
        ProviderKey::query()->create([
            'provider' => 'openai',
            'api_key' => 'db-stored-key',
            'default_model' => 'gpt-4o-mini',
            'is_active' => true,
            'status' => 'verified',
        ]);

        $provider = $this->resolver->text(null, []);

        $reflection = new \ReflectionProperty($provider, 'apiKey');
        $reflection->setAccessible(true);

        $this->assertSame('db-stored-key', $reflection->getValue($provider));
    }

    public function test_available_models_never_include_any_api_key(): void
    {
        ProviderKey::query()->create([
            'provider' => 'openai',
            'api_key' => 'super-secret-key',
            'default_model' => 'gpt-4o-mini',
            'is_active' => true,
            'status' => 'verified',
        ]);

        $models = $this->resolver->availableModels(null);

        $json = json_encode($models);

        $this->assertStringNotContainsString('super-secret-key', $json);
        $this->assertStringNotContainsString('api_key', $json);
        $this->assertNotEmpty($models);
    }
}
