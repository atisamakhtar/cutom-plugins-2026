/* global FGBW, jQuery */
(function ($) {
  "use strict";

  // ---------- Utilities ----------
  function debounce(fn, wait) {
    let t = null;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  function uid(prefix = "id") {
    return `${prefix}_${Math.random().toString(16).slice(2)}_${Date.now()}`;
  }

  function setError(key, msg) {
    const $el = $(`[data-error-for="${key}"]`);
    if (!$el.length) return;
    $el.text(msg || "");
    $el.toggleClass("is-visible", !!msg);
  }

  function clearErrors() {
    $(".fgbw__error").text("").removeClass("is-visible");
  }

  function wpAjax(action, data) {
    return $.ajax({
      url: FGBW.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: Object.assign({ action, nonce: FGBW.nonce }, data || {}),
    });
  }

  // ==============================
  // SAFE GOOGLE PLACES LOADER
  // ==============================

  let fgbwGooglePromise = null;

  function loadGooglePlaces() {

    if (window.google && window.google.maps && window.google.maps.places) {
      return Promise.resolve();
    }

    if (fgbwGooglePromise) {
      return fgbwGooglePromise;
    }

    fgbwGooglePromise = new Promise((resolve, reject) => {

      if (!FGBW.googlePlacesKey && !window.google) {
        console.warn("FGBW: Google Places API Key is missing.");
        reject("Missing API Key");
        return;
      }

      const existingScript = document.querySelector('script[src*="maps.googleapis.com"]');

      if (existingScript) {
        if (window.google && window.google.maps && window.google.maps.places) {
          resolve();
          return;
        }

        // Poll for load completion in case event already fired
        let attempts = 0;
        const timer = setInterval(() => {
          attempts++;
          if (window.google && window.google.maps && window.google.maps.places) {
            clearInterval(timer);
            resolve();
          }
          if (attempts > 50) { // ~10 seconds
            clearInterval(timer);
            // Don't reject, maybe it will load later or user doesn't need places immediately?
            // But we can't resolve really.
          }
        }, 200);

        // Also listen for load event
        existingScript.addEventListener('load', () => {
          if (window.google && window.google.maps && window.google.maps.places) {
            clearInterval(timer);
            resolve();
          }
        });
        return;
      }

      const callbackName = "fgbwGoogleInitCallback";

      window[callbackName] = function () {
        resolve();
        delete window[callbackName];
      };

      const script = document.createElement("script");
      script.src =
        "https://maps.googleapis.com/maps/api/js?key=" +
        encodeURIComponent(FGBW.googlePlacesKey || '') +
        "&libraries=places&callback=" +
        callbackName;

      script.async = true;
      script.defer = true;

      script.onerror = function () {
        reject("Google Maps failed to load.");
      };

      document.head.appendChild(script);
    });

    return fgbwGooglePromise;
  }

  // ---------- Modal Component ----------
  class FGBWModal {
    constructor() {
      this.init();
    }
    init() {
      if (document.querySelector('.fgbw__modal-backdrop')) {
        this.$el = $('.fgbw__modal-backdrop');
        return;
      }
      const html = `
        <div class="fgbw__modal-backdrop">
          <div class="fgbw__modal">
             <div class="fgbw__modal-head">
               <div class="fgbw__modal-title">Additional Info</div>
               <button type="button" class="fgbw__modal-close">&times;</button>
             </div>
             <div class="fgbw__modal-body">
               <div class="fgbw__modal-row">
                  <label>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Passenger Count
                  </label>
                  <input type="number" class="fgbw__modal-input fgbw__modal-pax" min="1" value="1">
               </div>
               <div class="fgbw__modal-row">
                  <label>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Note
                  </label>
                  <textarea class="fgbw__modal-textarea fgbw__modal-note" rows="3"></textarea>
               </div>
             </div>
             <div class="fgbw__modal-foot">
               <button type="button" class="fgbw__btn fgbw__btn--ghost fgbw__modal-close-btn">Close</button>
               <button type="button" class="fgbw__btn fgbw__modal-save fgbw__btn--primary" style="background:#111;color:#fff;border-color:#111;">Save</button>
             </div>
          </div>
        </div>
      `;
      $('body').append(html);
      this.$el = $('.fgbw__modal-backdrop');
      this.bind();
    }
    bind() {
      const close = () => this.close();
      this.$el.on('click', '.fgbw__modal-close, .fgbw__modal-close-btn', close);
      this.$el.on('click', (e) => {
        if ($(e.target).is('.fgbw__modal-backdrop')) close();
      });
      this.$el.on('click', '.fgbw__modal-save', () => {
        if (this.onSave) {
          const pax = parseInt(this.$el.find('.fgbw__modal-pax').val()) || 1;
          const note = this.$el.find('.fgbw__modal-note').val().trim();
          this.onSave({ pax, note });
        }
        close();
      });
    }
    open({ title, pax, note, onSave }) {
      this.$el.find('.fgbw__modal-title').text(title || 'Additional Info');
      this.$el.find('.fgbw__modal-pax').val(pax || 1);
      this.$el.find('.fgbw__modal-note').val(note || '');
      this.onSave = onSave;
      this.$el.addClass('is-visible');
    }
    close() {
      this.$el.removeClass('is-visible');
      this.onSave = null;
    }
  }

  const fgbwModal = new FGBWModal();

  // ---------- LocationBlock Component ----------
  class LocationBlock {
    constructor({ mountEl, type, tripSegment, index = null, onChange, onDelete = null }) {
      this.mountEl = mountEl;
      this.type = type; // pickup | dropoff | stop
      this.tripSegment = tripSegment; // oneway | round_pickup | round_return
      this.index = index; // stop index
      this.onChange = onChange;
      this.onDelete = onDelete;

      this.state = {
        mode: "address", // address | airport
        address: null,   // { formatted_address, lat, lng, place_id }
        airport: null,   // { iata_code, airport_name, country_name, city }
        airline: null,   // { iata_code, airline_name }
        flight: "",
        noFlightInfo: false,
        passenger_count: 1,
        note: ""
      };

      this.id = uid(`loc_${tripSegment}_${type}`);
      this.render();
      this.bind();
      this.initAddressAutocomplete();
    }

    render() {
      const title = this.type === "pickup" ? "Pick-Up" : this.type === "dropoff" ? "Drop-Off" : `Stop ${this.index + 1}`;

      let actionsHtml = "";
      if (this.type === "stop") {
        actionsHtml = `
          <div class="fgbw__loc-actions">
           <button type="button" class="fgbw__icon-btn fgbw__edit-stop" title="Edit">
             <i class="fa fa-edit"></i>
           </button>
           <button type="button" class="fgbw__icon-btn fgbw__del-stop" title="Delete">
             <i class="fa fa-trash"></i>
           </button>
         </div>
        `;
      }

      const html = `
        <div class="fgbw__loc" data-loc-id="${this.id}">
          <div class="fgbw__loc-head">
            <div class="fgbw__loc-title">${title}</div>
            ${actionsHtml}
            <div class="fgbw__loc-modes" role="tablist">
              <button type="button" class="fgbw__loc-mode is-active" data-mode="address">Address</button>
              <button type="button" class="fgbw__loc-mode" data-mode="airport">Airport</button>
            </div>
          </div>

          <div class="fgbw__loc-body">
            <!-- Address Mode -->
            <div class="fgbw__loc-pane" data-pane="address">
              <label class="fgbw__label">Address</label>
              <input type="text" class="fgbw__input fgbw__address" placeholder="Enter address" />
              <div class="fgbw__hint">Powered by Google Places</div>
            </div>

            <!-- Airport Mode -->
            <div class="fgbw__loc-pane is-hidden" data-pane="airport">
              <div class="fgbw__row">
                <div class="fgbw__col">
                  <label class="fgbw__label">Arrival Airport <span class="fgbw__req">*</span></label>
                  <input type="text" class="fgbw__input fgbw__airport" placeholder="Search airport..." />
                  <div class="fgbw__dropdown is-hidden" data-dd="airport"></div>
                </div>
              </div>

              <div class="fgbw__row">
                <div class="fgbw__col">
                  <label class="fgbw__label">Airline</label>
                  <input type="text" class="fgbw__input fgbw__airline" placeholder="Search airline..." />
                  <div class="fgbw__dropdown is-hidden" data-dd="airline"></div>
                </div>

                <div class="fgbw__col">
                  <label class="fgbw__label">Flight</label>
                  <input type="text" class="fgbw__input fgbw__flight" placeholder="e.g. AA123" />
                </div>
              </div>

              <label class="fgbw__chk">
                <input type="checkbox" class="fgbw__no-flight" />
                <span>I do not have my flight information</span>
              </label>

              <button type="button" class="fgbw__btn fgbw__btn--ghost fgbw__airport-confirm" disabled>
                Confirm
              </button>

              <div class="fgbw-flight-result"></div>

              <div class="fgbw__loader is-hidden" data-loader></div>
            </div>
          </div>
        </div>
        `;
      this.mountEl.innerHTML = html;
      this.$root = $(this.mountEl).find(`[data-loc-id="${this.id}"]`);
    }

    bind() {
      // Mode switch
      this.$root.on("click", ".fgbw__loc-mode", (e) => {
        const mode = $(e.currentTarget).data("mode");
        this.setMode(mode);
      });

      // Airport inputs
      this.$airport = this.$root.find(".fgbw__airport");
      this.$airline = this.$root.find(".fgbw__airline");
      this.$flight = this.$root.find(".fgbw__flight");
      this.$noFlight = this.$root.find(".fgbw__no-flight");
      this.$confirm = this.$root.find(".fgbw__airport-confirm");
      this.$loader = this.$root.find("[data-loader]");

      this.$airport.on("input", debounce(() => this.searchAirport(), 250));
      this.$airline.on("input", debounce(() => this.searchAirline(), 250));

      this.$flight.on("input", () => {
        this.state.flight = this.$flight.val().trim();
        this.updateAirportUIState();
        this.emit();
      });

      this.$noFlight.on("change", () => {
        this.state.noFlightInfo = !!this.$noFlight.prop("checked");
        this.updateAirportUIState();
        this.emit();
      });

      this.$confirm.on("click", async () => {
        // If checkbox checked => bypass confirm
        if (this.state.noFlightInfo) return;
        // Optional: call flight validation if both airline+flight present
        if (this.state.airport && this.state.airline && this.state.flight) {
          try {
            this.setLoading(true);
            const res = await wpAjax("fgbw_validate_flight", {
              airport_iata: this.state.airport.iata_code,
              airline_iata: this.state.airline.iata_code,
              flight_number: this.state.flight,
            });

            if (res.success && res.data) {
              this.renderFlightCard(res.data);
            }
          } catch (e) {
            // If validation fails, you can show UI error — kept minimal here
          } finally {
            this.setLoading(false);
          }
        }
      });

      // Click outside dropdown closes
      $(document).on("click", (ev) => {
        if (!this.$root[0].contains(ev.target)) {
          this.hideDropdown("airport");
          this.hideDropdown("airline");
        }
      });

      // Stop Actions
      this.$root.on('click', '.fgbw__del-stop', () => {
        if (this.onDelete) {
          // e.g. sweetalert or standard confirm
          if (confirm("Are you sure you want to remove this stop?")) {
            this.onDelete();
          }
        }
      });

      this.$root.on('click', '.fgbw__edit-stop', () => {
        console.log("Edit stop clicked", this.index);
        fgbwModal.open({
          title: `Additional Stop ${this.index + 1} Info`,
          pax: this.state.passenger_count,
          note: this.state.note,
          onSave: (data) => {
            this.state.passenger_count = data.pax;
            this.state.note = data.note;
            this.emit();
          }
        });
      });
    }

    setMode(mode) {
      this.state.mode = mode;
      this.$root.find(".fgbw__loc-mode").removeClass("is-active");
      this.$root.find(`.fgbw__loc-mode[data-mode="${mode}"]`).addClass("is-active");

      this.$root.find(`[data-pane="address"]`).toggleClass("is-hidden", mode !== "address");
      this.$root.find(`[data-pane="airport"]`).toggleClass("is-hidden", mode !== "airport");

      // Reset mode-specific state (optional)
      if (mode === "address") {
        this.state.airport = null;
        this.state.airline = null;
        this.state.flight = "";
        this.state.noFlightInfo = false;
      } else {
        this.state.address = null;
      }
      this.updateAirportUIState();
      this.emit();
    }

    setLoading(isLoading) {
      this.$loader.toggleClass("is-hidden", !isLoading);
    }

    updateAirportUIState() {
      const noInfo = !!this.state.noFlightInfo;

      // Grey-out airline+flight (screenshot behavior)
      this.$airline.prop("disabled", noInfo).toggleClass("is-disabled", noInfo);
      this.$flight.prop("disabled", noInfo).toggleClass("is-disabled", noInfo);

      if (noInfo) {
        this.$confirm.prop("disabled", true).addClass("is-disabled");
        return;
      }

      // Confirm enabled only when required airport chosen
      const ok = !!this.state.airport;
      this.$confirm.prop("disabled", !ok).toggleClass("is-disabled", !ok);
    }

    // async initAddressAutocomplete() {
    //   await loadGooglePlacesIfNeeded();
    //   if (!window.google || !window.google.maps || !window.google.maps.places) return;

    //   const input = this.$root.find(".fgbw__address")[0];
    //   if (!input) return;

    //   const autocomplete = new google.maps.places.Autocomplete(input, {
    //     fields: ["formatted_address", "place_id", "geometry"],
    //   });

    //   autocomplete.addListener("place_changed", () => {
    //     const place = autocomplete.getPlace();
    //     if (!place || !place.geometry) return;

    //     this.state.address = {
    //       formatted_address: place.formatted_address || "",
    //       place_id: place.place_id || "",
    //       lat: place.geometry.location.lat(),
    //       lng: place.geometry.location.lng(),
    //     };
    //     this.emit();
    //   });
    // }

    async initAddressAutocomplete() {

      await loadGooglePlaces();

      if (!window.google || !google.maps || !google.maps.places) {
        console.error("Google Places not available");
        return;
      }

      const input = this.$root.find(".fgbw__address")[0];
      if (!input) return;

      const autocomplete = new google.maps.places.Autocomplete(input, {
        types: ["geocode"]
      });

      autocomplete.addListener("place_changed", () => {
        const place = autocomplete.getPlace();
        if (!place.geometry) return;

        this.state.address = {
          formatted_address: place.formatted_address,
          lat: place.geometry.location.lat(),
          lng: place.geometry.location.lng(),
          place_id: place.place_id
        };
        this.emit();
      });
    }

    dropdownEl(kind) {
      return this.$root.find(`[data-dd="${kind}"]`);
    }

    showDropdown(kind, items, onPick) {
      const $dd = this.dropdownEl(kind);
      if (!items || !items.length) {
        $dd.addClass("is-hidden").empty();
        return;
      }

      const html = items
        .slice(0, 8)
        .map((it, i) => `<div class="fgbw__dd-item" data-idx="${i}">${it.label}</div>`)
        .join("");

      $dd.removeClass("is-hidden").html(html);

      $dd.off("click").on("click", ".fgbw__dd-item", (e) => {
        const idx = parseInt($(e.currentTarget).data("idx"), 10);
        const chosen = items[idx];
        if (chosen) onPick(chosen);
        $dd.addClass("is-hidden").empty();
      });
    }

    hideDropdown(kind) {
      this.dropdownEl(kind).addClass("is-hidden").empty();
    }

    async searchAirport() {
      const q = this.$airport.val().trim();
      if (q.length < 2) return this.hideDropdown("airport");

      try {
        this.setLoading(true);
        const res = await wpAjax("fgbw_search_airports", { q });
        const items = (res && res.data && res.data.items) ? res.data.items : [];
        const mapped = items.map((a) => ({
          value: a,
          label: `${a.airport_name} (${a.iata_code}) - ${a.city || ""} ${a.country_name || ""} `.trim(),
        }));

        this.showDropdown("airport", mapped, (chosen) => {
          this.state.airport = chosen.value;
          this.$airport.val(chosen.value.airport_name + " (" + chosen.value.iata_code + ")");
          this.updateAirportUIState();
          this.emit();
        });
      } finally {
        this.setLoading(false);
      }
    }

    async searchAirline() {
      const q = this.$airline.val().trim();
      if (q.length < 2) return this.hideDropdown("airline");

      // If "no flight info" checked => no search needed
      if (this.state.noFlightInfo) return;

      try {
        this.setLoading(true);
        const res = await wpAjax("fgbw_search_airlines", { q });
        const items = (res && res.data && res.data.items) ? res.data.items : [];
        const mapped = items.map((a) => ({
          value: a,
          label: `${a.airline_name} (${a.iata_code || a.icao_code || ""})`.trim(),
        }));

        this.showDropdown("airline", mapped, (chosen) => {
          this.state.airline = chosen.value;
          this.$airline.val(`${chosen.value.airline_name} (${chosen.value.iata_code || chosen.value.icao_code || ""})`);
          this.emit();
        });
      } finally {
        this.setLoading(false);
      }
    }

    getValue() {
      const out = { mode: this.state.mode };
      if (this.state.mode === "address") {
        out.address = this.state.address; // set by Google Places, or null if freehand
        // _rawText captures whatever the user typed even without a Places selection
        out._rawText = (this.$root.find(".fgbw__address").val() || "").trim();
        // If Places didn't fire but user typed something, build a minimal address object
        if (!out.address && out._rawText) {
          out.address = { formatted_address: out._rawText, lat: null, lng: null, place_id: null };
        }
        out.airport = null;
      } else {
        out.address = null;
        out._rawText = "";
        out.airport = this.state.airport;
        out.airline = this.state.noFlightInfo ? null : this.state.airline;
        out.flight = this.state.noFlightInfo ? "" : this.state.flight;
        out.no_flight_info = this.state.noFlightInfo;
      }
      out.passenger_count = this.state.passenger_count;
      out.note = this.state.note;
      return out;
    }

    emit() {
      if (typeof this.onChange === "function") {
        this.onChange(this.getValue());
      }
    }

    renderFlightCard(data) {

      const $result = this.$root.find(".fgbw-flight-result");
      if (!$result.length) return;

      $result.html(`
        <div class="fgbw-flight-card">
            <div class="fgbw-flight-card__top">
                <strong>${data.airline} (${data.flight_iata})</strong>
                <span class="fgbw-flight-badge">
                    ${(data.status || "").toUpperCase()}
                </span>
            </div>

            <div class="fgbw-flight-card__row">
                <div>
                    <strong>${data.departure_iata}</strong><br>
                    ${data.departure_airport}<br>
                    ${this.formatDT(data.departure_time)}
                </div>

                <div>✈</div>

                <div>
                    <strong>${data.arrival_iata}</strong><br>
                    ${data.arrival_airport}<br>
                    ${this.formatDT(data.arrival_time)}
                </div>
            </div>
        </div>
    `);
    }

    formatDT(s) {
      if (!s) return "-";
      const d = new Date(s);
      if (isNaN(d.getTime())) return s;
      return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

  }

  // ---------- Wizard Controller ----------
  class BookingWizard {
    constructor($root) {
      this.$root = $root;
      this.step = 1;

      this.state = {
        trip_type: "one_way",
        order_type: "",
        trip: {
          pickup: this.emptySegment(), // used for one-way AND round pickup
          return: this.emptySegment(), // used only for round return
        },
        vehicle: "",
        contact: { name: "", email: "", phone: "" },
        luggage: { carry: 0, checked: 0, oversize: 0 }
      };

      this.blocks = {
        oneway: { pickup: null, dropoff: null, stops: [] },
        round_pickup: { pickup: null, dropoff: null, stops: [] },
        round_return: { pickup: null, dropoff: null, stops: [] },
      };

      // this.$root.on("click", "[data-next]", () => this.next());
      // this.$root.on("click", "[data-prev]", () => this.prev());
      // this.$root.on("click", "[data-submit]", () => this.submit());

      this.init();
    }

    showSuccess(id) {
      this.$root.find(".fgbw__form").html(`
    <div class="fgbw__thankyou">
      <h2>Thank You!</h2>
      <p>Your booking request has been submitted successfully.</p>
      <p><strong>Booking ID:</strong> ${id}</p>
    </div>
  `);
    }

    emptySegment() {
      return { datetime: "", pickup: null, stops: [], dropoff: null, passenger_count: 1 };
    }

    init() {
      // Anti-duplicate token
      const tok = uid("subm");
      this.$root.find(".fgbw__submission_token").val(tok);

      // Clear honeypot immediately — browsers can autofill even hidden fields
      this.$root.find('input[name="company_hp"]').val("");
      this.initTripToggle();
      this.initOrderTypeSelect();
      this.initDatePickers();
      this.initPassengerControls();
      this.initLocationBlocks();
      this.initStops();
      this.initVehicles();
      this.initLuggage();
      this.bindNavButtons();
      this.applyTripTypeUI();
    }

    initTripToggle() {
      this.$root.on("click", ".fgbw__toggle-btn", (e) => {
        const type = $(e.currentTarget).data("trip-type");
        this.state.trip_type = type;
        this.$root.find(".fgbw__toggle-btn").removeClass("is-active");
        $(e.currentTarget).addClass("is-active");
        this.applyTripTypeUI();
      });
    }

    applyTripTypeUI() {
      const isRound = this.state.trip_type === "round_trip";
      this.$root.find('.fgbw__segment--oneway').toggleClass('is-hidden', isRound);
      this.$root.find('.fgbw__segment--round-pickup').toggleClass('is-hidden', !isRound);
      this.$root.find('.fgbw__segment--round-return').toggleClass('is-hidden', !isRound);
    }

    initOrderTypeSelect() {
      const $sel = this.$root.find(".fgbw__order_type");
      const data = (FGBW.orderTypeGroups || []).map(g => ({
        text: g.label,
        children: g.options.map(o => ({ id: o.id, text: o.text })),
      }));

      $sel.select2({
        data,
        width: "100%",
        placeholder: "Select order type",
        allowClear: true,
      });

      $sel.on("change", () => {
        this.state.order_type = $sel.val() || "";
      });
    }

    initDatePickers() {
      const make = (segKey) => {
        const el = this.$root.find(`[data-datetime-for="${segKey}"]`)[0];
        if (!el) return;
        window.flatpickr(el, {
          enableTime: true,
          dateFormat: FGBW.dateFormat || "Y-m-d h:i K",
          time_24hr: false,
          allowInput: false,
        });
        $(el).on("change", () => {
          const v = $(el).val().trim();
          if (segKey === "oneway") this.state.trip.pickup.datetime = v;
          if (segKey === "round_pickup") this.state.trip.pickup.datetime = v;
          if (segKey === "round_return") this.state.trip.return.datetime = v;
        });
      };
      make("oneway");
      make("round_pickup");
      make("round_return");
    }

    initPassengerControls() {
      const bindQty = (segKey, getter, setter) => {
        const $wrap = this.$root.find(`[data-qty-for="${segKey}"]`);
        if (!$wrap.length) return;

        const $input = $wrap.find(".fgbw__qty-input");
        const sync = () => $input.val(String(getter()));

        $wrap.on("click", "[data-qty-plus]", () => {
          const v = Math.max(1, getter() + 1);
          setter(v);
          sync();
        });

        $wrap.on("click", "[data-qty-minus]", () => {
          const v = Math.max(1, getter() - 1);
          setter(v);
          sync();
        });

        sync();
      };

      bindQty("oneway",
        () => this.state.trip.pickup.passenger_count,
        (v) => (this.state.trip.pickup.passenger_count = v)
      );

      bindQty("round_pickup",
        () => this.state.trip.pickup.passenger_count,
        (v) => (this.state.trip.pickup.passenger_count = v)
      );

      bindQty("round_return",
        () => this.state.trip.return.passenger_count,
        (v) => (this.state.trip.return.passenger_count = v)
      );
    }

    initLuggage() {
      const bindLug = (key) => {
        const $wrap = this.$root.find(`[data-qty-for="lug_${key}"]`);
        if (!$wrap.length) return;

        const $input = $wrap.find(".fgbw__qty-input");
        const sync = () => $input.val(this.state.luggage[key]);

        $wrap.on("click", "[data-qty-plus]", () => {
          this.state.luggage[key]++;
          sync();
        });

        $wrap.on("click", "[data-qty-minus]", () => {
          this.state.luggage[key] = Math.max(0, this.state.luggage[key] - 1);
          sync();
        });
      };

      bindLug("carry");
      bindLug("checked");
      bindLug("oversize");
    }

    initLocationBlocks() {
      const mount = (selector, args) => {
        const el = this.$root.find(selector)[0];
        if (!el) return null;
        return new LocationBlock(Object.assign({ mountEl: el }, args));
      };

      // One-way uses trip.pickup segment
      this.blocks.oneway.pickup = mount('[data-location-block="oneway_pickup"]', {
        type: "pickup",
        tripSegment: "oneway",
        onChange: (val) => { this.state.trip.pickup.pickup = val; }
      });

      this.blocks.oneway.dropoff = mount('[data-location-block="oneway_dropoff"]', {
        type: "dropoff",
        tripSegment: "oneway",
        onChange: (val) => { this.state.trip.pickup.dropoff = val; }
      });

      // Round pickup uses trip.pickup
      this.blocks.round_pickup.pickup = mount('[data-location-block="round_pickup_pickup"]', {
        type: "pickup",
        tripSegment: "round_pickup",
        onChange: (val) => { this.state.trip.pickup.pickup = val; }
      });

      this.blocks.round_pickup.dropoff = mount('[data-location-block="round_pickup_dropoff"]', {
        type: "dropoff",
        tripSegment: "round_pickup",
        onChange: (val) => { this.state.trip.pickup.dropoff = val; }
      });

      // Round return uses trip.return
      this.blocks.round_return.pickup = mount('[data-location-block="round_return_pickup"]', {
        type: "pickup",
        tripSegment: "round_return",
        onChange: (val) => { this.state.trip.return.pickup = val; }
      });

      this.blocks.round_return.dropoff = mount('[data-location-block="round_return_dropoff"]', {
        type: "dropoff",
        tripSegment: "round_return",
        onChange: (val) => { this.state.trip.return.dropoff = val; }
      });
    }

    initStops() {
      this.$root.on("click", "[data-add-stop]", (e) => {
        const segKey = $(e.currentTarget).data("add-stop"); // oneway|round_pickup|round_return
        this.addStop(segKey);
      });
    }

    addStop(segKey) {
      const listEl = this.$root.find(`[data-stops-list="${segKey}"]`)[0];
      if (!listEl) return;

      const idx = this.blocks[segKey].stops.length;
      const wrap = document.createElement("div");
      wrap.className = "fgbw__stop-item";
      listEl.appendChild(wrap);

      const block = new LocationBlock({
        mountEl: wrap,
        type: "stop",
        tripSegment: segKey,
        index: idx,
        onChange: (val) => {
          if (segKey === "oneway") {
            this.state.trip.pickup.stops[idx] = val;
          } else if (segKey === "round_pickup") {
            this.state.trip.pickup.stops[idx] = val;
          } else {
            this.state.trip.return.stops[idx] = val;
          }
        },
        onDelete: () => this.removeStop(segKey, idx)
      });

      this.blocks[segKey].stops.push(block);

      // Init array slot
      if (segKey === "oneway" || segKey === "round_pickup") {
        this.state.trip.pickup.stops[idx] = block.getValue();
      } else {
        this.state.trip.return.stops[idx] = block.getValue();
      }
    }

    removeStop(segKey, idx) {
      const stops = this.blocks[segKey].stops;
      if (!stops[idx]) return;

      // Remove DOM
      stops[idx].mountEl.remove();

      // Remove from array
      stops.splice(idx, 1);

      // Re-index remaining stops
      stops.forEach((blk, i) => {
        blk.index = i;
        blk.render();
        blk.bind();
        blk.initAddressAutocomplete(); // Need to re-init autocomplete after re-render!
      });

      // Update State
      const currentVals = stops.map(b => b.getValue());
      if (segKey === "oneway" || segKey === "round_pickup") {
        this.state.trip.pickup.stops = currentVals;
      } else {
        this.state.trip.return.stops = currentVals;
      }
    }

    initVehicles() {
      // Kept empty or basic for compatibility, but logic removed visually in HTML
    }

    // bindNavButtons() {
    //   this.$root.on("click", "[data-next]", () => this.next());
    //   this.$root.on("click", "[data-prev]", () => this.prev());
    //   this.$root.on("click", "[data-submit]", () => this.submit());
    //   this.$root.on("click", "[data-new-booking]", () => this.resetForm());
    // }

    bindNavButtons() {
      this.$root.on("click", "[data-next]", (e) => {
        e.preventDefault();
        this.next();
      });

      this.$root.on("click", "[data-prev]", (e) => {
        e.preventDefault();
        this.prev();
      });

      this.$root.on("click", "[data-submit]", (e) => {
        e.preventDefault();
        this.submit();
      });

      this.$root.on("click", "[data-new-booking]", (e) => {
        e.preventDefault();
        this.resetForm();
      });
    }

    resetForm() {
      // Reset to initial state
      this.step = 1;
      this.state = {
        trip_type: "one_way",
        order_type: "",
        trip: {
          pickup: this.emptySegment(),
          return: this.emptySegment(),
        },
        vehicle: "",
        contact: { name: "", email: "", phone: "" },
        luggage: { carry: 0, checked: 0, oversize: 0 }
      };

      // Reset form fields
      this.$root.find('input[type="text"], input[type="email"], input[type="tel"], textarea').val('');
      this.$root.find('select').val('').trigger('change');
      this.$root.find('input[type="checkbox"]').prop('checked', false);

      // Clear location blocks
      Object.values(this.blocks).forEach(segment => {
        Object.values(segment).forEach(block => {
          if (block && block.getValue) {
            block.state = {
              mode: "address",
              address: null,
              airport: null,
              airline: null,
              flight: "",
              noFlightInfo: false,
              passenger_count: 1,
              note: ""
            };
            block.render();
            block.bind();
          }
        });
      });

      // Clear stops
      this.blocks.oneway.stops = [];
      this.blocks.round_pickup.stops = [];
      this.blocks.round_return.stops = [];

      // Reset UI
      this.$root.find('[data-stops-list="oneway"], [data-stops-list="round_pickup"], [data-stops-list="round_return"]').empty();
      this.$root.find('.fgbw__toggle-btn').removeClass("is-active").first().addClass("is-active");

      // Generate new submission token
      const tok = uid("subm");
      this.$root.find(".fgbw__submission_token").val(tok);

      // Clear errors
      clearErrors();

      // Go to step 1
      this.goTo(1);
    }

    goTo(step) {
      this.step = step;
      this.$root.find(".fgbw__step").removeClass("is-active");
      this.$root.find(`[data-step="${step}"]`).addClass("is-active");

      this.$root.find(".fgbw__step-indicator").removeClass("is-active");
      this.$root.find(`[data-step-ind="${step}"]`).addClass("is-active");
      clearErrors();

      // Scroll top
      $('html, body').animate({ scrollTop: this.$root.offset().top - 100 }, 300);

      if (step === 2) this.renderSummary();
    }

    // next() {
    //   if (!this.validateStep(this.step)) return;
    //   this.goTo(Math.min(2, this.step + 1));
    // }

    next() {
      const isValid = this.validateStep(this.step);
      console.log("Validation result:", isValid);

      if (!isValid) return;

      this.goTo(Math.min(2, this.step + 1));
    }


    prev() {
      this.goTo(Math.max(1, this.step - 1));
    }

    validateStep(step) {
      if (step === 1) {
        if (!this.state.order_type) {
          this.toast("Please select an order type.", true);
          return false;
        }

        if (this.state.trip_type === "one_way") {
          if (!this.state.trip.pickup.datetime) {
            this.toast("Date & time is required.", true);
            return false;
          }
          if (!this.isLocationValid(this.state.trip.pickup.pickup, "Pick-Up")) return false;
          if (!this.isLocationValid(this.state.trip.pickup.dropoff, "Drop-Off")) return false;
          if (this.state.trip.pickup.passenger_count < 1) {
            this.toast("Passenger count must be at least 1.", true);
            return false;
          }
        } else {
          // Round pickup segment
          if (!this.state.trip.pickup.datetime) {
            this.toast("Pick-up date & time is required.", true);
            return false;
          }
          if (!this.isLocationValid(this.state.trip.pickup.pickup, "Pick-Up")) return false;
          if (!this.isLocationValid(this.state.trip.pickup.dropoff, "Drop-Off")) return false;

          // Round return segment
          if (!this.state.trip.return.datetime) {
            this.toast("Return date & time is required.", true);
            return false;
          }
          if (!this.isLocationValid(this.state.trip.return.pickup, "Return Pick-Up")) return false;
          if (!this.isLocationValid(this.state.trip.return.dropoff, "Return Drop-Off")) return false;

          if (this.state.trip.pickup.passenger_count < 1) {
            this.toast("Passenger count must be at least 1.", true);
            return false;
          }
          if (this.state.trip.return.passenger_count < 1) {
            this.toast("Passenger count must be at least 1.", true);
            return false;
          }
        }

        return true;
      }

      if (step === 2) {

        // FIX: Read contact from this.state.contact (already set by submit() before calling us)
        // rather than re-reading from DOM here. This prevents double-read bugs and makes
        // validateStep() a pure validator with no side effects.
        const nameParts = (this.state.contact.name || "").split(" ");
        const first = nameParts[0] || "";
        const last  = nameParts.slice(1).join(" ") || "";
        const email = this.state.contact.email || "";
        const phone = this.state.contact.phone || "";

        if (!first) { this.toast("First name is required", true); return false; }
        if (!last) { this.toast("Last name is required", true); return false; }
        if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
          this.toast("Valid email is required", true);
          return false;
        }
        if (!phone) { this.toast("Phone is required", true); return false; }

        return true;
      }

      return true;
    }

    isLocationValid(loc, label = "Location") {
      // Accept: (1) Google Places selection, (2) freehand typed text, (3) airport selection
      if (!loc || !loc.mode) {
        this.toast("Please complete the " + label + ".", true);
        return false;
      }
      if (loc.mode === "address") {
        const hasPlaces  = loc.address && loc.address.formatted_address;
        const hasRawText = loc._rawText && loc._rawText.trim().length > 0;
        if (!hasPlaces && !hasRawText) {
          this.toast("Please enter an address for " + label + ".", true);
          return false;
        }
      } else {
        if (!loc.airport || !loc.airport.iata_code) {
          this.toast("Please select an airport for " + label + ".", true);
          return false;
        }
      }
      return true;
    }

    renderSummary() {
      // Use state data, not block values which are partial
      const trip = this.state.trip.pickup;

      const isRound = this.state.trip_type === "round_trip";
      const tripTitle = isRound ? "Round Trip" : "One Way";
      const totalPax = Math.max(1, parseInt(trip.passenger_count || 1));

      // Date Formatting: Wednesday, Feb 18th, 2026 7:44 PM
      const dateObj = trip.datetime ? new Date(trip.datetime) : null;
      let dateStr = "Select Date";
      let timeStr = "";
      if (dateObj && !isNaN(dateObj.getTime())) {
        const day = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
        const month = dateObj.toLocaleDateString('en-US', { month: 'short' });
        const dNum = dateObj.getDate();
        const year = dateObj.getFullYear();
        // ordinal suffix
        const suffix = (dNum > 3 && dNum < 21) ? 'th' : ['th', 'st', 'nd', 'rd'][dNum % 10] || 'th';

        timeStr = dateObj.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        dateStr = `${day}, ${month} ${dNum}${suffix}, ${year} ${timeStr}`;
      }

      // Header Icons (dummy data for visual match)
      const headerIcons = `
        <div class="fgbw__sum-meta-icons">
           <span class="fgbw__meta-icon"><i class="fa fa-bell-slash-o"></i></span>
           <span class="fgbw__meta-icon"><i class="fa fa-clock-o"></i> N/A <i class="fa fa-bolt fgbw__text-green"></i></span>
           <span class="fgbw__meta-icon"><i class="fa fa-user-o"></i> ${totalPax}</span>
        </div>
      `;

      const headerHtml = `
         <div class="fgbw__sum-main-title">
            <h2>${tripTitle}</h2>
            ${headerIcons}
         </div>
         <div class="fgbw__sum-date-label">PICK-UP DATE & TIME</div>
         <div class="fgbw__sum-date-val">${dateStr}</div>
      `;
      this.$root.find("[data-summary-header]").html(headerHtml);

      // Timeline Logic
      const getAddr = (loc) => {
        if (!loc) return "Select Location";
        if (loc.mode === 'airport') return `<span class="fgbw__addr-main">${loc.airport.airport_name}</span> <span class="fgbw__addr-sub">(${loc.airport.iata_code})</span>`;
        return loc.address ? `<span class="fgbw__addr-main">${loc.address.formatted_address.split(',')[0]}</span>` : "Select Location";
      };

      const mkTimeLineItem = (type, loc, isLast) => {
        const colorClass = type === 'pickup' ? 'fgbw__marker--orange' : (type === 'dropoff' ? 'fgbw__marker--purple' : 'fgbw__marker--black');
        const label = type === 'pickup' ? 'PICK-UP' : (type === 'dropoff' ? 'DROP-OFF' : 'STOP');

        // Extras below address
        let extras = "";
        if (type === 'pickup' && loc && loc.mode === 'airport') {
          extras = `<div class="fgbw__tl-extra"><i class="fa fa-plane"></i> ${loc.flight || 'Flight info'}</div>`;
        }
        else if (type === 'dropoff') {
          extras = `<div class="fgbw__tl-extra"><i class="fa fa-clock-o"></i> ${timeStr} <i class="fa fa-bolt fgbw__text-green"></i></div>`;
        }

        return `
            <div class="fgbw__tl-item ${type}">
               <div class="fgbw__tl-marker ${colorClass}"></div>
               <div class="fgbw__tl-content">
                  <div class="fgbw__tl-label">${label}</div>
                  <div class="fgbw__tl-val">${getAddr(loc)}</div>
                  ${extras}
               </div>
            </div>
         `;
      };

      let timelineHtml = `<div class="fgbw__tl-line"></div>`;
      timelineHtml += mkTimeLineItem('pickup', trip.pickup);

      if (trip.stops && trip.stops.length) {
        trip.stops.forEach(stop => {
          timelineHtml += mkTimeLineItem('stop', stop);
        });
      }

      timelineHtml += mkTimeLineItem('dropoff', trip.dropoff, true);
      this.$root.find("[data-summary-timeline]").html(timelineHtml);


      // Additional Info Grid
      const luggageCount = (this.state.luggage.carry || 0) + (this.state.luggage.checked || 0) + (this.state.luggage.oversize || 0);

      const addGridHtml = `
         <div class="fgbw__ainfo-col">
            <label>PASSENGER COUNT</label>
            <div>${totalPax}</div>
         </div>
         <div class="fgbw__ainfo-col">
            <label>PASSENGER CONTACT</label>
            <div>-</div> <!-- placeholder until filled -->
         </div>
         <div class="fgbw__ainfo-col">
            <label>LUGGAGE COUNT</label>
            <div>${luggageCount}</div>
         </div>
         <div class="fgbw__ainfo-col">
            <label>TRIP NOTE</label>
            <div>-</div>
         </div>
      `;
      this.$root.find("[data-summary-additional]").html(addGridHtml);

      // Bind Edit Link
      this.$root.find(".fgbw__edit-lnk").off("click").on("click", () => this.prev());
    }

    locToText(loc) {
      if (!loc) return "-";
      if (loc.mode === "address") return (loc.address && loc.address.formatted_address) ? loc.address.formatted_address : "-";
      const a = loc.airport;
      if (!a) return "-";
      return `${a.airport_name} (${a.iata_code})`;
    }

    async submit() {

      console.log("=== FGBW Submit clicked ===");
      console.log("Current step:", this.step);

      // FIX: Populate state.contact from DOM BEFORE validateStep(2) runs.
      // Previously, validateStep(2) set this.state.contact internally, but if any
      // validation failed the state was still set yet submit() had already read stale
      // empty state values. Now we always read fresh DOM values here first.
      const first = this.$root.find('input[name="first_name"]').val().trim();
      const last  = this.$root.find('input[name="last_name"]').val().trim();
      const email = this.$root.find('input[name="email"]').val().trim();
      const phone = this.$root.find('input[name="phone"]').val().trim();
      this.state.contact = { name: (first + " " + last).trim(), email, phone };

      console.log("Contact state:", JSON.stringify(this.state.contact));
      console.log("Trip state:", JSON.stringify(this.state.trip));
      console.log("Order type:", this.state.order_type, "| Trip type:", this.state.trip_type);

      if (!this.validateStep(this.step)) {
        console.log("validateStep(" + this.step + ") FAILED - check toast message");
        return;
      }
      console.log("validateStep passed - proceeding to AJAX");

      const hp = this.$root.find('input[name="company_hp"]').val().trim();
      if (hp) {
        // HONEYPOT TRIGGERED - likely browser autofill filled a hidden field.
        // This was silently blocking all submissions. Log it so it's visible.
        console.warn("FGBW: Honeypot field was filled (value: '" + hp + "'). Clearing and retrying.");
        this.$root.find('input[name="company_hp"]').val("");
        // Don't block — autofill filling a hidden field is not a real spam signal.
        // Just clear it and continue.
      }

      const $btn = this.$root.find("[data-submit]");
      const $spin = this.$root.find(".fgbw__spinner");
      $btn.prop("disabled", true);
      $spin.removeClass("is-hidden");

      const payload = {
        trip_type: this.state.trip_type,
        order_type: this.state.order_type,
        vehicle: this.state.vehicle,
        name: this.state.contact.name,
        email: this.state.contact.email,
        phone: this.state.contact.phone,
        luggage: this.state.luggage,
        submission_token: this.$root.find(".fgbw__submission_token").val(),
        trip: this.state.trip,
      };

      try {
        const res = await wpAjax("fgbw_submit_booking", {
          payload: JSON.stringify(payload),
        });

        if (!res || !res.success) {
          const msg = (res && res.data && res.data.message) ? res.data.message : "Submission failed.";
          this.toast(msg, true);
          return;
        }

        // Show success screen with booking details
        this.$root.find('[data-booking-id]').text(res.data.booking_id || "");
        this.$root.find('[data-confirmation-email]').text(this.state.contact.email);
        this.goTo(3);
      } catch (e) {
        console.error("FGBW submit error:", e);
        this.toast("Submission failed. Please try again.", true);
      } finally {
        $btn.prop("disabled", false);
        $spin.addClass("is-hidden");
      }
    }

    toast(msg, isError) {
      console.log("FGBW Toast:", isError ? "[ERROR]" : "[INFO]", msg);
      const $t = this.$root.find("[data-toast]");
      $t.text(msg).removeClass("is-hidden").toggleClass("is-error", !!isError);
      clearTimeout(this._toastTimer);
      this._toastTimer = setTimeout(() => $t.addClass("is-hidden"), 8000);
    }
  }

  // ---------- Boot ----------
  $(function () {
    const $root = $(".fgbw");
    if (!$root.length) return;
    $root.each((_, el) => new BookingWizard($(el)));
  });

  function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleString();
  }

  document.addEventListener("click", function (e) {

    if (!e.target.classList.contains("fgbw-airport-confirm-btn")) return;

    const block = e.target.closest(".fgbw-location-block");
    if (!block) return;

    const airlineIata = block.querySelector(".fgbw-airport-airline-iata")?.value?.trim();
    const flightNo = block.querySelector(".fgbw-airport-flight")?.value?.trim();
    const resultBox = block.querySelector(".fgbw-flight-result");

    if (!resultBox) return;

    if (!airlineIata || !flightNo) {
      resultBox.innerHTML = `<div class="fgbw__error">Please select airline and enter flight number.</div>`;
      return;
    }

    resultBox.innerHTML = `<div class="fgbw-loading">Loading flight details...</div>`;

    fetch(fgbw_ajax.ajax_url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: new URLSearchParams({
        action: "fgbw_fetch_flight",
        _wpnonce: fgbw_ajax.nonce,
        airline_iata: airlineIata,
        flight_number: flightNo
      })
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          resultBox.innerHTML = `<div class="fgbw__error">${res.data?.message || "Flight not found"}</div>`;
          return;
        }
        renderFlightCard(resultBox, res.data);
      })
      .catch(() => {
        resultBox.innerHTML = `<div class="fgbw__error">Unable to fetch flight details.</div>`;
      });

  });

  function renderFlightCard(container, data) {
    container.innerHTML = `
    <div class="fgbw-flight-card">
      <div class="fgbw-flight-card__top">
        <strong>${data.airline} (${data.flight_iata})</strong>
        <span class="fgbw-flight-badge">${(data.status || "").toUpperCase()}</span>
      </div>
      <div class="fgbw-flight-card__row">
        <div class="fgbw-flight-col">
          <div class="fgbw-flight-iata">${data.departure_iata || "-"}</div>
          <div class="fgbw-flight-airport">${data.departure_airport || "-"}</div>
          <div class="fgbw-flight-time">${formatDT(data.departure_time)}</div>
        </div>
        <div class="fgbw-flight-mid">✈</div>
        <div class="fgbw-flight-col">
          <div class="fgbw-flight-iata">${data.arrival_iata || "-"}</div>
          <div class="fgbw-flight-airport">${data.arrival_airport || "-"}</div>
          <div class="fgbw-flight-time">${formatDT(data.arrival_time)}</div>
        </div>
      </div>
    </div>
  `;
  }

  function formatDT(s) {
    if (!s) return "-";
    const d = new Date(s);
    if (isNaN(d.getTime())) return s;
    return d.toLocaleString();
  }

})(jQuery);