<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminAuditLog extends Model
{
    public const ACTION_LABELS = [
        'user.created' => 'Tạo tài khoản',
        'user.updated' => 'Cập nhật tài khoản',
        'user.deleted' => 'Xóa tài khoản',
        'user.deletion_blocked' => 'Khóa thay cho xóa',
        'user.status_updated' => 'Đổi trạng thái tài khoản',
        'user.roles_synced' => 'Đồng bộ role tài khoản',
        'role.created' => 'Tạo role',
        'role.updated' => 'Cập nhật role',
        'role.deleted' => 'Xóa role',
        'role.permissions_synced' => 'Đồng bộ permission',
        'settings.group_updated' => 'Cập nhật cài đặt hệ thống',
        'menu.created' => 'Tạo menu',
        'menu.updated' => 'Cập nhật menu',
        'menu.deleted' => 'Xóa menu',
        'menu.status_updated' => 'Đổi trạng thái menu',
        'menu.moved' => 'Di chuyển menu',
        'menu.reordered' => 'Sắp xếp menu',
    ];

    public const SUBJECT_LABELS = [
        'user' => 'Tài khoản',
        'role' => 'Vai trò',
        'setting' => 'Cài đặt hệ thống',
        'menu' => 'Menu website',
    ];

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'before_values',
        'after_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    public function getSubjectLabelAttribute(): string
    {
        return match ($this->subject_type) {
            User::class => self::SUBJECT_LABELS['user'],
            Role::class => self::SUBJECT_LABELS['role'],
            Setting::class => self::SUBJECT_LABELS['setting'],
            Menu::class => self::SUBJECT_LABELS['menu'],
            default => class_basename($this->subject_type),
        };
    }
}
