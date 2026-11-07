# CU05: Reserva de citas

## Objetivo y actor

Permitir que un cliente autenticado solicite una cita con el concesionario, consulte sus citas y gestione las que todavía cumplen las condiciones de modificación.

## Flujo implementado

1. El cliente abre **Mis citas** (`GET /reservas`) o inicia una reserva (`GET /reservas/nueva`).
2. En `POST /reservas/nueva`, selecciona servicio, fecha y, opcionalmente, un auto disponible. El servidor valida los datos y conserva el borrador en la sesión.
3. En `GET/POST /reservas/nueva/horario`, elige un turno ofrecido por el servidor. La disponibilidad se vuelve a validar al confirmar para evitar aceptar un turno tomado en paralelo.
4. En `GET/POST /reservas/nueva/confirmar`, confirma la solicitud. La cita queda en estado `pendiente` y se asocia al usuario autenticado.
5. El cliente puede consultar el detalle (`GET /reservas/{id}`), cancelar (`GET/POST /reservas/{id}/cancelar`) o reprogramar (`GET/POST /reservas/{id}/reprogramar`) sus propias citas activas cuando se cumplen las reglas.

También existe `GET /reservas/horarios`, que entrega en JSON los turnos disponibles para la fecha solicitada.

| Ruta | Método | Acción | Vista |
| --- | --- | --- | --- |
| `/reservas` | GET | Ver próximas citas e historial | `reserva/index.html.twig` |
| `/reservas/nueva` | GET/POST | Elegir servicio, fecha y auto opcional | `reserva/nueva.html.twig` |
| `/reservas/nueva/horario` | GET/POST | Elegir un turno disponible | `reserva/horario.html.twig` |
| `/reservas/nueva/confirmar` | GET/POST | Revisar y registrar la solicitud | `reserva/confirmar.html.twig` |
| `/reservas/horarios` | GET | Consultar turnos disponibles (JSON) | No renderiza |
| `/reservas/{id}` | GET | Consultar detalle propio | `reserva/detalle.html.twig` |
| `/reservas/{id}/cancelar` | GET/POST | Confirmar cancelación propia | `reserva/cancelar.html.twig` |
| `/reservas/{id}/reprogramar` | GET/POST | Elegir nuevo turno para cita propia | `reserva/reprogramar.html.twig` |

Las rutas HTTP pertenecen a `ReservaController`; las acciones de negocio pasan por `ReservaManager`, que usa `DisponibilidadCitaService` y `CitaRepository`.

## Reglas aplicadas por la implementación

- Solo un usuario con rol Cliente puede operar con reservas; cada cliente solo puede acceder a sus propias citas.
- Se ofrecen turnos de lunes a viernes, de 08:30 a 17:30, con intervalos de una hora.
- El turno debe comenzar con al menos 24 horas de anticipación.
- El cliente puede tener hasta dos citas pendientes.
- Una cita activa ocupa su horario. La base de datos aplica una restricción única para cubrir solicitudes concurrentes.
- Al cancelar, el horario se libera. La cancelación y reprogramación requieren al menos 24 horas de anticipación.
- Se admiten los servicios definidos en `Cita::SERVICIOS`; el auto es opcional y, si se informa, debe existir.

## Componentes

| Responsabilidad | Implementación |
| --- | --- |
| Rutas y respuestas HTTP | `ReservaController` |
| Reglas de reserva, cancelación y reprogramación | `ReservaManager` |
| Cálculo de turnos y zona horaria | `DisponibilidadCitaService` |
| Persistencia y consultas | `Cita` / `CitaRepository` |
| Pantallas | `templates/reserva/` |

## Validación pendiente con el equipo

El documento de contexto compartido identifica las funcionalidades posibles de CU05, pero no contiene el flujo aprobado ni sus reglas detalladas. Antes de cerrar la entrega, confirmar con la especificación académica si los límites de dos citas, 24 horas, horario laboral, estados y acciones de cancelación/reprogramación son los acordados. Actualizar esta guía y las pruebas si difieren.
