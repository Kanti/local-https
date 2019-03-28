<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use function Safe\json_encode;

final class SlackNotification
{
    public function sendNotification(array $payload): void
    {
        $payload['username'] = $payload['username'] ?? 'Local HTTPS Companion';
        $payload['icon_emoji'] = $payload['icon_emoji'] ?? ':closed_lock_with_key:';
        $slackToken = getenv('SLACK_TOKEN');
        if (!$slackToken) {
            return;
        }
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://hooks.slack.com/services/' . $slackToken,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo 'cURL Error #:' . $err;
        }
    }
}
