<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformLlmKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages platform-level LLM API keys for super_admin.
 *
 * Small tenants can rely on these shared keys instead of providing their own.
 * Each provider (claude | gpt4o | minimax) has at most one active key.
 * Stored encrypted via the PlatformLlmKey model cast.
 */
class AdminLlmKeyController extends Controller
{
    /**
     * Render the LLM key management page.
     *
     * @return View
     */
    public function index(): View
    {
        $keys = PlatformLlmKey::query()->orderBy('provider')->orderByDesc('created_at')->get();

        return view('admin.llm-keys.index', ['keys' => $keys]);
    }

    /**
     * Store a new platform LLM key.
     *
     * Deactivates any existing active key for the same provider before saving
     * the new one, enforcing the one-active-key-per-provider constraint.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider'  => ['required', Rule::in(['claude', 'gpt4o', 'minimax'])],
            'label'     => ['required', 'string', 'max:255'],
            'key_value' => ['required', 'string', 'min:20', 'max:500'],
        ]);

        // Deactivate existing active key for this provider
        PlatformLlmKey::query()
            ->where('provider', $validated['provider'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        PlatformLlmKey::create(array_merge($validated, ['is_active' => true]));

        return redirect()->route('admin.llm-keys.index')
            ->with('success', ucfirst($validated['provider']) . ' key saved and set as active.');
    }

    /**
     * Set a specific key as the active one for its provider.
     *
     * All other keys for the same provider are deactivated.
     *
     * @param  PlatformLlmKey  $llmKey
     * @return RedirectResponse
     */
    public function activate(PlatformLlmKey $llmKey): RedirectResponse
    {
        PlatformLlmKey::query()
            ->where('provider', $llmKey->provider)
            ->update(['is_active' => false]);

        $llmKey->update(['is_active' => true]);

        return redirect()->route('admin.llm-keys.index')
            ->with('success', "{$llmKey->label} set as active {$llmKey->provider} key.");
    }

    /**
     * Delete a platform LLM key.
     *
     * @param  PlatformLlmKey  $llmKey
     * @return RedirectResponse
     */
    public function destroy(PlatformLlmKey $llmKey): RedirectResponse
    {
        $llmKey->delete();

        return redirect()->route('admin.llm-keys.index')
            ->with('success', 'Key deleted.');
    }
}
