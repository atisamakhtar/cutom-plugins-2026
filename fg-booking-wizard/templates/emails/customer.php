<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation Confirmation</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:32px 16px;">
  <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);">

    <!-- HEADER -->
    <tr>
      <td style="background-color:#111827;padding:28px 32px;text-align:center;">
        <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:0.5px;">Optimus Fleets LLC</p>
        <p style="margin:6px 0 0;font-size:13px;color:#9ca3af;letter-spacing:1px;text-transform:uppercase;">Premium Transportation Services</p>
      </td>
    </tr>

    <!-- HERO BANNER -->
    <tr>
      <td style="background-color:#f97316;padding:20px 32px;text-align:center;">
        <p style="margin:0;font-size:18px;font-weight:700;color:#ffffff;">&#10003; Reservation Successfully Submitted</p>
        <p style="margin:6px 0 0;font-size:13px;color:#fff7ed;">Booking Reference: <strong>#{booking_id}</strong></p>
      </td>
    </tr>

    <!-- GREETING -->
    <tr>
      <td style="padding:28px 32px 12px;">
        <p style="margin:0;font-size:15px;color:#374151;">Dear <strong>{name}</strong>,</p>
        <p style="margin:12px 0 0;font-size:14px;color:#6b7280;line-height:1.6;">
          Your reservation from <strong>Optimus Fleets LLC</strong> was successfully submitted.
          We will review your booking and get back to you shortly.
        </p>
      </td>
    </tr>

    <!-- SECTION: SERVICE DETAILS -->
    <tr>
      <td style="padding:20px 32px 0;">
        <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#f97316;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #f97316;padding-bottom:6px;">Service Details</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;width:180px;">Services Type</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;"><strong>{order_type_label}</strong></td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Trip Type</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{trip_type_label}</td>
          </tr>
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Number of Passengers</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{passenger_count}</td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- SECTION: TRIP DETAILS (pickup + dropoff + date/time) -->
    <tr>
      <td style="padding:20px 32px 0;">
        <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#f97316;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #f97316;padding-bottom:6px;">Trip Details</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;width:180px;">Pick-Up Date</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{pickup_date}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Pick-Up Time</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{pickup_time}</td>
          </tr>
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Pick-Up Location</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{pickup_location}</td>
          </tr>
          {pickup_stops_html}
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Drop-Off Location</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{dropoff_location}</td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- SECTION: LUGGAGE -->
    <tr>
      <td style="padding:20px 32px 0;">
        <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#f97316;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #f97316;padding-bottom:6px;">Luggage</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;width:180px;">Carry-On Luggage</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{carry_on}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Checked-in Luggage</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{checked}</td>
          </tr>
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Oversize Luggage</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{oversize}</td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- SECTION: FLIGHT INFO -->
    <tr>
      <td style="padding:20px 32px 0;">
        <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#f97316;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #f97316;padding-bottom:6px;">Flight Information</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;width:180px;">Select Airlines</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{airline}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Flight Number</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{flight_number}</td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- SECTION: RETURN -->
    <tr>
      <td style="padding:20px 32px 0;">
        <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#f97316;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #f97316;padding-bottom:6px;">Return Reservation</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;width:180px;">Return Trip</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{is_round_trip}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Return Pick-Up Date</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{return_date}</td>
          </tr>
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Return Pick-Up Time</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{return_time}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Return Airlines</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{return_airline}</td>
          </tr>
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Return Flight Number</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{return_flight_number}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Return Pick-Up</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{return_pickup_location}</td>
          </tr>
          {return_stops_html}
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Return Drop-Off</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{return_dropoff_location}</td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- SECTION: CONTACT INFO -->
    <tr>
      <td style="padding:20px 32px 0;">
        <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#f97316;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #f97316;padding-bottom:6px;">Contact Information</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;width:180px;">Name</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{name}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Email</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{email}</td>
          </tr>
          <tr>
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Phone / Mobile</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{phone}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;">Messages</td>
            <td style="padding:7px 0;font-size:14px;color:#111827;vertical-align:top;">{additional_note}</td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- CLOSING MESSAGE -->
    <tr>
      <td style="padding:24px 32px 0;">
        <p style="margin:0;font-size:14px;color:#374151;line-height:1.6;">
          Thank you! We look forward to serving you.
        </p>
      </td>
    </tr>

    <!-- SIGNATURE — name only, no duplicate contact info -->
    <tr>
      <td style="padding:20px 32px 28px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5e7eb;padding-top:18px;margin-top:4px;">
          <tr>
            <td>
              <p style="margin:0;font-size:14px;font-weight:700;color:#111827;">Best Wishes,</p>
              <p style="margin:4px 0 0;font-size:14px;color:#374151;">Team of <strong>Optimus Fleets LLC</strong></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- BRANDED FOOTER WITH LOGO -->
    <tr>
      <td style="background-color:#111827;padding:28px 32px;text-align:center;">
        <!--
          Logo: PNG, 480x231px (2x retina source), transparent background.
          Displayed at 240x116px CSS — sharp on all screens including HiDPI/Retina.
          PNG used instead of WebP for universal email client support
          (Gmail, Outlook 2013–2021, Apple Mail, Yahoo Mail all render PNG with transparency).
          width+height HTML attrs = Outlook layout stability.
          -ms-interpolation-mode:bicubic = Outlook high-quality downscale.
          background-color on <a> matches footer so Outlook (which ignores PNG alpha) 
          shows the logo on the same dark background instead of white.
        -->
        <a href="https://optimusfleets.us" target="_blank"
           style="display:inline-block;text-decoration:none;line-height:0;background-color:#111827;">
          <img src="{email_logo_url}"
               alt="Optimus Fleets LLC"
               width="240"
               height="116"
               border="0"
               style="display:block;width:240px;height:116px;margin:0 auto;border:0;outline:none;-ms-interpolation-mode:bicubic;" />
        </a>
        <div style="height:12px;"></div>
        <p style="margin:0 0 6px;">
          <a href="https://www.optimusfleets.us" target="_blank" style="color:#FD8B48;text-decoration:none;font-size:14px;font-weight:600;letter-spacing:0.3px;">www.optimusfleets.us</a>
        </p>
        <p style="margin:0 0 8px;font-size:13px;font-weight:500;letter-spacing:0.2px;">
          <a href="mailto:optimusfleetsllc@gmail.com" style="color:#FD8B48;text-decoration:none;">optimusfleetsllc@gmail.com</a>
          <span style="color:#4b5563;">&nbsp;&nbsp;|&nbsp;&nbsp;</span>
          <a href="tel:+18564433401" style="color:#FD8B48;text-decoration:none;">856-443-3401</a>
        </p>
        <p style="margin:0;font-size:12px;color:#9ca3af;letter-spacing:0.3px;">
          &copy; Optimus Fleets LLC &middot; All rights reserved
        </p>
      </td>
    </tr>

  </table>
  </td></tr>
</table>

</body>
</html>
