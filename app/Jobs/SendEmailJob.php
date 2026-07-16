<?php

namespace App\Jobs;

use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    protected string $to;
    protected string $templateType;
    protected array $data;

    public function __construct(string $to, string $templateType, array $data = [])
    {
        $this->to = $to;
        $this->templateType = $templateType;
        $this->data = $data;
    }

    public function handle(EmailService $emailService): void
    {
        $emailService->send($this->to, $this->templateType, $this->data);
    }
}
