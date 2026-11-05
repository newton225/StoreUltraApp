<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Employee = 'employee';
    case Agent = 'agent';
}
