<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public const EMAIL_TAKEN_MESSAGE = 'This email address is already associated with an account. Please log in or use a different email.';

    /** Password rules shared by sign-up and password reset (the forms show the same checklist). */
    public static function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'confirmed'];
    }

    public static function passwordMessages(): array
    {
        return [
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must include an uppercase letter, a lowercase letter, and a number.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }

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
            'middle_name' => trim((string) $request->input('middle_name')),
            'suffix' => trim((string) $request->input('suffix')),
        ]);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => 'required|string|max:100',
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL.\s]+$/u'],
            'email' => 'required|email|unique:users',
            // The form shows a fixed "09" prefix; only the remaining 9 digits are submitted.
            'phone' => ['nullable', 'digits:9'],
            // Optional expert application; the account stays a resident until an admin approves it.
            'apply_as_expert' => ['nullable', 'boolean'],
            'organization' => ['exclude_unless:apply_as_expert,1,true', 'required', 'string', 'max:255'],
            // Optional when a certificate is uploaded: the certificate already shows the expertise.
            'expert_qualifications' => ['exclude_unless:apply_as_expert,1,true', 'nullable', 'required_without:expert_certificate', 'string', 'max:2000'],
            'expert_certificate' => [
                'exclude_unless:apply_as_expert,1,true',
                'required',
                'file',
                'mimes:' . implode(',', User::EXPERT_CERTIFICATE_MIMES),
                'max:' . User::EXPERT_CERTIFICATE_MAX_KB,
            ],
            'password' => self::passwordRules(),
            'terms' => ['accepted'],
        ], self::passwordMessages() + [
            'email.unique' => self::EMAIL_TAKEN_MESSAGE,
            'email.email' => 'Please enter a valid email address.',
            'email.required' => 'Email address is required.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'middle_name.max' => 'Middle name must be 100 characters or fewer.',
            'suffix.max' => 'Suffix must be 10 characters or fewer (e.g. Jr., Sr., III).',
            'suffix.regex' => 'Suffix can only contain letters and periods (e.g. Jr., Sr., III).',
            'phone.digits' => 'Enter the 9 digits after 09 (e.g. 09123456789).',
            'organization.required' => 'Organization or affiliation is required for an expert application.',
            'expert_qualifications.required_without' => 'Describe your expertise or upload a certificate so an administrator can review your application.',
            'expert_qualifications.max' => 'Qualifications must be 2000 characters or fewer.',
            'expert_certificate.required' => 'Upload a certificate or proof of expertise for your expert application.',
            'expert_certificate.file' => 'The certificate could not be uploaded. Please try again.',
            'expert_certificate.uploaded' => 'The certificate could not be uploaded. Please try again.',
            'expert_certificate.mimes' => 'The certificate must be a PDF, JPG, or PNG file.',
            'expert_certificate.max' => 'The certificate must be 5 MB or smaller.',
            'terms.accepted' => 'You must agree to the Terms and Conditions to create an account.',
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

        $certificatePath = null;
        $certificateName = null;
        if ($appliesAsExpert) {
            $certificate = $request->file('expert_certificate');
            // Private disk only: admins open it through an authorized route, never the public symlink.
            $certificatePath = $certificate->store(User::EXPERT_CERTIFICATE_DIRECTORY, 'local');
            $certificateName = mb_substr($certificate->getClientOriginalName(), 0, 255);
        }

        $user = User::create([
            // The User model builds the display name ("Juan D. Dela Cruz Jr.") from these parts.
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'end_user',
            'phone' => isset($validated['phone']) ? '09' . $validated['phone'] : null,
            'organization' => $appliesAsExpert ? trim($validated['organization']) : null,
            'expert_application_status' => $appliesAsExpert ? User::EXPERT_APPLICATION_PENDING : null,
            'expert_qualifications' => $appliesAsExpert ? (trim((string) ($validated['expert_qualifications'] ?? '')) ?: null) : null,
            'expert_certificate_path' => $certificatePath,
            'expert_certificate_name' => $certificateName,
            'expert_applied_at' => $appliesAsExpert ? now() : null,
        ]);

        if ($appliesAsExpert) {
            $user->notifyAdminsOfExpertApplication();
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
}
