# Frontend Vue Pages Analysis - Laravel + Inertia.js + Vue 3

## Overview
This document provides a comprehensive analysis of 6 key frontend pages in the reservation system, focusing on Inertia props, real-time mechanisms, and data that needs live updates.

---

## 1. Client Calendar (`resources/js/pages/client/Calendar.vue`)

### Inertia Props
```typescript
defineProps<{
    initialSlots: TimeSlot[];      // Time slots for selected court
    today: string;                  // Current date
    courts: Court[];                // Available courts
    selected_court_id: number | null; // Pre-selected court
}>();
```

### Data Displayed
- **FullCalendar integration** showing time slots with color-coded status:
  - Green: Available ($price display)
  - Orange: Pre-reserved (count waiting for payment)
  - Red: Reserved/Occupied
  - Gray: Blocked (with reason)
- Court selection dropdown
- Slot details with start/end times and pricing
- Reservation modal for booking slots

### Current Real-Time Mechanisms
- ✅ **Client-side countdown**: Uses `setInterval()` to track countdown (not currently visible in code but pattern exists)
- ✅ **Manual slot loading**: `loadSlotsForDay()` function uses axios to fetch `/api/slots/day` when court changes
- ❌ **No polling or WebSocket**: Slots only update when user manually changes court or navigates

### Data Needing Real-Time Updates
1. **Slot availability status** - Critical: When slots change from available → pre-reserved → reserved
2. **Pre-reserved counts** - Important: When other users join the queue
3. **Blocked slots** - Medium: When admin blocks/unblocks slots
4. **Court information** - Low: Rarely changes

### Current Issues
- No polling mechanism to detect when slots change
- Users see stale slot data unless they manually refresh/change courts
- Pre-reserved queue count is static

---

## 2. Client Reservations (`resources/js/pages/client/Reservations.vue`)

### Inertia Props
```typescript
defineProps<{ 
    reservations: Reservation[] 
}>();

interface Reservation {
    id: number;
    date: string;
    date_raw: string;
    start_time: string;
    end_time: string;
    duration_hours: number;
    status: string;                // e.g., 'confirmed', 'cancelled'
    payment_status: string;         // e.g., 'unpaid', 'payment_review', 'paid'
    total_price: number;
    confirmation_code: string;
    can_cancel: boolean;
    court_name: string | null;
}
```

### Data Displayed
- List of user's reservations (paginated or full list)
- Each reservation card showing:
  - Confirmation code
  - Date, time range, duration
  - Court name
  - Total price
  - Status badges (confirmed/cancelled)
  - Payment status (unpaid/in review/paid)
- Cancel button for eligible reservations
- Links to payment page for unpaid reservations

### Current Real-Time Mechanisms
- ✅ **Flash messages**: Displays success toasts on mount if reservation created
- ❌ **No polling or WebSocket**
- ❌ **No status updates**: Payment status changes won't be reflected

### Data Needing Real-Time Updates
1. **Payment status** - Critical: When admin approves/rejects payment
2. **Reservation status** - Medium: If admin cancels or modifies
3. **Expiry information** - Medium: If payment deadline approaches

### Current Issues
- No way to know when payment has been approved without manual refresh
- User must manually refresh to see updated payment status

---

## 3. Client Payment (`resources/js/pages/client/Payment.vue`)

### Inertia Props
```typescript
defineProps<{
    reservation: ReservationPayment;
    nequi_number: string;          // Payment destination
    expiry_minutes: number;         // Payment expiry window
}>();

interface ReservationPayment {
    id: number;
    confirmation_code: string;
    total_price: number;
    payment_status: string;
    payment_expires_at: string | null;
    payment_proof: string | null;
}
```

### Data Displayed
- Reservation summary (code, price, date/time)
- Nequi payment number (static instruction)
- Payment status indicator
- Countdown timer to payment deadline
- File upload area for payment proof
- Payment reference input field
- Current payment proof preview (if exists)

### Current Real-Time Mechanisms
- ✅ **Client-side countdown**: `startCountdown()` uses `setInterval()` every 1 second
  - Calculates remaining seconds from `payment_expires_at`
  - Shows countdown in MM:SS format
  - Marks urgent when < 60 seconds
  - Auto-redirects to `/reservations` when expired
- ❌ **No server polling**: No refresh of payment status after upload
- ❌ **No WebSocket**: Status changes from admin review aren't pushed

### Data Needing Real-Time Updates
1. **Payment status** - Critical: When admin approves/rejects (currently static)
2. **Payment expiry** - Medium: Could be extended by admin (countdown based on client time)

### Current Issues
- **Critical**: No automatic refresh when payment is approved/rejected by admin
- User won't know payment approved unless manually refreshing
- Countdown is client-side; if browser clock differs from server, could be inaccurate

---

## 4. Admin Dashboard (`resources/js/pages/admin/Dashboard.vue`)

### Inertia Props
```typescript
defineProps<{
    stats: {
        reservations_today: number;
        reservations_pending: number;
        income_month: number;
        low_stock_products: number;
    };
    upcoming_today: {
        id: number;
        user_name: string;
        start_time: string;
        end_time: string;
        status: string;
        payment_status: string;
        total_price: number;
        confirmation_code: string;
    }[];
}>();
```

### Data Displayed
- 4 KPI cards:
  - Reservations today (count)
  - Pending payments (count)
  - Monthly income ($)
  - Low stock products (count)
- Today's upcoming reservations list:
  - User name
  - Time range
  - Price
  - Payment status
  - Quick links to reservations, calendar, products, reports

### Current Real-Time Mechanisms
- ❌ **No polling**: Stats are static from page load
- ❌ **No WebSocket**: No live updates

### Data Needing Real-Time Updates
1. **Reservations today count** - Critical: New bookings should increment
2. **Pending payments count** - Critical: When payments approved/rejected
3. **Upcoming reservations list** - Critical: New bookings appear
4. **Monthly income** - Important: Updates when payments confirmed
5. **Low stock products** - Low: Changes rarely

### Current Issues
- Dashboard becomes stale immediately after load
- Admin has no visibility into new bookings until manual refresh
- Stats not useful for real-time monitoring

---

## 5. Admin Reservations (`resources/js/pages/admin/Reservations.vue`)

### Inertia Props
```typescript
defineProps<{
    reservations: { 
        data: Reservation[]; 
        links: any[]; 
        meta?: any 
    };
    filters: { 
        date?: string; 
        status?: string; 
        payment_status?: string 
    };
    clients: Client[];
}>();

interface Reservation {
    id: number;
    user: { id: number; name: string; email: string };
    date: string;
    date_raw: string;
    start_time: string;
    end_time: string;
    duration_hours: number;
    status: string;
    payment_status: string;
    total_price: number;
    confirmation_code: string;
}
```

### Data Displayed
- Paginated list of all reservations
- Filterable by: date, status, payment_status
- Each reservation card with:
  - Confirmation code
  - User name/email
  - Date, time, duration
  - Status & payment status
  - Price
  - Action buttons: View, Cancel, Mark as Paid
  - Create manual reservation modal

### Current Real-Time Mechanisms
- ✅ **Manual creation form**: Can create reservations via modal
- ✅ **Axios API calls**: Uses `/api/slots/day` to load available slots when date changes
- ❌ **No polling**: List doesn't auto-refresh
- ❌ **No WebSocket**: New bookings not visible until refresh

### Data Needing Real-Time Updates
1. **Reservation list** - Critical: New bookings, cancellations, payment updates
2. **Payment status** - Critical: Changes from payment review process
3. **Reservation status** - Medium: Cancellations

### Current Issues
- Admin doesn't see new reservations without refresh
- Payment status updates (from PendingPayments page) don't propagate here
- No visibility into real-time booking activity

---

## 6. Admin Pending Payments (`resources/js/pages/admin/PendingPayments.vue`)

### Inertia Props
```typescript
defineProps<{
    reservations: PaymentReservation[];
}>();

interface PaymentReservation {
    id: number;
    confirmation_code: string;
    date: string;
    start_time: string;
    end_time: string;
    total_price: number;
    payment_method: string;
    payment_reference: string | null;
    payment_proof_url: string | null;
    submitted_at: string;
    user: { name: string; email: string; phone: string | null };
}
```

### Data Displayed
- List of reservations awaiting payment proof review
- Each entry shows:
  - Confirmation code
  - User info (name, email, phone)
  - Reservation date/time
  - Total price
  - Payment reference (if provided)
  - Payment proof preview button
  - Approve/Reject buttons
- Empty state when no pending payments
- Modal preview for payment proofs

### Current Real-Time Mechanisms
- ✅ **Flash messages**: Success/error toasts from server actions
- ❌ **No polling**: List doesn't auto-refresh
- ❌ **No WebSocket**: New submissions not visible until refresh

### Data Needing Real-Time Updates
1. **Payment list** - Critical: New proofs submitted by clients
2. **List length** - Critical: As items are approved/rejected

### Current Issues
- Admin won't see new payment submissions without refresh
- After approving/rejecting a payment, list doesn't update to reflect removal
- Requires constant manual refresh to stay current

---

## Summary Table

| Page | Primary Props | Key Realtime Data | Has Polling? | Has WebSocket? | Polling Needed? |
|------|---------------|-------------------|--------------|----------------|-----------------|
| Client Calendar | initialSlots, courts | Slot availability | ❌ | ❌ | ✅ HIGH |
| Client Reservations | reservations[] | Payment status, reservation status | ❌ | ❌ | ✅ MEDIUM |
| Client Payment | reservation, nequi_number | Payment status (approval/rejection) | ❌ | ❌ | ✅ CRITICAL |
| Admin Dashboard | stats, upcoming_today | All stats, reservations list | ❌ | ❌ | ✅ CRITICAL |
| Admin Reservations | reservations[], clients, filters | Reservation list, payment status | ❌ | ❌ | ✅ HIGH |
| Admin Pending Payments | reservations[] | Payment submissions, approvals | ❌ | ❌ | ✅ CRITICAL |

---

## Recommendations for Real-Time Implementation

### High Priority (Critical user experience impact)
1. **Payment Status Updates**: Client Payment & Admin Dashboard need immediate updates when payment approved/rejected
2. **Pending Payments List**: Auto-refresh when new proofs submitted or items approved/rejected
3. **Dashboard KPIs**: At least 30-60 second polling for counts

### Medium Priority (Improves workflow)
1. **Reservations Lists**: Client & Admin should refresh when new bookings made
2. **Calendar Slots**: Should update availability as reservations change

### Implementation Approaches
- **Short-term**: Add polling with `setInterval()` on relevant pages (30-60s intervals)
- **Medium-term**: Use Inertia's polling feature or implement Echo/WebSocket for instant updates
- **Long-term**: Full real-time architecture with Laravel Echo + Pusher/Reverb

### Existing Patterns to Leverage
- Already uses `axios` for some API calls
- Already has `useToast()` composable for feedback
- Already has `router` from Inertia for navigation
- Payment page already implements client-side countdown pattern
