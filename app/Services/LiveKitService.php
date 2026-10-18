<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use Illuminate\Support\Facades\Log;

/**
 * Mints LiveKit room access tokens for call participants.
 *
 * LiveKit replaces the hand-rolled WebRTC-over-Supabase-Realtime media path: the
 * browser connects to a LiveKit SFU instead of negotiating RTCPeerConnection between
 * the two tabs. The ring / accept / decline lifecycle still runs through the existing
 * CallCoordinator signals — only the media transport changes.
 *
 * Tokens are always minted server-side: the API key/secret never reach the browser,
 * and joining a room requires a token signed for that exact room, so a student cannot
 * join their counselor's other sessions.
 */
class LiveKitService
{
    public function isConfigured(): bool
    {
        return $this->apiKey() !== ''
            && $this->apiSecret() !== ''
            && $this->url() !== '';
    }

    /**
     * The public wss:// URL the browser connects to. In production this is the
     * self-hosted LiveKit host (or LiveKit Cloud project URL); locally it is
     * ws://localhost:7880.
     */
    public function url(): string
    {
        $url = trim((string) config('services.livekit.url', ''));
        if (str_starts_with($url, 'http://')) {
            return 'ws://'.substr($url, 7);
        }
        if (str_starts_with($url, 'https://')) {
            return 'wss://'.substr($url, 8);
        }

        return $url;
    }

    public function apiKey(): string
    {
        return trim((string) config('services.livekit.key', ''));
    }

    public function apiSecret(): string
    {
        return trim((string) config('services.livekit.secret', ''));
    }

    /**
     * Mint a join token for one participant of one appointment's call room.
     *
     * Room names are namespaced by appointment id so each appointment gets an
     * isolated room; identity is "role-{userId}" so both sides appear as distinct
     * participants even if a student and counselor id ever collided across tables.
     */
    public function mintJoinToken(int $appointmentId, int $userId, string $role, int $ttlSeconds = 3600): string
    {
        $roomName = $this->roomName($appointmentId);
        $identity = $role.'-'.$userId;

        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($identity)
            ->setName($identity)
            ->setTtl($ttlSeconds)
            ->setMetadata(json_encode([
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
                'role' => $role,
            ]) ?: '{}');

        $videoGrant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName($roomName)
            ->setCanPublish(true)
            ->setCanSubscribe(true)
            ->setCanPublishData(true);

        $token = (new AccessToken($this->apiKey(), $this->apiSecret()))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();

        Log::info('[LiveKit] Token minted', [
            'appointment_id' => $appointmentId,
            'room' => $roomName,
            'identity' => $identity,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return $token;
    }

    /** Deterministic room name per appointment — shared by both participants. */
    public function roomName(int $appointmentId): string
    {
        return 'appointment-'.$appointmentId;
    }
}
