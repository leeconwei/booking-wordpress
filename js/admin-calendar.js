document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin calendar JS loaded"); // sanity check

  let calendarEl = document.getElementById("studio-calendar");

  let calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "timeGridWeek",
    slotMinTime: "08:00:00",
    slotMaxTime: "22:00:00",
    allDaySlot: false,
    headerToolbar: {
      left: "prev,next today",
      center: "title",
      right: "timeGridWeek,timeGridDay",
    },
    events: function (fetchInfo, successCallback) {
      jQuery.post(
        StudioBookingAjax.ajax_url,
        { action: "get_all_bookings" },
        function (res) {
          successCallback(res);
        },
      );
    },
    eventClick: function (info) {
      alert("Booking at: " + info.event.start.toLocaleString());
    },
  });

  calendar.render();
});
