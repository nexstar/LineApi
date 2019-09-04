<?php
namespace App\Service\Other;

use Symfony\Component\HttpFoundation\Response;

class ResponseService
{
    public function JSON_HTTP_OK($Array)
    {
        return response()->json($Array,Response::HTTP_OK);
    }

    public function HTTP_OK($Array)
    {
        return response($Array,Response::HTTP_OK);
    }

    public function HTTP_BAD_REQUEST($Array)
    {
        return response($Array,Response::HTTP_BAD_REQUEST);
    }

    public function HTTP_NO_CONTENT($Array)
    {
        return response($Array,Response::HTTP_NO_CONTENT);
    }

    public function HTTP_UNAUTHORIZED($Array)
    {
        return response($Array,Response::HTTP_UNAUTHORIZED);
    }
}