# API del inventario de souvenirs

La API permite consultar productos disponibles y registrar entregas desde formularios, portales o integraciones externas.

## Configuración

Define el token en el servidor:

```text
SKC_INVENTORY_API_TOKEN=un-token-largo-y-aleatorio
```

Envía el token en una de estas cabeceras:

```http
Authorization: Bearer un-token-largo-y-aleatorio
```

o:

```http
X-Inventory-Token: un-token-largo-y-aleatorio
```

Nunca incluyas el token en parámetros de la URL ni en código JavaScript público.

## Consultar productos

```http
GET /api/inventory/products
```

Ejemplo con cURL:

```bash
curl "https://tu-dominio.example/api/inventory/products" \
  -H "Authorization: Bearer TU_TOKEN"
```

Respuesta:

```json
{
  "ok": true,
  "products": [
    {
      "id": 1,
      "name": "Botella",
      "type": "Souvenir",
      "sku": "BOT-001",
      "quantity": 12,
      "unit": "unidad",
      "minimum_stock": 2,
      "price": "18000.00",
      "points": 18,
      "origin": "pph"
    }
  ]
}
```

El formulario debe guardar el valor `id` como `product_id` o como valor del checkbox `product_ids[]`.

## Registrar una entrega

```http
POST /api/inventory/deliveries
Content-Type: application/json
```

Ejemplo:

```bash
curl -X POST "https://tu-dominio.example/api/inventory/deliveries" \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "external_id": "formulario-entrega-0001",
    "delivered_at": "2026-07-25T14:30:00-05:00",
    "recipient_name": "Nombre de la persona",
    "recipient_type": "Funcionario",
    "recipient_document": "123456789",
    "recipient_source": "Formulario del portal",
    "recipient_id": "1508",
    "recipient_metadata": {
      "sede": "Cartagena",
      "formulario": "entrega-souvenirs"
    },
    "items": [
      {
        "product_id": 1,
        "quantity": 2
      }
    ],
    "evidence_urls": [
      "https://tu-dominio.example/evidencias/entrega-0001.jpg"
    ],
    "notes": "Entrega realizada"
  }'
```

Campos:

| Campo | Obligatorio | Descripción |
|---|---:|---|
| `external_id` | Sí | Identificador único generado por el formulario. Evita descuentos duplicados. |
| `delivered_at` | Sí | Fecha ISO 8601 con hora y zona horaria. |
| `recipient_name` | Sí | Nombre de quien recibe. |
| `recipient_type` | No | Funcionario, cliente, proveedor u otro tipo. |
| `recipient_document` | No | Documento, tarjeta o referencia. |
| `recipient_source` | No | Sistema o formulario del que provienen los datos. |
| `recipient_id` | No | Identificador en el sistema de origen. |
| `recipient_metadata` | No | Objeto JSON con datos adicionales. |
| `items` | Sí | Lista de productos y cantidades positivas. |
| `product_ids` | Alternativa | Lista simple de IDs seleccionados con checkboxes. Cada ID equivale a una unidad. |
| `evidence_urls` | No | Hasta cinco URLs HTTPS de fotografías. |
| `notes` | No | Observaciones de la entrega. |

Para subir archivos directamente, usa `multipart/form-data`, envía `items` como JSON y los archivos con el nombre `evidence[]`. Se permiten hasta cinco imágenes JPG, PNG o WebP de máximo 8 MB cada una.

También se acepta una lista simple de productos, ideal para checkboxes:

```json
{
  "product_ids": [12, 15, 21]
}
```

Esto equivale a enviar cantidad `1` para cada producto. Si necesitas entregar dos o más unidades del mismo producto, usa `items` con `product_id` y `quantity`.

## Caso: entrega de un inmueble

El sistema donde se realiza la entrega debe obtener el arrendatario, el inmueble y el funcionario autenticado desde su propia base de datos. Luego envía una fotografía de esos datos a la API. El inventario no intenta adivinarlos únicamente con el ID.

Ejemplo de solicitud:

```json
{
  "external_id": "entrega-inmueble-contrato-4582",
  "delivered_at": "2026-08-11T10:30:00-05:00",
  "recipient_name": "María Pérez",
  "recipient_type": "Arrendatario",
  "recipient_document": "1020304050",
  "recipient_source": "Formulario entrega de inmueble",
  "recipient_id": "ARR-1508",
  "recipient_metadata": {
    "inmueble_id": "INM-908",
    "inmueble_direccion": "Calle 10 # 20-30 Apto 401",
    "contrato_id": "CTR-4582",
    "funcionario_id": "EMP-27",
    "funcionario_nombre": "Carlos Gómez"
  },
  "product_ids": [12, 15],
  "notes": "Entrega inicial del inmueble"
}
```

Con una sola solicitud, la API:

1. Registra una entrega en **Historial** para el arrendatario.
2. Muestra el inmueble y el funcionario que realizó la entrega.
3. Descuenta una unidad por cada checkbox seleccionado.
4. Crea un movimiento de salida por cada producto.
5. Si algún producto no tiene existencias, revierte toda la operación.

### Formulario con checkboxes

Los valores `12` y `15` deben venir de `GET /api/inventory/products`:

```html
<form method="post" action="/procesar-entrega-inmueble.php">
  <input type="hidden" name="contract_id" value="4582">

  <label>
    <input type="checkbox" name="product_ids[]" value="12">
    Entregó llavero
  </label>

  <label>
    <input type="checkbox" name="product_ids[]" value="15">
    Entregó kit de bienvenida
  </label>

  <button type="submit">Registrar entrega</button>
</form>
```

La llamada con el token debe hacerse desde el servidor, nunca directamente desde JavaScript público. Ejemplo simplificado en PHP:

```php
<?php

// Estas funciones representan las consultas de tu portal.
$contract = findContractWithTenantAndProperty((int) $_POST['contract_id']);
$employee = currentAuthenticatedEmployee();
$productIds = array_values(array_unique(array_filter(
    array_map('intval', $_POST['product_ids'] ?? [])
)));

$payload = [
    // Usa el ID único de la entrega/formulario y conserva el mismo al reintentar.
    'external_id' => 'entrega-inmueble-' . $contract['delivery_id'],
    'delivered_at' => date(DATE_ATOM),
    'recipient_name' => $contract['tenant_name'],
    'recipient_type' => 'Arrendatario',
    'recipient_document' => $contract['tenant_document'],
    'recipient_source' => 'Formulario entrega de inmueble',
    'recipient_id' => (string) $contract['tenant_id'],
    'recipient_metadata' => [
        'inmueble_id' => (string) $contract['property_id'],
        'inmueble_direccion' => $contract['property_address'],
        'contrato_id' => (string) $contract['contract_id'],
        'funcionario_id' => (string) $employee['id'],
        'funcionario_nombre' => $employee['name'],
    ],
    'product_ids' => $productIds,
    'notes' => 'Entrega inicial del inmueble',
];

$request = curl_init('https://tu-dominio.example/api/inventory/deliveries');
curl_setopt_array($request, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . getenv('SKC_INVENTORY_API_TOKEN'),
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$responseBody = curl_exec($request);
$status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
curl_close($request);

if (!in_array($status, [200, 201], true)) {
    throw new RuntimeException('No se pudo registrar la entrega: ' . $responseBody);
}
```

El funcionario debe tomarse de la sesión autenticada y el arrendatario/inmueble deben consultarse nuevamente en el servidor usando el contrato. No confíes esos datos sensibles a campos ocultos enviados por el navegador.

## Respuestas

Entrega creada:

```json
{
  "ok": true,
  "delivery": {
    "id": 125,
    "duplicate": false,
    "status": "delivered"
  }
}
```

Reintento de una entrega ya procesada:

```json
{
  "ok": true,
  "delivery": {
    "id": 125,
    "duplicate": true,
    "status": "delivered"
  }
}
```

Códigos HTTP:

| Código | Significado |
|---:|---|
| `200` | Consulta correcta o entrega repetida ya procesada. |
| `201` | Entrega creada y existencias descontadas. |
| `401` | Token ausente o inválido. |
| `405` | Método no permitido. |
| `409` | Existencias insuficientes o conflicto de estado. |
| `422` | Faltan campos o el formato enviado es incorrecto. |
| `500` | Error interno; la operación se revierte y no descuenta parcialmente. |

## Recomendaciones para formularios

- Genera `external_id` con el ID único de la respuesta del formulario, no con la fecha.
- Consulta primero `/products` para mostrar únicamente productos activos.
- Conserva el `external_id` al reintentar; no generes uno nuevo.
- Si la API responde `409`, muestra el mensaje recibido y actualiza la lista de productos.
- Realiza la llamada desde el servidor o webhook del formulario para no exponer el token.
