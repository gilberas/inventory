<?php

namespace App\Models;

/**
 * Backward-compatibility alias for Category.
 * All existing code that type-hints ProductCategory continues to work;
 * new code should use Category directly.
 */
class ProductCategory extends Category
{
    //
}
