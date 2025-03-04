<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait FileHandlerTrait {
    /**
     * Uploads a file to the specified directory, optionally deleting an old image.
     *
     * @param \Illuminate\Http\UploadedFile $file The file to be uploaded.
     * @param string $dir The directory where the file will be stored.
     * @param string|null $oldImage The path of the old image to be deleted, if any.
     * @return string The path of the stored file within the public directory.
     */
    public function fileUpload($file, string $dir, $oldImage = null) {
        $this->fileUnlink($oldImage);
        $fileName = time() . '_' . Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 200, '') . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/' . $dir, $fileName);
        return $dir . '/' . $fileName;
    }

    /**
     * Deletes a file from the public directory if it exists.
     *
     * @param string|null $oldImage The path of the file to be deleted, if any.
     * @return void
     */
    public function fileUnlink(?string $oldImage = null)
    {
        if (empty($oldImage)) return;

        $filePath = storage_path('app/public/' . $oldImage);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
