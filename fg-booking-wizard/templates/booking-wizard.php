<?php if (!defined('ABSPATH')) exit; ?>
<div class="fgbw">
  <form class="fgbw__form" autocomplete="off">
    <input type="text" name="company_hp" class="fgbw__hp" tabindex="-1" value="" aria-hidden="true" />
    <input type="hidden" name="submission_token" class="fgbw__submission_token" value="" />

    <div class="fgbw__steps">
      <div class="fgbw__step-indicator is-active" data-step-ind="1">Request Information</div>
      <div class="fgbw__step-indicator" data-step-ind="2">Choose Vehicle</div>
      <div class="fgbw__step-indicator" data-step-ind="3">Confirm</div>
    </div>

    <!-- STEP 1 -->
    <section class="fgbw__step is-active" data-step="1">
      <div class="fgbw__row">
        <div class="fgbw__toggle" role="tablist" aria-label="Trip Type">
          <button type="button" class="fgbw__toggle-btn is-active" data-trip-type="one_way">One Way</button>
          <button type="button" class="fgbw__toggle-btn" data-trip-type="round_trip">Round Trip</button>
        </div>
      </div>

      <div class="fgbw__row">
        <label class="fgbw__label">Order Type</label>
        <select class="fgbw__order_type" name="order_type" required></select>
        <div class="fgbw__error" data-error-for="order_type"></div>
      </div>

      <!-- One Way Segment -->
      <div class="fgbw__segment fgbw__segment--oneway" data-segment="oneway">
        <h3 class="fgbw__segment-title">One Way</h3>

        <div class="fgbw__row">
          <label class="fgbw__label">Date &amp; Time</label>
          <input type="text" class="fgbw__datetime" data-datetime-for="oneway" placeholder="YYYY-MM-DD 10:30 AM" required />
          <div class="fgbw__error" data-error-for="oneway_datetime"></div>
        </div>

        <div class="fgbw__block" data-location-block="oneway_pickup"></div>

        <div class="fgbw__stops" data-stops-wrap="oneway">
          <button type="button" class="fgbw__add-stop" data-add-stop="oneway">+ Add Stop</button>
          <div class="fgbw__stops-list" data-stops-list="oneway"></div>
        </div>

        <div class="fgbw__block" data-location-block="oneway_dropoff"></div>

        <div class="fgbw__row">
          <label class="fgbw__label">Passenger Count</label>
          <div class="fgbw__qty" data-qty-for="oneway">
            <button type="button" class="fgbw__qty-btn" data-qty-minus>-</button>
            <input type="text" class="fgbw__qty-input" value="1" inputmode="numeric" readonly />
            <button type="button" class="fgbw__qty-btn" data-qty-plus>+</button>
          </div>
          <div class="fgbw__error" data-error-for="oneway_passengers"></div>
        </div>
      </div>

      <!-- Round Trip Pickup Segment -->
      <div class="fgbw__segment fgbw__segment--round-pickup is-hidden" data-segment="round_pickup">
        <h3 class="fgbw__segment-title">Round Trip: Pick-Up</h3>

        <div class="fgbw__row">
          <label class="fgbw__label">Date &amp; Time</label>
          <input type="text" class="fgbw__datetime" data-datetime-for="round_pickup" placeholder="YYYY-MM-DD 10:30 AM" />
          <div class="fgbw__error" data-error-for="round_pickup_datetime"></div>
        </div>

        <div class="fgbw__block" data-location-block="round_pickup_pickup"></div>

        <div class="fgbw__stops" data-stops-wrap="round_pickup">
          <button type="button" class="fgbw__add-stop" data-add-stop="round_pickup">+ Add Stop</button>
          <div class="fgbw__stops-list" data-stops-list="round_pickup"></div>
        </div>

        <div class="fgbw__block" data-location-block="round_pickup_dropoff"></div>

        <div class="fgbw__row">
          <label class="fgbw__label">Passenger Count</label>
          <div class="fgbw__qty" data-qty-for="round_pickup">
            <button type="button" class="fgbw__qty-btn" data-qty-minus>-</button>
            <input type="text" class="fgbw__qty-input" value="1" readonly />
            <button type="button" class="fgbw__qty-btn" data-qty-plus>+</button>
          </div>
          <div class="fgbw__error" data-error-for="round_pickup_passengers"></div>
        </div>
      </div>

      <!-- Round Trip Return Segment -->
      <div class="fgbw__segment fgbw__segment--round-return is-hidden" data-segment="round_return">
        <h3 class="fgbw__segment-title">Round Trip: Return</h3>

        <div class="fgbw__row">
          <label class="fgbw__label">Return Date &amp; Time</label>
          <input type="text" class="fgbw__datetime" data-datetime-for="round_return" placeholder="YYYY-MM-DD 10:30 AM" />
          <div class="fgbw__error" data-error-for="round_return_datetime"></div>
        </div>

        <div class="fgbw__block" data-location-block="round_return_pickup"></div>

        <div class="fgbw__stops" data-stops-wrap="round_return">
          <button type="button" class="fgbw__add-stop" data-add-stop="round_return">+ Add Stop</button>
          <div class="fgbw__stops-list" data-stops-list="round_return"></div>
        </div>

        <div class="fgbw__block" data-location-block="round_return_dropoff"></div>

        <div class="fgbw__row">
          <label class="fgbw__label">Passenger Count</label>
          <div class="fgbw__qty" data-qty-for="round_return">
            <button type="button" class="fgbw__qty-btn" data-qty-minus>-</button>
            <input type="text" class="fgbw__qty-input" value="1" readonly />
            <button type="button" class="fgbw__qty-btn" data-qty-plus>+</button>
          </div>
          <div class="fgbw__error" data-error-for="round_return_passengers"></div>
        </div>
      </div>

      <div class="fgbw__actions">
        <button type="button" class="fgbw__btn fgbw__btn--primary" data-next>Next</button>
      </div>
    </section>

    <!-- STEP 2 -->
    <section class="fgbw__step" data-step="2">
      <div class="fgbw__vehicles" data-vehicles></div>
      <div class="fgbw__error" data-error-for="vehicle"></div>

      <div class="fgbw__actions">
        <button type="button" class="fgbw__btn" data-prev>Back</button>
        <button type="button" class="fgbw__btn fgbw__btn--primary" data-next>Next</button>
      </div>
    </section>

    <!-- STEP 3 -->
    <section class="fgbw__step" data-step="3">
      <div class="fgbw__summary" data-summary></div>

      <div class="fgbw__row fgbw__contact">
        <div class="fgbw__col">
          <label class="fgbw__label">Name</label>
          <input type="text" class="fgbw__input" name="name" required />
          <div class="fgbw__error" data-error-for="name"></div>
        </div>
        <div class="fgbw__col">
          <label class="fgbw__label">Email</label>
          <input type="email" class="fgbw__input" name="email" required />
          <div class="fgbw__error" data-error-for="email"></div>
        </div>
        <div class="fgbw__col">
          <label class="fgbw__label">Phone</label>
          <input type="tel" class="fgbw__input" name="phone" required />
          <div class="fgbw__error" data-error-for="phone"></div>
        </div>
      </div>

      <div class="fgbw__actions">
        <button type="button" class="fgbw__btn" data-prev>Back</button>
        <button type="button" class="fgbw__btn fgbw__btn--primary" data-submit>
          <span class="fgbw__btn-text">Confirm Booking</span>
          <span class="fgbw__spinner is-hidden" aria-hidden="true"></span>
        </button>
      </div>

      <div class="fgbw__toast is-hidden" data-toast></div>
    </section>
  </form>
</div>