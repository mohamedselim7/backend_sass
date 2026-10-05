<?php

namespace Tests\Unit\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Providers\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    public function test_generate_returns_inline_image_data_from_native_gemini_response(): void
    {
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nfoAAAAASUVORK5CYII=';

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'models' => [[
                    'name' => 'models/gemini-3.1-flash-image',
                    'displayName' => 'Gemini 3.1 Flash Image',
                    'supportedGenerationMethods' => ['generateContent'],
                ]],
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => $png],
                ]]]]],
            ], 200),
        ]);

        config(['ai.providers.gemini.recommended_image_models' => ['gemini-3.1-flash-image']]);
        $provider = new GeminiProvider(
            'https://generativelanguage.googleapis.com/v1beta/openai',
            'gm-secret',
            'gemini-2.5-flash',
            imageModel: 'gemini-3.1-flash-image',
        );

        $result = $provider->generate('A simple blue circle on a white background', ['size' => '1024x1024']);

        $this->assertSame($png, $result->base64OrUrl);
        $this->assertFalse($result->isUrl);
        $this->assertSame('image/png', $result->mime);
        $this->assertSame('gemini-3.1-flash-image', $result->model);
        $this->assertSame('gemini', $result->provider);
        $this->assertNotSame('', base64_decode($result->base64OrUrl, true));

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/models/gemini-3.1-flash-image:generateContent')) {
                return true;
            }

            return $request->hasHeader('x-goog-api-key', 'gm-secret')
                && $request['contents'][0]['parts'][0]['text'] === 'A simple blue circle on a white background'
                && $request['generationConfig']['responseModalities'] === ['TEXT', 'IMAGE']
                && $request['generationConfig']['imageConfig']['aspectRatio'] === '1:1'
                && $request['generationConfig']['imageConfig']['imageSize'] === '1K';
        });
    }

    public function test_generate_surfaces_gemini_image_error_without_leaking_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'status' => 'PERMISSION_DENIED',
                    'message' => 'Image generation unavailable for gm-secret',
                ],
            ], 403),
        ]);

        config(['ai.model_discovery.enabled' => false]);
        $provider = new GeminiProvider(
            'https://generativelanguage.googleapis.com/v1beta',
            'gm-secret',
            'gemini-2.5-flash',
            imageModel: 'gemini-3.1-flash-image',
        );

        try {
            $provider->generate('blue circle');
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertStringNotContainsString('gm-secret', $e->getMessage());
            $this->assertSame(403, $e->render()->getStatusCode());
        }
    }

    public function test_complete_parses_a_successful_generate_content_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Gemini says hi']]]]],
                'usageMetadata' => ['promptTokenCount' => 7, 'candidatesTokenCount' => 4],
            ], 200),
        ]);

        $provider = new GeminiProvider('https://generativelanguage.googleapis.com/v1beta/openai', 'gm-secret', 'gemini-2.5-flash');

        $result = $provider->complete('system', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('Gemini says hi', $result->text);
        $this->assertSame(7, $result->promptTokens);
        $this->assertSame('gemini', $result->provider);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
                && $request->hasHeader('x-goog-api-key', 'gm-secret')
                && $request['contents'][0]['parts'][0]['text'] === 'hi';
        });
    }

    public function test_complete_surfaces_provider_errors_as_ai_provider_exception(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'bad key']], 400),
        ]);

        $provider = new GeminiProvider('https://generativelanguage.googleapis.com/v1beta', 'gm-secret', 'gemini-2.5-flash');

        $this->expectException(AiProviderException::class);

        $provider->complete('system', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_error_never_leaks_the_api_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'invalid key gm-secret']], 400),
        ]);

        $provider = new GeminiProvider('https://generativelanguage.googleapis.com/v1beta', 'gm-secret', 'gemini-2.5-flash');

        try {
            $provider->complete('system', [['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertStringNotContainsString('gm-secret', $e->getMessage());
        }
    }
}
