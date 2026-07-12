<?php

namespace App\Extensions;

use App\Contracts\Extensions\HashidsInterface;
use Hashids\Hashids as VendorHashids;
use Illuminate\Support\Arr;

class Hashids extends VendorHashids implements HashidsInterface
{
    public function decodeFirst(string $encoded, ?string $default = null): mixed
    {
        $result = $this->decode($encoded);

        return Arr::first($result, null, $default);
    }
}
