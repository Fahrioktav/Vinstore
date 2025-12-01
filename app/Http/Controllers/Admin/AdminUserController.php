<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'user')->get();
        return Inertia::render('admin/users/index', compact('users'));
    }

    public function edit($id)
    {
        $editedUser = User::where('role', 'user')->findOrFail($id);
        return Inertia::render('admin/users/edit', compact('editedUser'));
    }

    public function update(Request $request, $id)
    {
        $user = User::where('role', 'user')->findOrFail($id);
        
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $user = User::where('role', 'user')->findOrFail($id);
        
        // Hapus orders yang terkait
        $user->orders()->delete();
        
        $user->delete();
        
        return redirect()->back()->with('success', 'User berhasil dihapus.');
    }
}
