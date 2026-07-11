<?php declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PortalLoginLinkEmail extends Mailable
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
