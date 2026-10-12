<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Thank You" push sent right after a feedback submission is stored.
 *
 * Unlike the stock pushes this is NOT an alert: the payload carries
 * `category: system`, so the app files it under the System tab of the
 * notifications page, and tapping it opens a document-style detail screen
 * (the same shape as the daily stock report) instead of a product page.
 * The detail screen rebuilds the sentence from the {v1} tags in `data`,
 * so the message is translated where it is displayed.
 *
 * The claim is keyed by the feedback id: TiDB lets two workers pop the same
 * queue row, and only the worker holding the lock may send.
 */
class SendFeedbackThankYouJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $shopId,
        private readonly int $userId,
        private readonly string $userName,
        private readonly int $feedbackId,
        private readonly string $feedbackType,
        private readonly string $feedbackMessage,
    ) {}

    public function handle(FcmService $fcmService): void
    {
        if (!$this->userId) {
            return;
        }

        if (!$this->claimEvent()) {
            return;
        }

        $body = $this->userName !== ''
            ? 'Thank you for your feedback, ' . $this->userName . '!'
            : 'Thank you for your feedback!';

        // FCM caps the whole message at 4KB, and a 2000-character feedback
        // written in Burmese/Thai/Japanese is ~6KB of UTF-8 — over the limit
        // the API rejects with INVALID_ARGUMENT, which sendToToken also reads
        // as "token is dead" and deactivates. The push only needs to preview
        // the text (the full record lives behind GET /feedback), so echo a
        // truncated copy.
        $preview = Str::limit($this->feedbackMessage, 500);

        $fcmService->sendToUser(
            $this->userId,
            'Thank You',
            $body,
            [
                'type' => 'feedback_thanks',
                'category' => 'system',
                'feedback_id' => (string) $this->feedbackId,
                'feedback_type' => $this->feedbackType,
                'user_name' => $this->userName,
                'message' => $preview,
            ],
            $this->shopId,
        );
    }

    /**
     * Atomically claims this feedback event, same double-pop protection as
     * the stock jobs: the first worker holding the lock sends, the duplicate
     * pop finds the key and exits.
     */
    private function claimEvent(): bool
    {
        $key = 'feedback_thanks:feedback_' . $this->feedbackId;
        $lock = Cache::lock($key . ':lock', 15);

        if (!$lock->get()) {
            return false;
        }

        try {
            if (Cache::has($key)) {
                return false;
            }
            Cache::put($key, true, now()->addHours(24));
            return true;
        } finally {
            $lock->release();
        }
    }
}
