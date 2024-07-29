<?php

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

//*******************Response Modifier Start************************/

/**
* Returns a custom response with success status and data.
*
* @param mixed $data
* @param string $message
* @param int $status
* @return Response
*/
function withSuccess(mixed $data = new stdClass, string $message = '', int $status = 200)
{
    return customResponse($data, true, $status, $message);
}

/**
 * Returns a custom response with success status and data.
 *
 * @param ResourceCollection $data
 * @param string $message
 * @param int $status
 * @return Response
 */
function withSuccessResourceList(ResourceCollection $data, string $message = '', int $status = 200)
{
    return customResponse($data->response()->getData(), true, $status, $message);
}

/**
 * Returns a custom response with error status and data.
 *
 * @param string $message
 * @param int $status
 * @param mixed $data
 * @return Response
 */
function withError(string $message, int $status = 400, mixed $data = new stdClass)
{
    return customResponse($data, false, $status, $message);
}

/**
 * Returns a custom response with validation error status and data.
 *
 * @param object $message
 * @param mixed $data
 * @return Response
 */
function withValidationError(object $message, mixed $data = new stdClass): Response
{
    return response([
        'json_data' => $data,
        'success' => false,
        'status' => 422,
        'messages' => (object) $message
    ], 422);
}

/**
 * Returns a custom response with the given data, success status, status code, and message.
 *
 * @param mixed $data
 * @param bool $success
 * @param int $status
 * @param string $message
 * @return Response
 */
function customResponse(mixed $data, bool $success, int $status, string $message): Response
{
    return response([
        'json_data' => $data,
        'success' => (bool) $success,
        'status' => (int) $status,
        'message' => (string) $message
    ], $status);
}

//*******************Response Modifier End************************/


/**
 * Returns value after formatting currency and with $ sign.
 *
 * @param float $number
 * @return int | float
 */
function minusBeforeDollarSign(?string $currency = '', float $number)
{
    //if contains comma, remove it
    if (strpos($number, ',') !== false) {

        $senitized_number = str_replace(',', '', $number);

        $formatted = $senitized_number < 0 ? '-'.$currency . number_format($senitized_number * (-1)) : $currency . $number;
    } else {

        $formatted = $number < 0 ? '-'.$currency . $number * (-1) : $currency . $number;
    }

    return $formatted;
}
