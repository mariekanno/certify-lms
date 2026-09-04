<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\UseCases\Settings\UpdatePasswordAction;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function update(
        UpdatePasswordRequest $request,
        UpdatePasswordAction $action,
    ): RedirectResponse {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.show', ['tab' => 'password'])
            ->with('success', 'パスワードを変更しました。');
    }
}