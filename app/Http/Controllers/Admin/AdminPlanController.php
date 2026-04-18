<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminPlanController extends Controller
{
    private const ALLOWED_CODES = ['trial', 'starter', 'pro', 'enterprise'];

    public function index(): View
    {
        $plans = Plan::query()
            ->orderByRaw("CASE code
                WHEN 'trial' THEN 1
                WHEN 'starter' THEN 2
                WHEN 'pro' THEN 3
                WHEN 'enterprise' THEN 4
                ELSE 999
            END")
            ->orderBy('name')
            ->get();

        $missingCodes = array_values(array_diff(self::ALLOWED_CODES, $plans->pluck('code')->all()));

        return view('admin.plans.index', [
            'plans' => $plans,
            'missingCodes' => $missingCodes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', Rule::in(self::ALLOWED_CODES), 'unique:plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'included_quotas' => ['nullable', 'string'],
            'feature_flags' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Plan::query()->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'included_quotas' => $this->decodeJson($validated['included_quotas'] ?? null, 'included_quotas'),
            'feature_flags' => $this->decodeJson($validated['feature_flags'] ?? null, 'feature_flags'),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.plans.index')
            ->with('success', ucfirst($validated['code']) . ' plan created.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'included_quotas' => ['nullable', 'string'],
            'feature_flags' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'included_quotas' => $this->decodeJson($validated['included_quotas'] ?? null, 'included_quotas'),
            'feature_flags' => $this->decodeJson($validated['feature_flags'] ?? null, 'feature_flags'),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.plans.index')
            ->with('success', $plan->name . ' updated.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(?string $value, string $field): ?array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($trimmed, true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Must be valid JSON object or array.',
            ]);
        }

        return $decoded;
    }
}
