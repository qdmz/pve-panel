<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send an email using a template type.
     */
    public function send(string $to, string $templateType, array $data = [], array $attachments = []): bool
    {
        try {
            $template = EmailTemplate::getByType($templateType);

            if (!$template) {
                Log::warning("Email template not found for type: {$templateType}");
                return false;
            }

            $subject = $template->compileSubject($data);
            $content = $template->compileContent($data);

            Mail::html($content, function ($message) use ($to, $subject, $attachments) {
                $message->to($to)
                    ->subject($subject);

                foreach ($attachments as $attachment) {
                    if (is_string($attachment)) {
                        $message->attach($attachment);
                    } else {
                        $message->attachData(
                            $attachment['content'] ?? '',
                            $attachment['name'] ?? 'attachment',
                            $attachment['options'] ?? []
                        );
                    }
                }
            });

            Log::info("Email sent to {$to} with template: {$templateType}");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send email to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send verification/activation email.
     */
    public function sendVerificationEmail(User $user): bool
    {
        $token = app('auth.password.broker')->createToken($user);

        return $this->send($user->email, 'register', [
            'username' => $user->name,
            'email' => $user->email,
            'site_name' => config('app.name', 'PVE Panel'),
            'site_url' => config('app.url'),
            'verify_url' => url("/verify-email?token={$token}"),
        ]);
    }

    /**
     * Send password reset email.
     */
    public function sendPasswordReset(User $user, string $token): bool
    {
        return $this->send($user->email, 'reset_password', [
            'username' => $user->name,
            'email' => $user->email,
            'site_name' => config('app.name', 'PVE Panel'),
            'reset_url' => url("/password/reset/{$token}?email={$user->email}"),
            'token' => $token,
        ]);
    }

    /**
     * Send recharge success notification.
     */
    public function sendRechargeSuccess(User $user, Payment $payment): bool
    {
        return $this->send($user->email, 'recharge', [
            'username' => $user->name,
            'amount' => number_format($payment->amount, 2),
            'balance' => number_format($user->balance, 2),
            'payment_method' => $payment->payment_method,
            'transaction_id' => $payment->transaction_id,
            'site_name' => config('app.name', 'PVE Panel'),
            'time' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Send VM created notification.
     */
    public function sendVmCreated(User $user, VirtualMachine $vm): bool
    {
        return $this->send($user->email, 'vm_create', [
            'username' => $user->name,
            'vm_name' => $vm->name,
            'vm_type' => strtoupper($vm->type),
            'cpu' => $vm->cpu,
            'memory' => $vm->memory,
            'disk' => $vm->disk,
            'ip' => $vm->ip ?? '分配中...',
            'os' => $vm->os_template ?? 'N/A',
            'expires_at' => $vm->expires_at->format('Y-m-d H:i:s'),
            'site_name' => config('app.name', 'PVE Panel'),
            'control_panel_url' => url('/user/instances/' . $vm->id),
        ]);
    }

    /**
     * Send VM expiry warning.
     */
    public function sendVmExpiry(User $user, VirtualMachine $vm, int $daysLeft): bool
    {
        return $this->send($user->email, 'vm_expiry', [
            'username' => $user->name,
            'vm_name' => $vm->name,
            'days_left' => $daysLeft,
            'expires_at' => $vm->expires_at->format('Y-m-d H:i:s'),
            'renew_url' => url('/user/instances/' . $vm->id . '/renew'),
            'site_name' => config('app.name', 'PVE Panel'),
        ]);
    }

    /**
     * Send ticket reply notification.
     */
    public function sendTicketReply(User $user, Ticket $ticket): bool
    {
        return $this->send($user->email, 'ticket_reply', [
            'username' => $user->name,
            'ticket_subject' => $ticket->subject,
            'ticket_status' => $ticket->status,
            'ticket_url' => url('/user/tickets/' . $ticket->id),
            'site_name' => config('app.name', 'PVE Panel'),
        ]);
    }

    /**
     * Send welcome email.
     */
    public function sendWelcome(User $user): bool
    {
        return $this->send($user->email, 'welcome', [
            'username' => $user->name,
            'email' => $user->email,
            'site_name' => config('app.name', 'PVE Panel'),
            'site_url' => config('app.url'),
            'login_url' => url('/login'),
            'panel_url' => url('/user'),
        ]);
    }
}
