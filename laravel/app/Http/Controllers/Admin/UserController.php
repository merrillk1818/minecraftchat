<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search', '');

        $users = User::withCount(['chatSessions'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', compact('users', 'search'));
    }

    public function edit(int $id)
    {
        $user = User::findOrFail($id);
        return view('admin.user-edit', compact('user'));
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email,' . $id,
            'password'              => 'nullable|string|min:8|confirmed',
            'password_confirmation' => 'nullable|string',
        ]);

        $data = [
            'name'  => $request->input('name'),
            'email' => $request->input('email'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return redirect('/admin/users')->with('success', "\"{$user->name}\" updated.");
    }

    public function setRole(Request $request, int $id)
    {
        $request->validate(['role' => 'required|in:user,admin']);

        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $user->update(['role' => $request->input('role')]);

        return back()->with('success', "{$user->name} is now a {$request->input('role')}.");
    }

    public function destroy(int $id)
    {
        if ($id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user = User::findOrFail($id);
        $name = $user->name;

        // Remove private documents from MariaDB (user notes)
        Document::where('user_id', $user->id)->where('data_type', 'user')->delete();

        // Delete chat history
        $user->chatSessions()->each(fn ($s) => $s->messages()->delete());
        $user->chatSessions()->delete();

        $user->delete();

        return redirect('/admin/users')->with('success', "User \"{$name}\" deleted.");
    }
}
