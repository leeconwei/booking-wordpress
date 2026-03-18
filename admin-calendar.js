document.addEventListener('DOMContentLoaded', function() {

    const calendarEl = document.getElementById('studio-calendar');
    if (!calendarEl) return;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        nowIndicator: true,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        height: 'auto',

        events: function(fetchInfo, successCallback) {
            jQuery.ajax({
                url: StudioBookingAjax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: { action: 'get_all_bookings' },
                success: function(res) {
                    successCallback(res);
                },
                error: function(err){
                    console.error('Failed to load bookings', err);
                    successCallback([]);
                }
            });
        },

        eventColor: '#4f46e5',
        eventTextColor: '#fff',
        eventBorderColor: '#4338ca',

        eventClick: function(info) {
            const props = info.event.extendedProps;
            const content = `
                <div style="padding:20px;font-family:sans-serif;">
                    <h2 style="margin-top:0;">${info.event.title}</h2>
                    <p><strong>Status:</strong> ${props.status || '—'}</p>
                    <p><strong>Notes:</strong> ${props.notes || '—'}</p>
                    <p><strong>Date & Time:</strong> ${info.event.start.toLocaleString()}</p>
                </div>
            `;

            const modal = document.createElement('div');
            modal.innerHTML = content;
            Object.assign(modal.style, {
                position:'fixed', top:'50%', left:'50%',
                transform:'translate(-50%, -50%)',
                background:'#fff', padding:'20px', borderRadius:'12px',
                boxShadow:'0 8px 30px rgba(0,0,0,0.1)',
                zIndex: 9999
            });

            const overlay = document.createElement('div');
            Object.assign(overlay.style, {
                position:'fixed', top:0, left:0, width:'100%', height:'100%',
                background:'rgba(0,0,0,0.4)', zIndex: 9998
            });

            overlay.addEventListener('click', ()=>{ overlay.remove(); modal.remove(); });

            document.body.appendChild(overlay);
            document.body.appendChild(modal);
        }

    });

    calendar.render();

});