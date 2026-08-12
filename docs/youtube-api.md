# Integración de YouTube

## Configuración

El proyecto lee estas variables desde `dashboard-marketing/.env`:

```env
GOOGLE_YOUTUBE_CLIENT_ID=
GOOGLE_YOUTUBE_CLIENT_SECRET=
GOOGLE_YOUTUBE_REDIRECT_URI=https://sucasainmobiliaria.com.co/dashboard-marketing/youtube/callback
GOOGLE_YOUTUBE_CHANNEL_ID=UCaTF2yQafkEH6loKgQZrWnA
GOOGLE_YOUTUBE_CHANNEL_NAME=SK&C SuCasa Inmobiliaria
GOOGLE_YOUTUBE_TOKEN_KEY=
```

`GOOGLE_YOUTUBE_CHANNEL_ID` identifica el canal que debe conectarse. Para SK&C SuCasa Inmobiliaria corresponde a `UCaTF2yQafkEH6loKgQZrWnA`; así se evita autorizar accidentalmente otro canal administrado por la misma cuenta. `GOOGLE_YOUTUBE_CHANNEL_NAME` se utiliza para mostrar una instrucción clara cuando la autorización no coincide.

`GOOGLE_YOUTUBE_TOKEN_KEY` también es opcional. Cuando está vacío, el cifrado utiliza una clave derivada del Client Secret. Si se cambia cualquiera de esas claves, se debe reconectar el canal.

## Conectar el canal correcto de SK&C

1. En YouTube Studio, abre la foto de perfil y usa **Cambiar de cuenta** para confirmar que está activo **SK&C SuCasa Inmobiliaria**.
2. Abre `https://www.youtube.com/account_advanced`, activa **Usar este canal cuando inicie sesión en mi cuenta** y guarda. Esto hace que las herramientas de terceros utilicen el canal de marca correcto.
3. En `https://myaccount.google.com/connections`, elimina el acceso anterior de la aplicación de analíticas de YouTube.
4. En el dashboard, entra a **Redes sociales → YouTube** y desconecta la autorización anterior.
5. Pulsa **Conectar con YouTube**, inicia sesión con la cuenta administradora y selecciona el canal de marca **SK&C SuCasa Inmobiliaria** cuando Google lo solicite.
6. Regresa al dashboard y pulsa **Sincronizar YouTube**.

La aplicación compara el canal autorizado con `GOOGLE_YOUTUBE_CHANNEL_ID`. Si no coincide, bloquea la sincronización y muestra una instrucción para reconectar, evitando mezclar videos y KPI de canales distintos.

En Google Cloud deben estar activadas:

- YouTube Data API v3.
- YouTube Analytics API.

El cliente OAuth debe incluir los permisos:

```text
https://www.googleapis.com/auth/youtube.readonly
https://www.googleapis.com/auth/yt-analytics.readonly
```

## Flujo

1. El funcionario abre `Redes sociales > YouTube`.
2. `Conectar con YouTube` genera un estado OAuth válido durante 15 minutos.
3. Google retorna a `/dashboard-marketing/youtube/callback`.
4. El access token y refresh token se cifran con AES-256-GCM antes de guardarse.
5. La sincronización renueva automáticamente el access token cuando está próximo a expirar.

## Información sincronizada

- Perfil y acumulados públicos del canal.
- Videos publicados dentro del rango seleccionado.
- Estadísticas públicas actuales por video.
- Visualizaciones, vistas con interés y minutos vistos por día.
- Duración y porcentaje promedio de visualización.
- Likes, comentarios, compartidos y suscriptores ganados/perdidos.
- Rendimiento por video durante el periodo.
- Formato de contenido, fuentes de tráfico, búsquedas, países, dispositivos y condición de suscripción.

Los videos se obtienen mediante la lista de subidas del canal para consumir menos cuota que una búsqueda general.

## Tablas

- `wp_marketing_youtube_connections`
- `wp_marketing_youtube_daily_stats`
- `wp_marketing_youtube_videos`
- `wp_marketing_youtube_breakdowns`

Desconectar el canal elimina los tokens guardados, pero conserva las estadísticas ya sincronizadas.

## Referencias oficiales

- [YouTube Data API](https://developers.google.com/youtube/v3/docs)
- [YouTube Analytics API](https://developers.google.com/youtube/analytics/reference)
- [Reportes de canal](https://developers.google.com/youtube/analytics/channel_reports)
- [OAuth para aplicaciones web](https://developers.google.com/youtube/reporting/guides/authorization/server-side-web-apps)
