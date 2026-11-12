<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Services\PermanentDeleteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission('students.view');

        $search = trim((string) $request->query('search'));

        $guardians = Guardian::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('alt_phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('nid', 'like', '%'.$search.'%');
                });
            })
            ->withCount('students')
            ->with(['students' => fn ($query) => $query
                ->with(['enrollments' => fn ($query) => $query
                    ->where('status', 'active')
                    ->with(['schoolClass:id,name', 'section:id,name'])
                    ->latest('enrolled_on')])])
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        return view('guardians.index', [
            'guardians' => $guardians,
            'search' => $search,
        ]);
    }

    /**
     * A family contact is a directory entry, so deleting it only unlinks the
     * guardian from their students; the children, their bills and their results
     * are untouched.
     */
    public function destroy(Request $request, Guardian $guardian, PermanentDeleteService $deletes): RedirectResponse
    {
        $this->requirePermission('students.delete');

        $deletes->delete($guardian, [
            'label' => __('guardian'),
            'description' => __('Guardian #:id was permanently deleted.', ['id' => $guardian->getKey()]),
            'actor' => $request->user(),
        ]);

        return redirect()->route('guardians.index')
            ->with('success', __('The guardian record was permanently deleted.'));
    }
}
