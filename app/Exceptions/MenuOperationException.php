<?php

namespace App\Exceptions;

use DomainException;

class MenuOperationException extends DomainException
{
    public static function forbidden(): self
    {
        return new self('Bạn không có quyền thực hiện thao tác với menu website.');
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }

    public static function hasChildren(): self
    {
        return new self('Không thể xóa menu đang có mục con. Hãy xử lý các mục con trước.');
    }
}
