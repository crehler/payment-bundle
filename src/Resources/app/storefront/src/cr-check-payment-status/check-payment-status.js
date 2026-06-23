import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class CrehlerCheckPayment extends Plugin {
    static options = {
        orderId: "",
        failUrl: "",
        checkUrl: "",
        textSuccess: "",
        subtitleSuccess: "",
        textTimeout: "",
        subtitleTimeout: "",
        textDeclined: "",
        subtitleDeclined: "",
        textMismatch: "",
        subtitleMismatch: "",
        waitingTime: 120000,
        redirectTo: "",
    }

    init() {
        this._client = new HttpClient();
        this._ticks = 0;
        this._inFlight = false;
        this._finished = false;
        this._checkInterval = 3000;
        this.totalTicks = Math.floor(this.options.waitingTime / this._checkInterval);
        this._card = this.el.querySelector('.blik-auth-card') || this.el.querySelector('[data-info]');
        this._progressBar = this.el.querySelector('.blik-progress-bar');

        this._checkOrderPayment();
        this._checkPaymentInterval();
    }

    _checkPaymentInterval() {
        this.loopInterval = setInterval(this._loop.bind(this), this._checkInterval);
    }

    _loop() {
        this._ticks++;

        // Hard stop on elapsed time (independent of how many checks completed), so a
        // slow/stalled request can never block the timeout.
        if (!this._finished && this._ticks >= this.totalTicks) {
            // Wait window elapsed — do ONE reconcile check against the gateway before
            // giving up, to detect a payment that succeeded but wasn't booked locally.
            this._reconcile();

            return;
        }

        this._checkOrderPayment();
    }

    /**
     * Final reconcile after the wait window: asks the backend to compare with the
     * gateway. paid → success; mismatch (paid at gateway, not booked) → support
     * message; otherwise → timeout.
     */
    _reconcile() {
        if (this._finished) {
            return;
        }

        this._client.post(
            this.options.checkUrl,
            JSON.stringify({orderId: this.options.orderId, reconcile: true}),
            (response) => {
                try {
                    const res = JSON.parse(response);
                    if (res.status === true) {
                        this._finish('success');
                    } else if (res.mismatch === true) {
                        this._finish('mismatch');
                    } else {
                        this._finish('failed', 'timeout');
                    }
                } catch (e) {
                    this._finish('failed', 'timeout');
                }
            }
        );
    }

    _checkOrderPayment() {
        // Serialize: skip while finished or while a previous check is still in flight.
        if (this._finished || this._inFlight) {
            return;
        }

        this._inFlight = true;

        this._client.post(
            this.options.checkUrl,
            JSON.stringify({orderId: this.options.orderId}),
            (response) => {
                this._inFlight = false;
                try {
                    this._handleStatus(JSON.parse(response));
                } catch (e) {
                    console.error('Error parsing payment status response', e);
                }
            }
        );
    }

    /**
     * Three terminal outcomes, mirroring the Store API contract
     * (status / waiting / failed):
     *  - paid                       → success
     *  - failed (cancelled/declined) → stop now, show the declined state
     *  - still waiting              → keep polling until the timeout in _loop()
     */
    _handleStatus(res) {
        if (this._finished) {
            return;
        }

        if (res.status === true && res.waiting === false) {
            this._finish('success');
            return;
        }

        // Paid at the gateway but not booked in the shop — stop and show the
        // "contact support" message instead of a generic error.
        if (res.mismatch === true) {
            this._finish('mismatch');
            return;
        }

        // Terminal failure detected by the gateway/transaction state — don't wait
        // for the timeout, surface the error (and the retry action) immediately.
        if (res.failed === true || (res.status === false && res.waiting === false)) {
            this._finish('failed', 'declined');
        }
    }

    _finish(status, reason) {
        if (this._finished) {
            return;
        }
        this._finished = true;
        clearInterval(this.loopInterval);

        this._changeLayout(status, reason);

        if (status === 'success') {
            this._redirectToPage();
        }
    }

    _redirectToPage() {
        if (!this.options.redirectTo) {
            return;
        }

        const countdownEl = this.el.querySelector('.redirect-countdown');

        if (this._countdownInterval) {
            clearInterval(this._countdownInterval);
        }

        let secondsLeft = 3;

        // Build "Przekierowanie za <span>N</span> sekund..." without innerHTML so the
        // counter value is always inserted as text, never parsed as markup.
        const renderCountdown = (seconds, unit) => {
            if (!countdownEl) {
                return;
            }
            countdownEl.textContent = 'Przekierowanie za ';
            const valueEl = document.createElement('span');
            valueEl.textContent = String(seconds);
            countdownEl.appendChild(valueEl);
            countdownEl.appendChild(document.createTextNode(` ${unit}...`));
        };

        if (countdownEl) {
            renderCountdown(secondsLeft, 'sekund');
            countdownEl.style.display = 'block';
        }

        this._countdownInterval = setInterval(() => {
            secondsLeft--;
            if (secondsLeft > 0) {
                renderCountdown(secondsLeft, secondsLeft === 1 ? 'sekundę' : 'sekund');
            } else {
                if (countdownEl) {
                    countdownEl.textContent = 'Przekierowanie...';
                }
                clearInterval(this._countdownInterval);

                setTimeout(() => {
                    window.location.replace(this.options.redirectTo);
                }, 300);
            }
        }, 1000);
    }

    _changeLayout(status, reason) {
        const card = this._card;
        if (!card) {
            return;
        }

        const titleEl = card.querySelector('[data-title]') || card.querySelector('h2');
        const subtitleEl = card.querySelector('[data-subtitle]');
        const spinnerEl = card.querySelector('.spinner-border');
        const checkEl = card.querySelector('.check');
        const errorIconEl = card.querySelector('[data-error-icon]');
        const editPaymentBtn = card.querySelector('#editPayment');
        const countdownEl = card.querySelector('.redirect-countdown');

        // Spinner is only meaningful while waiting — hide it once resolved.
        if (spinnerEl) spinnerEl.classList.add('d-none');

        if (status === 'success') {
            if (checkEl) checkEl.classList.remove('d-none');
            if (errorIconEl) errorIconEl.classList.add('d-none');
            if (editPaymentBtn) editPaymentBtn.classList.add('d-none');

            if (titleEl) {
                titleEl.textContent = this.options.textSuccess || 'Płatność zakończona pomyślnie!';
                titleEl.classList.remove('text-danger');
                titleEl.classList.add('text-success');
            }
            if (subtitleEl) subtitleEl.textContent = this.options.subtitleSuccess || '';
            if (countdownEl) countdownEl.style.display = 'block';

            return;
        }

        // Paid at the gateway but the shop hasn't booked it — payment went through,
        // so no retry; tell the customer to contact support with the order number.
        if (status === 'mismatch') {
            if (checkEl) checkEl.classList.add('d-none');
            if (errorIconEl) errorIconEl.classList.remove('d-none');
            if (countdownEl) countdownEl.style.display = 'none';
            if (editPaymentBtn) editPaymentBtn.classList.add('d-none');

            if (titleEl) {
                titleEl.textContent = this.options.textMismatch || 'Płatność zrealizowana, ale niezaksięgowana';
                titleEl.classList.remove('text-success', 'text-danger');
                titleEl.classList.add('text-warning');
            }
            if (subtitleEl) {
                subtitleEl.textContent = this.options.subtitleMismatch
                    || 'Płatność została zrealizowana w bramce, ale sklep jeszcze jej nie zaksięgował. Skontaktuj się z obsługą sklepu, podając numer zamówienia.';
            }

            return;
        }

        // Failure — distinguish a gateway decline/cancel from a plain timeout so
        // the customer understands what happened. Both offer the retry action.
        const declined = reason === 'declined';

        if (checkEl) checkEl.classList.add('d-none');
        if (errorIconEl) errorIconEl.classList.remove('d-none');
        if (countdownEl) countdownEl.style.display = 'none';

        if (titleEl) {
            titleEl.textContent = declined
                ? (this.options.textDeclined || 'Płatność nie powiodła się')
                : (this.options.textTimeout || 'Upłynął czas oczekiwania na płatność');
            titleEl.classList.remove('text-success');
            titleEl.classList.add('text-danger');
        }
        if (subtitleEl) {
            subtitleEl.textContent = declined
                ? (this.options.subtitleDeclined || 'Twoja płatność została odrzucona lub anulowana.')
                : (this.options.subtitleTimeout || 'Nie otrzymaliśmy potwierdzenia płatności na czas.');
        }

        // Reveal the retry button (choose another method / retry payment).
        if (editPaymentBtn) editPaymentBtn.classList.remove('d-none');
    }
}
