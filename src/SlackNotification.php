<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\json_encode;

final class SlackNotification
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function sendNotification(string $text): void
    {
        $payload = [];
        $payload['text'] = $text;
        $payload['username'] = 'Local HTTPS Companion';
        $payload['icon_emoji'] = ':closed_lock_with_key:';
        $slackToken = getenv('SLACK_TOKEN');
        if (!$slackToken) {
            return;
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://hooks.slack.com/services/' . $slackToken,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $output = $this->output;
            if ($output instanceof ConsoleOutputInterface) {
                $output = $output->getErrorOutput();
            }

            $output->writeln(sprintf('cURL Error #: <error>%s</error>', $err));
        }

        $this->output->writeln('send notification to slack');
    }
}
