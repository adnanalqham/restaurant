<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name', 'name_en', 'username', 'password',
        'role_id', 'permissions', 'is_active', 'can_print', 'printer_mac',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'permissions' => 'array',
        'is_active'   => 'boolean',
        'can_print'   => 'boolean',
    ];

    // ────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // ────────────────────────────────────────────────────────────
    // Role helpers (role name comes from joined roles table)
    // ────────────────────────────────────────────────────────────

    /**
     * Get the role name (English) from the roles table.
     * We cache it in a property to avoid repeated queries.
     */
    public function getRoleName(): string
    {
        return $this->roleModel?->name ?? 'unknown';
    }

    public function isAdmin(): bool
    {
        return $this->getRoleName() === 'admin';
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->getRoleName(), (array) $roles);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) return true;
        $perms = $this->permissions ?? [];
        return in_array($permission, $perms);
    }

    // ────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────

    public function orders()
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }
}
