// NPRU Room Booking System - Frontend Logic

let currentCalDate = new Date();
let selectedRoomData = null;

const thaiMonthNames = [
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน',
    'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม',
    'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];

document.addEventListener('DOMContentLoaded', () => {
    initCalendar();
    initFilters();
    initDateConstraints();
});

// ==========================================
// 1. Calendar Widget
// ==========================================
function initCalendar() {
    renderCalendar();
}

function renderCalendar() {
    const year = currentCalDate.getFullYear();
    const month = currentCalDate.getMonth();

    // Update label
    const label = document.getElementById('calMonthLabel');
    if (label) {
        label.textContent = `${thaiMonthNames[month]} ${year + 543}`;
    }

    const container = document.getElementById('calendarDays');
    if (!container) return;
    container.innerHTML = '';

    const firstDayIndex = new Date(year, month, 1).getDay(); // 0 is Sun
    const lastDayOfMonth = new Date(year, month + 1, 0).getDate();
    const lastDayPrevMonth = new Date(year, month, 0).getDate();

    const today = new Date();
    const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;
    const todayDate = today.getDate();

    // Previous month padding days
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const cell = document.createElement('div');
        cell.className = 'cal-day-cell is-other-month';
        cell.textContent = lastDayPrevMonth - i;
        container.appendChild(cell);
    }

    // Days of current month
    for (let d = 1; d <= lastDayOfMonth; d++) {
        const cell = document.createElement('div');
        cell.className = 'cal-day-cell';
        cell.textContent = d;

        if (isCurrentMonth && d === todayDate) {
            cell.classList.add('is-today');
        }

        // Add booking dot for demo days or today
        if (d === 23 || (isCurrentMonth && d === todayDate)) {
            cell.classList.add('has-booking');
        }

        cell.addEventListener('click', () => {
            document.querySelectorAll('.cal-day-cell').forEach(c => c.classList.remove('is-today'));
            cell.classList.add('is-today');
            // If date picker exists in form, sync
            const formatted = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const dateInput = document.getElementById('formDate');
            if (dateInput) dateInput.value = formatted;
        });

        container.appendChild(cell);
    }

    // Next month padding days to complete 35 or 42 grid
    const totalRendered = firstDayIndex + lastDayOfMonth;
    const remaining = (7 - (totalRendered % 7)) % 7;
    for (let j = 1; j <= remaining; j++) {
        const cell = document.createElement('div');
        cell.className = 'cal-day-cell is-other-month';
        cell.textContent = j;
        container.appendChild(cell);
    }
}

function prevMonth() {
    currentCalDate.setMonth(currentCalDate.getMonth() - 1);
    renderCalendar();
}

function nextMonth() {
    currentCalDate.setMonth(currentCalDate.getMonth() + 1);
    renderCalendar();
}

function resetCalendarToToday() {
    currentCalDate = new Date();
    renderCalendar();
}

// ==========================================
// 2. Room Search & Filtering
// ==========================================
function initFilters() {
    const searchInput = document.getElementById('roomSearch');
    const typeFilter = document.getElementById('typeFilter');

    function applyFilter() {
        const q = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedType = typeFilter ? typeFilter.value : 'all';

        document.querySelectorAll('.room-card').forEach(card => {
            const code = (card.dataset.roomCode || '').toLowerCase();
            const type = card.dataset.roomType || '';

            const matchSearch = !q || code.includes(q);
            const matchType = selectedType === 'all' || type === selectedType;

            card.style.display = (matchSearch && matchType) ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', applyFilter);
    typeFilter?.addEventListener('change', applyFilter);
}

// ==========================================
// 3. User Menu Dropdown & Notifications
// ==========================================
function toggleUserDropdown() {
    const drop = document.getElementById('userDropdown');
    drop?.classList.toggle('show');
}

function toggleNotifModal() {
    const modal = document.getElementById('notifModal');
    if (!modal) return;
    const isShowing = modal.classList.toggle('show');
    if (isShowing) {
        loadNotifications();
    }
}

async function loadNotifications() {
    const container = document.getElementById('notifListContainer');
    if (!container) return;

    try {
        const res = await fetch('api/notifications.php?action=list');
        const data = await res.json();

        if (!data.success) {
            // Guest or not logged in: fallback notices
            container.innerHTML = `
                <div class="notif-item">
                    <b>ยินดีต้อนรับสู่ระบบจองห้อง อาคาร C</b>
                    <p>นักศึกษาสามารถเข้าสู่ระบบด้วยรหัสนักศึกษาและอีเมลเว็บเมล์ NPRU เพื่อส่งคำขอจองห้องเรียนและห้องประชุมได้ตลอด 24 ชม.</p>
                    <small>วันนี้</small>
                </div>
                <div class="notif-item">
                    <b>ห้องประชุมใหญ่ C301 พร้อมใช้งาน</b>
                    <p>รองรับผู้เข้าร่วมได้สูงสุด 200 คน พร้อมระบบภาพและเสียงความละเอียดสูง</p>
                    <small>อาคาร C ชั้น 3</small>
                </div>
            `;
            return;
        }

        updateNotifBadge(data.unread_count);

        if (!data.notifications || data.notifications.length === 0) {
            container.innerHTML = `
                <div class="notif-empty-state">
                    <span style="font-size: 32px;">🎉</span>
                    <p style="margin: 8px 0 0; font-weight: 600; color: #08755e;">ไม่มีการแจ้งเตือนใหม่</p>
                    <small style="color: #6d807b;">คุณได้อ่านการแจ้งเตือนทั้งหมดแล้ว</small>
                </div>
            `;
            return;
        }

        container.innerHTML = '';
        data.notifications.forEach(n => {
            const item = document.createElement('div');
            item.className = `notif-item notif-type-${n.type} ${n.is_read ? 'notif-read' : 'notif-unread'}`;
            item.id = `notif-card-${n.id}`;

            let icon = '📢';
            if (n.type === 'approved') icon = '🎉';
            if (n.type === 'ready') icon = '🔑';
            if (n.type === 'rejected') icon = '❌';
            if (n.type === 'new_booking') icon = '📋';

            item.innerHTML = `
                <div class="notif-item-body">
                    <div class="notif-title-row">
                        <span class="notif-icon">${icon}</span>
                        <b>${escapeHtml(n.title)}</b>
                        ${n.is_read ? '' : '<span class="unread-dot"></span>'}
                    </div>
                    <p>${escapeHtml(n.message)}</p>
                    <div class="notif-footer-row">
                        <small>${n.created_at}</small>
                        ${n.is_read ? '' : `<button type="button" class="btn-read-single" onclick="markNotifRead(${n.id}, this)">✓ อ่านแล้ว</button>`}
                    </div>
                </div>
            `;
            container.appendChild(item);
        });

    } catch (e) {
        container.innerHTML = '<div style="color:red; text-align:center; padding:15px;">ไม่สามารถโหลดการแจ้งเตือนได้</div>';
    }
}

async function markNotifRead(id, btn) {
    const item = document.getElementById(`notif-card-${id}`);
    if (item) {
        item.style.transition = 'all 0.3s ease';
        item.style.opacity = '0.3';
        item.style.transform = 'translateX(20px)';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('id', id);

        const res = await fetch('api/notifications.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            updateNotifBadge(data.unread_count);
            setTimeout(() => {
                if (item) {
                    item.classList.remove('notif-unread');
                    item.classList.add('notif-read');
                    item.style.opacity = '0.65';
                    item.style.transform = 'none';
                    if (btn) btn.remove();
                    const dot = item.querySelector('.unread-dot');
                    if (dot) dot.remove();
                }
                if (data.unread_count === 0) {
                    loadNotifications();
                }
            }, 300);
        }
    } catch (e) {
        console.error(e);
    }
}

async function markAllNotifsRead() {
    try {
        const formData = new FormData();
        formData.append('action', 'mark_all_read');

        const res = await fetch('api/notifications.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            updateNotifBadge(0);
            loadNotifications();
        }
    } catch (e) {
        console.error(e);
    }
}

function updateNotifBadge(count) {
    const badge = document.getElementById('topNotifBadge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}

window.addEventListener('click', (e) => {
    // Close user dropdown if clicking outside
    const userMenu = document.querySelector('.user-menu-wrapper');
    if (userMenu && !userMenu.contains(e.target)) {
        document.getElementById('userDropdown')?.classList.remove('show');
    }

    // Close modals on backdrop click
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('show');
    }
});

// ==========================================
// 4. Room Schedule & Booking Modal
// ==========================================
function openRoomSchedule(roomId, roomCode, roomName, capacity) {
    selectedRoomData = { id: roomId, code: roomCode, name: roomName, capacity: capacity };

    document.getElementById('formRoomId').value = roomId;
    document.getElementById('modalRoomTitle').textContent = `ห้อง ${roomCode} (${roomName})`;
    document.getElementById('modalRoomSubtitle').textContent = `ความจุ ${capacity} คน • อาคาร C ชั้น 3`;

    const modal = document.getElementById('bookingModal');
    modal?.classList.add('show');

    // Default dates
    const dateInput = document.getElementById('formDate');
    const schedDate = document.getElementById('schedDate');
    const todayStr = new Date().toISOString().split('T')[0];

    if (!dateInput.value) dateInput.value = todayStr;
    if (schedDate) schedDate.value = dateInput.value;

    loadRoomSchedule();
}

function closeBookingModal() {
    document.getElementById('bookingModal')?.classList.remove('show');
}

async function loadRoomSchedule() {
    if (!selectedRoomData) return;

    const schedDateInput = document.getElementById('schedDate');
    const date = schedDateInput ? schedDateInput.value : new Date().toISOString().split('T')[0];
    const container = document.getElementById('timelineSlots');

    container.innerHTML = '<div style="text-align: center; padding: 20px; color: #888;">กำลังโหลดตาราง...</div>';

    try {
        const res = await fetch(`api/get_room_schedule.php?room_id=${selectedRoomData.id}&date=${date}`);
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div style="color: red; padding: 15px;">${data.message}</div>`;
            return;
        }

        // Render facilities chips
        const facContainer = document.getElementById('modalRoomFacilities');
        if (facContainer && data.room.facilities) {
            facContainer.innerHTML = data.room.facilities.split(',').map(f => `<span class="fac-chip">✓ ${f.trim()}</span>`).join('');
        }

        // Render timeline
        container.innerHTML = '';
        data.timeline.forEach(slot => {
            const slotEl = document.createElement('div');
            slotEl.className = `timeline-slot ${slot.is_booked ? 'slot-booked' : 'slot-available'}`;

            if (slot.is_booked) {
                slotEl.innerHTML = `
                    <span>⏱ ${slot.time_label}</span>
                    <span style="font-weight: 500;">🚫 ไม่ว่าง (${slot.booking.topic})</span>
                `;
            } else {
                slotEl.innerHTML = `
                    <span>⏱ ${slot.time_label}</span>
                    <span style="font-weight: 500;">✓ ว่าง (คลิกเพื่อเลือกช่วงเวลานี้)</span>
                `;
                slotEl.style.cursor = 'pointer';
                slotEl.addEventListener('click', () => {
                    document.getElementById('formDate').value = date;
                    document.getElementById('formStartTime').value = slot.start;
                    document.getElementById('formEndTime').value = slot.end;
                    highlightSelectedSlot(slotEl);
                });
            }

            container.appendChild(slotEl);
        });

    } catch (err) {
        container.innerHTML = '<div style="color: red; padding: 15px;">เกิดข้อผิดพลาดในการโหลดข้อมูลตาราง</div>';
    }
}

function highlightSelectedSlot(el) {
    document.querySelectorAll('.timeline-slot').forEach(s => s.style.outline = 'none');
    el.style.outline = '2px solid #08755e';
}

function initDateConstraints() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('input[type="date"]').forEach(input => {
        if (!input.min) input.min = today;
    });
}

function validateBookingSubmit() {
    const start = document.getElementById('formStartTime').value;
    const end = document.getElementById('formEndTime').value;
    const people = parseInt(document.getElementById('formPeople').value, 10);
    const alertBox = document.getElementById('conflictAlert');

    if (start >= end) {
        alertBox.textContent = '⚠️ เวลาเริ่มต้องน้อยกว่าเวลาสิ้นสุด';
        alertBox.style.display = 'block';
        return false;
    }

    if (selectedRoomData && people > selectedRoomData.capacity) {
        alertBox.textContent = `⚠️ จำนวนคน (${people} คน) เกินความจุของห้อง (${selectedRoomData.capacity} คน)`;
        alertBox.style.display = 'block';
        return false;
    }

    alertBox.style.display = 'none';
    return true;
}
