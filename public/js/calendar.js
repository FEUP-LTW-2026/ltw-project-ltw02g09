class GymCalendar {
    constructor(containerId, options = {}) {
        this.container    = document.getElementById(containerId);
        this.options      = {
            onDayClick:   options.onDayClick   || null,
            onEventClick: options.onEventClick || null,
            events:       options.events       || [],
            selectable:   options.selectable   || false,
            minDate:      options.minDate       || null,
        };
        this.today        = new Date();
        this.current      = new Date(this.today.getFullYear(), this.today.getMonth(), 1);
        this.selectedDate = null;
        this.events       = this.options.events;

        if (this.container) this.render();
    }

    get monthLabel() {
        return this.current.toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });
    }

    prevMonth() {
        this.current = new Date(this.current.getFullYear(), this.current.getMonth() - 1, 1);
        this.render();
    }

    nextMonth() {
        this.current = new Date(this.current.getFullYear(), this.current.getMonth() + 1, 1);
        this.render();
    }

    setEvents(events) {
        this.events = events;
        this.render();
    }

    getEventsForDate(dateStr) {
        return this.events.filter(e => e.date === dateStr);
    }

    dateStr(y, m, d) {
        return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    }

    isToday(y, m, d) {
        return y === this.today.getFullYear() && m === this.today.getMonth() && d === this.today.getDate();
    }

    isPast(y, m, d) {
        const date = new Date(y, m, d);
        const today = new Date(this.today.getFullYear(), this.today.getMonth(), this.today.getDate());
        return date < today;
    }

    isBeforeMinDate(y, m, d) {
        if (!this.options.minDate) return false;
        const date = new Date(y, m, d);
        const [minY, minM, minD] = this.options.minDate.split('-').map(Number);
        const minDate = new Date(minY, minM - 1, minD);
        return date < minDate;
    }

    render() {
        if (!this.container) return;

        const year  = this.current.getFullYear();
        const month = this.current.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrev  = new Date(year, month, 0).getDate();

        const dayHeaders = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        let html = `
            <div class="calendar-container">
                <div class="calendar-header">
                    <button class="btn btn-sm btn-secondary" id="calPrev">◀</button>
                    <span class="calendar-title">${this.monthLabel}</span>
                    <button class="btn btn-sm btn-secondary" id="calNext">▶</button>
                </div>
                <div class="calendar-grid">
                    ${dayHeaders.map(d => `<div class="calendar-day-header">${d}</div>`).join('')}
        `;

        let cellsRendered = 0;

        for (let i = 0; i < firstDay; i++) {
            const d   = daysInPrev - firstDay + 1 + i;
            const ds  = this.dateStr(year, month - 1, d);
            const evs = this.getEventsForDate(ds);
            html += this.renderDay(year, month - 1, d, ds, evs, true);
            cellsRendered++;
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const ds  = this.dateStr(year, month, d);
            const evs = this.getEventsForDate(ds);
            html += this.renderDay(year, month, d, ds, evs, false);
            cellsRendered++;
        }

        const remaining = 42 - cellsRendered;
        for (let d = 1; d <= remaining; d++) {
            const ds  = this.dateStr(year, month + 1, d);
            const evs = this.getEventsForDate(ds);
            html += this.renderDay(year, month + 1, d, ds, evs, true);
        }

        html += '</div></div>';
        this.container.innerHTML = html;

        this.container.querySelector('#calPrev').addEventListener('click', () => this.prevMonth());
        this.container.querySelector('#calNext').addEventListener('click', () => this.nextMonth());

        this.container.querySelectorAll('.calendar-day').forEach(cell => {
            cell.addEventListener('click', (e) => {
                const ds = cell.dataset.date;
                if (!ds) return;
            if (cell.classList.contains('past') || cell.classList.contains('disabled')) return;

                if (this.options.selectable) {
                    this.container.querySelectorAll('.calendar-day').forEach(c => c.classList.remove('selected'));
                    cell.classList.add('selected');
                    this.selectedDate = ds;
                }

                if (this.options.onDayClick) {
                    this.options.onDayClick(ds, this.getEventsForDate(ds));
                }
            });

            cell.querySelectorAll('.calendar-event').forEach(ev => {
                ev.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (this.options.onEventClick) {
                        this.options.onEventClick(ev.dataset);
                    }
                });
            });
        });
    }

    renderDay(y, m, d, ds, events, otherMonth) {
        const todayClass    = this.isToday(y, m, d) ? 'today' : '';
        const otherClass    = otherMonth ? 'other-month' : '';
        const selectedClass = this.selectedDate === ds ? 'selected' : '';
        const pastClass     = this.isPast(y, m, d) ? 'past' : '';
        const disabledClass = this.isBeforeMinDate(y, m, d) ? 'disabled' : '';

        const evHtml = events.slice(0, 3).map(ev => {
            const cls = ev.type ? `event-${ev.type}` : '';
            const title = escapeHtml(ev.title || '');
            return `<div class="calendar-event ${cls}" data-id="${ev.id || ''}" data-date="${ds}" title="${title}">${title}</div>`;
        }).join('');

        const more = events.length > 3 ? `<div class="calendar-event event-more">+${events.length - 3}</div>` : '';

        return `
            <div class="calendar-day ${todayClass} ${otherClass} ${selectedClass} ${pastClass} ${disabledClass}" data-date="${ds}">
                <span class="day-number">${d}</span>
                ${evHtml}
                ${more}
            </div>
        `;
    }
}

function initBookingCalendar(trainerId) {
    let calendar;
    let selectedSlot = null;

    async function loadSlots(dateStr) {
        const [year, month] = dateStr.split('-');
        try {
            const data = await apiFetch(`/api/bookings.php?action=slots&trainer_id=${trainerId}&year=${year}&month=${month}`);
            const events = data.slots.map(slot => ({
                id:    slot.id,
                date:  slot.date,
                title: slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5),
                type:  slot.booked ? 'booked' : 'available',
                start: slot.start_time,
                end:   slot.end_time,
                booked: slot.booked,
            }));
            calendar.setEvents(events);
        } catch (e) {
            console.error('Erro ao carregar disponibilidade:', e);
        }
    }

    async function showDaySlots(ds, events) {
        const panel = document.getElementById('daySlots');
        const title = document.getElementById('daySlotsTitle');
        if (!panel || !title) return;

        const date  = new Date(ds + 'T00:00:00');
        title.textContent = date.toLocaleDateString('pt-PT', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        if (events.length === 0) {
            panel.innerHTML = '<p class="text-muted text-center mt-3">Sem disponibilidade neste dia.</p>';
            return;
        }

        panel.innerHTML = '<div class="slots-list">' + events.map(ev => {
            const booked   = ev.type === 'booked';
            const disabled = booked ? 'disabled' : '';
            const label    = booked ? 'Ocupado' : 'Disponível';
            const cls      = booked ? 'btn-secondary' : 'btn-success';
            return `
                <div class="slot-item">
                    <div>
                        <div class="slot-time">${ev.start.substring(0,5)} — ${ev.end.substring(0,5)}</div>
                        <div class="slot-status">${label}</div>
                    </div>
                    <button class="btn btn-sm ${cls}" ${disabled}
                        onclick="selectSlot(${ev.id}, '${ds}', '${ev.start}', '${ev.end}')">
                        ${booked ? 'Indisponível' : 'Marcar'}
                    </button>
                </div>
            `;
        }).join('') + '</div>';
    }

    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    calendar = new GymCalendar('bookingCalendar', {
        selectable:  true,
        minDate:     todayStr,
        onDayClick:  (ds, events) => {
            showDaySlots(ds, events);
            const [y, m] = ds.split('-');
            loadSlots(`${y}-${m}-01`);
        },
    });

    loadSlots(todayStr);
}

window.selectSlot = function(slotId, date, start, end) {
    document.getElementById('bookingSlotId').value  = slotId;
    document.getElementById('bookingDate').textContent  = new Date(date + 'T00:00:00').toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    document.getElementById('bookingTime').textContent  = `${start.substring(0,5)} — ${end.substring(0,5)}`;
    const overlay = document.getElementById('bookingModal');
    if (overlay) overlay.classList.remove('hidden');
};

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
