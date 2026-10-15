<?php

namespace App\Jobs;

use App\Services\ImgBBService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deletes a replaced or removed ImgBB image off the request thread.
 *
 * Product saves used to call ImgBB inline, *before* the row was written. A
 * slow api.imgbb.com (a shop on mobile data, an ImgBB outage) held the PATCH
 * open for its full 30s HTTP timeout, so the save the cashier asked for never
 * happened — and because the delete ran first, a save that did fail left the
 * product pointing at a photo that was already gone.
 *
 * Queueing it makes a product save exactly as fast as the database. The worst
 * case degrades to "an orphan file on ImgBB", which is strictly better than a
 * broken save or a broken image.
 */
class DeleteImgbbImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A delete link that has not worked in two tries is not worth more. */
    public int $tries = 2;

    /** Long enough for ImgBB, short enough that one bad job cannot pile up. */
    public int $timeout = 30;

    public function __construct(
        private readonly ?string $deleteUrl,
    ) {}

    public function handle(ImgBBService $imgbb): void
    {
        if (blank($this->deleteUrl)) {
            return;
        }

        $imgbb->delete($this->deleteUrl);
    }
}
