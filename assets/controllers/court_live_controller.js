import { Controller } from '@hotwired/stimulus';

/*
 * Live Evidence Court: polls /court/{id}/live while a hearing runs and inserts only the
 * messages that are new, in conversation order, so a Skeptic reply lands under the point
 * it answers. The "typing" bubble is swapped each time. When the hearing ends, the full
 * page (with the ruling form) is loaded without replaying the entrance animation.
 */
export default class extends Controller {
    static targets = ['thread'];
    static values = { url: String, done: String, interval: { type: Number, default: 1500 } };

    connect() {
        this.stopped = false;
        this.failures = 0;
        this.tick();
    }

    disconnect() {
        this.stopped = true;
        clearTimeout(this.timer);
    }

    async tick() {
        if (this.stopped) return;
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'text/html' }, cache: 'no-store' });
            if (!response.ok) throw new Error(String(response.status));
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const list = doc.querySelector('ol[data-status]');
            if (list) {
                this.merge(list);
                if ('pending' !== list.dataset.status) {
                    this.finish();
                    return;
                }
            }
            this.failures = 0;
        } catch (error) {
            // Keep trying through short blips; give up quietly after a while.
            if (++this.failures > 20) return;
        }
        this.timer = setTimeout(() => this.tick(), this.intervalValue);
    }

    merge(list) {
        const thread = this.threadTarget;
        const incoming = Array.from(list.children).filter((el) => el.dataset.key);
        const keys = new Set(incoming.map((el) => el.dataset.key));
        // Drop items the server no longer sends (the typing bubble, the "opening" line).
        thread.querySelectorAll(':scope > [data-key]').forEach((el) => {
            if (!keys.has(el.dataset.key) || 'typing' === el.dataset.key) el.remove();
        });
        let previous = null;
        for (const item of incoming) {
            let current = thread.querySelector(`:scope > [data-key="${CSS.escape(item.dataset.key)}"]`);
            if (!current) {
                current = document.importNode(item, true);
                current.classList.add('is-arriving');
                if (previous) previous.after(current); else thread.prepend(current);
            }
            previous = current;
        }
    }

    finish() {
        const url = this.doneValue + (this.doneValue.includes('?') ? '&' : '?') + 'watched=1';
        if (window.Turbo) {
            window.Turbo.visit(url, { action: 'replace' });
        } else {
            window.location.replace(url);
        }
    }
}
