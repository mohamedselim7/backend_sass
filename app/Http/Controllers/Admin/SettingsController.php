<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\CreditPackage;
use App\Models\Plan;
use App\Models\ProviderKey;
use App\Services\UsageLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly UsageLogger $logger)
    {
    }

    public function index(): Response
    {
        return Inertia::render('settings/Index', [
            'settings' => AppConfig::all()->pluck('value', 'key'),
            'plans' => Plan::orderBy('sort_order')->get([
                'id', 'code', 'name', 'description', 'price', 'currency',
                'interval', 'monthly_credits', 'features', 'is_active', 'sort_order', 'ai_model',
            ]),
            'aiModels' => Plan::aiModels(),
            'creditPackages' => CreditPackage::orderBy('sort_order')->orderBy('price')->get([
                'id', 'name', 'description', 'price', 'currency', 'credits', 'is_active', 'sort_order',
            ]),
            'providers' => ProviderKey::orderBy('provider')->get()->map(fn (ProviderKey $key) => [
                'id' => $key->getKey(),
                'provider' => $key->provider,
                'default_model' => $key->default_model,
                'is_active' => (bool) $key->is_active,
                'status' => $key->status,
                // The raw key never leaves the server — only a masked hint.
                'key_hint' => $key->maskedKey(),
                'last_error' => $key->last_error,
                'verified_at' => $key->verified_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            AppConfig::put((string) $key, $value);
        }

        $this->logger->activity($request->user(), 'admin.settings.updated', [
            'entity' => 'app_config',
            'details' => ['keys' => array_keys($data['settings'])],
        ]);

        return back()->with('success', __('Settings have been saved.'));
    }

    public function upsertPlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'uuid', 'exists:plans,id'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', \Illuminate\Validation\Rule::unique('plans', 'code')->ignore($request->input('id'))],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'interval' => ['required', 'in:month,year'],
            'monthly_credits' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'ai_model' => ['required', 'string', \Illuminate\Validation\Rule::in(Plan::aiModels())],
        ]);

        // Features keep the exact order the admin arranged; blanks and duplicates are dropped.
        $data['features'] = array_values(array_unique(array_filter(array_map('trim', $data['features'] ?? []), 'strlen')));
        $data['currency'] = strtoupper($data['currency']);
        $plan = Plan::updateOrCreate(
            ['id' => $data['id'] ?? null],
            collect($data)->except('id')->all(),
        );

        $this->logger->activity($request->user(), 'admin.plan.saved', [
            'entity' => 'plan',
            'details' => ['target' => $plan->getKey(), 'code' => $plan->code],
        ]);

        return back()->with('success', __('The plan has been saved.'));
    }

    /** Credit packages are soft-toggled (is_active) rather than deleted, so past payments keep their link. */
    public function upsertCreditPackage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'uuid', 'exists:credit_packages,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'credits' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $package = CreditPackage::updateOrCreate(
            ['id' => $data['id'] ?? null],
            collect($data)->except('id')->all(),
        );

        $this->logger->activity($request->user(), 'admin.credit_package.saved', [
            'entity' => 'credit_package',
            'details' => ['target' => $package->getKey(), 'credits' => $package->credits, 'price' => (float) $package->price],
        ]);

        return back()->with('success', __('The credit package has been saved.'));
    }

    public function upsertProviderKey(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:64'],
            'api_key' => ['required', 'string', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        ProviderKey::updateOrCreate(
            ['provider' => $data['provider']],
            $data + ['status' => 'connected', 'last_error' => null, 'verified_at' => now()],
        );

        $this->logger->activity($request->user(), 'admin.provider_key.saved', [
            'entity' => 'provider_key',
            'details' => ['provider' => $data['provider']],
        ]);

        return back()->with('success', __('The provider key has been updated.'));
    }

    public function deleteProviderKey(Request $request, ProviderKey $providerKey): RedirectResponse
    {
        $provider = $providerKey->provider;
        $providerKey->delete();

        $this->logger->activity($request->user(), 'admin.provider_key.deleted', [
            'entity' => 'provider_key',
            'details' => ['provider' => $provider],
        ]);

        return back()->with('success', __('The provider key has been removed.'));
    }
}
