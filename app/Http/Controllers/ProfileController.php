<?php

namespace App\Http\Controllers;

use App\Models\User; // ✅ WAJIB ADA
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', [
            'user' => Auth::user()
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $user = Auth::user(); // ✅ Ini akan menjadi instance App\Models\User jika Auth disetting dengan benar

        $user->username = $request->username;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->address = $request->address;
        
        dd(get_class($user));


        $user->save(); // ✅ Harusnya sudah tidak error

        return redirect()->route('profile.edit')->with('success', 'Profile updated!');
    }
}
