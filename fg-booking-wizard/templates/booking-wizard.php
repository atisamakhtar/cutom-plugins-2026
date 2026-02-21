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
                     <label class="fgbw__label">Phone</label>
                     <div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;">
                       <select name="phone_country" class="fgbw__select fgbw__phone-country" style="width:180px;" required>
                         <option value="+1" selected>+1 ([ 'United States)</option>
                         <option value="+1" selected>+1 ([ 'Canada)</option>
                         <option value="+44">+44 ([ 'United Kingdom)</option>
                         <option value="+93">+93 ([ 'Afghanistan)</option>
                         <option value="+355">+355 ([ 'Albania)</option>
                         <option value="+213">+213 ([ 'Algeria)</option>
                         <option value="+376">+376 ([ 'Andorra)</option>
                         <option value="+244">+244 ([ 'Angola)</option>
                         <option value="+54">+54 ([ 'Argentina)</option>
                         <option value="+374">+374 ([ 'Armenia)</option>
                         <option value="+61">+61 ([ 'Australia)</option>
                         <option value="+43">+43 ([ 'Austria)</option>
                         <option value="+994">+994 ([ 'Azerbaijan)</option>
                         <option value="+1242">+1242 ([ 'Bahamas)</option>
                         <option value="+973">+973 ([ 'Bahrain)</option>
                         <option value="+880">+880 ([ 'Bangladesh)</option>
                         <option value="+1246">+1246 ([ 'Barbados)</option>
                         <option value="+375">+375 ([ 'Belarus)</option>
                         <option value="+32">+32 ([ 'Belgium)</option>
                         <option value="+501">+501 ([ 'Belize)</option>
                         <option value="+229">+229 ([ 'Benin)</option>
                         <option value="+975">+975 ([ 'Bhutan)</option>
                         <option value="+591">+591 ([ 'Bolivia)</option>
                         <option value="+387">+387 ([ 'Bosnia and Herzegovina)</option>
                         <option value="+267">+267 ([ 'Botswana)</option>
                         <option value="+55">+55 ([ 'Brazil)</option>
                         <option value="+673">+673 ([ 'Brunei)</option>
                         <option value="+359">+359 ([ 'Bulgaria)</option>
                         <option value="+226">+226 ([ 'Burkina Faso)</option>
                         <option value="+257">+257 ([ 'Burundi)</option>
                         <option value="+855">+855 ([ 'Cambodia)</option>
                         <option value="+237">+237 ([ 'Cameroon)</option>
                         <option value="+238">+238 ([ 'Cape Verde)</option>
                         <option value="+236">+236 ([ 'Central African Republic)</option>
                         <option value="+235">+235 ([ 'Chad)</option>
                         <option value="+56">+56 ([ 'Chile)</option>
                         <option value="+86">+86 ([ 'China)</option>
                         <option value="+57">+57 ([ 'Colombia)</option>
                         <option value="+269">+269 ([ 'Comoros)</option>
                         <option value="+242">+242 ([ 'Congo)</option>
                         <option value="+506">+506 ([ 'Costa Rica)</option>
                         <option value="+385">+385 ([ 'Croatia)</option>
                         <option value="+53">+53 ([ 'Cuba)</option>
                         <option value="+357">+357 ([ 'Cyprus)</option>
                         <option value="+420">+420 ([ 'Czech Republic)</option>
                         <option value="+45">+45 ([ 'Denmark)</option>
                         <option value="+253">+253 ([ 'Djibouti)</option>
                         <option value="+1809">+1809 ([ 'Dominican Republic)</option>
                         <option value="+243">+243 ([ 'DR Congo)</option>
                         <option value="+593">+593 ([ 'Ecuador)</option>
                         <option value="+20">+20 ([ 'Egypt)</option>
                         <option value="+503">+503 ([ 'El Salvador)</option>
                         <option value="+240">+240 ([ 'Equatorial Guinea)</option>
                         <option value="+291">+291 ([ 'Eritrea)</option>
                         <option value="+372">+372 ([ 'Estonia)</option>
                         <option value="+251">+251 ([ 'Ethiopia)</option>
                         <option value="+679">+679 ([ 'Fiji)</option>
                         <option value="+358">+358 ([ 'Finland)</option>
                         <option value="+33">+33 ([ 'France)</option>
                         <option value="+241">+241 ([ 'Gabon)</option>
                         <option value="+220">+220 ([ 'Gambia)</option>
                         <option value="+995">+995 ([ 'Georgia)</option>
                         <option value="+49">+49 ([ 'Germany)</option>
                         <option value="+233">+233 ([ 'Ghana)</option>
                         <option value="+30">+30 ([ 'Greece)</option>
                         <option value="+1473">+1473 ([ 'Grenada)</option>
                         <option value="+502">+502 ([ 'Guatemala)</option>
                         <option value="+224">+224 ([ 'Guinea)</option>
                         <option value="+245">+245 ([ 'Guinea-Bissau)</option>
                         <option value="+592">+592 ([ 'Guyana)</option>
                         <option value="+509">+509 ([ 'Haiti)</option>
                         <option value="+504">+504 ([ 'Honduras)</option>
                         <option value="+852">+852 ([ 'Hong Kong)</option>
                         <option value="+36">+36 ([ 'Hungary)</option>
                         <option value="+354">+354 ([ 'Iceland)</option>
                         <option value="+91">+91 ([ 'India)</option>
                         <option value="+62">+62 ([ 'Indonesia)</option>
                         <option value="+98">+98 ([ 'Iran)</option>
                         <option value="+964">+964 ([ 'Iraq)</option>
                         <option value="+353">+353 ([ 'Ireland)</option>
                         <option value="+972">+972 ([ 'Israel)</option>
                         <option value="+39">+39 ([ 'Italy)</option>
                         <option value="+1876">+1876 ([ 'Jamaica)</option>
                         <option value="+81">+81 ([ 'Japan)</option>
                         <option value="+962">+962 ([ 'Jordan)</option>
                         <option value="+7">+7 ([ 'Kazakhstan)</option>
                         <option value="+254">+254 ([ 'Kenya)</option>
                         <option value="+965">+965 ([ 'Kuwait)</option>
                         <option value="+996">+996 ([ 'Kyrgyzstan)</option>
                         <option value="+856">+856 ([ 'Laos)</option>
                         <option value="+371">+371 ([ 'Latvia)</option>
                         <option value="+961">+961 ([ 'Lebanon)</option>
                         <option value="+266">+266 ([ 'Lesotho)</option>
                         <option value="+231">+231 ([ 'Liberia)</option>
                         <option value="+218">+218 ([ 'Libya)</option>
                         <option value="+370">+370 ([ 'Lithuania)</option>
                         <option value="+352">+352 ([ 'Luxembourg)</option>
                         <option value="+389">+389 ([ 'Macedonia)</option>
                         <option value="+261">+261 ([ 'Madagascar)</option>
                         <option value="+265">+265 ([ 'Malawi)</option>
                         <option value="+60">+60 ([ 'Malaysia)</option>
                         <option value="+960">+960 ([ 'Maldives)</option>
                         <option value="+223">+223 ([ 'Mali)</option>
                         <option value="+356">+356 ([ 'Malta)</option>
                         <option value="+222">+222 ([ 'Mauritania)</option>
                         <option value="+230">+230 ([ 'Mauritius)</option>
                         <option value="+52">+52 ([ 'Mexico)</option>
                         <option value="+373">+373 ([ 'Moldova)</option>
                         <option value="+377">+377 ([ 'Monaco)</option>
                         <option value="+976">+976 ([ 'Mongolia)</option>
                         <option value="+382">+382 ([ 'Montenegro)</option>
                         <option value="+212">+212 ([ 'Morocco)</option>
                         <option value="+258">+258 ([ 'Mozambique)</option>
                         <option value="+95">+95 ([ 'Myanmar)</option>
                         <option value="+264">+264 ([ 'Namibia)</option>
                         <option value="+977">+977 ([ 'Nepal)</option>
                         <option value="+31">+31 ([ 'Netherlands)</option>
                         <option value="+64">+64 ([ 'New Zealand)</option>
                         <option value="+505">+505 ([ 'Nicaragua)</option>
                         <option value="+227">+227 ([ 'Niger)</option>
                         <option value="+234">+234 ([ 'Nigeria)</option>
                         <option value="+47">+47 ([ 'Norway)</option>
                         <option value="+968">+968 ([ 'Oman)</option>
                         <option value="+92">+92 ([ 'Pakistan)</option>
                         <option value="+970">+970 ([ 'Palestine)</option>
                         <option value="+507">+507 ([ 'Panama)</option>
                         <option value="+675">+675 ([ 'Papua New Guinea)</option>
                         <option value="+595">+595 ([ 'Paraguay)</option>
                         <option value="+51">+51 ([ 'Peru)</option>
                         <option value="+63">+63 ([ 'Philippines)</option>
                         <option value="+48">+48 ([ 'Poland)</option>
                         <option value="+351">+351 ([ 'Portugal)</option>
                         <option value="+1787">+1787 ([ 'Puerto Rico)</option>
                         <option value="+974">+974 ([ 'Qatar)</option>
                         <option value="+40">+40 ([ 'Romania)</option>
                         <option value="+7">+7 ([ 'Russia)</option>
                         <option value="+250">+250 ([ 'Rwanda)</option>
                         <option value="+966">+966 ([ 'Saudi Arabia)</option>
                         <option value="+221">+221 ([ 'Senegal)</option>
                         <option value="+381">+381 ([ 'Serbia)</option>
                         <option value="+248">+248 ([ 'Seychelles)</option>
                         <option value="+232">+232 ([ 'Sierra Leone)</option>
                         <option value="+65">+65 ([ 'Singapore)</option>
                         <option value="+421">+421 ([ 'Slovakia)</option>
                         <option value="+386">+386 ([ 'Slovenia)</option>
                         <option value="+252">+252 ([ 'Somalia)</option>
                         <option value="+27">+27 ([ 'South Africa)</option>
                         <option value="+82">+82 ([ 'South Korea)</option>
                         <option value="+211">+211 ([ 'South Sudan)</option>
                         <option value="+34">+34 ([ 'Spain)</option>
                         <option value="+94">+94 ([ 'Sri Lanka)</option>
                         <option value="+249">+249 ([ 'Sudan)</option>
                         <option value="+597">+597 ([ 'Suriname)</option>
                         <option value="+46">+46 ([ 'Sweden)</option>
                         <option value="+41">+41 ([ 'Switzerland)</option>
                         <option value="+963">+963 ([ 'Syria)</option>
                         <option value="+886">+886 ([ 'Taiwan)</option>
                         <option value="+992">+992 ([ 'Tajikistan)</option>
                         <option value="+255">+255 ([ 'Tanzania)</option>
                         <option value="+66">+66 ([ 'Thailand)</option>
                         <option value="+228">+228 ([ 'Togo)</option>
                         <option value="+1868">+1868 ([ 'Trinidad and Tobago)</option>
                         <option value="+216">+216 ([ 'Tunisia)</option>
                         <option value="+90">+90 ([ 'Turkey)</option>
                         <option value="+993">+993 ([ 'Turkmenistan)</option>
                         <option value="+256">+256 ([ 'Uganda)</option>
                         <option value="+380">+380 ([ 'Ukraine)</option>
                         <option value="+971">+971 ([ 'United Arab Emirates)</option>
                         <option value="+598">+598 ([ 'Uruguay)</option>
                         <option value="+998">+998 ([ 'Uzbekistan)</option>
                         <option value="+58">+58 ([ 'Venezuela)</option>
                         <option value="+84">+84 ([ 'Vietnam)</option>
                         <option value="+967">+967 ([ 'Yemen)</option>
                         <option value="+260">+260 ([ 'Zambia)</option>
                         <option value="+263">+263 ([ 'Zimbabwe)</option>
                       </select>
                       <input type="tel" class="fgbw__input fgbw__phone" name="phone_number" placeholder="123-456-7890" required style="flex:1;min-width:200px;" />
                     </div>
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