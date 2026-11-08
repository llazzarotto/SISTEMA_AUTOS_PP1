# Guía de Estudio y Presentación — Luxury Cars (Symfony 7.4)

Este documento está diseñado como material de estudio y guion técnico para defender el proyecto **Luxury Cars** en una presentación académica o técnica. Cubre la arquitectura, el modelo de datos, el flujo de cada caso de uso, las medidas de seguridad y las preguntas frecuentes de evaluación.

---

## 1. Resumen Ejecutivo del Proyecto

**Luxury Cars** es una aplicación web desarrollada con **Symfony 7.4**, **PHP 8.2+** y **Doctrine ORM 3** para la gestión integral de una concesionaria de vehículos. El sistema resuelve tanto la experiencia comercial del **Cliente** (exploración de catálogo, comparación de unidades, favoritos, simulación de financiación, consultas a asesores y reserva de turnos) como la gestión operativa del **Administrador** (ABM de inventario con galería de imágenes, administración de agenda de citas, respuesta a consultas y gestión jerárquica de usuarios).

---

## 2. Arquitectura del Sistema

El proyecto aplica el patrón **MVC (Modelo-Vista-Controlador)** extendido con una **Capa de Servicios y Managers** (Arquitectura en Capas), siguiendo las buenas prácticas oficiales de Symfony:

1. **Entidades (`src/Entity/`):** Representan las tablas de la base de datos mediante atributos de mapeo `#[ORM\...]` y encapsulan invariantes de dominio (por ejemplo, estados válidos de un `Auto` o de una `Cita`).
2. **Repositorios (`src/Repository/`):** Concentran todas las consultas DQL / `QueryBuilder` optimizadas con `innerJoin` y `leftJoin` (`addSelect`) para evitar el problema de rendimiento *N+1 queries*.
3. **Managers y Servicios (`src/Manager/` y `src/Service/`):** Contienen la **lógica de negocio pura** (cálculo financiero, reglas de agenda de turnos, jerarquía de roles, hash de contraseñas y envío de notificaciones por email). Esto mantiene los controladores livianos (*Thin Controllers*).
4. **Controladores (`src/Controller/`):** Reciben la petición HTTP (`Request`), validan tokens CSRF y permisos, delegan la operación al Manager/Repositorio correspondiente y devuelven una `Response` (HTML vía Twig o `JsonResponse` para llamadas asíncronas `fetch`).
5. **Vistas (`templates/`):** Plantillas **Twig** con herencia desde `base.html.twig` y componentes reutilizables (`_card.html.twig`, `_image.html.twig`, `_flashes.html.twig`).

---

## 3. Modelo de Datos y Relaciones (MER)

El esquema relacional está compuesto por **9 entidades** principales:

* **`Marca` (1) <-> (N) `Modelo` (1) <-> (N) `Auto`:** Normalización en 3FN. Evita repetir nombres de marcas (`BMW`, `Audi`, `Toyota`) y modelos en cada unidad física del inventario.
* **`Auto` (1) <-> (N) `ImagenAuto`:** Configurada con `cascade: ['persist', 'remove']` y `orphanRemoval: true`. Permite asociar entre 1 y 5 imágenes por vehículo.
* **`Rol` (1) <-> (N) `Usuario`:** `Rol::getNivel()` traduce el rol de base de datos en un nivel numérico (`Cliente = 1`, `Administrador = 2`), y `Usuario::getRoles()` otorga automáticamente `'ROLE_ADMIN'` a los usuarios de nivel >= 2 para integrarse con el componente de Seguridad de Symfony.
* **`Favorito` (Entidad asociativa `Usuario` <-> `Auto`):** Tiene una restricción única compuesta `uniq_favorito_usuario_auto` (`usuario_id`, `auto_id`) que impide a nivel de base de datos que un cliente marque dos veces el mismo vehículo como favorito.
* **`Cita` (`Usuario` y `Auto` opcional):** Implementa un bloqueo inteligente mediante el callback de ciclo de vida `#[ORM\PrePersist]` / `#[ORM\PreUpdate]` en `Cita::sincronizarBloqueoHorario()`. Mientras la cita está activa (`pendiente` o `confirmada`), copia `fechaHora` en `fechaHoraBloqueada` (que tiene índice `UNIQUE`). Si la cita se cancela, rechaza o completa, `fechaHoraBloqueada` pasa a `NULL`, liberando automáticamente el turno para otro cliente sin perder el historial.

---

## 4. Explicación Módulo por Módulo (Casos de Uso)

### Módulo 1: Autenticación, Registro y Sesión Inactiva (CU01)
* **Archivos clave:** `SecurityController`, `SecurityManager`, `RegistroType`, `ContrasenaType`, `SesionInactivaSubscriber`.
* **Cómo funciona:**
  * El registro público exige email válido y contraseña robusta (mínimo 6 caracteres, mayúscula, minúscula y símbolo especial validados por expresiones regulares Unicode en `ContrasenaType`).
  * Nunca se almacena la contraseña en texto plano: se aplica `UserPasswordHasherInterface::hashPassword()`.
  * En `Usuario::__serialize()` se guarda en sesión un hash `crc32c` del hash de contraseña para no exponer el hash real en el archivo de sesión.
  * `SesionInactivaSubscriber` escucha el evento `KernelEvents::REQUEST` en cada petición: si pasaron más de **10 minutos (600 segundos)** sin actividad, cierra la sesión automáticamente y redirige al login con un aviso.

### Módulo 2: Catálogo, Filtros, Comparador y Favoritos (CU02)
* **Archivos clave:** `AutoController`, `AutoRepository`, `FavoritoRepository`.
* **Cómo funciona:**
  * `AutoRepository::buscarDisponibles()` construye dinámicamente una consulta DQL con filtros combinables (texto libre por marca/modelo/año, rango de precios, kilometraje máximo, rango de años, marca y ordenamiento) y devuelve un objeto `Paginator` de Doctrine (9 autos por página).
  * Si el usuario invierte por error `precioMin > precioMax` o `anioDesde > anioHasta`, `AutoController::index()` los intercambia automáticamente.
  * El **Comparador** almacena en la sesión (`comparador_autos`) hasta 2 IDs de vehículos disponibles para contrastar sus especificaciones lado a lado.
  * Los **Favoritos** se persisten en la base de datos por usuario cliente (los administradores están excluidos de favoritos y acceden en cambio a la edición del vehículo desde su ficha).

### Módulo 3: Simulador de Financiación (CU03)
* **Archivos clave:** `FinanciamientoController`, `FinanciamientoManager`, `templates/financiamiento/calculadora.html.twig`.
* **Cómo funciona:**
  * Permite seleccionar cualquier vehículo disponible e ingresar una entrega inicial y un plan de cuotas (12, 24, 36, 48 o 60 meses con tasas del 15% al 75%).
  * Valida que la entrega no sea negativa ni supere el valor del auto, calcula el capital a financiar y el valor de la cuota:
    `Cuota = (Capital + (Capital * (Tasa / 100))) / Cantidad de Cuotas`
  * Envía el resultado por `fetch` (`POST /financiamiento/{id}/resultado`), guarda la simulación en sesión con un ID único y permite abrir directamente una consulta con el asesor llevando adjunto el contexto de la simulación.

### Módulo 4: Atención al Cliente y Respuesta de Mensajes (CU04)
* **Archivos clave:** `ChatController`, `ChatManager`, `Consulta`.
* **Cómo funciona:**
  * **Vista Cliente:** Permite enviar consultas en línea u offline vinculadas opcionalmente a un vehículo y/o simulación financiera. Los mensajes se persisten en la tabla `consulta`.
  * **Vista Administrador ("Contestar mensajes"):** Al ingresar como `ROLE_ADMIN` en `/chat`, el sistema muestra todas las consultas de los clientes ordenadas de más reciente a más antigua y permite responderlas mediante `POST /chat/{id}/responder`, actualizando su estado a `respondida` y haciendo visible la respuesta en el historial del cliente.

### Módulo 5: Reserva de Citas y Gestión de Agenda (CU05)
* **Archivos clave:** `ReservaController`, `CitaController`, `ReservaManager`, `DisponibilidadCitaService`, `NotificacionCitaService`.
* **Reglas de negocio implementadas:**
  * Flujo guiado en **3 pasos** (1. Servicio y Fecha -> 2. Horario disponible con actualización automática cada 15 s vía `fetch` -> 3. Confirmación).
  * Turnos de 60 minutos de lunes a viernes de `08:30` a `17:30` hs (`America/Argentina/Buenos_Aires`).
  * Anticipación mínima de **24 horas** tanto para reservar como para reprogramar o cancelar.
  * Límite máximo de **2 citas pendientes** simultáneas por cliente (`ReservaManager::MAXIMO_CITAS_PENDIENTES`).
  * Si una fecha no tiene turnos libres, `DisponibilidadCitaService::sugerirFechasDisponibles()` sugiere automáticamente las próximas 3 fechas hábiles con disponibilidad.
  * Envío de notificaciones por correo con `MailerInterface` encapsulado con `try/catch` en `NotificacionCitaService` para que un fallo del servidor SMTP nunca aborte la reserva ya guardada.

### Módulo 6: Gestión de Inventario y Gestión de Usuarios (Admin)
* **Inventario (`InventarioController`):**
  * Permite listar, filtrar por marca/modelo y estado, crear y editar vehículos, subir entre **1 y 5 imágenes** (`jpg`, `jpeg`, `png`, `webp`) y realizar **baja lógica** (`estado = 'inactivo'`) para no romper la integridad referencial con citas o consultas históricas.
* **Usuarios (`UsuarioController` y `UsuarioManager`):**
  * Controla la jerarquía de roles (`puedeGestionar()`): un administrador solo puede gestionar usuarios de nivel igual o inferior al suyo.
  * La eliminación de un usuario exige ingresar una razón obligatoria, marcar casilla de confirmación y elimina en una única transacción (`flush`) sus citas, consultas y favoritos asociados.

---

## 5. Preguntas Frecuentes para Defender la Presentación

1. **¿Por qué usaron baja lógica (`inactivo`) al eliminar un vehículo en Inventario en lugar de un `DELETE` físico?**
   * *Respuesta:* Porque un vehículo puede tener citas históricas o consultas asociadas. Al cambiar su estado a `'inactivo'`, deja de aparecer en el catálogo público y en el simulador de financiación, pero se preserva el historial de la concesionaria sin romper claves foráneas.
2. **¿Cómo evitan que dos clientes reserven el mismo turno al mismo tiempo (condición de carrera)?**
   * *Respuesta:* Además de validar disponibilidad en `DisponibilidadCitaService`, la entidad `Cita` tiene la columna `fechaHoraBloqueada` con restricción `UNIQUE` en la base de datos. Si dos peticiones concurren en el mismo milisegundo, MySQL rechaza la segunda con `UniqueConstraintViolationException`, que `ReservaController::confirmar()` captura para mostrar un mensaje amigable y pedir que elija otro horario.
3. **¿Cómo está protegida la aplicación contra ataques comunes (CSRF, SQL Injection, XSS)?**
   * *Respuesta:*
     * **SQL Injection:** Todas las consultas usan parámetros enlazados (`setParameter()`) en Doctrine DQL / `QueryBuilder`.
     * **CSRF:** Todos los formularios `POST` validan tokens con `isCsrfTokenValid()` o mediante el componente `Form` de Symfony.
     * **XSS:** Twig escapa automáticamente todas las variables impresas con `{{ ... }}`.
     * **Contraseñas:** Se almacenan con algoritmo de hashing adaptativo (`auto` -> `bcrypt`/`argon2i`) mediante `UserPasswordHasherInterface`.
