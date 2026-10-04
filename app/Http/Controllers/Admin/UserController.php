<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('steam_id', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'adminCount' => User::where('is_admin', true)->count(),
            'search' => $filters['search'] ?? '',
        ]);
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $message = DB::transaction(function () use ($request, $user): string {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($target->is_admin) {
                if ($request->user()->is($target)) {
                    return 'You cannot remove your own admin access.';
                }

                $admins = User::query()
                    ->where('is_admin', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                if ($admins->count() <= 1) {
                    return 'The last administrator cannot be removed.';
                }
            }

            $target->forceFill(['is_admin' => ! $target->is_admin])->save();

            return $target->is_admin
                ? "{$target->name} is now an administrator."
                : "Administrator access was removed from {$target->name}.";
        });

        if (str_contains($message, 'cannot')) {
            return redirect()->route('admin.users.index')->with('error', $message);
        }

        return redirect()->route('admin.users.index')->with('status', $message);
    }
}