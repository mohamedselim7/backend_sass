<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditTip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Admin CRUD for the "tips to reduce credit usage" page shown to users. */
class CreditTipController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('credit-tips/Index', [
            'tips' => CreditTip::query()->ordered()->get()->map->toRow()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CreditTip::create($this->validated($request));

        return back()->with('success', 'تمت إضافة النصيحة.');
    }

    public function update(Request $request, CreditTip $creditTip): RedirectResponse
    {
        $creditTip->update($this->validated($request));

        return back()->with('success', 'تم حفظ النصيحة.');
    }

    public function destroy(CreditTip $creditTip): RedirectResponse
    {
        $creditTip->delete();

        return back()->with('success', 'تم حذف النصيحة.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ], [
            'title.required' => 'اكتب عنوان النصيحة.',
            'body.required' => 'اكتب نص النصيحة.',
        ]);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
