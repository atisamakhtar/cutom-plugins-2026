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

  // ---------- Google Loader ----------
  // let googleLoaded = false;
  // function loadGooglePlacesIfNeeded() {
  //   if (googleLoaded) return Promise.resolve();
  //   if (!FGBW.googlePlacesKey) return Promise.resolve();
  //   return new Promise((resolve, reject) => {
  //     if (window.google && window.google.maps && window.google.maps.places) {
  //       googleLoaded = true;
  //       resolve();
  //       return;
  //     }
  //     const cbName = "fgbwGoogleInit_" + uid("cb");
  //     window[cbName] = () => {
  //       googleLoaded = true;
  //       resolve();
  //       try { delete window[cbName]; } catch (e) { }
  //     };
  //     const s = document.createElement("script");
  //     s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(
  //       FGBW.googlePlacesKey
  //     )}&libraries=places&callback=${cbName}`;
  //     s.async = true;
  //     s.onerror = reject;
  //     document.head.appendChild(s);
  //   });
  // }

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

      const existingScript = document.querySelector('script[src*="maps.googleapis.com"]');

      if (existingScript) {
        existingScript.addEventListener('load', () => resolve());
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
        encodeURIComponent(FGBW.googlePlacesKey) +
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

  // ---------- LocationBlock Component ----------
  class LocationBlock {
    constructor({ mountEl, type, tripSegment, index = null, onChange }) {
      this.mountEl = mountEl;
      this.type = type; // pickup | dropoff | stop
      this.tripSegment = tripSegment; // oneway | round_pickup | round_return
      this.index = index; // stop index
      this.onChange = onChange;

      this.state = {
        mode: "address", // address | airport
        address: null,   // { formatted_address, lat, lng, place_id }
        airport: null,   // { iata_code, airport_name, country_name, city }
        airline: null,   // { iata_code, airline_name }
        flight: "",
        noFlightInfo: false,
      };

      this.id = uid(`loc_${tripSegment}_${type}`);
      this.render();
      this.bind();
      this.initAddressAutocomplete();
    }

    render() {
      const title = this.type === "pickup" ? "Pick-Up" : this.type === "dropoff" ? "Drop-Off" : `Stop ${this.index + 1}`;
      const html = `
        <div class="fgbw__loc" data-loc-id="${this.id}">
          <div class="fgbw__loc-head">
            <div class="fgbw__loc-title">${title}</div>
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

      const input = this.$root.find(".fgbw__address-input")[0];
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
          label: `${a.airport_name} (${a.iata_code}) - ${a.city || ""} ${a.country_name || ""}`.trim(),
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
        out.address = this.state.address; // may be null until chosen
        out.airport = null;
      } else {
        out.address = null;
        out.airport = this.state.airport;
        out.airline = this.state.noFlightInfo ? null : this.state.airline;
        out.flight = this.state.noFlightInfo ? "" : this.state.flight;
        out.no_flight_info = this.state.noFlightInfo;
      }
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
      return d.toLocaleString();
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
      };

      this.blocks = {
        oneway: { pickup: null, dropoff: null, stops: [] },
        round_pickup: { pickup: null, dropoff: null, stops: [] },
        round_return: { pickup: null, dropoff: null, stops: [] },
      };

      this.init();
    }

    emptySegment() {
      return { datetime: "", pickup: null, stops: [], dropoff: null, passenger_count: 1 };
    }

    init() {
      // Anti-duplicate token
      const tok = uid("subm");
      this.$root.find(".fgbw__submission_token").val(tok);

      this.initTripToggle();
      this.initOrderTypeSelect();
      this.initDatePickers();
      this.initPassengerControls();
      this.initLocationBlocks();
      this.initStops();
      this.initVehicles();
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
      });

      this.blocks[segKey].stops.push(block);

      // Init array slot
      if (segKey === "oneway" || segKey === "round_pickup") {
        this.state.trip.pickup.stops[idx] = block.getValue();
      } else {
        this.state.trip.return.stops[idx] = block.getValue();
      }
    }

    initVehicles() {
      const $wrap = this.$root.find("[data-vehicles]");
      const items = FGBW.vehicles || [];
      const html = items.map(v => `
        <label class="fgbw__vehicle">
          <input type="radio" name="vehicle" value="${v.id}">
          <div class="fgbw__vehicle-card">
            <div class="fgbw__vehicle-name">${v.name}</div>
            <div class="fgbw__vehicle-meta">${v.desc || ""} • Seats: ${v.seats || ""}</div>
          </div>
        </label>
      `).join("");
      $wrap.html(html);

      $wrap.on("change", 'input[name="vehicle"]', () => {
        this.state.vehicle = $wrap.find('input[name="vehicle"]:checked').val() || "";
      });
    }

    bindNavButtons() {
      this.$root.on("click", "[data-next]", () => this.next());
      this.$root.on("click", "[data-prev]", () => this.prev());
      this.$root.on("click", "[data-submit]", () => this.submit());
    }

    goTo(step) {
      this.step = step;
      this.$root.find(".fgbw__step").removeClass("is-active");
      this.$root.find(`[data-step="${step}"]`).addClass("is-active");

      this.$root.find(".fgbw__step-indicator").removeClass("is-active");
      this.$root.find(`[data-step-ind="${step}"]`).addClass("is-active");
      clearErrors();

      if (step === 3) this.renderSummary();
    }

    next() {
      if (!this.validateStep(this.step)) return;
      this.goTo(Math.min(3, this.step + 1));
    }

    prev() {
      this.goTo(Math.max(1, this.step - 1));
    }

    validateStep(step) {
      clearErrors();

      if (step === 1) {
        if (!this.state.order_type) {
          setError("order_type", "Please select an order type.");
          return false;
        }

        if (this.state.trip_type === "one_way") {
          if (!this.state.trip.pickup.datetime) {
            setError("oneway_datetime", "Date & time is required.");
            return false;
          }
          if (!this.isLocationValid(this.state.trip.pickup.pickup)) return false;
          if (!this.isLocationValid(this.state.trip.pickup.dropoff)) return false;
          if (this.state.trip.pickup.passenger_count < 1) {
            setError("oneway_passengers", "Passenger count must be at least 1.");
            return false;
          }
        } else {
          // Round pickup segment
          if (!this.state.trip.pickup.datetime) {
            setError("round_pickup_datetime", "Pick-up date & time is required.");
            return false;
          }
          if (!this.isLocationValid(this.state.trip.pickup.pickup)) return false;
          if (!this.isLocationValid(this.state.trip.pickup.dropoff)) return false;

          // Round return segment
          if (!this.state.trip.return.datetime) {
            setError("round_return_datetime", "Return date & time is required.");
            return false;
          }
          if (!this.isLocationValid(this.state.trip.return.pickup, "round_return")) return false;
          if (!this.isLocationValid(this.state.trip.return.dropoff, "round_return")) return false;

          if (this.state.trip.pickup.passenger_count < 1) {
            setError("round_pickup_passengers", "Passenger count must be at least 1.");
            return false;
          }
          if (this.state.trip.return.passenger_count < 1) {
            setError("round_return_passengers", "Passenger count must be at least 1.");
            return false;
          }
        }

        return true;
      }

      if (step === 2) {
        if (!this.state.vehicle) {
          setError("vehicle", "Please choose a vehicle.");
          return false;
        }
        return true;
      }

      if (step === 3) {
        const name = this.$root.find('input[name="name"]').val().trim();
        const email = this.$root.find('input[name="email"]').val().trim();
        const phone = this.$root.find('input[name="phone"]').val().trim();

        this.state.contact = { name, email, phone };

        if (!name) { setError("name", "Name is required."); return false; }
        if (!email || !/^\S+@\S+\.\S+$/.test(email)) { setError("email", "Valid email is required."); return false; }
        if (!phone) { setError("phone", "Phone is required."); return false; }
        return true;
      }

      return true;
    }

    isLocationValid(loc, scope = "oneway") {
      // loc example = { mode, address | airport ... }
      if (!loc || !loc.mode) {
        setError(scope === "oneway" ? "oneway_datetime" : "round_pickup_datetime", "Please complete locations.");
        return false;
      }
      if (loc.mode === "address") {
        if (!loc.address || !loc.address.place_id) {
          setError("order_type", "Please select a valid address from suggestions.");
          return false;
        }
      } else {
        if (!loc.airport || !loc.airport.iata_code) {
          setError("order_type", "Please select an airport.");
          return false;
        }
      }
      return true;
    }

    renderSummary() {
      const $sum = this.$root.find("[data-summary]");
      const tripType = this.state.trip_type === "one_way" ? "One Way" : "Round Trip";

      const segToText = (seg, label) => {
        const pickup = this.locToText(seg.pickup);
        const dropoff = this.locToText(seg.dropoff);
        const stops = (seg.stops || []).filter(Boolean).map(s => this.locToText(s)).join(" → ");
        return `
          <div class="fgbw__sum-block">
            <div class="fgbw__sum-title">${label}</div>
            <div class="fgbw__sum-line"><strong>Date/Time:</strong> ${seg.datetime || "-"}</div>
            <div class="fgbw__sum-line"><strong>Pick-Up:</strong> ${pickup}</div>
            ${stops ? `<div class="fgbw__sum-line"><strong>Stops:</strong> ${stops}</div>` : ""}
            <div class="fgbw__sum-line"><strong>Drop-Off:</strong> ${dropoff}</div>
            <div class="fgbw__sum-line"><strong>Passengers:</strong> ${seg.passenger_count}</div>
          </div>
        `;
      };

      const pickupSeg = this.state.trip.pickup;
      const returnSeg = this.state.trip.return;

      const html = `
        <div class="fgbw__sum-head">
          <div><strong>Trip Type:</strong> ${tripType}</div>
          <div><strong>Order Type:</strong> ${this.state.order_type}</div>
          <div><strong>Vehicle:</strong> ${this.state.vehicle}</div>
        </div>
        ${segToText(pickupSeg, this.state.trip_type === "one_way" ? "One Way" : "Round Trip: Pick-Up")}
        ${this.state.trip_type === "round_trip" ? segToText(returnSeg, "Round Trip: Return") : ""}
      `;
      $sum.html(html);
    }

    locToText(loc) {
      if (!loc) return "-";
      if (loc.mode === "address") return (loc.address && loc.address.formatted_address) ? loc.address.formatted_address : "-";
      const a = loc.airport;
      if (!a) return "-";
      return `${a.airport_name} (${a.iata_code})`;
    }

    async submit() {
      if (!this.validateStep(3)) return;

      const hp = this.$root.find('input[name="company_hp"]').val().trim();
      if (hp) return; // honeypot: silent drop

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

        this.toast(`Booking confirmed. ID: ${res.data.booking_id}`, false);
      } catch (e) {
        this.toast("Submission failed. Please try again.", true);
      } finally {
        $btn.prop("disabled", false);
        $spin.addClass("is-hidden");
      }
    }

    toast(msg, isError) {
      const $t = this.$root.find("[data-toast]");
      $t.text(msg).removeClass("is-hidden").toggleClass("is-error", !!isError);
      setTimeout(() => $t.addClass("is-hidden"), 4500);
    }
  }

  // ---------- Boot ----------
  $(function () {
    const $root = $(".fgbw");
    if (!$root.length) return;
    $root.each((_, el) => new BookingWizard($(el)));
  });

  document.querySelector('.fgbw-confirm-btn').addEventListener('click', function () {

    const airlineIata = document.querySelector('#airline_iata').value;
    const flightNumber = document.querySelector('#flight_number').value;

    if (!airlineIata || !flightNumber) {
      alert("Please enter airline and flight number");
      return;
    }

    fetch(fgbw_ajax.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'fgbw_fetch_flight',
        _wpnonce: fgbw_ajax.nonce,
        airline_iata: airlineIata,
        flight_number: flightNumber
      })
    })
      .then(res => res.json())
      .then(response => {

        if (!response.success) {
          alert(response.data.message);
          return;
        }

        renderFlightCard(response.data);
      });
  });

  // function renderFlightCard(data) {

  //   const container = document.querySelector('.fgbw-flight-result');

  //   container.innerHTML = `
  //       <div class="fgbw-flight-card">
  //           <h4>${data.airline} (${data.flight_iata})</h4>
  //           <p>Status: ${data.status.toUpperCase()}</p>

  //           <div class="fgbw-flight-row">
  //               <div>
  //                   <strong>${data.departure_iata}</strong><br>
  //                   ${data.departure_airport}<br>
  //                   ${formatDate(data.departure_time)}
  //               </div>

  //               <div>
  //                   ✈
  //               </div>

  //               <div>
  //                   <strong>${data.arrival_iata}</strong><br>
  //                   ${data.arrival_airport}<br>
  //                   ${formatDate(data.arrival_time)}
  //               </div>
  //           </div>
  //       </div>
  //   `;
  // }

  // function renderFlightCard(data) {
  //   const $result = this.$root.find(".fgbw__flight-result");

  //   $result.html(`
  //   <div class="fgbw-flight-card">
  //     <div class="fgbw-flight-card__top">
  //       <strong>${data.airline} (${data.flight_iata})</strong>
  //       <span class="fgbw-flight-badge">${data.status?.toUpperCase() || ""}</span>
  //     </div>

  //     <div class="fgbw-flight-card__row">
  //       <div>
  //         <strong>${data.departure_iata}</strong><br>
  //         ${data.departure_airport}<br>
  //         ${data.departure_time}
  //       </div>

  //       <div>✈</div>

  //       <div>
  //         <strong>${data.arrival_iata}</strong><br>
  //         ${data.arrival_airport}<br>
  //         ${data.arrival_time}
  //       </div>
  //     </div>
  //   </div>
  // `);
  // }

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