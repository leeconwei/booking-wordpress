jQuery(function ($) {
  let selectedSlots = {};
  $.post(
    StudioBookingAjax.ajax_url,
    { action: "get_booked_days" },
    function (res) {
      let bookedDays = [];

      if (res.success) {
        bookedDays = res.data.map((d) => d.booking_date);
      }
      $("#booking-date").flatpickr({
        mode: "multiple",
        dateFormat: "Y-m-d",

        onDayCreate: function (dObj, dStr, fp, dayElem) {
          let date = dayElem.dateObj.toISOString().split("T")[0];

          if (bookedDays.includes(date)) {
            dayElem.classList.add("has-booking");
          }
        },
        onChange: function (selectedDates) {
          // Convert selected dates to formatted strings
          let currentDates = selectedDates.map((d) => {
            let year = d.getFullYear();
            let month = String(d.getMonth() + 1).padStart(2, "0");
            let day = String(d.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
          });

          // Remove slots for unselected dates
          Object.keys(selectedSlots).forEach((date) => {
            if (!currentDates.includes(date)) {
              delete selectedSlots[date];
            }
          });

          $("#sb-time-slots").empty();

          // Sort dates ascending
          selectedDates.sort((a, b) => a - b);

          selectedDates.forEach((d) => {
            let year = d.getFullYear();
            let month = String(d.getMonth() + 1).padStart(2, "0");
            let day = String(d.getDate()).padStart(2, "0");
            let date = `${year}-${month}-${day}`;

            selectedSlots[date] = selectedSlots[date] || [];

            $("#sb-time-slots").append(`
      <div class="sb-day" data-date="${date}">
        <h4>${date}</h4>
        <div class="slots"></div>
      </div>
    `);

            // Fetch booked slots
            $.post(
              StudioBookingAjax.ajax_url,
              { action: "get_booked_slots", date: date },
              function (res) {
                let booked = res.success ? res.data : [];
                for (
                  let i = parseInt(StudioBookingAjax.start_hour);
                  i < parseInt(StudioBookingAjax.end_hour);
                  i++
                ) {
                  let startHour = i;
                  let endHour = i + 1;
                  let displayHour =
                    (startHour < 10 ? "0" : "") +
                    startHour +
                    ":00 - " +
                    (endHour < 10 ? "0" : "") +
                    endHour +
                    ":00";
                  let isBooked = booked.includes(
                    (startHour < 10 ? "0" : "") + startHour + ":00",
                  );
                  let selected = selectedSlots[date].includes(
                    (startHour < 10 ? "0" : "") + startHour + ":00",
                  )
                    ? "selected"
                    : "";
                  let disabledClass = isBooked ? "booked" : "";
                  $('.sb-day[data-date="' + date + '"] .slots').append(`
              <span class="time-slot ${selected} ${disabledClass}" 
                    data-time="${(startHour < 10 ? "0" : "") + startHour}:00"
                    title="${isBooked ? "Booked" : "Available"}">
                ${displayHour}
              </span>
            `);
                }
                updateTotal();
              },
            );
          });

          updateTotal();
        },
      });

      // Slot click handler
      $(document).on("click", ".time-slot", function () {
        if ($(this).hasClass("booked")) return;
        let day = $(this).closest(".sb-day").data("date");
        let time = $(this).data("time");
        selectedSlots[day] = selectedSlots[day] || [];
        if ($(this).hasClass("selected")) {
          $(this).removeClass("selected");
          selectedSlots[day] = selectedSlots[day].filter((t) => t !== time);
        } else {
          $(this).addClass("selected");
          selectedSlots[day].push(time);
        }
        updateTotal();
      });

      // Update total price & summary list
      function updateTotal() {
        let totalHours = 0;
        $(".sb-summary-list").empty();

        // Sort dates ascending
        Object.keys(selectedSlots)
          .sort()
          .forEach((day) => {
            let hours = selectedSlots[day] || [];
            if (hours.length === 0) return;

            totalHours += hours.length;

            // Show just the count per day
            $(".sb-summary-list").append(`
        <div class="summary-card">
          <strong>${day}</strong>: ${hours.length} hour${hours.length > 1 ? "s" : ""}
        </div>
      `);
          });

        $("#booking-total").text(totalHours * StudioBookingAjax.hourly_rate);
      }

      // Submit booking via WooCommerce
      $("#booking-submit").on("click", function () {
        if (Object.keys(selectedSlots).length === 0) {
          alert("Please select at least one slot");
          return;
        }

        $.post(
          StudioBookingAjax.ajax_url,
          {
            action: "add_booking_to_cart",
            slots: JSON.stringify(selectedSlots),
          },
          function (res) {
            if (res.success && res.data.redirect) {
              window.location.href = res.data.redirect;
            } else {
              alert("Error: " + (res.data || "Unable to proceed"));
            }
          },
        );
      });
    },
  );
});
