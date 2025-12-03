<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\User;

class ProfileController extends Controller
{
    public function edit()
    {
        $userId = Auth::id();
        $user = User::with('store')->find($userId);
        
        return Inertia::render('profile/edit', compact('user'));
    }

    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Validasi input
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id . ',id',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id . ',id',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'nullable|string|min:6|confirmed'
        ]);

        // Update data user hanya jika field diisi
        if ($request->filled('username')) {
            $user->username = $validated['username'];
        }
        if ($request->filled('first_name')) {
            $user->first_name = $validated['first_name'];
        }
        if ($request->filled('last_name')) {
            $user->last_name = $validated['last_name'];
        }
        if ($request->filled('email')) {
            $user->email = $validated['email'];
        }
        if ($request->filled('phone')) {
            $user->phone = $validated['phone'];
        }
        if ($request->filled('address')) {
            $user->address = $validated['address'];
        }

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Hapus foto lama jika ada
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }
            // Simpan foto baru
            $photoPath = $request->file('photo')->store('profiles', 'public');
            $user->photo = $photoPath;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('profile.edit')->with('success', 'Profil berhasil diperbarui!');
    }
}
