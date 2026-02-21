<?php if (!defined('ABSPATH')) exit; ?>
<div class="fgbw">
  <form class="fgbw__form" autocomplete="off">
    <input type="text" name="company_hp" class="fgbw__hp" tabindex="-1" value="" aria-hidden="true" autocomplete="nope" style="display:none!important;visibility:hidden!important;position:absolute!important;left:-99999px!important;" />
    <input type="hidden" name="submission_token" class="fgbw__submission_token" value="" />

    <div class="fgbw__steps">
      <div class="fgbw__step-indicator is-active" data-step-ind="1">Request Information</div>
      <div class="fgbw__step-indicator" data-step-ind="2">Quote Request</div>
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
        <select class="fgbw__order_type" name="order_type" required>
          <option value=""></option>
        </select>
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

    <!-- STEP 2: Quote Request (Confirmation) -->
    <section class="fgbw__step" data-step="2">
      <div class="fgbw__quote-container">
        <!-- Left Side: Summary -->
        <div class="fgbw__quote-left">
           <div class="fgbw__summary-header" data-summary-header></div>

           <div class="fgbw__timeline-wrap">
              <div class="fgbw__timeline" data-summary-timeline></div>
           </div>

           <div class="fgbw__add-info">
             <div class="fgbw__add-info-head">
                <h3>Additional Trip Info</h3>
                <button type="button" class="fgbw__edit-lnk" data-prev>Edit <i class="fa fa-pencil"></i></button>
             </div>
             <div class="fgbw__add-info-grid" data-summary-additional></div>
           </div>
        </div>

        <!-- Right Side: Contact & Luggage -->
        <div class="fgbw__quote-right">
           <div class="fgbw__luggage-sect">
              <h3>Luggage Count</h3>
              <div class="fgbw__lr-row">
                 <span>Carry-On</span>
                 <div class="fgbw__qty fgbw__qty--sm" data-qty-for="lug_carry">
                   <button type="button" class="fgbw__qty-btn" data-qty-minus>-</button>
                   <input type="text" class="fgbw__qty-input" value="0" readonly />
                   <button type="button" class="fgbw__qty-btn" data-qty-plus>+</button>
                 </div>
              </div>
              <div class="fgbw__lr-row">
                 <span>Checked</span>
                 <div class="fgbw__qty fgbw__qty--sm" data-qty-for="lug_checked">
                   <button type="button" class="fgbw__qty-btn" data-qty-minus>-</button>
                   <input type="text" class="fgbw__qty-input" value="0" readonly />
                   <button type="button" class="fgbw__qty-btn" data-qty-plus>+</button>
                 </div>
              </div>
              <div class="fgbw__lr-row">
                 <span>Oversize</span>
                 <div class="fgbw__qty fgbw__qty--sm" data-qty-for="lug_oversize">
                   <button type="button" class="fgbw__qty-btn" data-qty-minus>-</button>
                   <input type="text" class="fgbw__qty-input" value="0" readonly />
                   <button type="button" class="fgbw__qty-btn" data-qty-plus>+</button>
                 </div>
              </div>
           </div>

           <div class="fgbw__contact-sect">
              <h3>Booking Contact</h3>

               <div class="fgbw__row">
                     <label class="fgbw__label">Phone *</label>
                     <input type="tel" class="fgbw__input fgbw__phone" name="phone_number" placeholder="Phone number *" required />
                     <div class="fgbw__error" data-error-for="phone" style="margin-top:4px;"></div>
                  </div>

              <div class="fgbw__row">
                 <input type="email" class="fgbw__input" name="email" placeholder="Email *" required />
              </div>

              <div class="fgbw__row fgbw__flex-row">
                 <input type="text" class="fgbw__input" name="first_name" placeholder="First name *" required />
                 <input type="text" class="fgbw__input" name="last_name" placeholder="Last name *" required />
              </div>
           </div>

           <div class="fgbw__actions fgbw__actions--col">
             <button type="button" class="fgbw__btn fgbw__btn--primary fgbw__btn--full" data-submit>
               <span class="fgbw__btn-text">Send Request</span>
               <span class="fgbw__spinner is-hidden" aria-hidden="true"></span>
             </button>
             <div class="fgbw__disclaimer">
               By clicking "Send Request" you agree to receive order updates via SMS/Email.
             </div>
           </div>

           <div class="fgbw__toast is-hidden" data-toast></div>
        </div>
      </div>
    </section>

    <!-- STEP 3: Success Screen -->
    <section class="fgbw__step" data-step="3">
      <div class="fgbw__success-screen">
        <div class="fgbw__success-icon">
          <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        </div>
        <h2 class="fgbw__success-title">Thank You for Your Booking!</h2>
        <p class="fgbw__success-message">
          Your booking request has been successfully submitted.
        </p>
        <div class="fgbw__success-details">
          <div class="fgbw__detail-row">
            <span class="fgbw__detail-label">Booking ID:</span>
            <span class="fgbw__detail-value" data-booking-id></span>
          </div>
          <div class="fgbw__detail-row">
            <span class="fgbw__detail-label">Confirmation Email:</span>
            <span class="fgbw__detail-value" data-confirmation-email></span>
          </div>
        </div>
        <p class="fgbw__success-notice">
          We've sent confirmation emails to both you and our admin team. We'll review your request and get back to you shortly with a quote.
        </p>
        <div class="fgbw__success-actions">
          <button type="button" class="fgbw__btn fgbw__btn--primary" data-new-booking>Create Another Booking</button>
        </div>
      </div>
    </section>
  </form>
</div>