<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public const EMAIL_TAKEN_MESSAGE = 'This email address is already associated with an account. Please log in or use a different email.';

    /**
     * Live availability check used by the sign-up form while the user types.
     */
    public function checkEmail(Request $request)
    {
        $email = trim((string) $request->query('email', ''));

        $validator = Validator::make(['email' => $email], ['email' => 'required|email|max:255'], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => $validator->errors()->first('email'),
            ]);
        }

        $taken = User::where('email', $email)->exists();

        return response()->json([
            'available' => ! $taken,
            'message' => $taken ? self::EMAIL_TAKEN_MESSAGE : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
        ]);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            // The form shows a fixed "09" prefix; only the remaining 9 digits are submitted.
            'phone' => ['nullable', 'digits:9'],
            // Optional expert application; the account stays a resident until an admin approves it.
            'apply_as_expert' => ['nullable', 'boolean'],
            'organization' => ['exclude_unless:apply_as_expert,1,true', 'required', 'string', 'max:255'],
            'expert_qualifications' => ['exclude_unless:apply_as_expert,1,true', 'required', 'string', 'min:20', 'max:2000'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'confirmed',
            ],
        ], [
            'email.unique' => self::EMAIL_TAKEN_MESSAGE,
            'email.email' => 'Please enter a valid email address.',
            'email.required' => 'Email address is required.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must include an uppercase letter, a lowercase letter, and a number.',
            'password.confirmed' => 'Password confirmation does not match.',
            'phone.digits' => 'Enter the 9 digits after 09 (e.g. 09123456789).',
            'organization.required' => 'Organization or affiliation is required for an expert application.',
            'expert_qualifications.required' => 'Describe your expertise so an administrator can review your application.',
            'expert_qualifications.min' => 'Please describe your expertise in at least 20 characters.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->filled('password') && $request->filled('password_confirmation') && $request->input('password') !== $request->input('password_confirmation')) {
                $validator->errors()->add('password_confirmation', 'Password confirmation does not match.');
            }
        });

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $appliesAsExpert = ! empty($validated['apply_as_expert']);

        $user = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'end_user',
            'phone' => isset($validated['phone']) ? '09' . $validated['phone'] : null,
            'organization' => $appliesAsExpert ? trim($validated['organization']) : null,
            'expert_application_status' => $appliesAsExpert ? User::EXPERT_APPLICATION_PENDING : null,
            'expert_qualifications' => $appliesAsExpert ? trim($validated['expert_qualifications']) : null,
            'expert_applied_at' => $appliesAsExpert ? now() : null,
        ]);

        if ($appliesAsExpert) {
            $this->notifyAdminsOfExpertApplication($user);
        }

        Auth::login($user);

        try {
            event(new Registered($user));
        } catch (\Throwable $exception) {
            logger()->error('Failed to send registration verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            if ($request->accepts('application/json')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account created, but verification email could not be sent. Please check your mail configuration or try again later.',
                    'email' => $user->email,
                ], 201);
            }

            return redirect('/login')->with('status', 'Account created, but verification email could not be sent. Please log in and resend verification.');
        }

        if ($request->accepts('application/json')) {
            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'email' => $user->email
            ], 201);
        }

        return redirect('/email/verify')->with('status', 'verification-link-sent');
    }

    private function notifyAdminsOfExpertApplication(User $applicant): void
    {
        try {
            Notification::send(
                User::where('role', 'admin')->get(),
                new AdminAlert(
                    'New expert account application',
                    $applicant->name . ' (' . $applicant->organization . ') applied for an expert account.',
                    'expert_application',
                    route('admin.users') . '#expert-applications',
                    'Review application',
                ),
            );
        } catch (\Throwable $exception) {
            // The application is saved either way; admins still see it on the Users page.
            logger()->error('Failed to notify admins of expert application', [
                'user_id' => $applicant->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
