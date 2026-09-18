<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BookingAccessLink extends Model
{
    use HasFactory;

    public const DRIVER = 'driver_control';

    public const CUSTOMER = 'customer_tracking';

    public const RECEIPT = 'receipt';

    protected $fillable = [
        'booking_id', 'driver_id', 'type', 'token_hash', 'channel_key',
        'expires_at', 'revoked_at', 'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public static function issue(Booking $booking, string $type, ?int $driverId = null, ?\DateTimeInterface $expiresAt = null): array
    {
        static::where('booking_id', $booking->id)
            ->where('type', $type)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = Str::random(64);
        $link = static::create([
            'booking_id' => $booking->id,
            'driver_id' => $driverId,
            'type' => $type,
            'token_hash' => hash('sha256', $token),
            'channel_key' => $type === self::CUSTOMER ? Str::random(48) : null,
            'expires_at' => $expiresAt,
        ]);

        return [$link, $token];
    }

    public static function issueIfMissing(Booking $booking, string $type, ?\DateTimeInterface $expiresAt = null): ?array
    {
        $existing = static::active()->where('booking_id', $booking->id)->where('type', $type)->first();

        return $existing ? null : static::issue($booking, $type, null, $expiresAt);
    }

    public static function resolveToken(string $token, string $type): ?self
    {
        $link = static::active()
            ->where('type', $type)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($link) {
            $link->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $link;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
