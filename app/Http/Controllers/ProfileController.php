<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Show the profile page
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        return view('components.profile', compact('user'));
    }

    /**
     * Update the profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $request->merge([
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
            'middle_name' => trim((string) $request->input('middle_name')),
            'suffix' => trim((string) $request->input('suffix')),
        ]);

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL.\s]+$/u'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['required', 'regex:/^[0-9]{11}$/', 'string'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];

        // Organization / affiliation: experts must keep one (it is required when applying),
        // admins may fill it in, and residents don't have the field.
        $editsOrganization = $user->isExpert() || $user->isAdmin();
        if ($user->isExpert()) {
            $request->merge(['organization' => trim((string) $request->input('organization'))]);
            $rules['organization'] = ['required', 'string', 'max:255'];
        } elseif ($user->isAdmin()) {
            $rules['organization'] = ['nullable', 'string', 'max:255'];
        }

        $messages = [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'middle_name.max' => 'Middle name must be 100 characters or fewer.',
            'suffix.max' => 'Suffix must be 10 characters or fewer (e.g. Jr., Sr., III).',
            'suffix.regex' => 'Suffix can only contain letters and periods (e.g. Jr., Sr., III).',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use. Please use a different email.',
            'organization.required' => 'Organization or affiliation is required for expert accounts.',
            'organization.max' => 'Organization or affiliation must be 255 characters or fewer.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be exactly 11 digits (e.g., 09123456789).',
            'profile_image.image' => 'The profile photo must be an image.',
            'profile_image.mimes' => 'The profile photo must be a JPG, PNG, GIF, or WebP file.',
            'profile_image.max' => 'The profile photo must be 2 MB or smaller.',
            'profile_image.uploaded' => 'The profile photo could not be uploaded. Please try a smaller image.',
        ];

        $validated = $request->validate($rules, $messages);

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $path = $request->file('profile_image')->store('profiles', 'public');
            $validated['profile_image'] = $path;
        }

        // Update user profile - only update fields that are in the validated data
        // The User model rebuilds the display name ("Juan D. Dela Cruz Jr.") from the name parts.
        $fieldsToUpdate = ['first_name', 'last_name', 'email', 'phone', 'profile_image'];

        foreach ($fieldsToUpdate as $field) {
            if (isset($validated[$field])) {
                $user->{$field} = $validated[$field];
            }
        }

        // Optional parts: an empty field clears them.
        foreach (['middle_name', 'suffix'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }

        // array_key_exists so an admin can clear their (optional) organization.
        if ($editsOrganization && array_key_exists('organization', $validated)) {
            $user->organization = $validated['organization'];
        }

        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your profile was updated.',
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'organization' => $user->organization,
                'profile_image_url' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,
            ]);
        }

        return back()->with('success', 'Profile updated successfully!');
    }

    /**
     * Send a new expert application after a rejected one. Same fields and rules as the sign-up form;
     * the account stays a resident until an admin approves it.
     */
    public function reapplyExpert(Request $request)
    {
        $user = $request->user();

        if (! $user->canReapplyAsExpert()) {
            return redirect()->route('profile.show')->with('error', $user->hasPendingExpertApplication()
                ? 'Your expert application is already waiting for review.'
                : 'Only residents whose expert application was not approved can reapply.');
        }

        $request->merge([
            'organization' => trim((string) $request->input('organization')),
            'expert_qualifications' => trim((string) $request->input('expert_qualifications')),
        ]);

        $validated = $request->validateWithBag('expertApplication', [
            'organization' => ['required', 'string', 'max:255'],
            'expert_qualifications' => ['nullable', 'string', 'max:2000'],
            'expert_certificate' => [
                'required',
                'file',
                'mimes:' . implode(',', User::EXPERT_CERTIFICATE_MIMES),
                'max:' . User::EXPERT_CERTIFICATE_MAX_KB,
            ],
        ], [
            'organization.required' => 'Organization or affiliation is required for an expert application.',
            'organization.max' => 'Organization or affiliation must be 255 characters or fewer.',
            'expert_qualifications.max' => 'Qualifications must be 2000 characters or fewer.',
            'expert_certificate.required' => 'Upload a certificate or proof of expertise for your expert application.',
            'expert_certificate.file' => 'The certificate could not be uploaded. Please try again.',
            'expert_certificate.uploaded' => 'The certificate could not be uploaded. Please try again.',
            'expert_certificate.mimes' => 'The certificate must be a PDF, JPG, or PNG file.',
            'expert_certificate.max' => 'The certificate must be 5 MB or smaller.',
        ]);

        // Private disk only: admins open it through an authorized route, never the public symlink.
        $certificate = $request->file('expert_certificate');
        $newPath = $certificate->store(User::EXPERT_CERTIFICATE_DIRECTORY, 'local');
        $oldPath = $user->expert_certificate_path;

        $user->forceFill([
            'organization' => $validated['organization'],
            'expert_qualifications' => $validated['expert_qualifications'] ?: null,
            'expert_certificate_path' => $newPath,
            'expert_certificate_name' => mb_substr($certificate->getClientOriginalName(), 0, 255),
            'expert_application_status' => User::EXPERT_APPLICATION_PENDING,
            'expert_applied_at' => now(),
            'expert_reviewed_at' => null,
            'expert_reviewed_by' => null,
            'expert_rejection_notes' => null,
        ])->save();

        // The rejected application's certificate is replaced by the new one.
        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        // The "not approved, reapply" notice has done its job.
        $user->notifications()->where('data->type', 'expert_application_rejected')->delete();

        UserActivity::record($user, 'Resubmitted expert application', 'User Management', 'pending', $user);
        $user->notifyAdminsOfExpertApplication(resubmitted: true);

        return redirect()->route('profile.show')
            ->with('success', 'Your expert application was sent. An administrator will review it.');
    }

    /**
     * Update the user's password
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Check if current password is correct
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        // Update password
        $user->password = Hash::make($validated['password']);
        $user->save();

        return back()->with('success', 'Password updated successfully!');
    }
}
