<?php

use App\Models\User;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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
 * Checks if the currently authenticated user has affiliate access.
 *
 * @return bool
 */
function hasAffiliateAccess(): bool
{
    if (!auth('sanctum')->check()) {
        return false;
    }

    $userId = auth('sanctum')->id();

    $user = User::query()
        ->leftJoin('admin_roles as ar', 'users.admin_role_id', '=', 'ar.id')
        ->where('users.id', $userId)
        ->select('ar.is_show_affiliate', 'ar.admin_role')
        ->first();

    if (empty($user)) {
        return false;
    }

    return $user->admin_role === 'super_admin' || (bool) $user->is_show_affiliate;
}

/**
 * Returns value after formatting currency and with $ sign.
 *
 * @param float $number
 * @return int | float
 */
function minusBeforeDollarSign(?string $currency = '', float $number = 0)
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

/**
 * Download invoice file from remote url.
 * Then save it to storage
 * Then return the full path of the saved file.
 * But before saving, check if the file already exists in the storage.
 * @param string $url
 * @return Response
 */
function downloadInvoiceFile(string $url): string
{
    $file_name = basename($url);
    $encoded_file_name = rawurlencode($file_name);

    $url = str_replace($file_name, $encoded_file_name, $url);

    $file_path = storage_path('app/public/invoices/' . $file_name);

    if (!file_exists($file_path)) {
        try {
            $file = file_get_contents($url);
            file_put_contents($file_path, $file);
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    return url(Storage::url('invoices/' . rawurlencode($file_name)));
}
