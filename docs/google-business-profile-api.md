# Integración con Google Business Profile

Documento de continuidad para incorporar **Google Perfil de Empresa** al módulo **Redes sociales** de Dashboard Marketing.

> Este documento no debe contener contraseñas, Client Secrets, access tokens ni refresh tokens. Esos valores deben guardarse fuera del repositorio y fuera de `public_html`.

## Estado de la solicitud

| Campo | Valor |
|---|---|
| Tipo de solicitud | Solicitud de acceso básico a la API |
| Fecha de envío | 2026-08-06 |
| Estado actual | En revisión por Google |
| ID del caso | `5-4555000040632` |
| Tiempo informado por Google | Aproximadamente 7 a 10 días hábiles |
| Perfil que se conectará | SK&C SuCasa Inmobiliaria |

Google confirmó que abrió un caso de asistencia. No se debe enviar otra solicitud mientras este caso siga en revisión, salvo que Google lo solicite expresamente.

La cuenta con la que se hizo la solicitud debe conservar el rol de propietaria o administradora del Perfil de Empresa.

## Objetivo funcional

Agregar una pestaña **Google** junto a Instagram, Facebook y Meta Ads. Debe mostrar:

- Interacciones del Perfil de Empresa.
- Visualizaciones en Google Search y Google Maps.
- Separación de visualizaciones por móvil y computador.
- Clics en el botón de llamada.
- Clics hacia el sitio web.
- Solicitudes de indicaciones para llegar.
- Conversaciones y reservas, cuando Google las entregue para el perfil.
- Palabras de búsqueda utilizadas para encontrar la empresa.
- Publicaciones realizadas en Google.
- KPI por publicación: visualizaciones y clics en el botón de llamado a la acción.
- Gráficas diarias y mensuales.
- Histórico propio conservado en la base de datos.

No se utilizará scraping. La integración debe consumir exclusivamente las APIs oficiales de Google.

## APIs necesarias

Después de recibir la aprobación, habilitar en el mismo proyecto de Google Cloud:

1. **Business Profile Performance API**: rendimiento diario y palabras de búsqueda.
2. **My Business Account Management API**: descubrimiento de las cuentas administradas.
3. **My Business Business Information API**: descubrimiento de ubicaciones y datos básicos.
4. **Google My Business API**: publicaciones locales y KPI de cada publicación.

Google puede pedir que se habiliten otras APIs de la familia Business Profile durante la configuración inicial. Habilitarlas únicamente en el proyecto aprobado.

Documentación oficial:

- [Requisitos y solicitud de acceso](https://developers.google.com/my-business/content/prereqs)
- [Configuración básica](https://developers.google.com/my-business/content/basic-setup)
- [Implementación de OAuth](https://developers.google.com/my-business/content/implement-oauth)
- [Métricas de rendimiento disponibles](https://developers.google.com/my-business/reference/performance/rest/v1/DailyMetric)
- [Series de métricas diarias](https://developers.google.com/my-business/reference/performance/rest/v1/locations/fetchMultiDailyMetricsTimeSeries)
- [Palabras de búsqueda mensuales](https://developers.google.com/my-business/reference/performance/rest/v1/locations.searchkeywords.impressions.monthly/list)
- [Listado de publicaciones](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.localPosts/list)
- [KPI de publicaciones](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.localPosts/reportInsights)

## Qué hacer cuando llegue la aprobación

### 1. Confirmar la aprobación

- Revisar el correo asociado al caso `5-4555000040632`.
- Abrir Google Cloud Console y seleccionar el proyecto enviado en la solicitud.
- Revisar la cuota de las APIs de Business Profile:
  - `0 QPM`: el proyecto todavía no está aprobado.
  - `300 QPM`: Google ya otorgó acceso básico.

### 2. Habilitar las APIs

Desde **Google Cloud Console → APIs y servicios → Biblioteca**, habilitar las cuatro APIs indicadas anteriormente.

### 3. Configurar OAuth

1. Abrir **APIs y servicios → Pantalla de consentimiento OAuth**.
2. Registrar el nombre de la aplicación, por ejemplo `Dashboard Marketing SKC`.
3. Configurar dominio, página principal, política de privacidad y datos de contacto.
4. Usar el permiso:

```text
https://www.googleapis.com/auth/business.manage
```

5. Crear una credencial **OAuth Client ID** de tipo **Web application**.
6. Registrar esta URL de redirección autorizada:

```text
https://sucasainmobiliaria.com.co/dashboard-marketing/google/callback
```

La URL debe coincidir exactamente, incluyendo HTTPS y la ruta completa.

### 4. Datos que se deben registrar sin exponer secretos

Completar esta tabla después de la aprobación. No pegar el Client Secret ni tokens.

| Dato | Valor pendiente |
|---|---|
| Nombre del proyecto |  |
| Project ID |  |
| Project Number |  |
| Fecha de aprobación |  |
| Correo propietario/administrador |  |
| OAuth Client ID, solo últimos caracteres |  |
| Account ID de Business Profile | Se descubrirá por API |
| Location ID | Se descubrirá por API |

## Configuración prevista en el proyecto

Las variables previstas son:

```text
GOOGLE_BUSINESS_CLIENT_ID
GOOGLE_BUSINESS_CLIENT_SECRET
GOOGLE_BUSINESS_REDIRECT_URI
```

El `Client Secret` no debe quedar escrito como valor por defecto en `config/app.php` ni subirse al repositorio.

El flujo recomendado para el dashboard es:

1. Mostrar un botón **Conectar con Google**.
2. Redirigir al consentimiento OAuth de Google.
3. Validar `state` al regresar a `/google/callback`.
4. Intercambiar el código de autorización por access token y refresh token.
5. Guardar el refresh token cifrado del lado del servidor.
6. Descubrir automáticamente la cuenta y la ubicación administradas.
7. Mostrar la cuenta conectada y permitir desconectarla.

No se debe pedir al funcionario que copie access tokens manualmente. Los access tokens vencen; el servidor debe renovarlos usando el refresh token.

## Endpoints principales

### Cuentas

```text
GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts
```

### Ubicaciones

Consultar las ubicaciones accesibles para la cuenta aprobada mediante Business Information API. Guardar el identificador sin ofuscar requerido por Performance API.

### Rendimiento diario

```text
GET https://businessprofileperformance.googleapis.com/v1/locations/{locationId}:fetchMultiDailyMetricsTimeSeries
```

Métricas previstas:

```text
BUSINESS_IMPRESSIONS_DESKTOP_MAPS
BUSINESS_IMPRESSIONS_DESKTOP_SEARCH
BUSINESS_IMPRESSIONS_MOBILE_MAPS
BUSINESS_IMPRESSIONS_MOBILE_SEARCH
BUSINESS_DIRECTION_REQUESTS
CALL_CLICKS
WEBSITE_CLICKS
BUSINESS_CONVERSATIONS
BUSINESS_BOOKINGS
```

No todas las métricas aplican a todos los perfiles. La interfaz debe mostrar **N/D** cuando Google no entregue una métrica y no convertir esa ausencia en cero engañoso.

### Palabras de búsqueda

```text
GET https://businessprofileperformance.googleapis.com/v1/locations/{locationId}/searchkeywords/impressions/monthly
```

Google entrega estas cifras de forma mensual. Algunas consultas pueden venir como un umbral en lugar de un valor exacto.

### Publicaciones

```text
GET https://mybusiness.googleapis.com/v4/accounts/{accountId}/locations/{locationId}/localPosts
```

La paginación admite hasta 100 publicaciones por página.

### KPI de publicaciones

```text
POST https://mybusiness.googleapis.com/v4/accounts/{accountId}/locations/{locationId}/localPosts:reportInsights
```

Procesar máximo 100 publicaciones por llamada. Métricas disponibles:

```text
LOCAL_POST_VIEWS_SEARCH
LOCAL_POST_ACTIONS_CALL_TO_ACTION
```

## Correspondencia con la interfaz

| Elemento del dashboard | Datos de Google |
|---|---|
| Interacciones | Suma disponible de llamadas, sitio web, indicaciones, conversaciones y reservas |
| Visualizaciones | Suma de Search y Maps en móvil y computador |
| Llamadas | `CALL_CLICKS` |
| Clics en el sitio web | `WEBSITE_CLICKS` |
| Direcciones | `BUSINESS_DIRECTION_REQUESTS` |
| Clics en el chat | `BUSINESS_CONVERSATIONS`, si aplica |
| Reservas | `BUSINESS_BOOKINGS`, si aplica |
| Cómo descubrieron la empresa | Search/Maps, dispositivo y palabras de búsqueda |
| Publicaciones | Local Posts API |
| Visualizaciones por publicación | `LOCAL_POST_VIEWS_SEARCH` |
| Clics CTA por publicación | `LOCAL_POST_ACTIONS_CALL_TO_ACTION` |

## Persistencia recomendada

No mezclar todos los campos de Google dentro de las columnas actuales de Meta. Crear tablas específicas:

- `wp_marketing_google_business_locations`
- `wp_marketing_google_business_daily_stats`
- `wp_marketing_google_business_search_keywords`
- `wp_marketing_google_business_posts`
- `wp_marketing_google_business_connections`

Claves de actualización recomendadas:

- Estadísticas: ubicación + fecha.
- Palabras: ubicación + mes + término.
- Publicaciones: identificador externo de Google.
- Conexión: usuario o cuenta autorizada + ubicación.

Los procesos deben ser idempotentes mediante `INSERT ... ON DUPLICATE KEY UPDATE` o una operación equivalente.

## Histórico y sincronización

La interfaz de Google normalmente permite consultar los últimos seis meses de rendimiento. Al conectar por primera vez:

1. Importar todo el histórico que Google todavía entregue, en bloques mensuales.
2. Guardarlo en la base local.
3. Ejecutar una sincronización incremental diaria.
4. Conservar los registros locales aunque Google deje de exponer periodos antiguos.

La sincronización no debe mantener abierta una petición web durante varios minutos. Diseño recomendado:

- El botón **Sincronizar Google** crea o actualiza un trabajo de cola.
- El proceso importa una ubicación y un mes por lote.
- Guardar cursor, página y última fecha procesada.
- Reintentar errores `429` y `5xx` con espera incremental.
- Renovar automáticamente el access token cuando venza.
- Mostrar progreso, fecha de última sincronización y advertencias en la pestaña Google.
- Un cron diario continúa lotes pendientes y actualiza los días recientes.

## Cambios previstos en Dashboard Marketing

1. Crear `GoogleBusinessAuthService` para OAuth y renovación de tokens.
2. Crear `GoogleBusinessSyncService` para cuentas, ubicaciones, rendimiento, palabras y publicaciones.
3. Ampliar `SocialStatsRepository` para consultar los datos de Google.
4. Agregar `google` a las plataformas aceptadas por el controlador.
5. Incorporar una pestaña **Google** en `views/social-stats.php`.
6. Agregar estilos de Google a `public/assets/social.css`.
7. Mantener navegación, filtros y sincronización dinámica mediante `fetch`, como las pestañas actuales.
8. Crear pruebas de esquema, normalización de métricas, paginación, renovación OAuth e idempotencia.

## Seguridad

- Usar exclusivamente HTTPS.
- Validar el parámetro OAuth `state` para prevenir CSRF.
- Solicitar solamente el scope `business.manage`.
- Guardar Client Secret y refresh token fuera del repositorio.
- Cifrar el refresh token antes de persistirlo.
- No enviar tokens al navegador ni incluirlos en parámetros de URL.
- No escribir tokens en logs, mensajes de error o capturas.
- Permitir revocar la conexión desde el dashboard.
- Limitar conectar, desconectar y sincronizar a funcionarios con permiso del módulo Redes sociales.

## Lista de verificación final

- [x] Solicitud de acceso básico enviada.
- [x] Caso de Google registrado: `5-4555000040632`.
- [ ] Aprobación recibida.
- [ ] Cuota confirmada en 300 QPM.
- [ ] APIs habilitadas.
- [ ] Pantalla de consentimiento configurada.
- [ ] OAuth Client ID creado.
- [ ] URL de callback registrada.
- [ ] Variables seguras configuradas en el servidor.
- [ ] Botón Conectar con Google implementado.
- [ ] Cuenta y ubicación descubiertas.
- [ ] Histórico inicial sincronizado.
- [ ] Cron incremental funcionando.
- [ ] Pestaña Google verificada en producción.

