# FG Booking Wizard - Deployment Instructions

## 🚀 Quick Deployment Steps

### 1. Backup Current Plugin
```bash
# Via FTP or cPanel File Manager
1. Download entire /wp-content/plugins/fg-booking-wizard/ folder
2. Export database table: wp_fg_bookings
3. Save backup with date: fg-booking-wizard-backup-2026-02-21.zip
```

### 2. Upload Updated Plugin
```bash
# Method A: Via WordPress Admin (Recommended)
1. Go to Plugins → Add New → Upload Plugin
2. Upload FG-Booking-Wizard.zip
3. Click "Replace current with uploaded"
4. Activate if needed

# Method B: Via FTP
1. Delete /wp-content/plugins/fg-booking-wizard/
2. Upload new folder
3. Activate plugin in WordPress admin
```

### 3. Fix Database (IMPORTANT)
```sql
-- Run this in phpMyAdmin → SQL tab
-- This fixes the trip type filter bug (53 vs 50 records)

UPDATE wp_fg_bookings
SET trip_type = 'one_way'
WHERE trip_type IS NULL 
   OR trip_type = '' 
   OR trip_type NOT IN ('one_way', 'round_trip');

-- Verify fix:
SELECT trip_type, COUNT(*) as count 
FROM wp_fg_bookings 
GROUP BY trip_type;

-- Expected result:
-- one_way: 30 (or similar)
-- round_trip: 23 (or similar)
-- Total should equal count from "All" filter
```

### 4. Test All Features
- [ ] Make a test booking with US phone number
- [ ] Make a test booking with UK phone number
- [ ] Try to select a past date (should be blocked)
- [ ] Check customer email - verify {first_name}, {email}, {phone} work
- [ ] Check admin email - verify all placeholders work
- [ ] Export CSV - verify vehicle column is hidden
- [ ] Export CSV - verify all stops appear in route column
- [ ] Test trip type filters - counts should match now

### 5. Update Email Templates (Optional)
```html
<!-- Go to: FG Bookings → Settings → Email Templates -->

<!-- Customer Email Example: -->
<h2>Booking Confirmed: #{booking_id}</h2>
<p>Dear {first_name} {last_name},</p>
<p>Thank you for your booking request.</p>

<h3>Contact Information</h3>
<p><strong>Email:</strong> {email}</p>
<p><strong>Phone:</strong> {phone}</p>

<h3>Trip Details</h3>
<pre style="white-space:pre-wrap">{pickup_summary}</pre>
{return_summary}

<p><strong>Vehicle:</strong> {vehicle}</p>
<p><strong>Passengers:</strong> {passenger_count}</p>
```

---

## ✅ Verification Checklist

### Phone Validation
- [ ] Country dropdown shows 180+ countries
- [ ] Phone error appears below input field (not inline)
- [ ] US number: Must be exactly 10 digits
- [ ] UK number: Must be exactly 10 digits
- [ ] Invalid number shows specific error

### Past Date Prevention
- [ ] Calendar doesn't allow selecting yesterday
- [ ] Trying to book past time shows error
- [ ] Current time or future works normally

### Vehicle Column
- [ ] Vehicle column NOT visible in bookings list
- [ ] Vehicle column NOT in CSV export
- [ ] Vehicle data still in database (check via SQL)

### Route & Stops
- [ ] CSV shows: "A → Stop 1 → Stop 2 → B"
- [ ] All intermediate stops included
- [ ] Round trip return route shows all stops too

### Email Placeholders
- [ ] {first_name} shows first name only
- [ ] {last_name} shows last name only
- [ ] {email} shows customer email
- [ ] {phone} shows phone with country code

### Trip Type Filters
- [ ] One Way count + Round Trip count = Total count
- [ ] No missing records
- [ ] "All" filter shows all bookings

---

## 🔧 Troubleshooting

### "Phone validation not working"
**Solution:** Hard refresh browser (Ctrl+F5) to clear JS cache

### "Trip type filters still wrong"
**Solution:** Run the SQL UPDATE query in phpMyAdmin

### "Email placeholders show {first_name} instead of name"
**Solution:** 
1. Check email template syntax - placeholders are case-sensitive
2. Make a new test booking (old bookings won't have split names)

### "CSV export still shows vehicle"
**Solution:** Clear browser cache, try export again

### "Past dates still selectable"
**Solution:** 
1. Hard refresh page (Ctrl+F5)
2. Check browser inspector - min attribute should be set on datetime input

---

## 📊 Database Health Check

Run these queries to verify plugin health:

```sql
-- 1. Check trip type distribution
SELECT trip_type, COUNT(*) as count 
FROM wp_fg_bookings 
GROUP BY trip_type;

-- 2. Check for NULL phone numbers
SELECT COUNT(*) as null_phones
FROM wp_fg_bookings 
WHERE phone IS NULL OR phone = '';

-- 3. Check for NULL emails
SELECT COUNT(*) as null_emails
FROM wp_fg_bookings 
WHERE email IS NULL OR email = '';

-- 4. Verify vehicle data preserved
SELECT COUNT(*) as with_vehicle
FROM wp_fg_bookings 
WHERE vehicle IS NOT NULL AND vehicle != '';
```

---

## 🎯 Performance Tips

1. **After deployment:** Clear all caches (plugin cache, CDN, browser)
2. **Large databases:** Add index on trip_type for faster filtering
   ```sql
   ALTER TABLE wp_fg_bookings ADD INDEX idx_trip_type (trip_type);
   ```
3. **CSV exports:** For 1000+ bookings, consider pagination

---

## 📞 Support

If issues persist after deployment:

1. Check `/wp-content/debug.log` for errors
2. Enable WP_DEBUG in wp-config.php
3. Test with default WordPress theme (Twenty Twenty-Four)
4. Disable all other plugins temporarily
5. Check browser console for JavaScript errors

---

## ✨ New Features Available

After deployment, you can:

1. **Customize email greetings:** Use {first_name} for personalization
2. **Include contact info in emails:** {email} and {phone} available
3. **Export detailed routes:** CSV now includes all stops
4. **Validate international numbers:** 180+ countries supported
5. **Prevent booking errors:** Past dates automatically blocked

---

**Deployment Time:** ~10 minutes  
**Downtime:** None (if using plugin upload method)  
**Rollback:** Simply restore backup ZIP if needed

