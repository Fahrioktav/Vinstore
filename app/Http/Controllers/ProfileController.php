<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit()
    {
        $userId = Auth::id();
        $user = User::with('store')->find($userId);

        return Inertia::render('profile/edit', [
            'user' => $user,
            // Dihitung di server: `google_id` dan `password` keduanya tidak
            // pernah ikut diserialisasi ke halaman, dan memang tidak boleh.
            // Yang dibutuhkan halaman hanyalah dua jawaban ya/tidak.
            'googleLinked' => ! empty($user->google_id),
            'hasPassword' => ! empty($user->password),
        ]);
    }

    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $rules = [
            'username' => 'required|string|max:255|unique:users,username,'.$user->id.',id',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id.',id',
            'phone' => 'required|string|max:20|unique:users,phone,'.$user->id.',id',
            'address' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'nullable|string|min:6|confirmed',
        ];

        // Mengganti password atau alamat surel menuntut pembuktian bahwa yang
        // duduk di depan layar memang pemilik akunnya, bukan orang yang
        // kebetulan menemukan sesi yang masih terbuka. Keduanya adalah kunci
        // masuk: yang satu dipakai login, yang satu dipakai memulihkan akun.
        // Tanpa penjagaan ini, satu laptop yang ditinggalkan sebentar cukup
        // untuk mengunci pemiliknya keluar selamanya — dan untuk akun seller,
        // ikut menguasai ke mana uang pencairan dikirim (temuan V10-02/T-03).
        //
        // Akun yang masuk lewat Google saja belum punya password lokal; bagi
        // mereka penjagaan ini dilewati, sebab meminta password yang tidak
        // pernah mereka buat sama saja dengan menutup halamannya. Login mereka
        // tetap bersandar pada `google_id`, bukan pada surel di tabel ini.
        $memintaGantiPassword = $request->filled('password');
        $memintaGantiEmail = $request->filled('email') && $request->input('email') !== $user->email;

        if (! empty($user->password) && ($memintaGantiPassword || $memintaGantiEmail)) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validate($rules, [
            'current_password.required' => 'Masukkan password Anda saat ini untuk mengubah email atau password.',
            'current_password.current_password' => 'Password saat ini tidak cocok.',
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
