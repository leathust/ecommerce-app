<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $fillable = [
        'email',
        'locale',
        'source',
        'status',
        'consented_at',
        'unsubscribed_at',
    ];

    protected $hidden = [
        'unsubscribe_token_hash',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /**
     * Rotate the one-way token used by a future unsubscribe link.
     *
     * The raw token is returned once and is never stored in the database.
     */
    public function issueUnsubscribeToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'unsubscribe_token_hash' => hash('sha256', $token),
        ])->save();

        return $token;
    }

    public function matchesUnsubscribeToken(string $token): bool
    {
        return $this->unsubscribe_token_hash !== null
            && hash_equals($this->unsubscribe_token_hash, hash('sha256', $token));
    }
}
