<?php

namespace App\Enums;

enum ProductType: string
{
    case SIMPLE = 'simple';
    case BUNDLE = 'bundle';
    case VARIANT = 'variant';
}
