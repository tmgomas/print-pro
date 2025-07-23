<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRegistrationRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Show the login form
     */
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]);
    }

    /**
     * Handle login request
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials = $request->only('email', 'password');

        if ($this->authService->login($credentials, $request)) {
            $user = Auth::user();
            
            // Redirect based on role
            return $this->redirectAfterLogin($user);
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    /**
     * Show company registration form
     */
    public function showCompanyRegistration(): Response
    {
        return Inertia::render('Auth/CompanyRegistration', [
            'availableRoles' => [
                'Company Admin' => 'Company Admin',
                'Branch Manager' => 'Branch Manager',
                'Cashier' => 'Cashier',
                'Production Staff' => 'Production Staff',
                'Delivery Coordinator' => 'Delivery Coordinator',
            ],
        ]);
    }

    /**
     * Handle company registration
     */
    public function registerCompany(CompanyRegistrationRequest $request): RedirectResponse
    {
        try {
            $user = $this->authService->registerCompany(
                $request->getCompanyData(),
                $request->getAdminData()
            );

            Auth::login($user);

            return redirect()->route('dashboard')
                ->with('success', 'Company registered successfully! Welcome to PrintCraft Pro.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }

    /**
     * Handle logout
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Show profile page
     */
    public function profile(): Response
    {
        $user = Auth::user();
        
        return Inertia::render('Auth/Profile', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'logo_url' => $user->company->logo_url,
                ] : null,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                ] : null,
                'roles' => $user->roles->pluck('name'),
                'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ],
            'stats' => [
                'total_logins' => $user->preferences['total_logins'] ?? 0,
                'account_age_days' => $user->created_at->diffInDays(now()),
            ],
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        try {
            $this->authService->updateProfile(Auth::user(), $request->all());
            
            return back()->with('success', 'Profile updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Profile update failed. Please try again.']);
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $this->authService->updatePassword(Auth::user(), $request->password);
            
            return back()->with('success', 'Password updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Password update failed. Please try again.']);
        }
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Show email verification notice
     */
    public function verificationNotice(): Response
    {
        return Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'),
        ]);
    }

    /**
     * Redirect user after login based on their role
     */
    private function redirectAfterLogin($user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isCompanyAdmin()) {
            return redirect()->route('company.dashboard');
        }

        if ($user->hasRole('Branch Manager')) {
            return redirect()->route('branch.dashboard');
        }

        if ($user->hasRole('Cashier')) {
            return redirect()->route('cashier.dashboard');
        }

        if ($user->hasRole('Production Staff')) {
            return redirect()->route('production.dashboard');
        }

        if ($user->hasRole('Delivery Coordinator')) {
            return redirect()->route('delivery.dashboard');
        }

        return redirect()->route('dashboard');
    }
}