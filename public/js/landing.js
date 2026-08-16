/* Takt landing page behaviour — ported from the React prototype.
   Clock, live timer, calendar hover detail, signup modal. No dependencies. */
(function () {
    'use strict';

    var pad = function (n) { return String(n).padStart(2, '0'); };

    /* ── analog clock (smooth seconds, ~5 fps) ─────────────────────────── */
    function initClock() {
        var hour = document.getElementById('taClockHour');
        var min = document.getElementById('taClockMin');
        var sec = document.getElementById('taClockSec');
        var dow = document.getElementById('taClockDow');
        var date = document.getElementById('taClockDate');
        if (!hour) return;

        var last = 0;
        function render(now) {
            var ms = now.getMilliseconds();
            var s = now.getSeconds() + ms / 1000;
            var m = now.getMinutes() + s / 60;
            var h = (now.getHours() % 12) + m / 60;
            hour.setAttribute('transform', 'rotate(' + (h * 30) + ' 100 100)');
            min.setAttribute('transform', 'rotate(' + (m * 6) + ' 100 100)');
            sec.setAttribute('transform', 'rotate(' + (s * 6) + ' 100 100)');
            dow.textContent = now.toLocaleString('en-US', { weekday: 'short' }).toUpperCase();
            date.textContent = now.toLocaleString('en-US', { month: 'short', day: 'numeric' }).toUpperCase();
        }
        function loop(t) {
            if (t - last > 200) { render(new Date()); last = t; }
            requestAnimationFrame(loop);
        }
        requestAnimationFrame(loop);
    }

    /* ── live timer pill (click to pause/resume) ───────────────────────── */
    function initTimer() {
        var btn = document.getElementById('taTimer');
        if (!btn) return;
        var dot = document.getElementById('taTimerDot');
        var label = document.getElementById('taTimerLabel');
        var time = document.getElementById('taTimerTime');

        var running = true;
        var seconds = 2 * 3600 + 14 * 60 + 36; // believable mid-session start

        function render() {
            var h = Math.floor(seconds / 3600);
            var m = Math.floor((seconds % 3600) / 60);
            var s = seconds % 60;
            time.innerHTML = pad(h) + ':' + pad(m) + ':' +
                '<span' + (running ? '' : ' class="is-paused"') + '>' + pad(s) + '</span>';
        }

        setInterval(function () {
            if (running) { seconds++; render(); }
        }, 1000);

        btn.addEventListener('click', function () {
            running = !running;
            dot.classList.toggle('is-on', running);
            label.textContent = running ? 'Tracking' : 'Paused';
            btn.setAttribute('aria-label', running ? 'Pause tracking' : 'Resume tracking');
            render();
        });

        render();
    }

    /* ── calendar hover/focus detail ───────────────────────────────────── */
    function initCalendar() {
        var cal = document.getElementById('taCal');
        var detail = document.getElementById('taCalDetail');
        if (!cal || !detail) return;

        var dotbig = detail.querySelector('.ta-cal__dotbig');
        var labelEl = detail.querySelector('.ta-cal__detail-label');
        var whoEl = detail.querySelector('.ta-cal__detail-who');
        var placeholderSub = detail.dataset.placeholderSub;

        function showPlaceholder() {
            dotbig.className = 'ta-cal__dotbig ta-cal__dotbig--placeholder';
            labelEl.textContent = "Hover a day to see who's out";
            whoEl.textContent = placeholderSub;
        }
        function showEvent(cell) {
            dotbig.className = 'ta-cal__dotbig ta-cal__dotbig--' + cell.dataset.kind;
            labelEl.textContent = cell.dataset.label;
            whoEl.textContent = cell.dataset.who;
        }

        cal.querySelectorAll('.ta-cal__cell[data-day]').forEach(function (cell) {
            function enter() {
                cell.classList.add('is-hovered');
                if (cell.dataset.kind) { showEvent(cell); } else { showPlaceholder(); }
            }
            function leave() {
                cell.classList.remove('is-hovered');
                showPlaceholder();
            }
            cell.addEventListener('mouseenter', enter);
            cell.addEventListener('mouseleave', leave);
            cell.addEventListener('focus', enter);
            cell.addEventListener('blur', leave);
        });
    }

    /* ── signup modal ──────────────────────────────────────────────────── */
    function initModal() {
        var modal = document.getElementById('taModal');
        if (!modal) return;

        var formWrap = document.getElementById('taSignupForm');
        var success = document.getElementById('taSignupSuccess');
        var form = formWrap.querySelector('form');
        var email = document.getElementById('taEmail');
        var company = document.getElementById('taCompany');
        var sizeInput = document.getElementById('taSizeInput');
        var submitBtn = document.getElementById('taSubmit');
        var touched = false;

        function open() {
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            showForm();
            email.focus();
        }
        function close() {
            modal.hidden = true;
            document.body.style.overflow = '';
            touched = false;
            form.reset();
            sizeInput.value = '11–50';
            modal.querySelectorAll('.ta-segment__opt').forEach(function (b) {
                var active = b.dataset.size === '11–50';
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-checked', String(active));
            });
            email.classList.remove('is-invalid');
            company.classList.remove('is-invalid');
        }
        function showForm() {
            formWrap.hidden = false;
            success.hidden = true;
        }

        document.querySelectorAll('[data-modal-open]').forEach(function (b) {
            b.addEventListener('click', open);
        });
        modal.querySelectorAll('[data-modal-close]').forEach(function (b) {
            b.addEventListener('click', close);
        });
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) close();
        });

        modal.querySelectorAll('.ta-segment__opt').forEach(function (b) {
            b.addEventListener('click', function () {
                sizeInput.value = b.dataset.size;
                modal.querySelectorAll('.ta-segment__opt').forEach(function (o) {
                    o.classList.toggle('is-active', o === b);
                    o.setAttribute('aria-checked', String(o === b));
                });
            });
        });

        function validate() {
            var validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
            var validCompany = company.value.trim().length > 1;
            if (touched) {
                email.classList.toggle('is-invalid', !validEmail);
                company.classList.toggle('is-invalid', !validCompany);
            }
            return validEmail && validCompany;
        }
        email.addEventListener('input', validate);
        company.addEventListener('input', validate);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            touched = true;
            if (!validate()) return;

            submitBtn.disabled = true;
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        document.getElementById('taSuccessEmail').textContent = data.email;
                        document.getElementById('taSuccessCompany').textContent = data.company || 'your team';
                        formWrap.hidden = true;
                        success.hidden = false;
                    } else if (data.errors) {
                        if (data.errors.email) email.classList.add('is-invalid');
                        if (data.errors.company) company.classList.add('is-invalid');
                    }
                })
                .catch(function () { /* keep the form editable on network failure */ })
                .finally(function () { submitBtn.disabled = false; });
        });

        document.getElementById('taSuccessBack').addEventListener('click', showForm);
    }

    initClock();
    initTimer();
    initCalendar();
    initModal();
})();
