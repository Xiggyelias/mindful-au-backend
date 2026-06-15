<?php

namespace App\Http\Controllers;

use App\Models\InstitutionAccount;
use App\Support\SafeEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstitutionAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !$admin->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $accounts = InstitutionAccount::query()
            ->orderBy('email')
            ->get();

        return response()->json($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !$admin->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'email' => SafeEmail::required([SafeEmail::unique('institution_accounts')]),
            'role' => ['required', Rule::in(['student', 'staff', 'counselor', 'peer_counselor', 'admin'])],
            'approved' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:255'],
        ]);

        $account = InstitutionAccount::query()->create([
            'email' => SafeEmail::normalize($validated['email']),
            'role' => $validated['role'],
            'approved' => (bool) ($validated['approved'] ?? true),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'full_name' => $validated['full_name'] ?? null,
            'id_number' => $validated['id_number'] ?? null,
        ]);

        return response()->json($account, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !$admin->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $account = InstitutionAccount::query()->findOrFail($id);

        $validated = $request->validate([
            'email' => SafeEmail::sometimes([Rule::unique('institution_accounts', 'email')->ignore($account->id)]),
            'role' => ['sometimes', Rule::in(['student', 'staff', 'counselor', 'peer_counselor', 'admin'])],
            'approved' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('email', $validated)) {
            $validated['email'] = SafeEmail::normalize($validated['email']);
        }

        $account->update($validated);

        return response()->json($account);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !$admin->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $account = InstitutionAccount::query()->findOrFail($id);
        $account->delete();

        return response()->json(['message' => 'Institution account removed']);
    }
}
