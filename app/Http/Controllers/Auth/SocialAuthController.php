<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect ke Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle callback dari Google
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $googleId = $googleUser->getId();
            $email = $googleUser->getEmail();
            $name = $googleUser->getName() ?: $googleUser->getNickname() ?: $email;

            // Cek apakah user sudah ada berdasarkan google_id atau email
            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                // Update google_id jika belum ada
                if (!$user->google_id) {
                    $user->google_id = $googleId;
                    $user->save();
                }
            } else {
                // Buat user baru dari data Google
                $nameParts = explode(' ', trim($name), 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';

                $user = User::create([
                    'username' => $this->generateUniqueUsername($email),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'google_id' => $googleId,
                    'phone' => $this->generateGooglePlaceholderPhone($googleId), // User bisa update nanti
                    'address' => 'Belum diisi', // Default, user bisa update nanti
                    'password' => null, // Tidak perlu password untuk Google login
                    'role' => 'user', // Default role
                ]);
            }

            // Login user
            Auth::login($user);

            // Redirect berdasarkan role
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role === 'seller') {
                return redirect()->route('seller.dashboard');
            }

            return redirect()->intended('/');

        } catch (\Exception $e) {
            return redirect()->route('login.form')
                ->withErrors(['google' => 'Gagal login dengan Google. Silakan coba lagi.']);
        }
    }

    /**
     * Generate username unik dari email
     */
    private function generateUniqueUsername($email)
    {
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Generate phone placeholder unik karena kolom phone wajib unik.
     */
    private function generateGooglePlaceholderPhone($googleId)
    {
        $basePhone = 'google-' . $googleId;
        $phone = $basePhone;
        $counter = 1;

        while (User::where('phone', $phone)->exists()) {
            $phone = $basePhone . '-' . $counter;
            $counter++;
        }

        return $phone;
    }
}
