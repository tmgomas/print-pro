<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Show the login page.
     */
    public function showLogin(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle login request - Redirect everyone to single dashboard
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();
        
        // Track login activity
        $this->authService->trackLogin($user, $request->ip());

        // Always redirect to the main dashboard regardless of role
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Show the registration page.
     */
    public function showRegister(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle registration request - Redirect to single dashboard
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        try {
            $user = $this->authService->registerUser($request->validated());
            
            Auth::login($user);
            
            // Always redirect to the main dashboard regardless of role
            return redirect()->route('dashboard');
            
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->trackLogout(Auth::user());
        
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => session('status'),
        ]);
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(Request $request): Response
    {
        return Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Show email verification notice
     */
    public function verificationNotice(): Response
    {
        return Inertia::render('auth/verify-email', [
            'status' => session('status'),
        ]);
    }
}