<?php declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deliberately queued, not sent inline: sending this synchronously would let
 * `POST /portal/{tenant}/login`'s response time leak whether the submitted
 * email matched a real Account (SMTP/render latency only on a match),
 * defeating the endpoint's anti-enumeration guarantee. Requires a real async
 * QUEUE_CONNECTION in production (not `sync`) to actually close that gap —
 * `sync` still executes inline and does not protect against the timing
 * side-channel; this is a deployment-config requirement, not just a code one.
 */
class PortalLoginLinkEmail extends Mailable implements ShouldQueue
{
    use Queueable;

    public string $loginUrl;

    public function __construct(Tenant $tenant, string $rawToken)
    {
        $this->loginUrl = route('portal.login.verify', [
            'tenantSlug' => $tenant->slug,
            'token' => $rawToken,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Liên kết đăng nhập cổng khách hàng',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-login-link',
            with: [
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
