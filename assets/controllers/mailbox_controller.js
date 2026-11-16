import { Controller } from '@hotwired/stimulus';

// Official IMAP/SMTP settings for providers that still allow app-password sign-in.
const PROVIDERS = {
    gmail: { imap: 'imap.gmail.com', smtp: 'smtp.gmail.com', port: '465', domains: ['gmail.com', 'googlemail.com'] },
    yahoo: { imap: 'imap.mail.yahoo.com', smtp: 'smtp.mail.yahoo.com', port: '465', domains: ['yahoo.com', 'ymail.com', 'rocketmail.com'] },
    icloud: { imap: 'imap.mail.me.com', smtp: 'smtp.mail.me.com', port: '587', domains: ['icloud.com', 'me.com', 'mac.com'] },
    zoho: { imap: 'imap.zoho.com', smtp: 'smtp.zoho.com', port: '465', domains: ['zoho.com', 'zohomail.com'] },
    microsoft: { domains: ['outlook.com', 'hotmail.com', 'live.com', 'msn.com'] },
};

export default class extends Controller {
    static targets = ['preset', 'sender', 'imap', 'smtp', 'port', 'servers', 'guide', 'recipient'];

    connect() {
        this.onInvalid = (event) => { const details = event.target.closest('details'); if (details) details.open = true; };
        this.element.addEventListener('invalid', this.onInvalid, true);
        this.showGuide(this.presetTarget.value);
    }

    disconnect() { this.element.removeEventListener('invalid', this.onInvalid, true); }

    // Picks the provider from the address the user types, unless they already chose one by hand.
    detect() {
        if (this.presetTarget.dataset.touched === '1') return;
        const domain = (this.senderTarget.value.split('@')[1] || '').toLowerCase().trim();
        const match = Object.entries(PROVIDERS).find(([key, provider]) => provider.domains.includes(domain) || (key === 'yahoo' && domain.startsWith('yahoo.')));
        const key = match ? match[0] : (domain ? 'custom' : this.presetTarget.value);
        if (key !== this.presetTarget.value) {
            this.presetTarget.value = key;
            this.apply(key);
        }
    }

    preset(event) {
        this.presetTarget.dataset.touched = '1';
        this.apply(event.target.value);
    }

    apply(key) {
        const provider = PROVIDERS[key];
        if (provider && provider.imap) {
            this.imapTarget.value = provider.imap;
            this.smtpTarget.value = provider.smtp;
            this.portTarget.value = provider.port;
            this.serversTarget.open = false;
        } else if (key === 'custom') {
            this.serversTarget.open = true;
        }
        this.showGuide(key);
    }

    showGuide(key) {
        this.guideTargets.forEach((guide) => { guide.hidden = guide.dataset.provider !== key; });
    }
}
