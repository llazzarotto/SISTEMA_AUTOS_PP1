# CU05: Reservar citas

## Especificación

La especificación académica `Especificacion_CU05_Reservar Citas (5).pdf` define al cliente autenticado como actor. El cliente solicita una cita para prueba de manejo o mantenimiento; el sistema la guarda como pendiente de confirmación y envía una notificación por correo.

También define estos escenarios:

- Reprogramación: validar disponibilidad, liberar el horario previo, guardar el nuevo y notificar por correo.
- Cancelación: marcar la cita como cancelada, liberar el horario y notificar por correo.
- Sin turnos: sugerir fechas próximas disponibles.
- Aplicar 24 horas de anticipación mínima, máximo dos citas pendientes por cliente y evitar asignar el mismo horario a más de una persona.
- Actualizar la disponibilidad para reducir conflictos de concurrencia y completar el flujo en no más de cuatro pasos interactivos.

## Flujo implementado

1. El cliente autenticado abre **Mis citas** (`GET /reservas`) o inicia una solicitud (`GET /reservas/nueva`).
2. Selecciona servicio, fecha y, opcionalmente, un auto disponible.
3. Elige un turno ofrecido por el servidor.
4. Revisa y confirma. La cita queda `pendiente`, se asocia al cliente y se envía una notificación.
5. Desde el detalle, el cliente puede consultar, cancelar o reprogramar sus citas activas propias, sujeto a las reglas de anticipación. Las acciones notifican por correo.

Si una fecha no tiene turnos, se sugieren hasta tres fechas próximas disponibles. La disponibilidad se comprueba de nuevo al guardar; una restricción única en la base protege contra dos reservas simultáneas del mismo horario.

| Ruta | Método | Acción |
| --- | --- | --- |
| `/reservas` | GET | Ver próximas citas e historial |
| `/reservas/nueva` | GET/POST | Elegir servicio, fecha y auto opcional |
| `/reservas/nueva/horario` | GET/POST | Elegir un turno disponible |
| `/reservas/nueva/confirmar` | GET/POST | Revisar y registrar la solicitud |
| `/reservas/horarios` | GET | Consultar turnos disponibles (JSON) |
| `/reservas/{id}` | GET | Consultar detalle propio |
| `/reservas/{id}/cancelar` | GET/POST | Confirmar cancelación propia |
| `/reservas/{id}/reprogramar` | GET/POST | Elegir otro turno para cita propia |

## Reglas y configuración

- Solo clientes autenticados pueden gestionar sus citas; la propiedad se comprueba en el servidor.
- Se exige al menos 24 horas antes del turno y se permiten hasta dos citas pendientes por cliente.
- Solo citas activas ocupan un turno. Cancelar libera el horario.
- El catálogo incluye prueba de manejo y mantenimiento, además de los servicios de asesoramiento que ya ofrece la aplicación.
- El calendario actual usa lunes a viernes, turnos de una hora entre 08:30 y 17:30, en `America/Argentina/Buenos_Aires`. La especificación PDF no fija días, horario ni duración: estos valores son decisiones actuales de implementación y deben confirmarse con el equipo.
- El modelo no registra asesor/profesional. Por eso la unicidad actual del turno es global para el concesionario; no permite agenda paralela por asesor.
- Las notificaciones usan Symfony Mailer. `.env` trae `MAILER_DSN=null://null` como valor local seguro; para entregar correos reales hay que configurar `MAILER_DSN` con un proveedor SMTP/API y `MAILER_FROM` con un remitente habilitado. Los fallos de envío se registran sin revertir una cita ya guardada.

## Componentes

| Responsabilidad | Implementación |
| --- | --- |
| Rutas y respuestas HTTP | `ReservaController` |
| Reglas de reserva, cancelación y reprogramación | `ReservaManager` |
| Cálculo de turnos y zona horaria | `DisponibilidadCitaService` |
| Notificaciones por correo | `NotificacionCitaService` |
| Persistencia y consultas | `Cita` / `CitaRepository` |
| Pantallas | `templates/reserva/` |
