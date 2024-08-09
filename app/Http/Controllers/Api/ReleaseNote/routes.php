<?php

use App\Http\Controllers\Api\ReleaseNote\ReleaseNoteController;
use Illuminate\Support\Facades\Route;


Route::prefix('release-note')->as('platform.')
    ->controller(ReleaseNoteController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('/', 'releaseNoteList')->name('notes');
        $route->get('show/{noteId}', 'releaseNote')->name('note.show');
        $route->post('create-note', 'createReleaseNote')->name('note.create');
        $route->put('update-note/{noteId}', 'updateReleaseNote')->name('note.update');
        $route->delete('note-delete/{noteId}', 'releaseNoteDelete')->name('note.delete');
        $route->post('upload-image', 'uploadImage')->name('upload.image');
    });
