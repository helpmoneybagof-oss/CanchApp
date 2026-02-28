# 🏟️ Sistema de Gestión de Reservas — Cancha Sintética

## 📌 Estado actual (Feb 2026)

### ✅ Cambios recientes (deploy Railway)
- WebSockets (Reverb): configuración en runtime (evita `localhost` en producción).
- PWA: fix `manifest.webmanifest` en producción.
- Notificaciones (campana): UI mejorada y navegación al tocar una notificación.
- Admin: opción en Ajustes para **cambiar contraseña**.
- Seed inicial en producción (creación de usuario admin) y mejoras de despliegue.
- UI móvil: barra de navegación inferior más arriba + animación de tap.
- Push notifications: claves VAPID configurables y soporte runtime para VAPID public key.

### 🔄 Pendiente crítico
- Push notifications: confirmar que la suscripción se guarda en `push_subscriptions` y que llegan pushes en iPhone/Chrome (PWA instalada). Verificar request `POST /api/push/subscribe` (CSRF/auth) y Service Worker.

---
**Stack:** Laravel 12 + Vue 3 + Inertia.js + TypeScript + Tailwind CSS v4 + MySQL  
**Última actualización:** 27 Febrero 2026 (Round 12 — Fix carga de slots en calendario cliente)  

---

## 📋 Contexto del Proyecto

Aplicación web y móvil (PWA) para gestionar reservas de una cancha sintética.  
- Dos portales: **Administrador** y **Cliente**
- Se ve y funciona como app nativa iOS/Android en móvil (bottom navigation bar)
- Perfectamente usable también en web/desktop (sidebar layout)
- Paleta de colores: **verde esmeralda** como color principal

**Usuarios de prueba:**
| Rol | Email | Contraseña |
|-----|-------|-----------|
| Admin | `admin@cancha.com` | `password` |
| Cliente | `cliente@cancha.com` | `password` |

**Base de datos:** MySQL — `cancha_sintetica`  
**MySQL WAMP path:** `C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe`

---

## 🟢 FASE 1 — Fundación y Roles ✅ COMPLETADA

### Objetivo
Base visual, roles de usuario, navegación tipo app nativa.

### ✅ Completado
- [x] Migración: campos `phone`, `role` (admin/client), `active` en tabla `users`
- [x] Migración: tabla `settings` (configuración del sistema)
- [x] Modelo `User` actualizado — helpers `isAdmin()`, `isClient()`, nuevos campos
- [x] Middleware `EnsureUserIsAdmin` — protege rutas `/admin/*`
- [x] Rutas separadas — `routes/admin.php` y `routes/client.php`
- [x] Redirección post-login por rol (admin → `/admin/dashboard`, cliente → `/dashboard`)
- [x] Nuevos registros siempre como `role: client`
- [x] Identidad visual — paleta verde esmeralda en light y dark mode (`app.css`)
- [x] `AppClientLayout.vue` — sidebar desktop + bottom nav móvil (5 tabs)
- [x] `AppAdminLayout.vue` — sidebar desktop + bottom nav móvil (5 tabs)
- [x] `Welcome.vue` — Landing page pública con hero, características y CTA
- [x] `Dashboard.vue` (cliente) — Home con bienvenida, CTA de reserva, accesos rápidos
- [x] `admin/Dashboard.vue` — Home admin con métricas y accesos rápidos
- [x] `Admin/DashboardController.php`
- [x] Seeders: admin, cliente de prueba y settings iniciales
- [x] `Schema::defaultStringLength(191)` para compatibilidad con MySQL en WAMP

### ❌ Pendiente Fase 1
- Nada — fase completamente terminada

---

## 🔵 FASE 2 — Calendario y Reservas ✅ COMPLETADA

### Objetivo
Calendario dinámico con FullCalendar, sistema completo de reservas para clientes y admin.

### ✅ Completado
- [x] Migración: tabla `time_slots` (slots de 5PM–11PM, unique por fecha+hora)
- [x] Migración: tabla `reservations` (estado, pago, código único, precios)
- [x] Migración: tabla `reservation_time_slots` (pivote reserva ↔ slots)
- [x] Modelo `TimeSlot` — scopes, helpers, atributos formateados
- [x] Modelo `Reservation` — lógica de cancelación, código único auto-generado
- [x] Modelo `Setting` — `getValue()` y `setValue()` estáticos
- [x] `TimeSlotService` — genera slots automáticamente, marca/libera/bloquea
- [x] `Client/CalendarController` — vista pública + API `/api/slots` + `/api/slots/day`
- [x] `Client/ReservationController` — index, history, checkout, store, cancel
- [x] `Admin/ReservationController` — index, calendar, store, cancel, markAsPaid, blockSlots, unblockSlots
- [x] Rutas cliente: `/calendar`, `/reservations`, `/history`, `/checkout`
- [x] Rutas admin: `/admin/reservations`, `/admin/calendar`, `/admin/calendar/slots`, `/admin/slots/block`, `/admin/slots/unblock`
- [x] FullCalendar instalado (`@fullcalendar/core`, `vue3`, `daygrid`, `timegrid`, `interaction`)
- [x] `client/Calendar.vue` — FullCalendar día, selección de slots, modal de confirmación, carrito de slots
- [x] `client/Reservations.vue` — lista de reservas activas con cancelación y modal
- [x] `client/ReservationHistory.vue` — historial de reservas pasadas
- [x] `admin/Calendar.vue` — vista semanal con bloqueo/desbloqueo de slots
- [x] `admin/Reservations.vue` — listado con marcar pagado y cancelar
- [x] Seeder de `settings`: precio por hora, nombre cancha, contacto

### ✅ Completado (actualización Feb 2026)
- [x] **Ajustes de estilos FullCalendar** — dark mode, responsive mobile (estilos centralizados en `app.css`)
- [x] **Página `/checkout`** — vista de resumen antes de confirmar con notas y código de confirmación
- [x] **Validación frontend** — errores del servidor mostrados como toasts (`useToast`)
- [x] **Componente Toast/Notificación** — `AppToast.vue` + `useToast` composable
- [x] **Navegación día a día** en el calendario cliente (prev/next/today)
- [x] **Dashboard admin** — métricas reales (reservas hoy, pendientes de pago, ingresos del mes)
- [x] **Página `/admin/reservations`** — filtros funcionales por fecha, estado y estado de pago
- [x] **Crear reserva manual (admin)** — modal con selector de cliente, fecha, slots disponibles y notas

### ✅ Correcciones adicionales (Feb 2026)
- [x] `block_reason` incluido en `getSlotsForDateRange()` — consistente con `getSlotsForDate()`
- [x] UI del calendario muestra la razón de bloqueo cuando existe (ej: "Bloqueado: Mantenimiento")
- [x] Validación de fechas pasadas en `/api/slots` y `/api/slots/day` (`after_or_equal:today`)
- [x] Rango máximo de 31 días en `/api/slots` para evitar generación masiva de slots
- [x] Rate limiting en endpoints públicos de slots (`throttle:60,1`)
- [x] Correos de Laravel (reset password, verify email) completamente en español con nombre dinámico de la cancha
- [x] `APP_NAME` cambiado de "Laravel" a "Cancha Sintética" como fallback
- [x] Mensaje de feedback "📧 ¡Correo enviado!" en página de olvidé mi contraseña
- [x] Cancelación de reservas sin restricción de tiempo — solo bloqueada si ya está pagada
- [x] `releaseSlots()` corregido: usa `where('!=')` encadenado en lugar de `whereNotIn()` para evitar bug con JOINs en MySQL
- [x] Orden correcto en cancelación: cancelar reserva primero, luego liberar slots (fix en `ReservationController` y `ExpireUnpaidReservations`)
- [x] Límite de reservas activas aumentado de 3 a 5 por cliente
- [x] Sidebar admin: muestra "CANCHA SINTÉTICA" + nombre dinámico de la cancha en dos líneas
- [x] Calendario admin: click en slot reservado/pre-reservado navega al detalle de la reserva
- [x] Countdown de expiración en página de pago Nequi (cambia a rojo en último minuto, redirige al expirar)
- [x] Push notifications corregidas: SW registrado manualmente con `registerSW()`, banner "Activa las notificaciones" en sidebar
- [x] Checkout redirige directo a página de pago Nequi al confirmar reserva

---

## 🟡 FASE 3 — Catálogo y Carrito ✅ COMPLETADA

### Objetivo
Catálogo de consumibles (bebidas, snacks), carrito integrado con la reserva, checkout completo.

### ✅ Completado
- [x] Migración: tabla `product_categories` (nombre, descripción, active)
- [x] Migración: tabla `products` (nombre, descripción, precio, stock, min_stock, imagen, categoría, active)
- [x] Migración: tabla `reservation_items` (reservation_id, product_id, quantity, unit_price, subtotal)
- [x] Modelo `Product` con scopes (`active`, `lowStock`) y helper `isLowStock()`
- [x] Modelo `ProductCategory` con scope `active`
- [x] Modelo `ReservationItem`
- [x] Relación `Reservation` → `hasMany(ReservationItem)`
- [x] `Admin/ProductController` — CRUD completo con upload de imagen (Laravel Storage)
- [x] `Admin/CategoryController` — CRUD de categorías
- [x] `Client/CatalogController` — vista del catálogo con filtros por categoría y búsqueda
- [x] `Client/CartController` — agregar/quitar items, persistencia en sesión, guardar slot_ids
- [x] Rutas admin: `/admin/products`, `/admin/categories`
- [x] Rutas cliente: `/catalog`, `/cart`, `/cart/items`, `/cart/slots`
- [x] `admin/Products.vue` — CRUD con upload de imagen, grid de tarjetas, alerta stock bajo
- [x] `admin/Categories.vue` — CRUD de categorías con contador de productos
- [x] `client/Catalog.vue` — catálogo con búsqueda, filtro por categoría, control de cantidad inline
- [x] `client/Cart.vue` — carrito con slot + consumibles, modificar cantidades, total general
- [x] `client/Checkout.vue` — resumen completo (cancha + consumibles + total), vacía carrito al confirmar
- [x] `ReservationController::store` — guarda items del carrito, descuenta stock, limpia sesión
- [x] Control de stock: alerta visual cuando stock ≤ min_stock en admin
- [x] Subir imágenes de productos (Laravel Storage `public`)
- [x] **Dashboard admin** — métrica "stock bajo" conectada con datos reales
- [x] Seeders: 3 categorías (Bebidas, Snacks, Equipos) y 9 productos de ejemplo

---

## 🟣 FASE 7 — Multi-Cancha ✅ COMPLETADA

### Objetivo
Soporte para establecimientos con múltiples canchas, cada una con su propio precio, horario y tipo.

### ✅ Completado
- [x] Migración: tabla `courts` (nombre, tipo, precio/hora, descripción, capacidad, superficie, start_hour, end_hour, activa)
- [x] Migración: `court_id` en `time_slots` — unique constraint ahora es `[court_id, date, start_time]`
- [x] Migración: `court_id` en `reservations`
- [x] Modelo `Court` — scopes `active`, relaciones `timeSlots` + `reservations`, accessor `schedule_label`
- [x] Modelo `TimeSlot` — nuevo scope `forCourt()`, relación `belongsTo(Court)`
- [x] Modelo `Reservation` — nueva relación `belongsTo(Court)`
- [x] `TimeSlotService` refactorizado — recibe objeto `Court`, usa precio y horario de cada cancha
- [x] `Admin/CourtController` — CRUD completo con validación, toggle activo/inactivo
- [x] `Client/CalendarController` — pasa lista de canchas activas + cancha seleccionada
- [x] `Admin/ReservationController` — calendario y slots filtrados por cancha, reserva manual usa precio real
- [x] `Client/ReservationController` — precio calculado desde slots (precio real de la cancha)
- [x] Rutas admin: `/admin/courts` (CRUD + toggle)
- [x] `admin/Courts.vue` — grid de tarjetas por cancha, modal crear/editar con tipo, precio, superficie, capacidad, horario
- [x] `client/Calendar.vue` — selector de canchas (pill buttons), info bar de cancha, horario dinámico
- [x] `admin/Calendar.vue` — selector de canchas, carga dinámica al cambiar
- [x] `client/Checkout.vue` — muestra nombre de la cancha en el resumen
- [x] `client/Reservations.vue` — muestra nombre de la cancha en cada reserva
- [x] `client/ReservationHistory.vue` — muestra nombre de la cancha en el historial
- [x] `admin/AppAdminLayout.vue` — ítem "Canchas" con ícono `Goal` en sidebar y bottom nav
- [x] Seeder: cancha de ejemplo "Cancha Fútbol 5" a $80.000/hora, 5 PM – 11 PM

---

## 🌐 TRADUCCIÓN AL ESPAÑOL ✅ COMPLETADA

- [x] `layouts/settings/Layout.vue` — nav: Perfil, Contraseña, Autenticación 2FA, Apariencia
- [x] `settings/Profile.vue` — campos y botones traducidos
- [x] `settings/Password.vue` — campos y botones traducidos
- [x] `settings/Appearance.vue` — título traducido
- [x] `settings/TwoFactor.vue` — Activado/Desactivado, Activar/Desactivar 2FA
- [x] `components/DeleteUser.vue` — Eliminar cuenta, diálogo de confirmación
- [x] `AppLayout.vue` — detecta rol y usa `AppAdminLayout` o `AppClientLayout` según corresponda

---

## 🔧 CORRECCIONES ✅

- [x] `NavUser` reemplazado por `AdminNavUser` en `AppAdminLayout` y `AppClientLayout` — corrige error `Injection SidebarContext not found`
- [x] `AdminNavUser.vue` — componente independiente sin dependencia del contexto `<Sidebar>` de shadcn
- [x] `routes/web.php` — `/dashboard` detecta rol: admin → redirect `/admin/dashboard`, cliente → render `Dashboard`
- [x] `app.blade.php` — `<link rel="manifest">` solo en producción (`@production`) para evitar 404 en dev

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 12) ✅

### Fix — Calendario cliente no cargaba horarios
- [x] **Causa raíz:** 3 funciones de carga en conflicto (`loadSlotsForDay`, `loadSlotsForRange`, `loadSlotsForWeek`) se sobreescribían mutuamente vaciando `slots.value`
- [x] **Solución:** Una única función `loadSlots(from, to)` que carga todo el rango y actualiza `slots.value` + FullCalendar
- [x] `loadSlotsForDay(date)` es ahora un alias simple que calcula la semana y llama `loadSlots`
- [x] `handleDatesSet` — solo actúa en desktop (`if (isMobile) return`), usa el rango real del calendario (lunes–domingo)
- [x] `watch(selectedCourtId)` — en móvil llama `loadSlots` con la semana actual; en desktop usa `api.view.activeStart/activeEnd`
- [x] `onMounted` — un solo `onMounted` combinado: en móvil carga la semana de hoy, en desktop `handleDatesSet` lo maneja automáticamente
- [x] `selectMobileDay` — si el día ya está en `slots.value` no hace request, cambio de día es instantáneo
- [x] Selector de días cliente móvil: cambiado de `overflow-x-auto shrink-0` a `flex-1` sin scroll (igual que admin)
- [x] `ring-2 ring-primary` reemplazado por `border-2 border-primary` en tarjetas para evitar overflow
- [x] `initialView` cambiado de `timeGridDay` a `timeGridWeek` en desktop

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 11) ✅

### Calendario cliente — Rediseño móvil nativo iOS
- [x] Mismo patrón visual que el admin: selector días scrollable + lista de tarjetas de slots
- [x] Selector de canchas cambiado a `<select>` desplegable nativo (igual que admin)
- [x] Tarjetas con color de fondo según estado (verde/amarillo/rojo/gris)
- [x] Checkbox ✓ al seleccionar un slot disponible/pre-reservado
- [x] Slots ocupados/bloqueados deshabilitados con `opacity-60`
- [x] Modal de confirmación al tocar un slot disponible
- [x] Desktop: FullCalendar sin cambios

### Fixes overflow horizontal (scroll lateral eliminado)
- [x] `resources/css/app.css` — `html, body { overflow-x: hidden; max-width: 100vw }` global
- [x] `AppAdminLayout.vue` — `min-w-0 overflow-x-hidden` en el contenedor principal del flex
- [x] `AppAdminLayout.vue` — `overflow-x-hidden` en el `<main>`
- [x] `admin/Calendar.vue` — `overflow-x-hidden w-full` en contenedores móvil y desktop
- [x] `admin/Calendar.vue` — Selector de días cambiado de scroll horizontal a `flex-1` distribuido en toda la fila (7 días sin scroll)
- [x] `admin/Calendar.vue` — Leyenda móvil en 2 filas: Disponible/Esperando/Reservado/Finalizado + Bloqueado abajo
- [x] `admin/Calendar.vue` — `ring-2` reemplazado por `border-2 border-primary` para evitar overflow del ring
- [x] FullCalendar desktop envuelto en `overflow-x-auto` para scroll interno sin afectar la página

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 10) ✅

### Fix — Notificaciones campana no llegaban en tiempo real
- [x] **`app/Services/NotificationService.php`** — Race condition corregida: antes se llamaba `$user->notify()` (queued/async) y luego se intentaba leer la notificación de BD inmediatamente → llegaba `null` → broadcast nunca se disparaba
- [x] **Solución:** Insertar directamente en tabla `notifications` vía `DB::table()->insert()` de forma síncrona (sin queue), luego contar no leídas y hacer broadcast al instante
- [x] `storage:link` ejecutado — symlink `public/storage` creado para que las imágenes de comprobantes sean accesibles

### Fix — Estado de reserva no actualizaba en tiempo real (cliente)
- [x] **`resources/js/pages/client/ReservationDetail.vue`** — Faltaba listener de Echo. Ahora se suscribe al canal privado `user.{userId}` y hace `router.reload()` ante:
  - `.payment.approved` → pago aprobado por admin
  - `.payment.rejected` → comprobante rechazado
  - `.reservation.cancelled` → reserva cancelada por admin

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 9) ✅

### 🔔 Campana de notificaciones en tiempo real (admin + cliente)

**Backend:**
- [x] `migrations/..._create_notifications_table` — Tabla `notifications` de Laravel (DB channel)
- [x] `app/Notifications/ReservationCreatedNotification` — Nueva reserva → admins
- [x] `app/Notifications/PaymentProofSubmittedNotification` — Comprobante subido → admins
- [x] `app/Notifications/PaymentApprovedNotification` — Pago aprobado → cliente
- [x] `app/Notifications/PaymentRejectedNotification` — Comprobante rechazado → cliente
- [x] `app/Notifications/ReservationCancelledNotification` — Reserva cancelada → cliente
- [x] `app/Services/NotificationService` — Helper `notifyUser()` + `notifyAdmins()` + broadcast `NotificationCreated`
- [x] `app/Events/NotificationCreated` — Broadcast por canal privado `notifications.{userId}` (ShouldBroadcastNow)
- [x] `app/Http/Controllers/Api/NotificationController` — GET /api/notifications, POST read, POST read-all, DELETE
- [x] `routes/web.php` — 4 rutas API de notificaciones bajo middleware `auth`
- [x] `routes/channels.php` — Canal privado `notifications.{id}` autorizado por userId

**Frontend:**
- [x] `components/NotificationBell.vue` — Campana completa:
  - Badge rojo con conteo de no leídas (máx "9+")
  - Dropdown animado con lista de 50 notificaciones
  - Punto azul en notificaciones no leídas
  - Clic → marca leída + navega a URL
  - Botón ✓ marcar una como leída, 🗑️ eliminar, "Todas leídas"
  - Tiempo relativo (ahora, 5m, 2h, 3d)
  - Cierra al hacer clic afuera (usa `mousedown` para no interferir con el toggle)
  - Suscripción en tiempo real al canal privado `notifications.{userId}` via Reverb
- [x] `AppAdminLayout.vue` — Campana en header móvil (derecha) + header desktop (arriba derecha)
- [x] `AppClientLayout.vue` — Campana en header móvil (derecha) + header desktop (arriba derecha)

**UX desktop:**
- [x] Nuevo header desktop compartido (`h-16`) en ambos layouts — título de sección (izquierda) + campana (derecha)
- [x] Altura sincronizada con el sidebar logo (`h-16`) para alinear las líneas divisoras
- [x] Badge "Admin" eliminado del header del sidebar

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 8) ✅

### Bottom nav admin — Rediseño con menú "Más"
- [x] **`AppAdminLayout.vue`** — Bottom nav ahora tiene 4 ítems principales + botón "···"
  - Dashboard · Reservas · **Calendario** (nuevo) · Pagos · **···**
- [x] Botón "···" abre un **bottom sheet** con grid 2×3: Canchas, Productos, Usuarios, Reportes, Configuración
- [x] El botón "···" se resalta en verde si la página activa es una de las opciones del menú extra
- [x] Bottom sheet se cierra al tocar cualquier opción o el fondo oscuro

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 7) ✅

### Calendario Admin Móvil — Rediseño nativo iOS
- [x] **Móvil (`lg:hidden`):** Eliminado FullCalendar en móvil — reemplazado por UI nativa estilo iOS
  - Selector de días horizontal scrollable (lun–dom) con día seleccionado en verde
  - Lista de slots como tarjetas con color de fondo según estado (verde/amarillo/azul/violeta/gris)
  - Tap en slot disponible/bloqueado → selecciona con checkbox para bloquear/desbloquear
  - Tap en slot reservado/pre-reservado → navega al detalle de la reserva
  - Botones Bloquear/Desbloquear aparecen solo cuando hay slots seleccionados
  - Navegación prev/next avanza/retrocede 7 días cargando semana completa
- [x] **Desktop (`hidden lg:block`):** FullCalendar semanal sin cambios
- [x] **Selector de canchas** cambiado de chips horizontales a `<select>` desplegable nativo (iOS-friendly)
- [x] Eliminados `console.log` de debug del calendario admin

### PWA Install — Botón en Welcome.vue
- [x] Captura `beforeinstallprompt` en `app.ts` y expone `window.showPWAInstallPrompt()`
- [x] `Welcome.vue` — botón "Instalar app gratis" aparece cuando el navegador detecta que es instalable
- [x] Si ya está instalada, muestra badge "App instalada ✓" en verde
- [x] Al instalar (`appinstalled`): pide permiso de notificaciones automáticamente tras 2 segundos
- [x] Al abrir en modo standalone: pide permiso de notificaciones si aún no se ha dado

### Fix crítico — PushNotificationService no crashea si VAPID inválido
- [x] `PushNotificationService` — propiedad `$webPush` ahora es nullable (`?WebPush`)
- [x] Constructor con `try/catch` — si las claves VAPID son inválidas o OpenSSL no soporta EC, loguea warning y no inicializa WebPush
- [x] `sendToUser()` y `sendToAdmins()` — guard `if (!$this->webPush) return` para no crashear
- [x] **Causa:** OpenSSL de WAMP no soporta curvas EC (P-256) → claves VAPID mal generadas
- [x] **Solución:** Regenerar claves en https://vapidkeys.com y actualizar `.env`

### Notas técnicas adicionales
17. **VAPID Keys** — Deben generarse en https://vapidkeys.com (OpenSSL de WAMP no soporta EC P-256). Public key ~87 chars, Private key ~43 chars.
18. **Calendario admin móvil** — No usa FullCalendar en móvil. Usa lista de tarjetas nativas. FullCalendar solo en desktop (`lg:`).
19. **PWA Install** — `beforeinstallprompt` capturado en `app.ts`. `window.showPWAInstallPrompt()` disponible globalmente. iOS no soporta este evento.

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 6) ✅

### Tiempo real — Calendario en vivo
- [x] **`TimeSlotService::broadcastSlotChanges()`** — nuevo método privado que emite `SlotStatusChanged` agrupado por cancha+fecha al final de `markAsPreReserved()`, `markAsReserved()` y `releaseSlots()`
- [x] **`client/Calendar.vue`** — suscripción reactiva manual al canal `slots.{courtId}` (se re-suscribe al cambiar de cancha), recarga slots al instante via WebSocket
- [x] **`client/Calendar.vue`** — nuevo `updateCalendarEvents()` que actualiza FullCalendar imperativa mente (`api.removeAllEvents()` + `api.addEventSource()`). Ya no depende del computed reactivo que FullCalendar no observa
- [x] **`admin/Calendar.vue`** — mismo patrón de suscripción reactiva, elimina `useRealtimeSlots` estático
- [x] **PWA dev mode** — `devOptions: { enabled: true, type: 'module' }` en `vite.config.ts` para que el Service Worker funcione en desarrollo

### Notificaciones Push — Fixes
- [x] **`Client/PaymentController::uploadProof()`** — ahora envía push a todos los admins cuando el cliente sube su comprobante: "💳 Nuevo comprobante de pago"
- [x] **`Admin/ReservationController::cancel()`** — ahora envía push al cliente cuando el admin cancela su reserva: "❌ Reserva cancelada"

### Fix estados de slots — "0 esperando pago"
- [x] **`TimeSlotService::releaseSlots()`** — incluye `unpaid` en el conteo de reservas activas (antes solo contaba `pending_payment` y `payment_review`)
- [x] **`TimeSlotService::getPreReservedCounts()`** — incluye `unpaid` en el conteo del frontend
- [x] **`client/Calendar.vue`** y **`admin/Calendar.vue`** — si `status === 'pre_reserved'` pero `pre_reserved_count === 0`, se muestra como verde/disponible

### Tabla de notificaciones push completa:
| Evento | Destinatario | Push |
|--------|-------------|------|
| Cliente reserva | Admin | 📅 Nueva reserva |
| Cliente sube comprobante | Admin | 💳 Nuevo comprobante ✅ nuevo |
| Admin aprueba pago | Cliente | ✅ Pago aprobado |
| Admin rechaza comprobante | Cliente | ❌ Comprobante rechazado |
| Admin cancela reserva | Cliente | ❌ Reserva cancelada ✅ nuevo |

### Script de inicio
- [x] **`start.bat`** — arranca los 4 servicios en terminales separadas con 1 doble clic

---

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 3) ✅

### UX / UI — App nativa
- [x] **Layouts rediseñados** — header móvil estilo app nativa: avatar (izquierda), título sección (centro), acción (derecha)
- [x] **Sin triángulos ni ChevronsUpDown** — eliminados `NavUser` y `AdminNavUser` de los headers
- [x] **Cerrar sesión** — bottom sheet móvil al tocar avatar; botón en sidebar desktop
- [x] **Bottom nav móvil** — íconos más gruesos (`stroke-[2.5px]`) cuando están activos
- [x] **Scroll al top** — `router.on('navigate')` hace scroll al top en cada navegación
- [x] **Sin zoom en iOS** — `maximum-scale=1, user-scalable=no` en meta viewport + `font-size: 16px` en inputs móvil
- [x] **Barra de progreso** — color verde esmeralda `#10b981` (antes gris)
- [x] **`safe-area-inset-bottom`** — bottom nav respeta el notch del iPhone

### Nombre del establecimiento dinámico
- [x] **`HandleInertiaRequests`** — comparte `app_name`, `app_address`, `app_phone` globalmente desde `Settings`
- [x] **`AppLogo.vue`** — muestra nombre real del establecimiento con inicial dinámica
- [x] **`Welcome.vue`** — título, header y footer usan nombre dinámico
- [x] **`AppClientLayout`** y **`AppAdminLayout`** — nombre del establecimiento en sidebar desktop
- [x] **Cambiar nombre** desde `/admin/settings` → se refleja en toda la app automáticamente

### Correcciones de datos
- [x] Reservas y slots sin `court_id` corregidos — asignados a la primera cancha activa
- [x] 90 slots huérfanos eliminados

## 🔧 MEJORAS Y CORRECCIONES (Feb 2026 — Round 2) ✅

### Flujo de reserva corregido
- [x] **Calendario** → "Continuar" guarda `slot_ids` en sesión (`POST /cart/slots`) y redirige a `/catalog?from_calendar=1`
- [x] **Catálogo** — banner "¡Horario(s) reservado(s)! ¿Deseas agregar algo más?" cuando viene del calendario
- [x] **Catálogo** — botón flotante adaptativo: "Continuar sin consumibles" o "Ir al pago · N producto(s)"
- [x] **Checkout** — lee `slot_ids` directamente del carrito de sesión (no de query params)
- [x] **Store** — lee `slot_ids` de la sesión (no del request body)
- [x] **Cart.vue** — botón "Confirmar" va a `/checkout` sin pasar slot_ids como parámetros
- [x] Un solo pago: cancha + consumibles juntos en el mismo checkout

### Detalle de reserva — cliente
- [x] `client/ReservationDetail.vue` — vista completa: cancha, horario, consumibles pedidos (con imagen, cantidad, precio), total desglosado, notas, botón cancelar
- [x] `GET /reservations/{reservation}` — ruta con protección (solo el dueño)
- [x] `Reservations.vue` — tarjetas clickeables con `ChevronRight`
- [x] `ReservationHistory.vue` — tarjetas clickeables con hover y flecha

### Detalle de reserva — admin
- [x] `admin/ReservationDetail.vue` — igual que cliente + tarjeta del cliente con link a perfil, acciones (pagar/cancelar)
- [x] `GET /admin/reservations/{reservation}` — ruta admin
- [x] `admin/Reservations.vue` — botón "Ver detalle →" en cada tarjeta

### Traducción al español (settings)
- [x] `layouts/settings/Layout.vue` — nav: Perfil, Contraseña, Autenticación 2FA, Apariencia
- [x] `settings/Profile.vue`, `Password.vue`, `Appearance.vue`, `TwoFactor.vue` — 100% español
- [x] `components/DeleteUser.vue` — Eliminar cuenta, diálogo de confirmación traducido

### Correcciones técnicas
- [x] `AdminNavUser.vue` — reemplaza `NavUser` en `AppAdminLayout` y `AppClientLayout` (fix error `SidebarContext not found`)
- [x] `AppLayout.vue` — detecta rol: admin → `AppAdminLayout`, cliente → `AppClientLayout`
- [x] `routes/web.php` — `/dashboard` redirige según rol
- [x] `app.blade.php` — manifest PWA solo en producción

---

## 💳 FASE 8 — Pagos en Línea ✅ COMPLETADA (Nequi Manual)

### ✅ Completado (Feb 2026)

#### Migraciones
- [x] Campo `payment_method` en `reservations` (nequi, cash, wompi)
- [x] Campo `payment_reference` — referencia del comprobante
- [x] Campo `payment_proof` — ruta de imagen del comprobante
- [x] Campo `payment_expires_at` — timestamp límite de pago
- [x] Estados de pago ampliados: `unpaid` → `pending_payment` → `payment_review` → `paid` / `rejected`

#### Backend
- [x] `Client/PaymentController` — muestra página de pago Nequi + recibe comprobante
- [x] `Admin/ReservationController::pendingPayments` — lista comprobantes enviados
- [x] `Admin/ReservationController::approvePayment` — aprueba pago + marca slots como `reserved`
- [x] `Admin/ReservationController::rejectPayment` — rechaza comprobante
- [x] `ExpireUnpaidReservations` Job — cancela reservas expiradas cada 5 minutos (scheduler)
- [x] Campos `nequi_number` y `payment_expiry_minutes` en Settings + panel admin
- [x] Al aprobar pago → cancela automáticamente otras pre-reservas del mismo slot

#### Frontend
- [x] `client/Payment.vue` — página con número Nequi, drag&drop comprobante, referencia opcional
- [x] `admin/PendingPayments.vue` — lista comprobantes, modal preview imagen, aprobar/rechazar
- [x] `AppAdminLayout` — ítem "Pagos" con ícono `CreditCard` en sidebar y bottom nav
- [x] `client/Reservations.vue` — botón "Pagar con Nequi" en reservas sin pagar/rechazadas
- [x] `client/Checkout.vue` — al confirmar → redirige directo a página de pago Nequi
- [x] Estados de pago con badges de colores en "Mis reservas"

#### Flujo completo
1. Cliente confirma reserva → va directo a página de pago Nequi
2. Ve número Nequi de la cancha + total a pagar
3. Transfiere y sube foto del comprobante
4. Admin ve comprobante en `/admin/payments/pending` → aprueba o rechaza
5. Si aprueba → slot pasa a 🔵 reservado, otras pre-reservas del mismo slot se cancelan
6. Si no paga en X minutos → reserva expira y slot se libera automáticamente

### ⏳ Pendiente (futuro)
- [ ] **Wompi** — integración con pasarela de pagos online (PSE, tarjeta, Nequi automático)
- [ ] Countdown en la página de pago ("⏱️ Tu reserva expira en 28:43")

---

## 🔔 FASE 9 — Notificaciones Push (PWA) ✅ COMPLETADA

### ✅ Completado (Feb 2026)
- [x] `minishlink/web-push` instalado vía Composer
- [x] Claves VAPID generadas y configuradas en `.env` (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`)
- [x] Migración: tabla `push_subscriptions` (user_id, endpoint, p256dh, auth)
- [x] `PushSubscription` modelo
- [x] `Api/PushSubscriptionController` — subscribe / unsubscribe
- [x] `PushNotificationService` — `sendToUser()` y `sendToAdmins()`, limpia endpoints expirados
- [x] `resources/js/sw-push.ts` — Service Worker custom con handler de `push` y `notificationclick`
- [x] `resources/js/composables/usePush.ts` — solicita permiso, suscribe, guarda en backend
- [x] `AppClientLayout` + `AppAdminLayout` — llaman `subscribeToPush()` en `onMounted`
- [x] VitePWA configurado con `injectManifest` + `sw-push.ts` como service worker custom

#### Notificaciones enviadas:
| Evento | Destinatario | Mensaje |
|---|---|---|
| Cliente reserva | **Admin** | "📅 Nueva reserva — [cliente] reservó [cancha] el [fecha]" |
| Admin aprueba pago | **Cliente** | "✅ Pago aprobado — ¡Nos vemos en la cancha!" |
| Admin rechaza comprobante | **Cliente** | "❌ Comprobante rechazado — por favor sube uno nuevo" |

---

## 🟡 FASE 10 — Estado Pre-Reservado en Slots ✅ COMPLETADA

### ✅ Completado (Feb 2026)
- [x] Nuevo estado `pre_reserved` en enum de `time_slots`
- [x] `TimeSlot::isPreReserved()`, `isBookable()` helpers
- [x] `TimeSlotService::markAsPreReserved()` — al crear reserva (pago pendiente)
- [x] `TimeSlotService::markAsReserved(slotIds, winnerReservationId)` — al confirmar pago, cancela otras pre-reservas
- [x] `TimeSlotService::releaseSlots()` — inteligente: vuelve a `pre_reserved` si hay otras reservas activas, o `available` si no
- [x] `TimeSlotService::getPreReservedCounts()` — conteo de pre-reservas activas por slot
- [x] `TimeSlotService::getReservationIds()` — reservation_id más prioritario por slot (para navegación admin)
- [x] Datos retornados: `pre_reserved_count` y `reservation_id` en todos los endpoints de slots
- [x] `client/Calendar.vue` — slot 🟡 amarillo con "X esperando pago", clickeable y reservable
- [x] `admin/Calendar.vue` — slot 🟡 amarillo con "⏳ X esperando pago", click → navega al detalle de reserva
- [x] Leyenda del calendario actualizada en cliente y admin
- [x] Límite de reservas activas aumentado de 3 a **5 por cliente**

#### Colores del calendario:
| Color | Estado | Acción |
|---|---|---|
| 🟢 Verde | Disponible | Reservable |
| 🟡 Amarillo | Esperando pago | Reservable (varios pueden pre-reservar) |
| 🔵 Azul (admin) / 🔴 Rojo (cliente) | Reservado/Pagado | No disponible |
| ⚫ Gris | Bloqueado | No disponible |

---

## 🟠 FASE 4 — Notificaciones y Comprobantes ✅ COMPLETADA

### Objetivo
Correos automáticos, comprobante PDF adjunto, recordatorios programados.

### ✅ Completado (Feb 2026)
- [x] Configuración SMTP desde el panel admin (`/admin/settings`) — sin tocar `.env`
- [x] `AppServiceProvider` — aplica config SMTP de BD en cada request automáticamente
- [x] `Mail/ReservationConfirmed` — correo de confirmación al cliente con PDF adjunto
- [x] `Mail/ReservationCancelled` — correo de cancelación al cliente
- [x] `Mail/ReservationReminder` — recordatorio 24h y 2h antes
- [x] `Mail/NewReservationAdmin` — alerta al admin por nueva reserva
- [x] `Mail/CancellationAdmin` — alerta al admin por cancelación de cliente
- [x] `Mail/DailySummaryAdmin` — resumen matutino de reservas del día (7:00 AM)
- [x] `Mail/LowStockAlert` — alerta de stock bajo al admin (8:00 AM)
- [x] `Mail/WelcomeUser` — correo de bienvenida al registrarse
- [x] Jobs asíncronos: `SendReservationConfirmed`, `SendReservationCancelled`, `SendReservationReminder`, `SendWelcomeEmail`, `SendDailySummary`, `SendLowStockAlert`
- [x] `routes/console.php` — Scheduler: recordatorios cada hora, resumen 7AM, stock bajo 8AM
- [x] Plantillas HTML responsivas para todos los correos (layout verde esmeralda, datos dinámicos)
- [x] Comprobante PDF con DomPDF (`barryvdh/laravel-dompdf`): código, cliente, cancha, consumibles, total
- [x] `Client/ReservationController::store` y `cancel` — disparan Jobs de correo
- [x] `Admin/ReservationController::store` y `cancel` — disparan Jobs de correo
- [x] `CreateNewUser` — dispara `SendWelcomeEmail` al registrarse

---

## 🔴 FASE 5 — Panel Admin y Reportes ✅ COMPLETADA

### Objetivo
Dashboard con métricas reales, reportes exportables, configuración avanzada.

### ✅ Completado
- [x] `Admin/UserController` — listado paginado, ver perfil, historial de reservas, habilitar/deshabilitar
- [x] `admin/Users.vue` — gestión de clientes con filtros por nombre/email y estado, paginación
- [x] `admin/UserDetail.vue` — perfil completo del cliente con historial de reservas
- [x] `Admin/SettingsController` — configurar nombre cancha, dirección, contacto, precio/hora
- [x] `admin/Settings.vue` — panel de configuración con validación y feedback visual
- [x] `Admin/ReportController` — resumen por período, ingresos diarios, por día de semana, top consumibles
- [x] `admin/Reports.vue` — vista de reportes con filtros rápidos (hoy/semana/mes) y rango de fechas
- [x] Gráficas de barras CSS para ingresos diarios y reservas por día de semana
- [x] Top 5 consumibles más vendidos con ingresos
- [x] Últimas 10 reservas del período seleccionado
- [x] Rutas: `/admin/users`, `/admin/users/{user}`, `/admin/settings`, `/admin/reports`

### ❌ Pendiente (futuro)
- [ ] Exportar reportes a Excel (`maatwebsite/excel`)
- [ ] Exportar reportes a PDF
- [ ] Gestión de múltiples administradores con roles
- [ ] Logs de actividad admin

---

## 🚀 FASE 6 — PWA y Optimización ✅ COMPLETADA (base)

### ✅ Completado
- [x] Instalar `vite-plugin-pwa` (workbox-based)
- [x] Configurar PWA en `vite.config.ts` — manifest completo, service worker con `autoUpdate`
- [x] Manifest con nombre, short_name, theme_color (#10b981), display standalone, start_url
- [x] Iconos PWA generados con GD: 64px, 96px, 192px, 512px, maskable-512px
- [x] `apple-touch-icon.png` actualizado (180px, diseño de cancha)
- [x] Splash screen via `background_color` + iconos en manifest
- [x] `theme-color` meta tag en `app.blade.php` (verde esmeralda)
- [x] Meta tags Apple PWA: `mobile-web-app-capable`, `apple-mobile-web-app-capable`
- [x] Caché offline: assets (CacheFirst), fuentes (CacheFirst 1 año), API slots (NetworkFirst 5min)
- [x] Shortcuts del manifest: "Ver disponibilidad" → `/calendar`, "Mis reservas" → `/reservations`
- [x] Screenshot de instalación incluido

### ❌ Pendiente (futuro)
- [ ] Integración con pasarela de pagos (Wompi, MercadoPago, Stripe)
- [ ] Código QR en comprobantes
- [ ] Soporte multicancha (multitenancy)
- [ ] App móvil nativa (React Native / Flutter) — fase muy futura

---

## 🗄️ Estructura de Archivos Clave

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── DashboardController.php     ✅
│   │   │   ├── ReservationController.php   ✅
│   │   │   ├── CourtController.php         ✅
│   │   │   ├── ProductController.php       ✅
│   │   │   ├── CategoryController.php      ✅
│   │   │   ├── UserController.php          ✅
│   │   │   ├── SettingsController.php      ✅
│   │   │   └── ReportController.php        ✅
│   │   ├── Client/
│   │   │   ├── CalendarController.php      ✅
│   │   │   ├── ReservationController.php   ✅
│   │   │   ├── CatalogController.php       ✅
│   │   │   └── CartController.php          ✅
│   │   └── Settings/                       ✅ (existente)
│   └── Middleware/
│       └── EnsureUserIsAdmin.php           ✅
├── Models/
│   ├── User.php                            ✅
│   ├── Court.php                           ✅
│   ├── Reservation.php                     ✅
│   ├── ReservationItem.php                 ✅
│   ├── TimeSlot.php                        ✅
│   ├── Setting.php                         ✅
│   ├── Product.php                         ✅
│   └── ProductCategory.php                 ✅
├── Services/
│   └── TimeSlotService.php                 ✅
└── Components/
    └── AdminNavUser.vue                    ✅

resources/js/
├── layouts/
│   ├── AppClientLayout.vue                 ✅
│   ├── AppAdminLayout.vue                  ✅
│   └── AppLayout.vue                       ✅ (detecta rol)
├── components/
│   └── AdminNavUser.vue                    ✅
└── pages/
    ├── Welcome.vue                         ✅
    ├── Dashboard.vue (cliente)             ✅
    ├── client/
    │   ├── Calendar.vue                    ✅ (selector cancha)
    │   ├── Reservations.vue                ✅ (nombre cancha)
    │   ├── ReservationHistory.vue          ✅ (nombre cancha)
    │   ├── Checkout.vue                    ✅ (nombre cancha)
    │   ├── Catalog.vue                     ✅
    │   └── Cart.vue                        ✅
    ├── admin/
    │   ├── Dashboard.vue                   ✅
    │   ├── Calendar.vue                    ✅ (selector cancha)
    │   ├── Reservations.vue                ✅ (filtros + modal)
    │   ├── Courts.vue                      ✅
    │   ├── Products.vue                    ✅
    │   ├── Categories.vue                  ✅
    │   ├── Users.vue                       ✅
    │   ├── UserDetail.vue                  ✅
    │   ├── Settings.vue                    ✅
    │   └── Reports.vue                     ✅
    └── settings/
        ├── Profile.vue                     ✅ (ES)
        ├── Password.vue                    ✅ (ES)
        ├── Appearance.vue                  ✅ (ES)
        └── TwoFactor.vue                   ✅ (ES)

routes/
├── web.php                                 ✅ (redirect por rol)
├── admin.php                               ✅
├── client.php                              ✅
└── settings.php                            ✅

database/migrations/
├── ...create_users_table                   ✅
├── ...add_two_factor_columns               ✅
├── 2026_02_25_000001_add_fields_to_users   ✅
├── 2026_02_25_000002_create_settings       ✅
├── 2026_02_25_100001_create_time_slots     ✅
├── 2026_02_25_100002_create_reservations   ✅
├── 2026_02_25_100003_create_reservation_time_slots ✅
├── 2026_02_26_000001_create_product_categories ✅
├── 2026_02_26_000002_create_products       ✅
├── 2026_02_26_000003_create_reservation_items ✅
├── 2026_02_26_100001_create_courts         ✅
└── 2026_02_26_100002_add_court_id_to_time_slots_and_reservations ✅
```

---

## ⚙️ Comandos útiles

```bash
# Iniciar el proyecto — opción fácil (1 doble clic)
start.bat   # Abre 4 terminales automáticamente

# Iniciar el proyecto (4 terminales en paralelo)
php artisan serve
npm run dev
php artisan reverb:start      # ← WebSocket server (tiempo real)
php artisan queue:work        # ← Jobs: correos + push notifications

# Base de datos
php artisan migrate:fresh --seed --force   # Reiniciar BD y seeders
php artisan migrate --force                # Solo nuevas migraciones
php artisan db:seed --force                # Solo seeders

# Crear la BD en WAMP si no existe
C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cancha_sintetica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Rutas
php artisan route:list                     # Ver todas las rutas

# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## 📝 Notas Técnicas Importantes

1. **MySQL WAMP** — Se usa `Schema::defaultStringLength(191)` en `AppServiceProvider` para evitar error de key length
2. **Roles** — El middleware `admin` está registrado en `bootstrap/app.php` como alias
3. **Slots** — Se generan automáticamente al consultar el calendario (5PM–11PM, 6 slots por día)
4. **Precio** — Cada cancha tiene su propio `price_per_hour`. Los slots heredan el precio al generarse
5. **Cancelaciones** — Clientes: mínimo 2h de anticipación. Admin: en cualquier momento
6. **Límite reservas** — Máximo 5 reservas activas por cliente simultáneamente
7. **FullCalendar** — Instalado: `@fullcalendar/core`, `vue3`, `daygrid`, `timegrid`, `interaction`
8. **Redirección post-login** — Admin → `/admin/dashboard`, Cliente → `/dashboard`
9. **Multi-cancha** — `court_id` en `time_slots` y `reservations`. Selector de cancha en calendarios
10. **NavUser** — `AdminNavUser.vue` reemplaza `NavUser` en layouts custom (sin contexto Sidebar)
11. **Settings pages** — `AppLayout.vue` detecta rol y usa el layout correspondiente
12. **Idioma** — Toda la UI está en español. Páginas de settings traducidas completamente
13. **Pagos** — Nequi manual implementado. Estados: `unpaid` → `payment_review` → `paid`/`rejected`
14. **Slots** — Nuevo estado `pre_reserved` (🟡 amarillo): reserva creada pero sin pago. Varios clientes pueden pre-reservar el mismo slot. El primero en pagar lo gana.
15. **Push notifications** — Web Push API con claves VAPID. Service Worker custom en `sw-push.ts`. Tabla `push_subscriptions` en BD.
16. **Correos** — Notificaciones de Laravel (reset password, verify email) completamente en español con nombre dinámico de la cancha desde Settings.
