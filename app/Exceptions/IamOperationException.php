<?php

namespace App\Exceptions;

use DomainException;

class IamOperationException extends DomainException
{
    public static function forbidden(string $message = 'Bạn không có quyền thực hiện thao tác quản trị này.'): self
    {
        return new self($message);
    }

    public static function selfRoleChange(): self
    {
        return new self('Bạn không thể tự thay đổi vai trò quản trị của chính mình.');
    }

    public static function selfDeactivation(): self
    {
        return new self('Bạn không thể tự khóa hoặc vô hiệu hóa tài khoản của chính mình.');
    }

    public static function selfDeletion(): self
    {
        return new self('Bạn không thể tự xóa tài khoản quản trị của chính mình.');
    }

    public static function lastSuperAdmin(): self
    {
        return new self('Hệ thống phải luôn còn ít nhất một super admin đang hoạt động.');
    }

    public static function invalidRoles(): self
    {
        return new self('Danh sách vai trò không hợp lệ hoặc chứa vai trò không thuộc khu vực quản trị.');
    }

    public static function systemRoleReadOnly(): self
    {
        return new self('Vai trò super_admin được bảo vệ tuyệt đối và không thể thay đổi.');
    }

    public static function systemRoleNameReadOnly(): self
    {
        return new self('Tên của vai trò hệ thống không thể thay đổi.');
    }

    public static function reservedRoleName(): self
    {
        return new self('Tên vai trò này được dành riêng cho role hệ thống.');
    }

    public static function roleInUse(): self
    {
        return new self('Không thể xóa vai trò đang được gán cho tài khoản. Hãy chuyển người dùng sang vai trò khác trước.');
    }

    public static function selfPermissionLockout(): self
    {
        return new self('Thay đổi này sẽ làm bạn mất quyền quản lý phân quyền. Hãy giữ một role quản trị quyền khác trước khi tiếp tục.');
    }
}
