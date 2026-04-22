<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CredentialMetadata;
use App\Models\MessagingChannelConnection;
use App\Models\VoiceChannel;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * AdminCredentialsController - Admin control plane for credential metadata tracking.
 *
 * This controller provides:
 *  - View credential metadata (provider, rotation schedule, verification status)
 *  - Record credential rotations
 *  - Set rotation schedules
 *  - Track verification status
 *
 * IMPORTANT: This is a MASKED METADATA WORKSPACE ONLY.
 * Actual credentials remain in their original sources:
 *  - MessagingChannelConnection.credentials
 *  - VoiceChannel configuration
 *  - Never expose raw secrets in these views.
 */
class AdminCredentialsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Render the credentials overview page.
     *
     * Shows credential metadata across all businesses.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = CredentialMetadata::query()
            ->orderByDesc('last_rotated_at')
            ->orderBy('provider');

        if ($request->filled('provider')) {
            $query->where('provider', $request->input('provider'));
        }

        if ($request->filled('needs_rotation')) {
            $query->where('is_active', true)
                ->whereNotNull('next_rotation_due_at')
                ->where('next_rotation_due_at', '<=', now());
        }

        if ($request->filled('failed_verification')) {
            $query->where('last_verification_status', 'fail');
        }

        $credentials = $query->paginate(50)->withQueryString();
        $providers = CredentialMetadata::query()
            ->select('provider')
            ->distinct()
            ->pluck('provider');

        return view('admin.credentials.index', [
            'credentials' => $credentials,
            'providers' => $providers,
            'filters' => $request->only(['provider', 'needs_rotation', 'failed_verification']),
        ]);
    }

    /**
     * View credential details for a specific source.
     *
     * @param  string  $sourceType
     * @param  int  $sourceId
     * @return View
     */
    public function show(string $sourceType, int $sourceId): View
    {
        $credentials = CredentialMetadata::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get();

        return view('admin.credentials.show', [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'credentials' => $credentials,
        ]);
    }

    /**
     * Record a credential rotation.
     *
     * @param  Request  $request
     * @param  string  $sourceType
     * @param  int  $sourceId
     * @param  CredentialMetadata  $credential
     * @return RedirectResponse
     */
    public function recordRotation(
        Request $request,
        string $sourceType,
        int $sourceId,
    ): RedirectResponse {
        $request->validate([
            'credential_id' => ['required', 'integer', 'exists:credential_metadata,id'],
            'rotation_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $credential = CredentialMetadata::query()
            ->whereKey((int) $request->input('credential_id'))
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->firstOrFail();

        $rotationDate = $request->filled('rotation_date')
            ? Carbon::parse((string) $request->input('rotation_date'))
            : now();

        $credential->update([
            'last_rotated_at' => $rotationDate,
            'next_rotation_due_at' => $rotationDate->copy()->addDays($credential->rotation_interval_days),
            'last_verified_at' => null,
            'last_verification_status' => null,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'credential.rotation_recorded',
            subjectType: CredentialMetadata::class,
            subjectId: $credential->id,
            payload: [
                'credential_id' => $credential->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'provider' => $credential->provider,
                'rotation_date' => $rotationDate->toIso8601String(),
                'next_rotation_due_at' => $credential->next_rotation_due_at?->toIso8601String(),
                'note' => $request->input('note'),
            ],
            request: $request,
        );

        return redirect()->route('admin.credentials.show', [$sourceType, $sourceId])
            ->with('success', "Rotation recorded for {$credential->provider} credential.");
    }

    /**
     * Update rotation settings for a credential.
     *
     * @param  Request  $request
     * @param  CredentialMetadata  $credential
     * @return RedirectResponse
     */
    public function updateSettings(Request $request, CredentialMetadata $credential): RedirectResponse
    {
        $request->validate([
            'rotation_interval_days' => ['required', 'integer', 'min:7', 'max:365'],
            'key_name' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string'],
        ]);

        $credential->update([
            'rotation_interval_days' => $request->input('rotation_interval_days'),
            'key_name' => $request->input('key_name', $credential->key_name),
            'description' => $request->input('description', $credential->description),
        ]);

        // Recalculate next rotation date
        if ($credential->last_rotated_at) {
            $credential->update([
                'next_rotation_due_at' => $credential->last_rotated_at->copy()->addDays($credential->rotation_interval_days),
            ]);
        }

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'credential.settings_updated',
            subjectType: CredentialMetadata::class,
            subjectId: $credential->id,
            payload: [
                'new_rotation_interval_days' => $credential->rotation_interval_days,
                'new_key_name' => $credential->key_name,
                'new_next_rotation_due_at' => $credential->next_rotation_due_at?->toIso8601String(),
            ],
            request: $request,
        );

        return redirect()->route('admin.credentials.show', [$credential->source_type, $credential->source_id])
            ->with('success', "Credential settings updated.");
    }

    /**
     * Record credential verification status.
     *
     * @param  Request  $request
     * @param  CredentialMetadata  $credential
     * @return RedirectResponse
     */
    public function recordVerification(Request $request, CredentialMetadata $credential): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:pass,fail,unknown'],
            'message' => ['nullable', 'string'],
        ]);

        $credential->update([
            'last_verified_at' => now(),
            'last_verification_status' => $request->input('status'),
            'last_verification_message' => $request->input('message'),
            'last_verified_by_user_id' => $request->user()->id,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'credential.verification_recorded',
            subjectType: CredentialMetadata::class,
            subjectId: $credential->id,
            payload: [
                'status' => $request->input('status'),
                'message' => $request->input('message'),
                'verified_by_user_id' => $request->user()->id,
            ],
            request: $request,
        );

        return redirect()->route('admin.credentials.show', [$credential->source_type, $credential->source_id])
            ->with('success', "Verification status recorded for {$credential->provider}.");
    }

    /**
     * Deactivate a credential.
     *
     * @param  Request  $request
     * @param  CredentialMetadata  $credential
     * @return RedirectResponse
     */
    public function deactivate(Request $request, CredentialMetadata $credential): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'min:5', 'max:255'],
        ]);

        $credential->update([
            'is_active' => false,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'credential.deactivated',
            subjectType: CredentialMetadata::class,
            subjectId: $credential->id,
            payload: [
                'reason' => $request->input('reason') ?: 'No reason provided.',
                'deactivated_by_user_id' => $request->user()->id,
            ],
            request: $request,
        );

        return redirect()->route('admin.credentials.index')
            ->with('success', "Credential {$credential->provider} has been deactivated.");
    }

    /**
     * Create metadata for a messaging connection.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @param  string  $channel
     * @return RedirectResponse
     */
    public function createForMessagingConnection(
        Request $request,
        Business $business,
        string $channel,
    ): RedirectResponse {
        $request->validate([
            'key_name' => ['required', 'string', 'max:128'],
            'provider' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'rotation_interval_days' => ['nullable', 'integer', 'min:7', 'max:365', 'default:90'],
        ]);

        $connection = $business->messagingConnections()
            ->where('channel', $channel)
            ->first();

        if (!$connection) {
            return redirect()->back()
                ->withErrors(['connection' => "No connection found for {$business->name} - {$channel}."]);
        }

        $credential = CredentialMetadata::create([
            'source_type' => 'messaging_connection',
            'source_id' => $connection->id,
            'provider' => $request->input('provider'),
            'key_name' => $request->input('key_name'),
            'description' => $request->input('description'),
            'rotation_interval_days' => $request->input('rotation_interval_days', 90),
            'is_active' => true,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'credential.created',
            subjectType: CredentialMetadata::class,
            subjectId: $credential->id,
            payload: [
                'source_type' => 'messaging_connection',
                'source_id' => $connection->id,
                'channel' => $channel,
                'provider' => $credential->provider,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', "Credential metadata created for {$business->name} - {$channel}.");
    }

    /**
     * Create metadata for a voice channel.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @param  VoiceChannel  $voiceChannel
     * @return RedirectResponse
     */
    public function createForVoiceChannel(
        Request $request,
        Business $business,
        VoiceChannel $voiceChannel,
    ): RedirectResponse {
        $request->validate([
            'key_name' => ['required', 'string', 'max:128'],
            'provider' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'rotation_interval_days' => ['nullable', 'integer', 'min:7', 'max:365', 'default:90'],
        ]);

        $credential = CredentialMetadata::create([
            'source_type' => 'voice_channel',
            'source_id' => $voiceChannel->id,
            'provider' => $request->input('provider'),
            'key_name' => $request->input('key_name'),
            'description' => $request->input('description'),
            'rotation_interval_days' => $request->input('rotation_interval_days', 90),
            'is_active' => true,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'credential.created',
            subjectType: CredentialMetadata::class,
            subjectId: $credential->id,
            payload: [
                'source_type' => 'voice_channel',
                'source_id' => $voiceChannel->id,
                'channel_id' => $voiceChannel->channel_id,
                'provider' => $credential->provider,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->back()
            ->with('success', "Credential metadata created for voice channel.");
    }
}
