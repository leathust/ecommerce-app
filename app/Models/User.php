<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUSES = [
        'active' => 'Hoạt động',
        'inactive' => 'Không hoạt động',
        'blocked' => 'Đã khóa',
    ];

    public const CUSTOMER_SEGMENTS = [
        'new' => [
            'label' => 'Khách mới',
            'description' => 'Chưa có hoặc mới có một đơn giao thành công.',
            'badge_class' => 'text-bg-info',
        ],
        'returning' => [
            'label' => 'Khách quay lại',
            'description' => 'Đã có từ hai đơn giao thành công.',
            'badge_class' => 'text-bg-primary',
        ],
        'loyal' => [
            'label' => 'Khách thân thiết',
            'description' => 'Có ít nhất năm đơn giao thành công hoặc chi tiêu từ 5 triệu đồng.',
            'badge_class' => 'text-bg-success',
        ],
        'cancellation_risk' => [
            'label' => 'Nhiều đơn bị hủy',
            'description' => 'Có ít nhất ba đơn bị hủy và tỷ lệ hủy từ 50%.',
            'badge_class' => 'text-bg-danger',
        ],
    ];

    public const LOYAL_MIN_DELIVERED_ORDERS = 5;
    public const LOYAL_MIN_SPENDING = 5000000;
    public const CANCELLATION_RISK_MIN_ORDERS = 3;
    public const CANCELLATION_RISK_RATE = 0.5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'status',
        'must_change_password',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withPivot('created_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderStatusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'changed_by');
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'actor_id');
    }

    public function updatedSettings(): HasMany
    {
        return $this->hasMany(Setting::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getDisplayNameAttribute(): string
    {
        $fullName = trim(implode(' ', array_filter([$this->first_name, $this->last_name])));

        return $fullName !== '' ? $fullName : $this->name;
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'roles',
            fn (Builder $roleQuery) => $roleQuery->where('roles.is_admin', true),
        );
    }

    public function scopeSearchCustomers(Builder $query, ?string $search): Builder
    {
        return $query->searchDirectory($search);
    }

    public function scopeSearchDirectory(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        foreach (preg_split('/\s+/u', trim($search)) ?: [] as $term) {
            $query->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        return $query;
    }

    public function hasRole(string $role): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $role);
        }

        return $this->roles()->where('roles.name', $role)->exists();
    }

    /**
     * @param  iterable<string>  $roles
     */
    public function hasAnyRole(iterable $roles): bool
    {
        $roleNames = collect($roles)->filter()->unique()->values();

        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('name')->intersect($roleNames)->isNotEmpty();
        }

        return $roleNames->isNotEmpty()
            && $this->roles()->whereIn('roles.name', $roleNames)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('is_admin', true);
        }

        return $this->roles()->where('roles.is_admin', true)->exists();
    }

    public function isCustomer(): bool
    {
        return ! $this->isAdmin();
    }

    /** @return Collection<int, string> */
    public function effectivePermissionNames(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()->orderBy('name')->pluck('name');
        }

        if ($this->relationLoaded('roles')
            && $this->roles->every(fn (Role $role): bool => $role->relationLoaded('permissions'))) {
            return $this->roles
                ->flatMap->permissions
                ->pluck('name')
                ->unique()
                ->sort()
                ->values();
        }

        return Permission::query()
            ->whereHas('roles.users', fn (Builder $query) => $query->whereKey($this->getKey()))
            ->orderBy('name')
            ->pluck('name');
    }

    public function scopeAdministrators(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('roles.is_admin', true));
    }

    /**
     * Classify a customer from live order aggregates without persisting a segment.
     *
     * @param  array{total_orders?: int, delivered_orders?: int, cancelled_orders?: int, delivered_spending?: float}  $stats
     * @return array{key: string, label: string, description: string, badge_class: string}
     */
    public function customerSegment(array $stats = []): array
    {
        $totalOrders = (int) ($stats['total_orders'] ?? $this->orders_count ?? 0);
        $deliveredOrders = (int) ($stats['delivered_orders'] ?? $this->delivered_orders_count ?? 0);
        $cancelledOrders = (int) ($stats['cancelled_orders'] ?? $this->cancelled_orders_count ?? 0);
        $deliveredSpending = (float) ($stats['delivered_spending'] ?? $this->delivered_spending ?? 0);
        $cancellationRate = $totalOrders > 0 ? $cancelledOrders / $totalOrders : 0;

        $key = match (true) {
            $cancelledOrders >= self::CANCELLATION_RISK_MIN_ORDERS
                && $cancellationRate >= self::CANCELLATION_RISK_RATE => 'cancellation_risk',
            $deliveredOrders >= self::LOYAL_MIN_DELIVERED_ORDERS
                || $deliveredSpending >= self::LOYAL_MIN_SPENDING => 'loyal',
            $deliveredOrders >= 2 => 'returning',
            default => 'new',
        };

        return ['key' => $key, ...self::CUSTOMER_SEGMENTS[$key]];
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn (Builder $query) => $query->where('permissions.name', $permission))
            ->exists();
    }

    /**
     * @param  iterable<string>  $permissions
     */
    public function hasAnyPermission(iterable $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionNames = collect($permissions)->filter()->unique()->values();

        return $permissionNames->isNotEmpty()
            && $this->roles()
                ->whereHas(
                    'permissions',
                    fn (Builder $query) => $query->whereIn('permissions.name', $permissionNames),
                )
                ->exists();
    }

    /**
     * Filter users that have at least one of the supplied roles.
     *
     * @param  string|array<string>  $roles
     */
    public function scopeRole(Builder $query, string|array $roles): Builder
    {
        $roleNames = is_array($roles) ? $roles : [$roles];

        return $query->whereHas(
            'roles',
            fn (Builder $roleQuery) => $roleQuery->whereIn('roles.name', $roleNames),
        );
    }

    /**
     * Filter users that receive a permission through any assigned role.
     *
     * @param  string|array<string>  $permissions
     */
    public function scopePermission(Builder $query, string|array $permissions): Builder
    {
        $permissionNames = is_array($permissions) ? $permissions : [$permissions];

        return $query->whereHas(
            'roles.permissions',
            fn (Builder $permissionQuery) => $permissionQuery->whereIn('permissions.name', $permissionNames),
        );
    }
}
