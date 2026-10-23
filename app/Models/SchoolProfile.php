<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A School account's own details, and its connection to the school's
 * installation of the school management software: the code that
 * installation identifies itself with and the secret both sides sign
 * their requests with (SchoolConnectionSignature). Made with the account,
 * so every School has one.
 */
#[Fillable(['user_id', 'name', 'eiin', 'address', 'phone', 'installation_url', 'connected_at'])]
class SchoolProfile extends Model
{
    use HasFactory;

    /**
     * Never shown in a list or a log — only on the School's own connection page.
     *
     * @var list<string>
     */
    protected $hidden = ['connection_secret'];

    protected static function booted(): void
    {
        static::creating(function (SchoolProfile $profile): void {
            $profile->connection_code ??= self::newConnectionCode();
            $profile->connection_secret ??= self::newSecret();
        });
    }

    protected function casts(): array
    {
        return [
            'connection_secret' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The School's profile, made the first time it is needed.
     */
    public static function for(User $school): self
    {
        return self::query()->firstOrCreate(['user_id' => $school->id]);
    }

    public function isConnected(): bool
    {
        return $this->connected_at !== null;
    }

    /**
     * A new secret, which disconnects the installation until it is given
     * the new one — for when the old one may have been seen by someone else.
     */
    public function regenerateSecret(): void
    {
        $this->forceFill([
            'connection_secret' => self::newSecret(),
            'installation_url' => null,
            'connected_at' => null,
        ])->save();
    }

    /**
     * The installation has proved it holds the secret: where it answers.
     */
    public function markConnected(string $installationUrl): void
    {
        $this->forceFill([
            'installation_url' => rtrim($installationUrl, '/'),
            'connected_at' => now(),
        ])->save();
    }

    private static function newConnectionCode(): string
    {
        do {
            $code = 'QB-'.Str::upper(Str::random(10));
        } while (self::query()->where('connection_code', $code)->exists());

        return $code;
    }

    private static function newSecret(): string
    {
        return Str::random(64);
    }
}
