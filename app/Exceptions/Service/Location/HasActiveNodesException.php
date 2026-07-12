<?php

namespace App\Exceptions\Service\Location;

use App\Exceptions\DisplayException;
use Illuminate\Http\Response;

class HasActiveNodesException extends DisplayException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
