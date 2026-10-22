<?php

namespace App\Support;

class OnPrem
{
    public static function enabled(): bool
    {
        return (bool) config('onprem.enabled');
    }
}
