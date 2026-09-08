<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAvatarRequest;
use App\UseCases\Settings\DestroyAvatarAction;
use App\UseCases\Settings\StoreAvatarAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AvatarController extends Controller
{
    public function store(
        StoreAvatarRequest $request,
        StoreAvatarAction $action,
    ): RedirectResponse {
        /** @var UploadedFile $file */
        $file = $request->file('avatar');

        $action($request->user(), $file);

        return redirect()
            ->route('settings.profile.show')
            ->with('success', 'アイコン画像を更新しました。');
    }

    public function destroy(
        Request $request,
        DestroyAvatarAction $action,
    ): RedirectResponse {
        $action($request->user());

        return redirect()
            ->route('settings.profile.show')
            ->with('success', 'アイコン画像を削除しました。');
    }
}