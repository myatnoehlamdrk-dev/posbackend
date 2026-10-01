<?php

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Route definitions live in `routes/api_routes.php` and are mounted here twice:
| once unversioned and once under `/api/v1`.
|
| Versioning exists so an old build keeps working while a new one migrates,
| which means both sets have to coexist -- an installed POS terminal is not
| something you can push an update to. Mounting one shared definition twice
| keeps them identical by construction; copying the list would let the two
| drift, and a route present in only one copy is the expensive kind of bug to
| chase down.
|
| The unversioned mount can be dropped in the same release that introduces v2,
| never before.
|
*/

require_once __DIR__.'/api_routes.php';

// Legacy, unversioned. Kept for builds already in the field.
pos_define_api_routes();

// Current version. `Route::prefix()` returns a registrar whose `group()` takes
// only the callback, so the prefix goes on the registrar rather than as the
// attributes argument the plain `Route::group()` expects. The callback has to
// be a closure, not a function name: a string is treated as a file to require.
Route::prefix('v1')->group(static fn () => pos_define_api_routes());