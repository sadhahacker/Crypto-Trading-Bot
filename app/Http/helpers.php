<?php

/**
 * Format the error message into json error response.
 *
 * @param array|string $message  Error message
 * @param int $statusCode
 * @return \Illuminate\Http\JsonResponse json response
 */
function errorResponse(array|string $message, int $statusCode = 400): \Illuminate\Http\JsonResponse
{
    return response()->json(['success' => false, 'message' => $message], $statusCode);
}

/**
 * Format success message/data into json success response.
 *
 * @param string $message  Success message
 * @param array|string $data  Data of the response
 * @param int $statusCode
 * @return \Illuminate\Http\JsonResponse json response
 */
function successResponse(string $message = '', array|string $data = '', int $statusCode = 200): \Illuminate\Http\JsonResponse
{
    $response = ['success' => true];

    // if message given
    if (! empty($message)) {
        $response['message'] = $message;
    }

    // If data given
    if (! empty($data)) {
        $response['data'] = $data;
    }

    return response()->json($response, $statusCode);
}


function assertLink(string $type, string $key){
    return asset(\Config::get("link.{$type}.{$key}"));
}

function isActiveMenu($pattern)
{
    return request()->is($pattern) ? 'active' : '';
}
