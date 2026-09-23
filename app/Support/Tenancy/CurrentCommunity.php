<?php

namespace App\Support\Tenancy;

use App\Models\Community;
use Illuminate\Contracts\Session\Session;

/**
 * Remembers which community the user is working in, across requests.
 */
class CurrentCommunity
{
    private const SESSION_KEY = 'current_community_id';

    private ?Community $resolved = null;

    public function __construct(private readonly Session $session) {}

    public function set(Community $community): void
    {
        $this->session->put(self::SESSION_KEY, $community->id);
        $this->resolved = $community;
    }

    public function get(): ?Community
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $communityId = $this->session->get(self::SESSION_KEY);

        if (! is_int($communityId)) {
            return null;
        }

        // The company scope ensures a stale or foreign id resolves to nothing.
        $this->resolved = Community::query()->find($communityId);

        if ($this->resolved === null) {
            $this->session->forget(self::SESSION_KEY);
        }

        return $this->resolved;
    }
}
