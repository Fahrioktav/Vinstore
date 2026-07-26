<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'store_name',
        'category',
        'description',
        'location',
        'latitude',
        'longitude',
        'photo',
        'available_balance',
        'withdrawn_balance',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'withdrawn_balance' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->public_id)) {
                $store->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'STR' . random_int(10000000, 99999999);
        } while (DB::table('stores')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function auctions()
    {
        return $this->hasMany(Auction::class);
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function barterRequestsSent()
    {
        return $this->hasMany(BarterRequest::class, 'requester_store_id');
    }

    public function barterRequestsReceived()
    {
        return $this->hasMany(BarterRequest::class, 'responder_store_id');
    }

    /**
     * Menghitung jarak antara toko ini dengan koordinat yang diberikan (dalam kilometer)
     * Menggunakan formula Haversine
     *
     * @param float $userLat Latitude user
     * @param float $userLng Longitude user
     * @return float Jarak dalam kilometer
     */
    public function calculateDistance(float $userLat, float $userLng): float
    {
        if ($this->latitude === null || $this->longitude === null) {
            return PHP_FLOAT_MAX; // Return nilai sangat besar jika koordinat tidak tersedia
        }

        $earthRadius = 6371; // Radius bumi dalam kilometer

        // Konversi derajat ke radian
        $latFrom = deg2rad($userLat);
        $lonFrom = deg2rad($userLng);
        $latTo = deg2rad($this->latitude);
        $lonTo = deg2rad($this->longitude);

        // Haversine formula
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Scope untuk mencari toko terdekat berdasarkan radius
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $latitude Latitude user
     * @param float $longitude Longitude user
     * @param float $radius Radius dalam kilometer (default: 10)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNearby($query, float $latitude, float $longitude, float $radius = 10)
    {
        // Menggunakan formula Haversine dalam SQL untuk efisiensi
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        return $query
            ->select('*')
            ->selectRaw("{$haversine} AS distance", [$latitude, $longitude, $latitude])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("{$haversine} <= ?", [$latitude, $longitude, $latitude, $radius])
            ->orderBy('distance', 'asc');
    }

    /**
     * Static method untuk mendapatkan toko terdekat
     *
     * @param float $latitude Latitude user
     * @param float $longitude Longitude user
     * @param float $radius Radius dalam kilometer (default: 10)
     * @param int|null $limit Jumlah hasil maksimal (null = semua)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNearbyStores(float $latitude, float $longitude, float $radius = 10, ?int $limit = null)
    {
        $query = self::nearby($latitude, $longitude, $radius);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
