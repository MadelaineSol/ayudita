# Manual del administrador 👩‍💼

Acceso: `admin.tudominio.com` (o `admin/index.html`). Solo cuentas con rol
**admin** pueden entrar.

## Dashboard 📊
Resumen en vivo: clientes, prestadores, trabajos, volumen procesado,
comisiones ganadas, y contadores de tareas pendientes (liberaciones,
retiros, disputas). Gráfico de ingresos por mes.

## Usuarios 👥
- Filtrar por rol, estado o texto.
- **Bloquear / activar** cuentas (un bloqueado no puede iniciar sesión).
- **Verificar prestadores** (✅ visible para los clientes; genera confianza).
- Eliminar usuarios (soft delete: los datos se conservan para auditoría).

## Pagos 💳
Todos los pagos que ingresaron a la plataforma, con desglose:
monto bruto, comisión aplicada (%) y monto neto para el prestador,
medio de pago, estado y fecha.

## Liberaciones 💸  (el corazón del flujo de dinero)
Cuando un cliente paga, el neto queda **pendiente de liberación**.
Al presionar **"Aprobar liberación"**, el dinero se acredita al saldo del
prestador dentro de la app. Recomendación: liberar cuando el trabajo esté
`Finalizado` y sin disputas.

## Retiros 🏦
Solicitudes de los prestadores para retirar su saldo:
1. Transferí el dinero por tu canal bancario al CBU/alias informado.
2. Presioná **"Aprobar y pagar"** (queda registrado quién y cuándo).
3. Si algo está mal, **"Rechazar"** devuelve el saldo al prestador automáticamente.

## Categorías 🧰 y Banners 🖼️
ABM completo. Las categorías usan un emoji como ícono. Los banners activos
aparecen en el inicio de la app.

## Disputas ⚖️
Reclamos abiertos por clientes o prestadores sobre un trabajo. Resolver con
una decisión y un texto de resolución (queda en auditoría).

## Reportes 📈
Servicios más solicitados, prestadores mejor calificados, clientes
frecuentes, clientes activos por mes. Datos de mapa de calor disponibles vía
`GET /api/v1/admin/reports/heatmap`.

## Configuración ⚙️
- **Comisión de la plataforma (%)**: se aplica a los pagos **futuros**
  (los ya registrados conservan el % con el que se cobraron).
- Impuestos, moneda, retiro mínimo, email de soporte, nombre de la app.

## Auditoría 📜
Registro inmutable de acciones sensibles: logins, cambios de configuración,
bloqueos, aprobaciones de dinero, con usuario, IP y fecha.
