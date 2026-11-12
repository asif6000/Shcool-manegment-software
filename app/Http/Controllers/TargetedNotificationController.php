<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notifications\SendTargetedNotificationRequest;
use App\Services\TargetedNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TargetedNotificationController extends Controller
{
    public function __construct(private readonly TargetedNotificationService $notifications) {}

    public function create(Request $request): View
    {
        $this->requirePermission('notices.manage');

        return view('notifications.compose', [
            'options' => $this->notifications->options($request->user()),
            'campaignId' => (string) Str::uuid(),
        ]);
    }

    public function store(SendTargetedNotificationRequest $request): RedirectResponse
    {
        $sent = $this->notifications->send($request->user(), $request->validated());

        return redirect()
            ->route('notifications.compose')
            ->with('success', trans_choice(
                ':count notification was sent.|:count notifications were sent.',
                $sent,
                ['count' => $sent],
            ));
    }
}
