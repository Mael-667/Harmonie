<?php

namespace App\Enum;

enum PermissionEnum: string
{
    case Read  = "read";
    case Write = "write";
    case Edit  = "edit";
    case Delete = "delete";
}
