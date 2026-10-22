<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

    public function updatedSettings(): HasMany
    {
        return $this->hasMany(Setting::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('roles.name', $role)->exists();
    }

    /**
     * @param  iterable<string>  $roles
     */
    public function hasAnyRole(iterable $roles): bool
    {
        $roleNames = collect($roles)->filter()->unique()->values();

        return $roleNames->isNotEmpty()
            && $this->roles()->whereIn('roles.name', $roleNames)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(Role::ADMIN_ROLES);
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
