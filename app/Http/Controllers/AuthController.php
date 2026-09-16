<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{    
    /**
     * Handle the tenant SSO login request from the Cloud Platform.
     * Validates HMAC signature using CLIENT_API_KEY and logs in a single admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        $payload = $request->query('payload');
        $signature = $request->query('signature');

        if (!$payload || !$signature) {
            $portalLoginUrl = env('CLIENT_SSO_PORTAL', 'https://cloud.barnomala.com/sign-in-with-barnomala/')
                . '?redirect_uri=' . urlencode(route('login'));
            return redirect()->away($portalLoginUrl);
        }

        // 1. Verify Signature
        $secret = env('CLIENT_API_KEY');
        
        if (!$secret) {
            Log::error('SSO Error: CLIENT_API_KEY is not configured.');
            return response()->view('auth.login', [
                'title' => 'SSO Configuration Missing',
                'message' => 'The CLIENT_API_KEY is not configured. Please contact support.',
            ], 500);
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('SSO Error: Invalid signature detected.');
            abort(403, 'Invalid signature');
        }

        // 2. Decode Payload
        $data = json_decode(base64_decode($payload), true);

        if (!$data || !isset($data['expires_at'])) {
            abort(400, 'Malformed payload');
        }

        // 3. Validate Expiration
        if ($data['expires_at'] < now()->timestamp) {
            abort(403, 'SSO Token Expired');
        }

        // 4. Find or Create Admin User
        $name = $data['name'] ?? 'Admin';
        $email = $data['email'] ?? 'admin@barnomala.com';
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt(str()->random(16)), // Random password since login is via SSO
            ]);
        }

        // 5. Establish Session
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Handle admin logout.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}