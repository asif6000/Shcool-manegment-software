<?php

namespace App\View\Composers;

use App\Models\School;
use App\Support\SchoolContext;
use Illuminate\View\View;

class AppShellComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        $contextId = app(SchoolContext::class)->id();
        $school = $contextId !== null
            ? School::query()->find($contextId)
            : $user?->school;

        $school ??= School::query()->first();

        $view->with([
            'shellUser' => $user,
            'shellSchool' => $school,
            'shellUnreadCount' => $user?->unreadNotifications()->count() ?? 0,
        ]);
    }
}
