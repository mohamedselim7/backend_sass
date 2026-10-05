<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SocialAccountResource;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SocialAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SocialAccountResource::collection(
            SocialAccount::visibleTo($request->user())->latest()->get()
        );
    }

    public function destroy(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        $this->authorize('delete', $socialAccount);

        $socialAccount->delete();

        return response()->json(['message' => 'تم فصل الحساب.']);
    }
}
