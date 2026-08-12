# Integración de TikTok

## Variables de entorno

```env
TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=
TIKTOK_REDIRECT_URI=https://sucasainmobiliaria.com.co/dashboard-marketing/tiktok/callback
TIKTOK_SCOPES=user.info.basic,video.list
TIKTOK_TOKEN_KEY=
```

`TIKTOK_TOKEN_KEY` es opcional. Si se deja vacío, el dashboard deriva la clave de cifrado del Client Secret. Si cambia cualquiera de esas claves, se debe desconectar y volver a conectar TikTok.

Para mostrar seguidores, biografía, nombre de usuario y totales del perfil, la aplicación debe tener aprobados `user.info.profile` y `user.info.stats`. Cuando TikTok los apruebe, se pueden agregar sin tocar el código:

```env
TIKTOK_SCOPES=user.info.basic,user.info.profile,user.info.stats,video.list
```

## Flujo del funcionario

1. Abrir **Redes sociales → TikTok**.
2. Pulsar **Conectar con TikTok**.
3. Autorizar la cuenta que publica los videos de SK&C.
4. Volver al dashboard, seleccionar las fechas y pulsar **Sincronizar TikTok**.
5. El dashboard recorre las páginas de 20 videos hasta cubrir la fecha inicial solicitada.

Los access tokens se guardan cifrados y se renuevan automáticamente con el refresh token. Al desconectar, el dashboard intenta revocar la autorización en TikTok y elimina localmente los tokens, pero conserva los videos sincronizados.

## Datos disponibles

- Perfil básico, avatar y nombre visible.
- Videos públicos, portada, descripción, fecha, duración y enlace.
- Visualizaciones, me gusta, comentarios y compartidos acumulados por video.
- Engagement calculado y agrupación mensual.

TikTok Display API entrega acumulados actuales por video, no una serie histórica diaria de visualizaciones. La gráfica del dashboard agrupa esos acumulados por la fecha en que se publicó cada video.

## Tablas

- `wp_marketing_tiktok_connections`
- `wp_marketing_tiktok_videos`

El prefijo real se toma de `SKC_DB_PREFIX`.

## Referencias oficiales

- https://developers.tiktok.com/doc/login-kit-web
- https://developers.tiktok.com/doc/oauth-user-access-token-management
- https://developers.tiktok.com/doc/tiktok-api-v2-get-user-info
- https://developers.tiktok.com/doc/tiktok-api-v2-video-list
