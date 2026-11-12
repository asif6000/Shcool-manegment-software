<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->input('q', ''));
        $users = collect();

        if ($query !== '' && $request->user()->can('users.view')) {
            $like = '%'.$query.'%';

            $users = User::query()
                ->visibleTo($request->user())
                ->with('roles')
                ->where(function (Builder $builder) use ($like): void {
                    $builder->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->limit(8)
                ->get();
        }

        return view('search.index', [
            'query' => $query,
            'users' => $users,
        ]);
    }
}
